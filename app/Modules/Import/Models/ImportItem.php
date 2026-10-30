<?php

namespace App\Modules\Import\Models;

use Illuminate\Database\Eloquent\Model;

/** Maparea unei înregistrări din sursă (ex. produsul WooCommerce 15799) la ținta din Optoplus. */
class ImportItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['messages' => 'array'];
    }
}
