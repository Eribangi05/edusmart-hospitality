<?php

namespace App\Models\Domain;

use App\Models\TenantModel;

class BookingParticipant extends TenantModel
{
    protected $table = 'booking_participants';
}
