<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Domain as D;
use App\Models\Property;
use App\Models\Setting;
use App\Models\TaxRate;
use App\Models\Tenant;
use App\Services\Rooms\FolioService;
use App\Support\Tenancy;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Read-only helpers that make the console and the workspaces feel alive: search, alerts, trends, timeline, checklists. */
class InsightsController extends Controller
{
    private function modules(): array
    {
        return app(Tenancy::class)->get()->enabledModules();
    }

    private function like(string $q): string
    {
        return '%'.addcslashes($q, '%_\\').'%';
    }

    /** Global search across what the user is allowed to see. */
    public function search(Request $r)
    {
        $q = trim((string) $r->query('q'));
        if (mb_strlen($q) < 2) {
            return response()->json(['data' => []]);
        }
        $u = $r->user();
        $m = $this->modules();
        $t = $this->like($q);
        $out = [];
        if (in_array('rooms', $m, true) && $u->can2('rooms.view')) {
            foreach (D\Guest::where(fn ($w) => $w->where('name', 'like', $t)->orWhere('phone', 'like', $t)->orWhere('email', 'like', $t)->orWhere('id_number', 'like', $t))->limit(6)->get() as $g) {
                $out[] = ['type' => 'guest', 'id' => $g->id, 'title' => $g->name, 'sub' => trim(($g->phone ?? '').' '.($g->nationality ?? '')), 'route' => 'crud/guests'];
            }
            foreach (D\Reservation::where('number', 'like', $t)->limit(5)->get() as $x) {
                $out[] = ['type' => 'reservation', 'id' => $x->id, 'title' => $x->number, 'sub' => $x->check_in->toDateString().' → '.$x->check_out->toDateString().' · '.$x->status, 'route' => 'rooms/reservations'];
            }
            foreach (D\Room::where('number', 'like', $t)->limit(4)->get() as $x) {
                $out[] = ['type' => 'room', 'id' => $x->id, 'title' => 'Room '.$x->number, 'sub' => $x->housekeeping_status, 'route' => 'rooms/board'];
            }
        }
        if (in_array('tourism', $m, true) && $u->can2('tourism.view')) {
            foreach (D\Booking::where(fn ($w) => $w->where('number', 'like', $t)->orWhere('customer_name', 'like', $t)->orWhere('customer_phone', 'like', $t))->limit(6)->get() as $x) {
                $out[] = ['type' => 'booking', 'id' => $x->id, 'title' => $x->number.' · '.$x->customer_name, 'sub' => $x->date->toDateString().' · '.$x->status, 'route' => 'tours/bookings'];
            }
        }
        if (in_array('pos', $m, true) && $u->can2('pos.view')) {
            foreach (D\Order::where('number', 'like', $t)->limit(5)->get() as $x) {
                $out[] = ['type' => 'order', 'id' => $x->id, 'title' => $x->number, 'sub' => $x->status.' · RWF '.number_format($x->total), 'route' => 'pos/orders'];
            }
            foreach (D\CatalogItem::where(fn ($w) => $w->where('name', 'like', $t)->orWhere('barcode', $q)->orWhere('sku', $q))->limit(6)->get() as $x) {
                $out[] = ['type' => 'item', 'id' => $x->id, 'title' => $x->name, 'sub' => $x->kind.' · RWF '.number_format($x->price), 'route' => in_array($x->kind, ['tour', 'activity', 'transfer', 'rental', 'package', 'spa', 'event'], true) ? 'tours/services' : 'menu'];
            }
        }
        if (in_array('staff', $m, true) && $u->can2('staff.view')) {
            foreach (D\Employee::where(fn ($w) => $w->where('name', 'like', $t)->orWhere('phone', 'like', $t))->limit(4)->get() as $x) {
                $out[] = ['type' => 'employee', 'id' => $x->id, 'title' => $x->name, 'sub' => $x->position, 'route' => 'crud/employees'];
            }
        }

        return response()->json(['data' => $out]);
    }

    /** Computed alerts (nothing stored): what needs attention right now. */
    public function notifications(Request $r)
    {
        $u = $r->user();
        $m = $this->modules();
        $tenant = app(Tenancy::class)->get();
        $today = now()->toDateString();
        $n = [];
        $add = function (string $kind, string $sev, string $title, string $detail, string $route) use (&$n) {
            $n[] = compact('kind') + ['severity' => $sev, 'title' => $title, 'detail' => $detail, 'route' => $route];
        };

        if ($u->can2('core.settings')) {
            if ($tenant->status === 'trial' && $tenant->trial_ends_at && $tenant->trial_ends_at->diffInDays(now(), false) > -8) {
                $add('billing', 'warning', 'Trial ends '.$tenant->trial_ends_at->diffForHumans(), 'Choose a plan to keep everything running.', 'settings/billing');
            }
            if (in_array($tenant->status, ['grace', 'suspended'], true)) {
                $add('billing', 'critical', 'Subscription '.$tenant->status, 'Renew to restore full access.', 'settings/billing');
            }
        }
        if (in_array('rooms', $m, true) && $u->can2('rooms.view')) {
            $arr = D\Reservation::whereIn('status', ['confirmed', 'pending'])->where('check_in', $today)->count();
            $dep = D\Reservation::where('status', 'checked_in')->where('check_out', $today)->count();
            $dirty = D\Room::where('is_active', true)->where('housekeeping_status', 'dirty')->count();
            $arr && $add('arrivals', 'info', "$arr arrival".($arr > 1 ? 's' : '').' today', 'Assign rooms and prepare check-in.', 'rooms/reservations');
            $dep && $add('departures', 'info', "$dep departure".($dep > 1 ? 's' : '').' today', 'Settle folios before check-out.', 'rooms/reservations');
            $dirty && $add('dirty', 'warning', "$dirty dirty room".($dirty > 1 ? 's' : ''), 'Waiting for housekeeping.', 'rooms/board');
            $fs = app(FolioService::class);
            $owing = 0;
            foreach (D\Reservation::where('status', 'checked_in')->where('check_out', '<=', $today)->get() as $res) {
                $f = D\Folio::where('reservation_id', $res->id)->first();
                $f && $fs->balance($f) > 0 && $owing++;
            }
            $owing && $add('balance', 'warning', "$owing guest".($owing > 1 ? 's' : '').' leaving with a balance', 'Collect payment at check-out.', 'rooms/reservations');
        }
        if (in_array('pos', $m, true) && $u->can2('pos.view')) {
            $pend = D\Payment::where('status', 'pending')->where('created_at', '<', now()->subMinutes(10))->count();
            $pend && $add('payments', 'warning', "$pend mobile-money payment".($pend > 1 ? 's' : '').' still pending', 'Check the status or take cash instead.', 'pos/orders');
            $old = D\Shift::where('status', 'open')->where('opened_at', '<', now()->subHours(16))->count();
            $old && $add('shift', 'warning', "$old cash shift".($old > 1 ? 's' : '').' open for over 16 hours', 'Close and count the till.', 'pos/shifts');
        }
        if (in_array('tourism', $m, true) && $u->can2('tourism.view')) {
            $b = D\Booking::where('date', $today)->whereNotIn('status', ['cancelled', 'no_show', 'completed'])->count();
            $b && $add('tours', 'info', "$b tour booking".($b > 1 ? 's' : '').' today', 'Check assignments and pickups.', 'tours/bookings');
            $un = D\Booking::whereBetween('date', [$today, now()->addDays(2)->toDateString()])->whereIn('status', ['confirmed', 'paid'])->whereNotIn('id', D\BookingAssignment::select('booking_id'))->count();
            $un && $add('assign', 'warning', "$un upcoming booking".($un > 1 ? 's' : '').' without a guide or vehicle', 'Assign resources.', 'tours/bookings');
        }
        if (in_array('inventory', $m, true) && $u->can2('inventory.view')) {
            $levels = D\StockMovement::select('inventory_item_id', DB::raw('SUM(quantity) q'))->groupBy('inventory_item_id')->pluck('q', 'inventory_item_id');
            $low = D\InventoryItem::where('is_active', true)->get()->filter(fn ($i) => (float) ($levels[$i->id] ?? 0) <= (float) $i->reorder_level)->count();
            $low && $add('stock', 'warning', "$low item".($low > 1 ? 's' : '').' low on stock', 'Create a purchase order.', 'inventory/stock');
        }
        if (in_array('housekeeping', $m, true) && $u->can2('housekeeping.view')) {
            $t = D\WorkTask::whereIn('status', ['pending', 'in_progress'])->whereIn('priority', ['high', 'urgent'])->count();
            $t && $add('tasks', 'warning', "$t high-priority task".($t > 1 ? 's' : '').' open', 'Assign or complete them.', 'crud/tasks');
        }
        if (in_array('staff', $m, true) && $u->can2('staff.attendance_manage')) {
            $f = D\AttendanceEvent::where('flagged', true)->where('at', '>', now()->subDays(7))->count();
            $f && $add('attendance', 'info', "$f attendance entr".($f > 1 ? 'ies' : 'y').' to review', 'Duplicate or odd clock times.', 'staff/attendance');
        }
        $order = ['critical' => 0, 'warning' => 1, 'info' => 2];
        usort($n, fn ($a, $b) => $order[$a['severity']] <=> $order[$b['severity']]);

        return response()->json(['data' => $n, 'count' => count($n)]);
    }

    /** Daily series for charts: collected money, orders, occupancy, plus top items and payment mix. */
    public function trends(Request $r)
    {
        $days = min(max((int) $r->query('days', 7), 3), 90);
        $to = Carbon::parse($r->query('to', now()))->startOfDay();
        $from = $to->copy()->subDays($days - 1);
        $m = $this->modules();
        $series = [];
        $collected = D\Payment::where('status', 'successful')->where('method_type', '!=', 'room_charge')->whereBetween('paid_at', [$from, $to->copy()->endOfDay()])
            ->select(DB::raw('DATE(paid_at) as d'), DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as n'))->groupBy(DB::raw('DATE(paid_at)'))->get()->keyBy('d');
        $totalRooms = in_array('rooms', $m, true) ? D\Room::where('is_active', true)->count() : 0;
        foreach (CarbonPeriod::create($from, $to) as $day) {
            $d = $day->toDateString();
            $occ = null;
            if ($totalRooms) {
                $sold = D\Reservation::whereIn('status', ['checked_in', 'checked_out'])->where('check_in', '<=', $d)->where('check_out', '>', $d)->count();
                $occ = round(100 * $sold / $totalRooms, 1);
            }
            $series[] = ['date' => $d, 'collected' => (int) ($collected[$d]->total ?? 0), 'payments' => (int) ($collected[$d]->n ?? 0), 'occupancy' => $occ];
        }
        $top = in_array('pos', $m, true) ? D\OrderItem::whereIn('order_id', D\Order::where('status', 'paid')->whereBetween('closed_at', [$from, $to->copy()->endOfDay()])->select('id'))
            ->whereNull('voided_at')->select('name', DB::raw('SUM(quantity) as qty'), DB::raw('SUM(line_total) as amount'))->groupBy('name')->orderByDesc('amount')->limit(6)->get() : [];
        $mix = D\Payment::where('status', 'successful')->whereBetween('paid_at', [$from, $to->copy()->endOfDay()])->select('method_type', DB::raw('SUM(amount) as amount'))->groupBy('method_type')->get();
        $hours = in_array('pos', $m, true) ? D\Order::where('status', 'paid')->whereBetween('closed_at', [$from, $to->copy()->endOfDay()])->get()->groupBy(fn ($o) => (int) $o->closed_at->format('G'))->map(fn ($g) => $g->count())->all() : [];

        return response()->json(['data' => ['series' => $series, 'top_items' => $top, 'payment_mix' => $mix, 'orders_by_hour' => $hours, 'from' => $from->toDateString(), 'to' => $to->toDateString()]]);
    }

    /** Rooms × days grid for the reception timeline. */
    public function timeline(Request $r)
    {
        $d = $r->validate(['from' => 'nullable|date', 'days' => 'nullable|integer|min:3|max:31', 'property_id' => 'nullable|uuid']);
        $from = Carbon::parse($d['from'] ?? now())->startOfDay();
        $days = (int) ($d['days'] ?? 14);
        $to = $from->copy()->addDays($days);
        $rooms = D\Room::where('is_active', true)->when($d['property_id'] ?? null, fn ($q, $p) => $q->where('property_id', $p))->orderBy('number')->get();
        $types = D\RoomType::pluck('name', 'id');
        $res = D\Reservation::whereIn('status', ['pending', 'confirmed', 'checked_in', 'checked_out'])->where('check_in', '<', $to->toDateString())->where('check_out', '>', $from->toDateString())->get();
        $guests = D\Guest::whereIn('id', $res->pluck('guest_id')->filter())->pluck('name', 'id');
        $row = fn ($x) => ['id' => $x->id, 'number' => $x->number, 'status' => $x->status, 'check_in' => $x->check_in->toDateString(), 'check_out' => $x->check_out->toDateString(), 'guest' => $guests[$x->guest_id] ?? '', 'room_type_id' => $x->room_type_id];

        return response()->json(['data' => [
            'from' => $from->toDateString(), 'days' => $days,
            'rooms' => $rooms->map(fn ($rm) => ['id' => $rm->id, 'number' => $rm->number, 'type' => $types[$rm->room_type_id] ?? '', 'housekeeping_status' => $rm->housekeeping_status,
                'reservations' => $res->where('room_id', $rm->id)->map($row)->values()])->values(),
            'unassigned' => $res->whereNull('room_id')->map($row)->values(),
        ]]);
    }

    /** Front desk: arrivals, departures and in-house guests with balances. */
    public function desk(Request $r)
    {
        $today = Carbon::parse($r->query('date', now()))->toDateString();
        $fs = app(FolioService::class);
        $types = D\RoomType::pluck('name', 'id');
        $rooms = D\Room::pluck('number', 'id');
        $build = function ($list) use ($fs, $types, $rooms) {
            $guests = D\Guest::whereIn('id', $list->pluck('guest_id')->filter())->get()->keyBy('id');

            return $list->map(function ($x) use ($fs, $types, $rooms, $guests) {
                $f = D\Folio::where('reservation_id', $x->id)->first();
                $g = $guests[$x->guest_id] ?? null;

                return ['id' => $x->id, 'number' => $x->number, 'status' => $x->status, 'check_in' => $x->check_in->toDateString(), 'check_out' => $x->check_out->toDateString(),
                    'guest' => $g?->name, 'phone' => $g?->phone, 'has_id' => (bool) $g?->id_number, 'room' => $rooms[$x->room_id] ?? null, 'room_id' => $x->room_id,
                    'type' => $types[$x->room_type_id] ?? '', 'adults' => $x->adults, 'children' => $x->children, 'balance' => $f ? $fs->balance($f) : 0, 'notes' => $x->notes, 'total' => $x->total];
            })->values();
        };

        return response()->json(['data' => [
            'date' => $today,
            'arrivals' => $build(D\Reservation::whereIn('status', ['confirmed', 'pending'])->where('check_in', $today)->get()),
            'departures' => $build(D\Reservation::where('status', 'checked_in')->where('check_out', $today)->get()),
            'in_house' => $build(D\Reservation::where('status', 'checked_in')->get()),
            'upcoming' => $build(D\Reservation::whereIn('status', ['confirmed', 'pending'])->where('check_in', '>', $today)->where('check_in', '<=', Carbon::parse($today)->addDays(7)->toDateString())->orderBy('check_in')->get()),
        ]]);
    }

    /** Setup checklist so a new company knows what to do next. */
    public function onboarding(Request $r)
    {
        $m = $this->modules();
        $has = fn (string $x) => in_array($x, $m, true);
        $steps = [
            ['key' => 'property', 'title' => 'Name your business and add your outlets', 'route' => 'crud/properties', 'done' => Property::count() > 0 && Property::where('name', '!=', app(Tenancy::class)->get()->name)->count() >= 0 && Property::count() > 0],
            ['key' => 'taxes', 'title' => 'Check your VAT / service charge settings', 'route' => 'crud/tax-rates', 'done' => TaxRate::where('updated_at', '>', DB::raw('created_at'))->count() > 0],
            ['key' => 'team', 'title' => 'Invite your team and give them roles', 'route' => 'settings/users', 'done' => \App\Models\User::where('tenant_id', app(Tenancy::class)->id())->count() > 1],
        ];
        if ($has('rooms')) {
            $steps[] = ['key' => 'rooms', 'title' => 'Add room types, rooms and rates', 'route' => 'crud/room-types', 'done' => D\Room::count() > 0];
        }
        if ($has('pos')) {
            $steps[] = ['key' => 'menu', 'title' => 'Build your menu and tables', 'route' => 'menu', 'done' => D\CatalogItem::whereIn('kind', ['menu', 'drink', 'product'])->count() > 0];
        }
        if ($has('tourism')) {
            $steps[] = ['key' => 'tours', 'title' => 'Create a tour, activity or transfer', 'route' => 'tours/services', 'done' => D\CatalogItem::whereIn('kind', ['tour', 'activity', 'transfer', 'rental', 'package'])->count() > 0];
            $steps[] = ['key' => 'resources', 'title' => 'Add your guides and vehicles', 'route' => 'crud/tourism-resources', 'done' => D\TourismResource::count() > 0];
        }
        if ($has('inventory')) {
            $steps[] = ['key' => 'stock', 'title' => 'Add stock items and opening balances', 'route' => 'crud/inventory-items', 'done' => D\InventoryItem::count() > 0];
        }
        $steps[] = ['key' => 'momo', 'title' => 'Confirm mobile-money is enabled as a payment method', 'route' => 'crud/payment-methods', 'done' => \App\Models\PaymentMethod::where('type', 'momo')->where('is_active', true)->count() > 0];
        $steps[] = ['key' => 'device', 'title' => 'Sign in on the Android app', 'route' => 'settings/devices', 'done' => \App\Models\Device::where('platform', 'android')->count() > 0];
        $done = collect($steps)->where('done', true)->count();

        return response()->json(['data' => ['steps' => $steps, 'done' => $done, 'total' => count($steps)]]);
    }

    /** Guest 360: stays, tours, spend and notes in one place. */
    public function guestProfile(string $id)
    {
        $g = D\Guest::findOrFail($id);
        $res = D\Reservation::where('guest_id', $id)->orderByDesc('check_in')->limit(30)->get();
        $bk = D\Booking::where('guest_id', $id)->orderByDesc('date')->limit(30)->get();
        $nights = $res->whereIn('status', ['checked_in', 'checked_out'])->sum(fn ($x) => $x->check_in->diffInDays($x->check_out));
        $spend = (int) $res->whereIn('status', ['checked_in', 'checked_out'])->sum('total') + (int) $bk->whereNotIn('status', ['cancelled', 'no_show', 'inquiry'])->sum('total');
        $tier = $spend >= 3000000 ? 'Platinum' : ($spend >= 1000000 ? 'Gold' : ($spend >= 300000 ? 'Silver' : 'New'));

        return response()->json(['data' => [
            'guest' => $g,
            'stats' => ['stays' => $res->whereIn('status', ['checked_in', 'checked_out'])->count(), 'nights' => $nights, 'tours' => $bk->whereNotIn('status', ['cancelled', 'no_show', 'inquiry'])->count(), 'spend' => $spend, 'tier' => $tier,
                'first_seen' => $g->created_at?->toDateString(), 'last_stay' => $res->first()?->check_in?->toDateString()],
            'reservations' => $res->map(fn ($x) => ['id' => $x->id, 'number' => $x->number, 'check_in' => $x->check_in->toDateString(), 'check_out' => $x->check_out->toDateString(), 'status' => $x->status, 'total' => $x->total]),
            'bookings' => $bk->map(fn ($x) => ['id' => $x->id, 'number' => $x->number, 'date' => $x->date->toDateString(), 'status' => $x->status, 'total' => $x->total]),
        ]]);
    }

    public function activity(Request $r)
    {
        $keep = ['order.voided', 'order.item_voided', 'order.discount', 'order.voucher', 'pos.manager_override', 'reservation.created', 'reservation.checked_in', 'reservation.checked_out', 'reservation.cancelled', 'booking.created', 'payment.recorded', 'shift.closed',
            'stock.waste', 'stock.stocktake', 'user.created', 'role.updated', 'setting.changed', 'device.revoked', 'folio.entry_voided', 'leave.approved', 'billing.invoice_paid'];
        $rows = AuditLog::where('tenant_id', app(Tenancy::class)->id())->whereIn('action', $keep)->orderByDesc('created_at')->limit(25)->get();
        $users = \App\Models\User::whereIn('id', $rows->pluck('user_id')->filter())->pluck('name', 'id');

        return response()->json(['data' => $rows->map(fn ($x) => ['id' => $x->id, 'action' => $x->action, 'by' => $users[$x->user_id] ?? 'System', 'entity' => $x->entity, 'at' => $x->created_at->toIso8601String(), 'info' => $x->after])]);
    }
}
