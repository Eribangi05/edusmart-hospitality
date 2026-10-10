<?php

namespace App\Models\Domain;

use App\Models\TenantModel;

class CatalogItem extends TenantModel
{
    protected $table = 'catalog_items';

    protected array $json = ['name_i18n', 'tax_rate_ids', 'options'];
}
