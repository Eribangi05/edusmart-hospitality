<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Domain\Booking;
use App\Models\Domain\Expense;
use App\Models\Domain\FolioEntry;
use App\Models\Domain\InventoryItem;
use App\Models\Domain\Order;
use App\Models\Domain\OrderItem;
use App\Models\Domain\Payment;
use App\Models\Domain\Reservation;
use App\Models\Domain\Room;
use App\Models\Domain\Shift;
use App\Models\Domain\StockMovement;
use App\Models\Domain\WorkTask;
use App\Support\Tenancy;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    private function range(Request $r): array
    {
        $d = $r->validate(['from' => 'nullable|date', 'to' => 'nullable|date', 'property_id' => 'nullable|uuid', 'format' => 'nullable|in:json,csv']);

        return [Carbon::parse($d['from'] ?? now()->startOfMonth())->startOfDay(), Carbon::parse($d['to'] ?? now())->endOfDay(), $d['property_id'] ?? null];
    }

    private function csv(array $rows): Response
    {
        if (! $rows) {
            return response('', 200, ['Content-Type' => 'text/csv']);
        }
        $esc = fn ($v) => '"'.str_replace('"', '""', (string) $v).'"';
        $out = implode(',', array_keys($rows[0]))."\n".collect($rows)->map(fn ($x) => collect($x)->map($esc)->implode(','))->implode("\n");

        return response($out, 200, ['Content-Type' => 'text/csv']);
    }

    public function dashboard(Request $r)
    {
        $date = Carbon::parse($r->query('date', now()))->toDateString();
        $pid = $r->query('property_id');
        $scope = fn ($q) => $pid ? $q->where('property_id', $pid) : $q;
        $collected = $scope(Payment::where('status', 'successful')->whereBetween('paid_at', [$date.' 00:00:00', $date.' 23:59:59']))
            ->select('method_type', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as n'))->groupBy('method_type')->get();
        $totalRooms = $scope(Room::where('is_active', true))->count();
        $occupied = $scope(Reservation::where('status', 'checked_in')->where('check_in', '<=', $date)->where('check_out', '>', $date))->count();
        $data = [
            'date' => $date,
            'collected' => ['total' => (int) $collected->where('method_type', '!=', 'room_charge')->sum('total'), 'by_method' => $collected->pluck('total', 'method_type')],
            'orders' => ['open' => $scope(Order::whereIn('status', ['open', 'sent']))->count(), 'paid_today' => $scope(Order::where('status', 'paid')->whereDate('closed_at', $date))->count(),
                'sales_today' => (int) $scope(Order::where('status', 'paid')->whereDate('closed_at', $date))->sum('total')],
            'rooms' => ['total' => $totalRooms, 'occupied' => $occupied, 'occupancy_pct' => $totalRooms ? round(100 * $occupied / $totalRooms, 1) : 0,
                'arrivals' => $scope(Reservation::whereIn('status', ['confirmed', 'pending'])->where('check_in', $date))->count(),
                'departures' => $scope(Reservation::where('status', 'checked_in')->where('check_out', $date))->count(),
                'dirty' => $scope(Room::where('housekeeping_status', 'dirty'))->count()],
            'tourism' => ['bookings_today' => $scope(Booking::where('date', $date)->whereNotIn('status', ['cancelled', 'no_show']))->count(),
                'pax_today' => (int) $scope(Booking::where('date', $date)->whereNotIn('status', ['cancelled', 'no_show']))->sum(DB::raw('pax_adults + pax_children'))],
            'tasks_open' => WorkTask::whereIn('status', ['pending', 'in_progress'])->count(),
            'open_shifts' => Shift::where('status', 'open')->count(),
        ];
        if (in_array('inventory', app(Tenancy::class)->get()->enabledModules(), true)) {
            $levels = StockMovement::select('inventory_item_id', DB::raw('SUM(quantity) q'))->groupBy('inventory_item_id')->pluck('q', 'inventory_item_id');
            $data['low_stock'] = InventoryItem::where('is_active', true)->get()->filter(fn ($i) => (float) ($levels[$i->id] ?? 0) <= (float) $i->reorder_level)->count();
        }

        return response()->json(['data' => $data]);
    }

    /** Sales by day / method / item / outlet kind. */
    public function sales(Request $r)
    {
        [$from, $to, $pid] = $this->range($r);
        $group = $r->query('group', 'day');
        $orders = Order::where('status', 'paid')->whereBetween('closed_at', [$from, $to])->when($pid, fn ($q) => $q->where('property_id', $pid));
        $rows = match ($group) {
            'method' => Payment::where('status', 'successful')->whereBetween('paid_at', [$from, $to])->when($pid, fn ($q) => $q->where('property_id', $pid))
                ->select('method_type as key', DB::raw('SUM(amount) as amount'), DB::raw('COUNT(*) as count'))->groupBy('method_type')->get(),
            'item' => OrderItem::whereIn('order_id', (clone $orders)->select('id'))->whereNull('voided_at')->select('name as key', DB::raw('SUM(quantity) as quantity'), DB::raw('SUM(line_total) as amount'))->groupBy('name')->orderByDesc('amount')->get(),
            'user' => (clone $orders)->select('opened_by as key', DB::raw('SUM(total) as amount'), DB::raw('COUNT(*) as count'))->groupBy('opened_by')->get(),
            default => (clone $orders)->select(DB::raw('DATE(closed_at) as `key`'), DB::raw('SUM(total) as amount'), DB::raw('SUM(tax) as tax'), DB::raw('COUNT(*) as count'))->groupBy(DB::raw('DATE(closed_at)'))->orderBy('key')->get(),
        };
        $rows = $rows->map(fn ($x) => $x->toArray())->all();

        return ($r->query('format') === 'csv') ? $this->csv($rows) : response()->json(['data' => $rows, 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'group' => $group]);
    }

    /** Occupancy, ADR and RevPAR per day (room revenue = nightly room charges posted by night audit, net of tax). */
    public function occupancy(Request $r)
    {
        [$from, $to, $pid] = $this->range($r);
        $total = Room::where('is_active', true)->when($pid, fn ($q) => $q->where('property_id', $pid))->count();
        $rows = [];
        $sumRev = 0;
        $sumSold = 0;
        foreach (CarbonPeriod::create($from, $to->copy()->startOfDay()) as $day) {
            $d = $day->toDateString();
            $sold = Reservation::whereIn('status', ['checked_in', 'checked_out'])->where('check_in', '<=', $d)->where('check_out', '>', $d)->when($pid, fn ($q) => $q->where('property_id', $pid))->count();
            $rev = (int) FolioEntry::whereNull('voided_at')->where('type', 'charge')->where('source_type', 'night_audit')->where('business_date', $d)->sum('amount');
            $rows[] = ['date' => $d, 'rooms_total' => $total, 'rooms_sold' => $sold, 'occupancy_pct' => $total ? round(100 * $sold / $total, 1) : 0, 'room_revenue' => $rev,
                'adr' => $sold ? (int) round($rev / $sold) : 0, 'revpar' => $total ? (int) round($rev / $total) : 0];
            $sumRev += $rev;
            $sumSold += $sold;
        }
        $days = max(count($rows), 1);
        $summary = ['room_revenue' => $sumRev, 'adr' => $sumSold ? (int) round($sumRev / $sumSold) : 0, 'revpar' => $total ? (int) round($sumRev / ($total * $days)) : 0, 'occupancy_pct' => $total ? round(100 * $sumSold / ($total * $days), 1) : 0];

        return $r->query('format') === 'csv' ? $this->csv($rows) : response()->json(['data' => $rows, 'summary' => $summary]);
    }

    public function cash(Request $r)
    {
        [$from, $to] = $this->range($r);
        $shifts = Shift::whereBetween('opened_at', [$from, $to])->orderBy('opened_at')->get();

        return response()->json(['data' => $shifts, 'variance_total' => (int) $shifts->sum('variance')]);
    }

    public function profit(Request $r)
    {
        [$from, $to, $pid] = $this->range($r);
        $revenue = (int) Payment::where('status', 'successful')->where('method_type', '!=', 'room_charge')->whereBetween('paid_at', [$from, $to])->when($pid, fn ($q) => $q->where('property_id', $pid))->sum('amount');
        $exp = Expense::whereBetween('date', [$from->toDateString(), $to->toDateString()])->when($pid, fn ($q) => $q->where('property_id', $pid));
        $byCat = (clone $exp)->select('category', DB::raw('SUM(amount) as amount'))->groupBy('category')->pluck('amount', 'category');

        return response()->json(['data' => ['revenue_collected' => $revenue, 'expenses' => (int) $byCat->sum(), 'net' => $revenue - (int) $byCat->sum(), 'expenses_by_category' => $byCat]]);
    }
}
