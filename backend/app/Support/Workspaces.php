<?php

namespace App\Support;

use App\Models\User;

/** Each job gets its own entry point and layout. Which ones a user sees follows their permissions and the company's modules. */
class Workspaces
{
    /** @return array<int, array{key:string,label:string,url:string,desc:string,icon:string}> */
    public static function for(User $u, array $modules): array
    {
        $has = fn (string $m) => in_array($m, $modules, true);
        $out = [];
        if ($u->can2('*') || $u->can2('core.users') || $u->can2('core.settings') || $u->can2('finance.view') || $u->can2('inventory.view') || $u->can2('staff.payroll_export') || $u->can2('rooms.create') || $u->can2('tourism.create')) {
            $out[] = ['key' => 'console', 'label' => 'Manager console', 'url' => '/app', 'icon' => 'dashboard', 'desc' => 'Dashboard, reports, setup, users and everything else'];
        }
        if ($has('pos') && $u->can2('pos.create')) {
            $out[] = ['key' => 'terminal', 'label' => 'Sales terminal', 'url' => '/terminal', 'icon' => 'pos', 'desc' => 'Full-screen touch POS: tables, orders, payments'];
        }
        if ($has('pos') && $u->can2('pos.view')) {
            $out[] = ['key' => 'kds', 'label' => 'Kitchen & bar display', 'url' => '/kds', 'icon' => 'kds', 'desc' => 'Live tickets for the kitchen and the bar'];
        }
        if (($has('rooms') && $u->can2('rooms.view')) || ($has('tourism') && $u->can2('tourism.view'))) {
            $out[] = ['key' => 'desk', 'label' => 'Front desk', 'url' => '/desk', 'icon' => 'desk', 'desc' => 'Arrivals, departures, in-house guests, timeline and tours'];
        }
        if (($has('housekeeping') && $u->can2('housekeeping.view')) || $has('staff') || ($has('tourism') && $u->can2('tourism.edit'))) {
            $out[] = ['key' => 'staff', 'label' => 'My shift', 'url' => '/staff', 'icon' => 'staff', 'desc' => 'My tasks and trips, clock in and out'];
        }

        return $out;
    }

    /** Where to send someone right after login. */
    public static function landing(array $workspaces, User $u): string
    {
        if ($u->can2('*') || ! $workspaces) {
            return '/app';
        }
        $managerish = $u->can2('core.users') || $u->can2('finance.view');
        if ($managerish) {
            return '/app';
        }

        return count($workspaces) === 1 ? $workspaces[0]['url'] : '/workspaces';
    }
}
