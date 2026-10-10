<?php

namespace App\Support;

use App\Models\CustomField;
use App\Models\Domain as D;
use App\Models\PaymentMethod;
use App\Models\Property;
use App\Models\TaxRate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Declarative description of every simple CRUD resource. One generic controller
 * serves them all, so adding a business object is a registry entry, not new code.
 *
 *   model   Eloquent class            module  gate (null = always on)
 *   perm    "rooms" => rooms.view/create/edit/delete;  fixed => single permission
 *   rules   validation for create (update = same rules made optional)
 *   search  columns used by ?q=       filters whitelist for ?filter[col]=
 */
class ResourceRegistry
{
    /** exists-rule restricted to the current tenant: blocks cross-tenant references. */
    public static function ref(string $table): Exists
    {
        return Rule::exists($table, 'id')->where('tenant_id', app(Tenancy::class)->id())->whereNull('deleted_at');
    }

    private static function prop(): array
    {
        return ['property_id' => ['nullable', 'uuid', Rule::exists('properties', 'id')->where('tenant_id', app(Tenancy::class)->id())]];
    }

    public static function all(): array
    {
        $ref = [self::class, 'ref'];
        $p = fn () => self::prop();

        return [
            // ---- core / flexibility layer ----
            'properties' => ['model' => Property::class, 'module' => null, 'fixed' => 'core.settings', 'search' => ['name', 'code'], 'filters' => ['type', 'is_active'],
                'rules' => fn () => ['name' => 'required|string|max:150', 'type' => 'nullable|string|max:40', 'code' => 'nullable|string|max:20', 'address' => 'nullable|string|max:255',
                    'phone' => 'nullable|string|max:30', 'email' => 'nullable|email', 'timezone' => 'nullable|timezone', 'config' => 'nullable|array', 'meta' => 'nullable|array', 'is_active' => 'boolean']],
            'custom-fields' => ['model' => CustomField::class, 'module' => null, 'fixed' => 'core.settings', 'search' => ['key'], 'filters' => ['entity', 'is_active'],
                'rules' => fn () => ['entity' => 'required|string|max:50', 'key' => 'required|alpha_dash|max:50', 'label' => 'required|array', 'type' => 'required|in:text,textarea,number,date,select,multiselect,boolean',
                    'options' => 'nullable|array', 'required' => 'boolean', 'position' => 'integer|min:0', 'is_active' => 'boolean']],
            'tax-rates' => ['model' => TaxRate::class, 'module' => null, 'fixed' => 'core.settings', 'search' => ['name'], 'filters' => ['kind', 'is_active'],
                'rules' => fn () => ['key' => 'required|alpha_dash|max:40', 'name' => 'required|string|max:100', 'rate_bp' => 'required|integer|min:0|max:100000',
                    'kind' => 'required|in:tax,service_charge,levy', 'inclusive' => 'boolean', 'is_active' => 'boolean']],
            'payment-methods' => ['model' => PaymentMethod::class, 'module' => null, 'fixed' => 'core.settings', 'search' => ['name'], 'filters' => ['type', 'is_active'],
                'rules' => fn () => ['key' => 'required|alpha_dash|max:40', 'name' => 'required|string|max:100', 'type' => 'required|in:cash,momo,card,bank,room_charge,voucher,other',
                    'config' => 'nullable|array', 'is_active' => 'boolean', 'position' => 'integer|min:0']],

            // ---- guests / catalog ----
            'guests' => ['model' => D\Guest::class, 'module' => null, 'perm' => 'rooms', 'perms_any' => ['rooms', 'tourism'], 'search' => ['name', 'phone', 'email', 'id_number'], 'filters' => ['nationality'],
                'rules' => fn () => ['name' => 'required|string|max:150', 'phone' => 'nullable|string|max:30', 'email' => 'nullable|email', 'nationality' => 'nullable|string|max:60',
                    'id_type' => 'nullable|string|max:30', 'id_number' => 'nullable|string|max:60', 'id_photo_path' => 'nullable|string', 'dob' => 'nullable|date', 'notes' => 'nullable|string', 'meta' => 'nullable|array']],
            'catalog-categories' => ['model' => D\CatalogCategory::class, 'module' => null, 'perm' => 'pos', 'perms_any' => ['pos', 'shop', 'tourism', 'rooms'], 'search' => ['name'], 'filters' => ['kind', 'property_id', 'is_active'],
                'rules' => fn () => ['name' => 'required|string|max:100', 'kind' => 'required|string|max:30', 'parent_id' => ['nullable', 'uuid', $ref('catalog_categories')], 'position' => 'integer|min:0', 'is_active' => 'boolean', 'meta' => 'nullable|array'] + $p()],
            'catalog-items' => ['model' => D\CatalogItem::class, 'module' => null, 'perm' => 'pos', 'perms_any' => ['pos', 'shop', 'tourism', 'rooms'], 'search' => ['name', 'sku', 'barcode'], 'filters' => ['kind', 'category_id', 'property_id', 'is_active', 'station', 'barcode'],
                'rules' => fn () => ['kind' => 'required|in:menu,drink,product,service,tour,activity,transfer,rental,package,spa,event,other', 'name' => 'required|string|max:150', 'name_i18n' => 'nullable|array',
                    'category_id' => ['nullable', 'uuid', $ref('catalog_categories')], 'description' => 'nullable|string', 'sku' => 'nullable|string|max:60', 'barcode' => 'nullable|string|max:60',
                    'price' => 'required|integer|min:0', 'cost' => 'integer|min:0', 'pricing_model' => 'in:flat,per_person,per_group,per_day,per_hour,per_night',
                    'tax_rate_ids' => 'nullable|array', 'tax_rate_ids.*' => ['uuid', Rule::exists('tax_rates', 'id')->where('tenant_id', app(Tenancy::class)->id())],
                    'track_stock' => 'boolean', 'station' => 'in:kitchen,bar,none', 'options' => 'nullable|array', 'image_path' => 'nullable|string', 'is_active' => 'boolean', 'meta' => 'nullable|array'] + $p()],

            'vouchers' => ['model' => D\Voucher::class, 'module' => 'pos', 'perm' => 'pos', 'search' => ['code', 'name'], 'filters' => ['is_active', 'kind'],
                'rules' => fn () => ['code' => 'required|string|max:40|alpha_dash', 'name' => 'nullable|string|max:100', 'kind' => 'required|in:percent,amount', 'value' => 'required|integer|min:1|max:100000000',
                    'min_total' => 'integer|min:0', 'valid_from' => 'nullable|date', 'valid_to' => 'nullable|date|after_or_equal:valid_from', 'max_uses' => 'nullable|integer|min:1', 'is_active' => 'boolean', 'meta' => 'nullable|array'] + $p()],

            // ---- rooms ----
            'room-types' => ['model' => D\RoomType::class, 'module' => 'rooms', 'perm' => 'rooms', 'search' => ['name', 'code'], 'filters' => ['property_id', 'is_active'],
                'rules' => fn () => ['name' => 'required|string|max:100', 'code' => 'nullable|string|max:20', 'description' => 'nullable|string', 'base_price' => 'required|integer|min:0',
                    'max_adults' => 'integer|min:1|max:20', 'max_children' => 'integer|min:0|max:20', 'amenities' => 'nullable|array', 'is_active' => 'boolean', 'meta' => 'nullable|array'] + $p()],
            'rooms' => ['model' => D\Room::class, 'module' => 'rooms', 'perm' => 'rooms', 'search' => ['number'], 'filters' => ['property_id', 'room_type_id', 'housekeeping_status', 'is_active'],
                'rules' => fn () => ['room_type_id' => ['required', 'uuid', $ref('room_types')], 'number' => 'required|string|max:20', 'floor' => 'nullable|string|max:20',
                    'housekeeping_status' => 'in:clean,dirty,inspected,out_of_order', 'is_active' => 'boolean', 'meta' => 'nullable|array'] + $p()],
            'rate-plans' => ['model' => D\RatePlan::class, 'module' => 'rooms', 'perm' => 'rooms', 'search' => ['name'], 'filters' => ['property_id', 'room_type_id', 'is_active'],
                'rules' => fn () => ['room_type_id' => ['nullable', 'uuid', $ref('room_types')], 'name' => 'required|string|max:100', 'price' => 'required|integer|min:0', 'valid_from' => 'nullable|date',
                    'valid_to' => 'nullable|date|after_or_equal:valid_from', 'days_of_week' => 'nullable|array', 'days_of_week.*' => 'integer|between:1,7', 'min_nights' => 'integer|min:1',
                    'meal_plan' => 'nullable|string|max:10', 'priority' => 'integer', 'is_active' => 'boolean', 'meta' => 'nullable|array'] + $p()],

            // ---- POS ----
            'tables' => ['model' => D\DiningTable::class, 'module' => 'pos', 'perm' => 'pos', 'search' => ['name', 'section'], 'filters' => ['property_id', 'section', 'is_active'],
                'rules' => fn () => ['name' => 'required|string|max:50', 'section' => 'nullable|string|max:50', 'capacity' => 'integer|min:1|max:100', 'x' => 'integer', 'y' => 'integer', 'is_active' => 'boolean', 'meta' => 'nullable|array'] + $p()],

            // ---- tourism ----
            'tourism-resources' => ['model' => D\TourismResource::class, 'module' => 'tourism', 'perm' => 'tourism', 'search' => ['name', 'plate'], 'filters' => ['kind', 'property_id', 'is_active'],
                'rules' => fn () => ['kind' => 'required|in:vehicle,guide,driver,boat,equipment,other', 'name' => 'required|string|max:120', 'capacity' => 'nullable|integer|min:1', 'plate' => 'nullable|string|max:20',
                    'phone' => 'nullable|string|max:30', 'user_id' => ['nullable', 'uuid', Rule::exists('users', 'id')->where('tenant_id', app(Tenancy::class)->id())], 'cost_per_day' => 'integer|min:0', 'is_active' => 'boolean', 'meta' => 'nullable|array'] + $p()],
            'tour-departures' => ['model' => D\TourDeparture::class, 'module' => 'tourism', 'perm' => 'tourism', 'search' => [], 'filters' => ['catalog_item_id', 'date', 'status', 'property_id'],
                'rules' => fn () => ['catalog_item_id' => ['required', 'uuid', $ref('catalog_items')], 'date' => 'required|date', 'start_time' => 'nullable|string|max:5', 'capacity' => 'required|integer|min:1',
                    'price_override' => 'nullable|integer|min:0', 'status' => 'in:open,closed,cancelled,completed', 'meeting_point' => 'nullable|string|max:150', 'meta' => 'nullable|array'] + $p()],

            // ---- inventory ----
            'suppliers' => ['model' => D\Supplier::class, 'module' => 'inventory', 'perm' => 'inventory', 'search' => ['name', 'phone'], 'filters' => [],
                'rules' => fn () => ['name' => 'required|string|max:150', 'phone' => 'nullable|string|max:30', 'email' => 'nullable|email', 'address' => 'nullable|string|max:255', 'meta' => 'nullable|array'] + $p()],
            'inventory-items' => ['model' => D\InventoryItem::class, 'module' => 'inventory', 'perm' => 'inventory', 'search' => ['name', 'sku'], 'filters' => ['property_id', 'is_active', 'supplier_id'],
                'rules' => fn () => ['name' => 'required|string|max:150', 'sku' => 'nullable|string|max:60', 'unit' => 'required|string|max:15', 'reorder_level' => 'numeric|min:0', 'cost' => 'integer|min:0',
                    'catalog_item_id' => ['nullable', 'uuid', $ref('catalog_items')], 'supplier_id' => ['nullable', 'uuid', $ref('suppliers')], 'is_active' => 'boolean', 'meta' => 'nullable|array'] + $p()],
            'recipes' => ['model' => D\Recipe::class, 'module' => 'inventory', 'perm' => 'inventory', 'search' => [], 'filters' => ['catalog_item_id', 'inventory_item_id'],
                'rules' => fn () => ['catalog_item_id' => ['required', 'uuid', $ref('catalog_items')], 'inventory_item_id' => ['required', 'uuid', $ref('inventory_items')], 'quantity' => 'required|numeric|gt:0']],

            // ---- staff ----
            'employees' => ['model' => D\Employee::class, 'module' => 'staff', 'perm' => 'staff', 'search' => ['name', 'position', 'phone'], 'filters' => ['property_id', 'is_active', 'position'],
                'rules' => fn () => ['user_id' => ['nullable', 'uuid', Rule::exists('users', 'id')->where('tenant_id', app(Tenancy::class)->id())], 'name' => 'required|string|max:150', 'position' => 'nullable|string|max:80', 'phone' => 'nullable|string|max:30',
                    'national_id' => 'nullable|string|max:40', 'hired_on' => 'nullable|date', 'pay_type' => 'in:monthly,daily,hourly', 'pay_rate' => 'integer|min:0', 'is_active' => 'boolean', 'meta' => 'nullable|array'] + $p()],
            'roster' => ['model' => D\RosterShift::class, 'module' => 'staff', 'perm' => 'staff', 'search' => [], 'filters' => ['employee_id', 'date', 'property_id'],
                'rules' => fn () => ['employee_id' => ['required', 'uuid', $ref('employees')], 'date' => 'required|date', 'start_time' => 'required|date_format:H:i', 'end_time' => 'required|date_format:H:i', 'label' => 'nullable|string|max:50', 'meta' => 'nullable|array'] + $p()],
            'leave-requests' => ['model' => D\LeaveRequest::class, 'module' => 'staff', 'perm' => 'staff', 'search' => [], 'filters' => ['employee_id', 'status'],
                'rules' => fn () => ['employee_id' => ['required', 'uuid', $ref('employees')], 'type' => 'string|max:30', 'from_date' => 'required|date', 'to_date' => 'required|date|after_or_equal:from_date', 'reason' => 'nullable|string|max:255', 'meta' => 'nullable|array']],

            // ---- housekeeping / maintenance ----
            'tasks' => ['model' => D\WorkTask::class, 'module' => 'housekeeping', 'perm' => 'housekeeping', 'search' => ['title'], 'filters' => ['type', 'status', 'room_id', 'assigned_to', 'priority', 'property_id'],
                'rules' => fn () => ['type' => 'required|in:clean,inspect,turndown,maintenance,other', 'room_id' => ['nullable', 'uuid', $ref('rooms')], 'title' => 'required|string|max:150', 'description' => 'nullable|string',
                    'status' => 'in:pending,in_progress,done,inspected', 'priority' => 'in:low,normal,high,urgent', 'assigned_to' => ['nullable', 'uuid', Rule::exists('users', 'id')->where('tenant_id', app(Tenancy::class)->id())],
                    'photos' => 'nullable|array', 'due_at' => 'nullable|date', 'meta' => 'nullable|array'] + $p()],

            // ---- finance ----
            'expenses' => ['model' => D\Expense::class, 'module' => 'finance', 'perm' => 'finance', 'search' => ['category', 'description'], 'filters' => ['category', 'date', 'property_id'],
                'rules' => fn () => ['category' => 'required|string|max:80', 'description' => 'nullable|string|max:255', 'amount' => 'required|integer|min:1', 'date' => 'required|date', 'paid_with' => 'string|max:30',
                    'supplier_id' => ['nullable', 'uuid', $ref('suppliers')], 'meta' => 'nullable|array'] + $p()],
        ];
    }
}
