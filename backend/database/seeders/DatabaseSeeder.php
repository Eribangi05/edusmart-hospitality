<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Support\Modules;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $all = Modules::switchable();
        $plans = [
            ['trial', 'Free trial (30 days)', 0, $all],
            ['starter', 'Starter', 30000, ['rooms', 'pos', 'housekeeping']],
            ['business', 'Business', 80000, ['rooms', 'pos', 'tourism', 'inventory', 'shop', 'housekeeping', 'staff']],
            ['enterprise', 'Enterprise', 200000, $all],
        ];
        foreach ($plans as [$key, $name, $price, $modules]) {
            Plan::updateOrCreate(['key' => $key], ['name' => $name, 'price_monthly' => $price, 'modules' => $modules, 'limits' => []]);
        }
    }
}
