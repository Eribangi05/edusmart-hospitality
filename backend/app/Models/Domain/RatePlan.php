<?php

namespace App\Models\Domain;

use App\Models\TenantModel;

class RatePlan extends TenantModel
{
    protected $table = 'rate_plans';

    protected array $json = ['days_of_week'];

    protected array $dates = ['valid_from', 'valid_to'];
}
