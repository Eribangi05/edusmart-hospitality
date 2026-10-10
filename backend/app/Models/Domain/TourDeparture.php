<?php

namespace App\Models\Domain;

use App\Models\TenantModel;

class TourDeparture extends TenantModel
{
    protected $table = 'tour_departures';

    protected array $dates = ['date'];
}
