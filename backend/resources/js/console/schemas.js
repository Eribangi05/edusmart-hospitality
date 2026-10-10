// Declarative description of every simple resource page. Field types:
// text | textarea | number | money | bool | date | time | select | ref | json | tags | email
const S = (v) => v.map((x) => (typeof x === 'string' ? { value: x, label: x.replace(/_/g, ' ') } : x));

export const schemas = {
    guests: { title: 'guests', perm: 'rooms', entity: 'guest', columns: ['name', 'phone', 'email', 'nationality', 'id_number'],
        fields: [{ k: 'name', req: 1 }, { k: 'phone', type: 'text' }, { k: 'email', type: 'email' }, { k: 'nationality' }, { k: 'id_type', opts: S(['national_id', 'passport', 'driving_licence', 'residence_permit', 'other']), type: 'select' }, { k: 'id_number' }, { k: 'dob', type: 'date' }, { k: 'notes', type: 'textarea' }] },
    'room-types': { title: 'room_types', perm: 'rooms', entity: 'room_type', columns: ['name', 'code', { k: 'base_price', type: 'money' }, 'max_adults', 'max_children'],
        fields: [{ k: 'name', req: 1 }, { k: 'code' }, { k: 'base_price', type: 'money', req: 1 }, { k: 'max_adults', type: 'number' }, { k: 'max_children', type: 'number' }, { k: 'amenities', type: 'tags' }, { k: 'description', type: 'textarea' }, { k: 'property_id', type: 'ref', ref: 'properties', label: 'property' }, { k: 'is_active', type: 'bool', def: true }] },
    rooms: { title: 'rooms', perm: 'rooms', entity: 'room', columns: ['number', 'floor', { k: 'room_type_id', type: 'ref', ref: 'room-types' }, 'housekeeping_status'],
        fields: [{ k: 'number', req: 1 }, { k: 'floor' }, { k: 'room_type_id', type: 'ref', ref: 'room-types', req: 1 }, { k: 'housekeeping_status', type: 'select', opts: S(['clean', 'dirty', 'inspected', 'out_of_order']), def: 'clean' }, { k: 'property_id', type: 'ref', ref: 'properties' }, { k: 'is_active', type: 'bool', def: true }] },
    'rate-plans': { title: 'rate_plans', perm: 'rooms', entity: 'rate_plan', columns: ['name', { k: 'price', type: 'money' }, 'valid_from', 'valid_to', 'meal_plan', 'priority'],
        fields: [{ k: 'name', req: 1 }, { k: 'price', type: 'money', req: 1 }, { k: 'room_type_id', type: 'ref', ref: 'room-types' }, { k: 'valid_from', type: 'date' }, { k: 'valid_to', type: 'date' }, { k: 'days_of_week', type: 'tags', hint: 'ISO weekdays 1 (Mon) to 7 (Sun), e.g. 5,6', numeric: true }, { k: 'min_nights', type: 'number', def: 1 }, { k: 'meal_plan', type: 'select', opts: S(['RO', 'BB', 'HB', 'FB', 'AI']) }, { k: 'priority', type: 'number', def: 0 }, { k: 'is_active', type: 'bool', def: true }] },
    'catalog-categories': { title: 'categories', perm: 'pos', entity: 'catalog_category', columns: ['name', 'kind', 'position'],
        fields: [{ k: 'name', req: 1 }, { k: 'kind', type: 'select', opts: S(['menu', 'drink', 'product', 'service', 'tour', 'activity', 'transfer', 'rental', 'package', 'spa', 'event', 'other']), def: 'menu' }, { k: 'position', type: 'number', def: 0 }, { k: 'is_active', type: 'bool', def: true }] },
    tables: { title: 'tables', perm: 'pos', entity: 'table', columns: ['name', 'section', 'capacity'],
        fields: [{ k: 'name', req: 1 }, { k: 'section' }, { k: 'capacity', type: 'number', def: 4 }, { k: 'property_id', type: 'ref', ref: 'properties' }, { k: 'is_active', type: 'bool', def: true }] },
    'tourism-resources': { title: 'resources', perm: 'tourism', entity: 'tourism_resource', columns: ['name', 'kind', 'capacity', 'plate', 'phone', { k: 'cost_per_day', type: 'money' }],
        fields: [{ k: 'kind', type: 'select', opts: S(['guide', 'driver', 'vehicle', 'boat', 'equipment', 'other']), req: 1 }, { k: 'name', req: 1 }, { k: 'capacity', type: 'number' }, { k: 'plate' }, { k: 'phone' }, { k: 'cost_per_day', type: 'money' }, { k: 'is_active', type: 'bool', def: true }] },
    'tour-departures': { title: 'departures', perm: 'tourism', entity: 'tour_departure', columns: ['date', 'start_time', { k: 'catalog_item_id', type: 'ref', ref: 'catalog-items' }, 'capacity', 'status'],
        fields: [{ k: 'catalog_item_id', type: 'ref', ref: 'catalog-items', refFilter: { kind: 'tour' }, req: 1 }, { k: 'date', type: 'date', req: 1 }, { k: 'start_time', type: 'time' }, { k: 'capacity', type: 'number', req: 1 }, { k: 'price_override', type: 'money' }, { k: 'meeting_point' }, { k: 'status', type: 'select', opts: S(['open', 'closed', 'cancelled', 'completed']), def: 'open' }] },
    suppliers: { title: 'suppliers', perm: 'inventory', entity: 'supplier', columns: ['name', 'phone', 'email'],
        fields: [{ k: 'name', req: 1 }, { k: 'phone' }, { k: 'email', type: 'email' }, { k: 'address' }] },
    'inventory-items': { title: 'items', perm: 'inventory', entity: 'inventory_item', columns: ['name', 'sku', 'unit', 'reorder_level', { k: 'cost', type: 'money' }],
        fields: [{ k: 'name', req: 1 }, { k: 'sku' }, { k: 'unit', req: 1, def: 'pcs' }, { k: 'reorder_level', type: 'number', def: 0 }, { k: 'cost', type: 'money' }, { k: 'supplier_id', type: 'ref', ref: 'suppliers' }, { k: 'catalog_item_id', type: 'ref', ref: 'catalog-items', hint: 'Link to a product sold directly (no recipe)' }, { k: 'property_id', type: 'ref', ref: 'properties' }, { k: 'is_active', type: 'bool', def: true }] },
    recipes: { title: 'recipes', perm: 'inventory', entity: 'recipe', columns: [{ k: 'catalog_item_id', type: 'ref', ref: 'catalog-items' }, { k: 'inventory_item_id', type: 'ref', ref: 'inventory-items' }, 'quantity'],
        fields: [{ k: 'catalog_item_id', type: 'ref', ref: 'catalog-items', req: 1 }, { k: 'inventory_item_id', type: 'ref', ref: 'inventory-items', req: 1 }, { k: 'quantity', type: 'number', req: 1 }] },
    employees: { title: 'employees', perm: 'staff', entity: 'employee', columns: ['name', 'position', 'phone', 'pay_type', { k: 'pay_rate', type: 'money' }],
        fields: [{ k: 'name', req: 1 }, { k: 'position' }, { k: 'phone' }, { k: 'national_id' }, { k: 'hired_on', type: 'date' }, { k: 'pay_type', type: 'select', opts: S(['monthly', 'daily', 'hourly']), def: 'monthly' }, { k: 'pay_rate', type: 'money' }, { k: 'user_id', type: 'ref', ref: 'users', hint: 'Link to a login so they can clock in from the app' }, { k: 'property_id', type: 'ref', ref: 'properties' }, { k: 'is_active', type: 'bool', def: true }] },
    roster: { title: 'staff', perm: 'staff', entity: 'roster', columns: ['date', { k: 'employee_id', type: 'ref', ref: 'employees' }, 'start_time', 'end_time', 'label'],
        fields: [{ k: 'employee_id', type: 'ref', ref: 'employees', req: 1 }, { k: 'date', type: 'date', req: 1 }, { k: 'start_time', type: 'time', req: 1 }, { k: 'end_time', type: 'time', req: 1 }, { k: 'label' }] },
    'leave-requests': { title: 'leave', perm: 'staff', entity: 'leave_request', columns: [{ k: 'employee_id', type: 'ref', ref: 'employees' }, 'type', 'from_date', 'to_date', 'status'],
        fields: [{ k: 'employee_id', type: 'ref', ref: 'employees', req: 1 }, { k: 'type', def: 'annual' }, { k: 'from_date', type: 'date', req: 1 }, { k: 'to_date', type: 'date', req: 1 }, { k: 'reason' }] },
    tasks: { title: 'tasks', perm: 'housekeeping', entity: 'task', columns: ['title', 'type', 'priority', 'status', { k: 'room_id', type: 'ref', ref: 'rooms' }, { k: 'assigned_to', type: 'ref', ref: 'users' }],
        fields: [{ k: 'type', type: 'select', opts: S(['clean', 'inspect', 'turndown', 'maintenance', 'other']), req: 1 }, { k: 'title', req: 1 }, { k: 'room_id', type: 'ref', ref: 'rooms' }, { k: 'priority', type: 'select', opts: S(['low', 'normal', 'high', 'urgent']), def: 'normal' }, { k: 'assigned_to', type: 'ref', ref: 'users' }, { k: 'due_at', type: 'datetime' }, { k: 'description', type: 'textarea' }] },
    expenses: { title: 'expenses', perm: 'finance', entity: 'expense', columns: ['date', 'category', 'description', { k: 'amount', type: 'money' }, 'paid_with'],
        fields: [{ k: 'date', type: 'date', req: 1 }, { k: 'category', req: 1 }, { k: 'description' }, { k: 'amount', type: 'money', req: 1 }, { k: 'paid_with', type: 'select', opts: S(['cash', 'momo', 'card', 'bank', 'other']), def: 'cash' }, { k: 'supplier_id', type: 'ref', ref: 'suppliers' }, { k: 'property_id', type: 'ref', ref: 'properties' }] },
    properties: { title: 'company', fixed: 'core.settings', entity: 'property', columns: ['name', 'type', 'code', 'phone', 'address'],
        fields: [{ k: 'name', req: 1 }, { k: 'type', type: 'select', opts: S(['hotel', 'guesthouse', 'lodge', 'restaurant', 'bar', 'shop', 'tour_operator', 'spa', 'other']), def: 'hotel' }, { k: 'code' }, { k: 'phone' }, { k: 'email', type: 'email' }, { k: 'address' }, { k: 'timezone', def: 'Africa/Kigali' }, { k: 'is_active', type: 'bool', def: true }] },
    'tax-rates': { title: 'taxes', fixed: 'core.settings', entity: 'tax_rate', columns: ['name', 'key', { k: 'rate_bp', type: 'bp' }, 'kind', { k: 'inclusive', type: 'bool' }, { k: 'is_active', type: 'bool' }],
        fields: [{ k: 'key', req: 1, hint: 'Short id, e.g. vat' }, { k: 'name', req: 1 }, { k: 'rate_bp', type: 'bp', req: 1, hint: 'Percent, e.g. 18' }, { k: 'kind', type: 'select', opts: S(['tax', 'service_charge', 'levy']), def: 'tax' }, { k: 'inclusive', type: 'bool', hint: 'Prices already include this' }, { k: 'is_active', type: 'bool', def: true }] },
    'payment-methods': { title: 'payment_methods', fixed: 'core.settings', entity: 'payment_method', columns: ['name', 'key', 'type', { k: 'is_active', type: 'bool' }],
        fields: [{ k: 'key', req: 1 }, { k: 'name', req: 1 }, { k: 'type', type: 'select', opts: S(['cash', 'momo', 'card', 'bank', 'room_charge', 'voucher', 'other']), def: 'cash' }, { k: 'position', type: 'number', def: 0 }, { k: 'is_active', type: 'bool', def: true }] },
    'custom-fields': { title: 'custom_fields', fixed: 'core.settings', entity: 'custom_field', columns: ['entity', 'key', { k: 'label', type: 'i18n' }, 'type', { k: 'required', type: 'bool' }],
        fields: [{ k: 'entity', type: 'select', req: 1, opts: S(['guest', 'reservation', 'booking', 'catalog_item', 'room_type', 'room', 'employee', 'supplier', 'inventory_item', 'expense', 'property']) }, { k: 'key', req: 1, hint: 'letters, numbers, dashes' }, { k: 'label', type: 'i18n', req: 1 }, { k: 'type', type: 'select', opts: S(['text', 'textarea', 'number', 'date', 'select', 'multiselect', 'boolean']), def: 'text' }, { k: 'options', type: 'tags', hint: 'For select / multiselect' }, { k: 'required', type: 'bool' }, { k: 'position', type: 'number', def: 0 }, { k: 'is_active', type: 'bool', def: true }] },
};

// Catalog items serve menu, products and bookable services. Pages pass a kind list.
export const catalogFields = (kinds) => [
    { k: 'kind', type: 'select', opts: S(kinds), req: 1, def: kinds[0] },
    { k: 'name', req: 1 }, { k: 'category_id', type: 'ref', ref: 'catalog-categories' },
    { k: 'price', type: 'money', req: 1 }, { k: 'cost', type: 'money' },
    { k: 'pricing_model', type: 'select', opts: S(['flat', 'per_person', 'per_group', 'per_day', 'per_night', 'per_hour']), def: 'flat', hint: 'How the price scales: per person, per group, per day…' },
    { k: 'station', type: 'select', opts: S(['none', 'kitchen', 'bar']), def: 'none' },
    { k: 'sku' }, { k: 'barcode' },
    { k: 'description', type: 'textarea' },
    { k: 'options', type: 'json', hint: 'Advanced: {"child_percent":50,"tiers":[{"min_pax":4,"price":90000}],"max_pax":12,"requires_assignment":true,"components":[{"catalog_item_id":"…","quantity":1}]}' },
    { k: 'property_id', type: 'ref', ref: 'properties' },
    { k: 'track_stock', type: 'bool' }, { k: 'is_active', type: 'bool', def: true },
];
export const MENU_KINDS = ['menu', 'drink', 'product', 'service', 'other'];
export const TOUR_KINDS = ['tour', 'activity', 'transfer', 'rental', 'package', 'spa', 'event', 'service'];
