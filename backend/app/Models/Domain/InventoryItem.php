<?php

namespace App\Models\Domain;

use App\Models\TenantModel;

class InventoryItem extends TenantModel
{
    protected $table = 'inventory_items';
}
