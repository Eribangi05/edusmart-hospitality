<?php

namespace App\Models\Domain;

use App\Models\TenantModel;

class Guest extends TenantModel
{
    protected $table = 'guests';

    protected array $dates = ['dob'];
}
