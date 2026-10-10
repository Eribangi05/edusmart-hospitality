<?php

namespace App\Models\Domain;

use App\Models\TenantModel;

class LeaveRequest extends TenantModel
{
    protected $table = 'leave_requests';

    protected array $dates = ['from_date', 'to_date'];
}
