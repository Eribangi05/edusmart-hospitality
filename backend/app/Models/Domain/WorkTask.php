<?php

namespace App\Models\Domain;

use App\Models\TenantModel;

class WorkTask extends TenantModel
{
    protected $table = 'work_tasks';

    protected array $json = ['photos'];
}
