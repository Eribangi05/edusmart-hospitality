<?php

namespace App\Models\Domain;

use App\Models\TenantModel;

class OrderItem extends TenantModel
{
    protected $table = 'order_items';

    protected array $datetimes = ['voided_at'];

    protected array $json = ['modifiers'];
}
