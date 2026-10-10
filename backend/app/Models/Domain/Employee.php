<?php

namespace App\Models\Domain;

use App\Models\TenantModel;

class Employee extends TenantModel
{
    protected $table = 'employees';

    protected array $dates = ['hired_on'];
}
