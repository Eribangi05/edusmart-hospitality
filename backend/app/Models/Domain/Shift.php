<?php

namespace App\Models\Domain;

use App\Models\TenantModel;

class Shift extends TenantModel
{
    protected $table = 'shifts';

    protected array $datetimes = ['opened_at', 'closed_at'];
}
