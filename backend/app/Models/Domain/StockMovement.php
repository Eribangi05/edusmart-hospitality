<?php

namespace App\Models\Domain;

use App\Models\TenantModel;

class StockMovement extends TenantModel
{
    protected $table = 'stock_movements';
}
