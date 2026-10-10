<?php

namespace App\Support;

/**
 * Module registry and permission catalog.
 * Permissions are "<module>.<action>". Tenants may also define custom roles
 * from any subset of these.
 */
class Modules
{
    public const ACTIONS = ['view', 'create', 'edit', 'delete'];

    /** module => special actions beyond view/create/edit/delete */
    public const MODULES = [
        'core' => ['users', 'roles', 'settings', 'audit'],
        'rooms' => ['rate_override', 'check_in', 'check_out', 'folio'],
        'pos' => ['void', 'discount', 'refund', 'price_override', 'close_shift'],
        'tourism' => ['assign', 'dispatch', 'price_override'],
        'inventory' => ['adjust', 'receive', 'stocktake'],
        'shop' => ['refund', 'price_override'],
        'staff' => ['roster', 'attendance_manage', 'payroll_export'],
        'housekeeping' => ['assign', 'inspect'],
        'finance' => ['export', 'reconcile'],
    ];

    /** Modules a tenant may switch on/off (core is always on). */
    public static function switchable(): array
    {
        return array_values(array_diff(array_keys(self::MODULES), ['core']));
    }

    public static function all(): array
    {
        $out = [];
        foreach (self::MODULES as $module => $extra) {
            foreach (array_merge(self::ACTIONS, $extra) as $action) {
                $out[] = "$module.$action";
            }
        }

        return $out;
    }

    public static function forModule(string $module): array
    {
        return array_values(array_filter(self::all(), fn ($p) => str_starts_with($p, "$module.")));
    }

    /** Default system roles seeded for each new tenant. */
    public static function defaultRoles(): array
    {
        $viewOf = fn (array $mods) => array_merge(...array_map(fn ($m) => ["$m.view"], $mods));
        $all = fn (string $m) => self::forModule($m);

        return [
            'owner' => ['Owner', ['*']],
            'manager' => ['Manager', array_values(array_unique(array_merge(
                $all('rooms'), $all('pos'), $all('tourism'), $all('inventory'), $all('shop'),
                $all('housekeeping'), $all('staff'), $all('finance'), ['core.users', 'core.view', 'core.audit']
            )))],
            'receptionist' => ['Receptionist', ['rooms.view', 'rooms.create', 'rooms.edit', 'rooms.check_in', 'rooms.check_out', 'rooms.folio', 'tourism.view', 'tourism.create', 'tourism.edit', 'housekeeping.view']],
            'cashier' => ['Cashier', ['pos.view', 'pos.create', 'pos.edit', 'shop.view', 'shop.create', 'rooms.view', 'rooms.folio']],
            'waiter' => ['Waiter', ['pos.view', 'pos.create', 'pos.edit']],
            'bartender' => ['Bartender', ['pos.view', 'pos.create', 'pos.edit']],
            'kitchen' => ['Kitchen', ['pos.view']],
            'storekeeper' => ['Storekeeper', array_merge($viewOf(['inventory', 'shop']), ['inventory.create', 'inventory.edit', 'inventory.receive', 'inventory.adjust', 'inventory.stocktake'])],
            'housekeeper' => ['Housekeeper', ['housekeeping.view', 'housekeeping.edit']],
            'guide' => ['Tour guide / driver', ['tourism.view', 'tourism.edit']],
            'accountant' => ['Accountant', ['finance.view', 'finance.export', 'finance.reconcile', 'rooms.view', 'pos.view', 'tourism.view', 'inventory.view', 'staff.view', 'staff.payroll_export']],
        ];
    }
}
