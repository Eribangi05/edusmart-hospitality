<?php

namespace App\Models\Domain;

use App\Models\TenantModel;

class FolioEntry extends TenantModel
{
    protected $table = 'folio_entries';

    protected array $dates = ['business_date'];
}
