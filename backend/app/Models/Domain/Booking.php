<?php

namespace App\Models\Domain;

use App\Models\TenantModel;

class Booking extends TenantModel
{
    protected $table = 'bookings';

    protected array $dates = ['date'];
}
