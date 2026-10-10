<?php

namespace App\Models\Domain;

use App\Models\TenantModel;

class Voucher extends TenantModel
{
    protected $table = 'vouchers';

    protected array $dates = ['valid_from', 'valid_to'];
}
