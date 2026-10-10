<?php

namespace App\Models\Domain;

use App\Models\TenantModel;

class Reservation extends TenantModel
{
    protected $table = 'reservations';

    protected array $dates = ['check_in', 'check_out'];
}
