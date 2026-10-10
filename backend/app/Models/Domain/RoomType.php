<?php

namespace App\Models\Domain;

use App\Models\TenantModel;

class RoomType extends TenantModel
{
    protected $table = 'room_types';

    protected array $json = ['amenities'];
}
