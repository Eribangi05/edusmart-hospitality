<?php

namespace App\Models\Domain;

use App\Models\TenantModel;

class Recipe extends TenantModel
{
    protected $table = 'recipes';
}
