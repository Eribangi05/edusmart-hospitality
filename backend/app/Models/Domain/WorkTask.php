<?php

namespace App\Models\Domain;

use App\Models\TenantModel;

class WorkTask extends TenantModel
{
    protected $table = 'work_tasks';

    protected array $datetimes = ['due_at', 'completed_at'];

    protected array $json = ['photos'];
}
