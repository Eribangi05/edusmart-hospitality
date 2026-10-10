<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiError;
use App\Http\Controllers\Controller;
use App\Models\Domain\AttendanceEvent;
use App\Models\Domain\Employee;
use App\Models\Domain\InventoryItem;
use App\Models\Domain\LeaveRequest;
use App\Models\Domain\PurchaseOrder;
use App\Models\Domain\Room;
use App\Models\Domain\StockMovement;
use App\Models\Domain\WorkTask;
use App\Services\Audit;
use App\Services\Inventory\InventoryService;
use App\Services\Numbering;
use App\Support\ResourceRegistry;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Inventory ledger, purchasing, staff attendance/payroll, housekeeping and maintenance actions. */
class OperationsController extends Controller
{
    public function __construct(private InventoryService $inv) {}

    // ---------- inventory ----------
    public function stock(Request $r)
    {
        $items = InventoryItem::where('is_active', true)->when($r->filled('property_id'), fn ($q) => $q->where('property_id', $r->query('property_id')))->orderBy('name')->get();
        $levels = StockMovement::whereIn('inventory_item_id', $items->pluck('id'))->select('inventory_item_id', DB::raw('SUM(quantity) as qty'))->groupBy('inventory_item_id')->pluck('qty', 'inventory_item_id');
        $rows = $items->map(fn ($i) => $i->toArray() + ['quantity' => (float) ($levels[$i->id] ?? 0), 'low' => (float) ($levels[$i->id] ?? 0) <= (float) $i->reorder_level]);
        if ($r->boolean('low_only')) {
            $rows = $rows->where('low', true);
        }

        return response()->json(['data' => $rows->values()]);
    }

    public function movements(Request $r)
    {
        $q = StockMovement::query()->orderByDesc('created_at');
        foreach (['inventory_item_id', 'type'] as $f) {
            if ($r->filled($f)) {
                $q->where($f, $r->query($f));
            }
        }
        $p = $q->paginate(min((int) $r->query('per_page', 50), 200));

        return response()->json(['data' => $p->items(), 'meta' => ['page' => $p->currentPage(), 'total' => $p->total()]]);
    }

    public function adjust(Request $r)
    {
        $d = $r->validate(['inventory_item_id' => ['required', 'uuid', ResourceRegistry::ref('inventory_items')], 'quantity' => 'required|numeric|not_in:0',
            'type' => 'required|in:adjustment,waste,purchase,return,transfer_in,transfer_out', 'note' => 'nullable|string|max:200', 'unit_cost' => 'nullable|integer|min:0', 'idempotency_key' => 'nullable|string|max:80']);
        $qty = (float) $d['quantity'];
        if (in_array($d['type'], ['waste', 'transfer_out'], true)) {
            $qty = -abs($qty);
        } elseif (in_array($d['type'], ['purchase', 'return', 'transfer_in'], true)) {
            $qty = abs($qty);
        }
        $item = InventoryItem::findOrFail($d['inventory_item_id']);
        $m = $this->inv->move($item, $qty, $d['type'], $d);
        Audit::log('stock.'.$d['type'], 'inventory_item', $item->id, null, ['quantity' => $qty, 'note' => $d['note'] ?? null]);

        return response()->json(['data' => $m, 'level' => $this->inv->level($item->id)], 201);
    }

    /** Stocktake: posts the difference between counted and system quantity as stocktake movements. */
    public function stocktake(Request $r)
    {
        $d = $r->validate(['counts' => 'required|array|min:1', 'counts.*.inventory_item_id' => ['required', 'uuid', ResourceRegistry::ref('inventory_items')], 'counts.*.counted' => 'required|numeric|min:0', 'note' => 'nullable|string|max:200']);
        $out = [];
        DB::transaction(function () use ($d, &$out) {
            foreach ($d['counts'] as $c) {
                $item = InventoryItem::findOrFail($c['inventory_item_id']);
                $diff = (float) $c['counted'] - $this->inv->level($item->id);
                if (abs($diff) > 0.0005) {
                    $this->inv->move($item, $diff, 'stocktake', ['note' => $d['note'] ?? 'Stocktake']);
                }
                $out[] = ['inventory_item_id' => $item->id, 'difference' => $diff];
            }
        });
        Audit::log('stock.stocktake', 'inventory', null, null, ['items' => count($out)]);

        return response()->json(['data' => $out]);
    }

    public function purchaseOrders(Request $r)
    {
        return response()->json(['data' => PurchaseOrder::orderByDesc('created_at')->when($r->filled('status'), fn ($q) => $q->where('status', $r->query('status')))->limit(200)->get()]);
    }

    public function storePurchaseOrder(Request $r)
    {
        $d = $r->validate(['supplier_id' => ['nullable', 'uuid', ResourceRegistry::ref('suppliers')], 'expected_on' => 'nullable|date', 'lines' => 'required|array|min:1',
            'lines.*.inventory_item_id' => ['required', 'uuid', ResourceRegistry::ref('inventory_items')], 'lines.*.quantity' => 'required|numeric|gt:0', 'lines.*.unit_cost' => 'required|integer|min:0', 'status' => 'in:draft,ordered']);
        $lines = array_map(fn ($l) => $l + ['received' => 0], $d['lines']);
        $po = new PurchaseOrder;
        $po->forceFill(['number' => Numbering::next('PO', PurchaseOrder::class), 'supplier_id' => $d['supplier_id'] ?? null, 'expected_on' => $d['expected_on'] ?? null,
            'status' => $d['status'] ?? 'draft', 'lines' => $lines, 'total' => (int) collect($lines)->sum(fn ($l) => $l['quantity'] * $l['unit_cost'])])->save();

        return response()->json(['data' => $po], 201);
    }

    /** Goods receipt (full or partial). Each receipt creates purchase movements; idempotent per PO line + receipt key. */
    public function receive(Request $r, string $id)
    {
        $d = $r->validate(['receipts' => 'required|array|min:1', 'receipts.*.inventory_item_id' => ['required', 'uuid', ResourceRegistry::ref('inventory_items')], 'receipts.*.quantity' => 'required|numeric|gt:0', 'receipt_key' => 'required|string|max:60']);
        $po = PurchaseOrder::findOrFail($id);
        if (! in_array($po->status, ['ordered', 'draft', 'partial'], true)) {
            throw new ApiError('invalid_state', "Purchase order is {$po->status}.", 409);
        }
        DB::transaction(function () use ($po, $d) {
            $lines = $po->lines;
            foreach ($d['receipts'] as $rc) {
                $idx = collect($lines)->search(fn ($l) => $l['inventory_item_id'] === $rc['inventory_item_id']);
                if ($idx === false) {
                    throw new ApiError('validation_failed', 'Item is not on this purchase order.', 422);
                }
                $item = InventoryItem::findOrFail($rc['inventory_item_id']);
                $this->inv->move($item, (float) $rc['quantity'], 'purchase', ['unit_cost' => $lines[$idx]['unit_cost'], 'ref_type' => 'purchase_order', 'ref_id' => $po->id, 'idempotency_key' => "po:{$po->id}:{$d['receipt_key']}:{$item->id}"]);
                $lines[$idx]['received'] = ($lines[$idx]['received'] ?? 0) + $rc['quantity'];
                $item->forceFill(['cost' => $lines[$idx]['unit_cost']])->save();
            }
            $done = collect($lines)->every(fn ($l) => $l['received'] >= $l['quantity']);
            $po->forceFill(['lines' => $lines, 'status' => $done ? 'received' : 'partial'])->save();
        });

        return response()->json(['data' => $po->fresh()]);
    }

    // ---------- staff ----------
    public function clock(Request $r)
    {
        $d = $r->validate(['employee_id' => ['nullable', 'uuid', ResourceRegistry::ref('employees')], 'type' => 'required|in:in,out', 'at' => 'nullable|date', 'lat' => 'nullable|numeric', 'lng' => 'nullable|numeric', 'device_id' => 'nullable|uuid', 'id' => 'nullable|uuid']);
        $emp = isset($d['employee_id']) ? Employee::findOrFail($d['employee_id']) : Employee::where('user_id', $r->user()->id)->first();
        if (! $emp) {
            throw new ApiError('not_found', 'No employee profile is linked to this user.', 404);
        }
        if ($emp->user_id !== $r->user()->id && ! $r->user()->can2('staff.attendance_manage')) {
            throw new ApiError('forbidden', 'You can only clock yourself in or out.', 403);
        }
        if (! empty($d['id']) && ($e = AttendanceEvent::withTrashed()->find($d['id']))) {
            return response()->json(['data' => $e]);
        }
        $at = isset($d['at']) ? Carbon::parse($d['at']) : now();
        $last = AttendanceEvent::where('employee_id', $emp->id)->orderByDesc('at')->first();
        $flag = ($last && $last->type === $d['type']) || $at->gt(now()->addMinutes(10)); // double clock-in or future time => needs review
        $e = new AttendanceEvent;
        $e->forceFill(['id' => $d['id'] ?? (string) Str::uuid7(), 'employee_id' => $emp->id, 'type' => $d['type'], 'at' => $at, 'lat' => $d['lat'] ?? null, 'lng' => $d['lng'] ?? null,
            'device_id' => $d['device_id'] ?? null, 'flagged' => $flag])->save();

        return response()->json(['data' => $e], 201);
    }

    public function attendance(Request $r)
    {
        $q = AttendanceEvent::query()->orderByDesc('at');
        foreach (['employee_id', 'flagged'] as $f) {
            if ($r->filled($f)) {
                $q->where($f, $r->query($f));
            }
        }
        $q->when($r->filled('from'), fn ($x) => $x->where('at', '>=', $r->query('from')))->when($r->filled('to'), fn ($x) => $x->where('at', '<=', $r->query('to')));

        return response()->json(['data' => $q->limit(1000)->get()]);
    }

    public function requestLeave(Request $r)
    {
        $d = $r->validate(['type' => 'string|max:30', 'from_date' => 'required|date', 'to_date' => 'required|date|after_or_equal:from_date', 'reason' => 'nullable|string|max:255']);
        $emp = Employee::where('user_id', $r->user()->id)->first() ?? throw new ApiError('not_found', 'No employee profile is linked to this user.', 404);
        $l = new LeaveRequest;
        $l->forceFill($d + ['employee_id' => $emp->id, 'status' => 'pending'])->save();

        return response()->json(['data' => $l], 201);
    }

    public function decideLeave(Request $r, string $id)
    {
        $d = $r->validate(['status' => 'required|in:approved,rejected']);
        $l = LeaveRequest::findOrFail($id);
        if ($l->status !== 'pending') {
            throw new ApiError('invalid_state', 'Already decided.', 409);
        }
        $l->forceFill($d + ['decided_by' => $r->user()->id])->save();
        Audit::log('leave.'.$d['status'], 'leave_request', $l->id);

        return response()->json(['data' => $l]);
    }

    /** Payroll export: hours from attendance pairs; amount from pay type. Optional CSV. */
    public function payroll(Request $r)
    {
        $d = $r->validate(['from' => 'required|date', 'to' => 'required|date|after_or_equal:from', 'format' => 'in:json,csv']);
        $rows = [];
        foreach (Employee::where('is_active', true)->orderBy('name')->get() as $e) {
            $events = AttendanceEvent::where('employee_id', $e->id)->whereBetween('at', [$d['from'].' 00:00:00', $d['to'].' 23:59:59'])->orderBy('at')->get();
            $hours = 0.0;
            $days = [];
            $open = null;
            foreach ($events as $ev) {
                if ($ev->type === 'in') {
                    $open = $ev->at;
                } elseif ($open) {
                    $hours += $open->diffInMinutes($ev->at) / 60;
                    $days[$open->toDateString()] = true;
                    $open = null;
                }
            }
            $amount = match ($e->pay_type) {
                'hourly' => (int) round($e->pay_rate * $hours),
                'daily' => $e->pay_rate * count($days),
                default => $e->pay_rate,
            };
            $rows[] = ['employee_id' => $e->id, 'name' => $e->name, 'position' => $e->position, 'pay_type' => $e->pay_type, 'pay_rate' => $e->pay_rate, 'days' => count($days), 'hours' => round($hours, 2), 'amount' => $amount];
        }
        if (($d['format'] ?? 'json') === 'csv') {
            $csv = "name,position,pay_type,pay_rate,days,hours,amount\n".collect($rows)->map(fn ($x) => implode(',', [str_replace(',', ' ', $x['name']), str_replace(',', ' ', (string) $x['position']), $x['pay_type'], $x['pay_rate'], $x['days'], $x['hours'], $x['amount']]))->implode("\n");

            return response($csv, 200, ['Content-Type' => 'text/csv']);
        }

        return response()->json(['data' => $rows, 'total' => array_sum(array_column($rows, 'amount'))]);
    }

    // ---------- housekeeping / maintenance ----------
    public function taskAction(Request $r, string $id, string $action)
    {
        $t = WorkTask::findOrFail($id);
        $user = $r->user();
        $forward = ['start' => ['pending', 'in_progress'], 'complete' => ['in_progress', 'done'], 'inspect' => ['done', 'inspected']];
        if ($action === 'assign') {
            if (! $user->can2('housekeeping.assign')) {
                throw new ApiError('forbidden', 'You cannot assign tasks.', 403);
            }
            $d = $r->validate(['assigned_to' => ['required', 'uuid', ResourceRegistry::ref('users')]]);
            $t->forceFill($d)->save();

            return response()->json(['data' => $t]);
        }
        [$from, $to] = $forward[$action];
        if ($action === 'inspect' && ! $user->can2('housekeeping.inspect')) {
            throw new ApiError('forbidden', 'You cannot inspect rooms.', 403);
        }
        if ($t->assigned_to && $t->assigned_to !== $user->id && ! $user->can2('housekeeping.assign') && $action !== 'inspect') {
            throw new ApiError('forbidden', 'This task is assigned to someone else.', 403);
        }
        if ($action === 'complete' && $t->status === 'pending') {
            $from = 'pending'; // allow finishing straight from pending (offline workers)
        }
        if (! in_array($t->status, [$from, 'pending'], true) || ($action === 'inspect' && $t->status !== 'done')) {
            throw new ApiError('invalid_state', "Cannot $action a {$t->status} task.", 409);
        }
        $photos = $r->validate(['photos' => 'nullable|array|max:10', 'photos.*' => 'string'])['photos'] ?? null;
        $t->forceFill(['status' => $to, 'photos' => $photos ? array_merge($t->photos ?? [], $photos) : $t->photos, 'completed_at' => $to === 'done' ? now() : $t->completed_at])->save();
        if ($t->type === 'clean' && $t->room_id) {
            if ($to === 'done') {
                Room::where('id', $t->room_id)->where('housekeeping_status', 'dirty')->update(['housekeeping_status' => 'clean']);
            } elseif ($to === 'inspected') {
                Room::where('id', $t->room_id)->where('housekeeping_status', '!=', 'out_of_order')->update(['housekeeping_status' => 'inspected']);
            }
        }
        Audit::log("task.$action", 'work_task', $t->id);

        return response()->json(['data' => $t]);
    }

    public function myTasks(Request $r)
    {
        return response()->json(['data' => WorkTask::where('assigned_to', $r->user()->id)->whereIn('status', ['pending', 'in_progress'])->orderByRaw("case priority when 'urgent' then 0 when 'high' then 1 when 'normal' then 2 else 3 end")->get()]);
    }
}
