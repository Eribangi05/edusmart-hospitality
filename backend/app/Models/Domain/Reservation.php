<?php

namespace App\Models\Domain;

use App\Models\TenantModel;

class Reservation extends TenantModel
{
    protected $table = 'reservations';

    protected array $datetimes = ['checked_in_at', 'checked_out_at'];

    protected array $dates = ['check_in', 'check_out'];
}
