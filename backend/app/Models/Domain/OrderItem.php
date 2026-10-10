<?php

namespace App\Models\Domain;

use App\Models\TenantModel;

class OrderItem extends TenantModel
{
    protected $table = 'order_items';

    protected array $json = ['modifiers'];
}
