<?php

namespace App\Services\Inventory;

use App\Models\Domain\InventoryItem;
use App\Models\Domain\Order;
use App\Models\Domain\OrderItem;
use App\Models\Domain\Recipe;
use App\Models\Domain\StockMovement;
use App\Support\Tenancy;

/** Stock only changes through the append-only movement ledger. */
class InventoryService
{
    public function level(string $inventoryItemId, ?string $propertyId = null): float
    {
        return (float) StockMovement::where('inventory_item_id', $inventoryItemId)
            ->when($propertyId, fn ($q) => $q->where('property_id', $propertyId))->sum('quantity');
    }

    public function move(InventoryItem $item, float $qty, string $type, array $x = []): StockMovement
    {
        if (! empty($x['idempotency_key']) && ($m = StockMovement::where('idempotency_key', $x['idempotency_key'])->first())) {
            return $m;
        }
        $m = new StockMovement;
        $m->forceFill([
            'property_id' => $x['property_id'] ?? $item->property_id, 'inventory_item_id' => $item->id, 'quantity' => $qty, 'type' => $type,
            'unit_cost' => $x['unit_cost'] ?? $item->cost, 'ref_type' => $x['ref_type'] ?? null, 'ref_id' => $x['ref_id'] ?? null,
            'note' => $x['note'] ?? null, 'user_id' => request()?->user()?->id, 'idempotency_key' => $x['idempotency_key'] ?? null,
        ])->save();

        return $m;
    }

    /** Deduct ingredients (recipes) and directly tracked products for a paid order. Idempotent. */
    public function deductForOrder(Order $order): void
    {
        if (! in_array('inventory', app(Tenancy::class)->get()->enabledModules(), true)) {
            return;
        }
        foreach (OrderItem::where('order_id', $order->id)->whereNull('voided_at')->get() as $line) {
            if (! $line->catalog_item_id) {
                continue;
            }
            $recipe = Recipe::where('catalog_item_id', $line->catalog_item_id)->get();
            if ($recipe->isNotEmpty()) {
                foreach ($recipe as $r) {
                    $item = InventoryItem::find($r->inventory_item_id);
                    $item && $this->move($item, -1 * $r->quantity * $line->quantity, 'sale', ['ref_type' => 'order', 'ref_id' => $order->id, 'property_id' => $order->property_id, 'idempotency_key' => "sale:{$line->id}:{$r->inventory_item_id}"]);
                }
            } else {
                foreach (InventoryItem::where('catalog_item_id', $line->catalog_item_id)->get() as $item) {
                    $this->move($item, -1 * (float) $line->quantity, 'sale', ['ref_type' => 'order', 'ref_id' => $order->id, 'property_id' => $order->property_id, 'idempotency_key' => "sale:{$line->id}:{$item->id}"]);
                }
            }
        }
    }
}
