<?php

namespace App\Models\Domain;

use App\Models\TenantModel;

class PurchaseOrder extends TenantModel
{
    protected $table = 'purchase_orders';

    protected array $json = ['lines'];

    protected array $dates = ['expected_on'];
}
