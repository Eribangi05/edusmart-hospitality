<?php

namespace App\Support;

use App\Models\CustomField;
use App\Models\Domain as D;
use App\Models\PaymentMethod;
use App\Models\Property;
use App\Models\TaxRate;

/** Which entities the mobile app can pull, and the module + permission needed to see them. */
class SyncRegistry
{
    public static function entities(): array
    {
        return [
            'properties' => [Property::class, null, null],
            'tax_rates' => [TaxRate::class, null, null],
            'payment_methods' => [PaymentMethod::class, null, null],
            'custom_fields' => [CustomField::class, null, null],
            'guests' => [D\Guest::class, null, ['rooms.view', 'tourism.view']],
            'catalog_categories' => [D\CatalogCategory::class, null, ['pos.view', 'tourism.view', 'rooms.view']],
            'catalog_items' => [D\CatalogItem::class, null, ['pos.view', 'tourism.view', 'rooms.view']],
            'room_types' => [D\RoomType::class, 'rooms', ['rooms.view']],
            'rooms' => [D\Room::class, 'rooms', ['rooms.view', 'housekeeping.view']],
            'rate_plans' => [D\RatePlan::class, 'rooms', ['rooms.view']],
            'reservations' => [D\Reservation::class, 'rooms', ['rooms.view']],
            'folios' => [D\Folio::class, 'rooms', ['rooms.folio', 'rooms.view']],
            'folio_entries' => [D\FolioEntry::class, 'rooms', ['rooms.folio', 'rooms.view']],
            'vouchers' => [D\Voucher::class, 'pos', ['pos.view']],
            'dining_tables' => [D\DiningTable::class, 'pos', ['pos.view']],
            'orders' => [D\Order::class, 'pos', ['pos.view']],
            'order_items' => [D\OrderItem::class, 'pos', ['pos.view']],
            'payments' => [D\Payment::class, null, ['pos.view', 'rooms.folio', 'tourism.view']],
            'shifts' => [D\Shift::class, 'pos', ['pos.view']],
            'tourism_resources' => [D\TourismResource::class, 'tourism', ['tourism.view']],
            'tour_departures' => [D\TourDeparture::class, 'tourism', ['tourism.view']],
            'bookings' => [D\Booking::class, 'tourism', ['tourism.view']],
            'booking_lines' => [D\BookingLine::class, 'tourism', ['tourism.view']],
            'booking_participants' => [D\BookingParticipant::class, 'tourism', ['tourism.view']],
            'booking_assignments' => [D\BookingAssignment::class, 'tourism', ['tourism.view']],
            'inventory_items' => [D\InventoryItem::class, 'inventory', ['inventory.view']],
            'recipes' => [D\Recipe::class, 'inventory', ['inventory.view']],
            'suppliers' => [D\Supplier::class, 'inventory', ['inventory.view']],
            'employees' => [D\Employee::class, 'staff', ['staff.view']],
            'roster_shifts' => [D\RosterShift::class, 'staff', ['staff.view']],
            'work_tasks' => [D\WorkTask::class, 'housekeeping', ['housekeeping.view']],
        ];
    }
}
