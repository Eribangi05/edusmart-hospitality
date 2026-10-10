<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DemoDataTest extends TestCase
{
    public function test_demo_company_is_complete_and_usable(): void
    {
        Artisan::call('demo:seed', ['--email' => 'demo@edusmart.test', '--password' => 'demo-password-123']);
        $login = $this->postJson('/api/v1/auth/login', ['email' => 'demo@edusmart.test', 'password' => 'demo-password-123', 'device' => $this->device()])->assertOk();
        $h = ['Authorization' => 'Bearer '.$login->json('access_token')];
        $ctx = $this->getJson('/api/v1/me/context', $h)->json();
        $this->assertCount(8, $ctx['modules']);
        $this->assertCount(3, $ctx['custom_fields']);
        $this->assertSame(6, $this->getJson('/api/v1/rooms', $h)->json('meta.total'));
        $this->assertSame(13, $this->getJson('/api/v1/catalog-items', $h)->json('meta.total'));

        // a package quote works out of the box
        $items = collect($this->getJson('/api/v1/catalog-items?per_page=100', $h)->json('data'));
        $pkg = $items->firstWhere('name', 'Kivu weekend package');
        $this->postJson('/api/v1/tourism/quote', ['catalog_item_id' => $pkg['id'], 'pax_adults' => 2], $h)->assertOk()->assertJsonPath('data.lines.1.description', 'Kigali airport transfer');
        // the omelette recipe deducts eggs when sold
        $omelette = $items->firstWhere('name', 'Omelette');
        $o = $this->postJson('/api/v1/orders', ['items' => [['catalog_item_id' => $omelette['id'], 'quantity' => 2]]], $h)->json('data');
        $this->postJson("/api/v1/orders/{$o['id']}/pay", ['amount' => $o['total'], 'method_type' => 'cash'], $h)->assertCreated();
        $eggs = collect($this->getJson('/api/v1/stock', $h)->json('data'))->firstWhere('name', 'Eggs');
        $this->assertEquals(114, $eggs['quantity']);
    }
}
