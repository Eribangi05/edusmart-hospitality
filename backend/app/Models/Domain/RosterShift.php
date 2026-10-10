<?php

namespace App\Models\Domain;

use App\Models\TenantModel;

class RosterShift extends TenantModel
{
    protected $table = 'roster_shifts';

    protected array $dates = ['date'];
}
