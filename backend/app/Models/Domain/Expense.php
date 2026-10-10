<?php

namespace App\Models\Domain;

use App\Models\TenantModel;

class Expense extends TenantModel
{
    protected $table = 'expenses';

    protected array $dates = ['date'];
}
