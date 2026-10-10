<?php

namespace App\Models\Domain;

use App\Models\TenantModel;

class Payment extends TenantModel
{
    protected $table = 'payments';

    protected array $json = ['provider'];
}
