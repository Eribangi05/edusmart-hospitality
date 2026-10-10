<?php

namespace App\Models\Domain;

use App\Models\TenantModel;

class BookingLine extends TenantModel
{
    protected $table = 'booking_lines';
}
