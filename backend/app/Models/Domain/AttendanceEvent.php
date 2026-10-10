<?php

namespace App\Models\Domain;

use App\Models\TenantModel;

class AttendanceEvent extends TenantModel
{
    protected $table = 'attendance_events';

    protected array $datetimes = ['at'];
}
