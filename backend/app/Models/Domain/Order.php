<?php

namespace App\Models\Domain;

use App\Models\TenantModel;

class Order extends TenantModel
{
    protected $table = 'orders';

    protected array $datetimes = ['closed_at'];
}
