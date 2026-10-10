<?php

namespace App\Models\Domain;

use App\Models\TenantModel;

class BookingAssignment extends TenantModel
{
    protected $table = 'booking_assignments';

    protected array $dates = ['date'];
}
