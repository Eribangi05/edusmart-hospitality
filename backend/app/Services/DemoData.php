<?php

namespace App\Services;

use App\Models\CustomField;
use App\Models\Domain as D;
use App\Models\Property;
use App\Models\Tenant;
use App\Services\Inventory\InventoryService;
use App\Support\Modules;
use App\Support\Tenancy;

/** A realistic Rwandan lodge + restaurant + tour operator, to show what the platform can do. */
class DemoData
{
    public function create(string $email, string $password): Tenant
    {
        [$tenant] = app(TenantProvisioner::class)->register([
            'company' => 'Lake Kivu Lodge (demo)', 'name' => 'Demo Owner', 'email' => $email, 'password' => $password, 'phone' => '250788000000',
            'property_name' => 'Lake Kivu Lodge', 'property_type' => 'lodge', 'modules' => Modules::switchable(), 'locale' => 'en',
        ]);

        app(Tenancy::class)->run($tenant, function () {
            $property = Property::first();
            $p = $property->id;

            // custom fields: each company decides what it needs to capture
            CustomField::create(['entity' => 'guest', 'key' => 'passport_expiry', 'label' => ['en' => 'Passport expiry', 'fr' => 'Expiration du passeport', 'rw' => 'Igihe pasiporo izarangirira'], 'type' => 'date', 'required' => false]);
            CustomField::create(['entity' => 'booking', 'key' => 'dietary', 'label' => ['en' => 'Dietary needs', 'fr' => 'Régime alimentaire', 'rw' => 'Ibyo kurya bitemewe'], 'type' => 'select', 'options' => ['none', 'vegetarian', 'vegan', 'halal'], 'required' => false]);
            CustomField::create(['entity' => 'reservation', 'key' => 'arrival_flight', 'label' => ['en' => 'Arrival flight', 'fr' => 'Vol d\'arrivée', 'rw' => 'Indege ihagera'], 'type' => 'text', 'required' => false]);

            // rooms
            $mk = function (string $cls, array $a) use ($p) {
                $m = new $cls;
                $m->forceFill($a + ['property_id' => $p])->save();

                return $m;
            };
            $std = $mk(D\RoomType::class, ['name' => 'Standard Lake View', 'code' => 'STD', 'base_price' => 85000, 'max_adults' => 2, 'max_children' => 1, 'amenities' => ['wifi', 'breakfast']]);
            $suite = $mk(D\RoomType::class, ['name' => 'Family Suite', 'code' => 'STE', 'base_price' => 180000, 'max_adults' => 4, 'max_children' => 2, 'amenities' => ['wifi', 'breakfast', 'balcony']]);
            foreach ([['101', $std], ['102', $std], ['103', $std], ['104', $std], ['201', $suite], ['202', $suite]] as [$n, $t]) {
                $mk(D\Room::class, ['room_type_id' => $t->id, 'number' => $n, 'housekeeping_status' => 'clean']);
            }
            $mk(D\RatePlan::class, ['name' => 'Weekend', 'price' => 110000, 'room_type_id' => $std->id, 'days_of_week' => [5, 6], 'priority' => 5, 'min_nights' => 1]);
            $mk(D\RatePlan::class, ['name' => 'High season (Jul-Aug)', 'price' => 130000, 'room_type_id' => $std->id, 'valid_from' => now()->year.'-07-01', 'valid_to' => now()->year.'-08-31', 'priority' => 8, 'min_nights' => 1]);

            // restaurant & bar
            $food = $mk(D\CatalogCategory::class, ['name' => 'Food', 'kind' => 'menu', 'position' => 1]);
            $drinks = $mk(D\CatalogCategory::class, ['name' => 'Drinks', 'kind' => 'drink', 'position' => 2]);
            $menu = [
                ['Brochette (goat)', 'menu', 4500, 'kitchen', $food], ['Tilapia & chips', 'menu', 7500, 'kitchen', $food], ['Isombe & rice', 'menu', 4000, 'kitchen', $food],
                ['Omelette', 'menu', 2500, 'kitchen', $food], ['Primus 72cl', 'drink', 1500, 'bar', $drinks], ['Mützig 50cl', 'drink', 1500, 'bar', $drinks],
                ['Rwandan coffee', 'drink', 1500, 'bar', $drinks], ['Fanta 30cl', 'drink', 800, 'bar', $drinks],
            ];
            $items = [];
            foreach ($menu as [$name, $kind, $price, $station, $cat]) {
                $items[$name] = $mk(D\CatalogItem::class, ['kind' => $kind, 'name' => $name, 'price' => $price, 'station' => $station, 'category_id' => $cat->id, 'pricing_model' => 'flat']);
            }
            foreach (['T1', 'T2', 'T3', 'T4', 'Terrace 1', 'Terrace 2'] as $i => $t) {
                $mk(D\DiningTable::class, ['name' => $t, 'section' => $i < 4 ? 'Indoor' : 'Terrace', 'capacity' => 4]);
            }

            // inventory with recipes
            $supplier = $mk(D\Supplier::class, ['name' => 'Gisenyi Fresh Market', 'phone' => '250788111111']);
            $eggs = $mk(D\InventoryItem::class, ['name' => 'Eggs', 'unit' => 'pcs', 'reorder_level' => 30, 'cost' => 150, 'supplier_id' => $supplier->id]);
            $primus = $mk(D\InventoryItem::class, ['name' => 'Primus 72cl (crate stock)', 'unit' => 'bottles', 'reorder_level' => 24, 'cost' => 900, 'catalog_item_id' => $items['Primus 72cl']->id]);
            $mk(D\Recipe::class, ['catalog_item_id' => $items['Omelette']->id, 'inventory_item_id' => $eggs->id, 'quantity' => 3]);
            $inv = app(InventoryService::class);
            $inv->move($eggs, 120, 'purchase', ['note' => 'Opening stock']);
            $inv->move($primus, 72, 'purchase', ['note' => 'Opening stock']);

            // tourism: priced per person, per group, per day and as a package
            $gorilla = $mk(D\CatalogItem::class, ['kind' => 'tour', 'name' => 'Gorilla trekking day trip', 'price' => 1500000, 'pricing_model' => 'per_person', 'options' => ['child_percent' => 100, 'max_pax' => 8, 'requires_assignment' => true], 'tax_rate_ids' => []]);
            $canoe = $mk(D\CatalogItem::class, ['kind' => 'activity', 'name' => 'Sunset canoe on Lake Kivu', 'price' => 20000, 'pricing_model' => 'per_person', 'options' => ['child_percent' => 50, 'tiers' => [['min_pax' => 5, 'price' => 16000]]]]);
            $transfer = $mk(D\CatalogItem::class, ['kind' => 'transfer', 'name' => 'Kigali airport transfer', 'price' => 120000, 'pricing_model' => 'per_group', 'options' => ['max_pax' => 6]]);
            $car = $mk(D\CatalogItem::class, ['kind' => 'rental', 'name' => '4x4 with driver', 'price' => 150000, 'pricing_model' => 'per_day']);
            $mk(D\CatalogItem::class, ['kind' => 'package', 'name' => 'Kivu weekend package', 'price' => 0, 'pricing_model' => 'flat', 'options' => ['components' => [['catalog_item_id' => $canoe->id, 'quantity' => 1], ['catalog_item_id' => $transfer->id, 'quantity' => 1]]]]);
            $guide = $mk(D\TourismResource::class, ['kind' => 'guide', 'name' => 'Jean Claude (guide)', 'phone' => '250788222222']);
            $mk(D\TourismResource::class, ['kind' => 'vehicle', 'name' => 'Land Cruiser RAE 123A', 'plate' => 'RAE 123A', 'capacity' => 7, 'cost_per_day' => 90000]);
            foreach ([2, 4, 7, 9, 12] as $days) {
                $mk(D\TourDeparture::class, ['catalog_item_id' => $canoe->id, 'date' => now()->addDays($days)->toDateString(), 'start_time' => '17:00', 'capacity' => 10, 'meeting_point' => 'Lodge jetty']);
            }
            $mk(D\TourDeparture::class, ['catalog_item_id' => $gorilla->id, 'date' => now()->addDays(3)->toDateString(), 'start_time' => '05:30', 'capacity' => 8, 'meeting_point' => 'Lodge reception']);

            // team
            $mk(D\Employee::class, ['name' => 'Aline Uwase', 'position' => 'Receptionist', 'pay_type' => 'monthly', 'pay_rate' => 180000]);
            $mk(D\Employee::class, ['name' => 'Eric Habimana', 'position' => 'Waiter', 'pay_type' => 'daily', 'pay_rate' => 6000]);
            $mk(D\Expense::class, ['category' => 'utilities', 'description' => 'Electricity', 'amount' => 45000, 'date' => now()->toDateString()]);
        });

        return $tenant;
    }
}
