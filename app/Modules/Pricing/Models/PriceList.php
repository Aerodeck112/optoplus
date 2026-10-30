<?php

namespace App\Modules\Pricing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Listă de preț pentru parteneri (B2B). Prețurile B2C de bază stau pe variante și pe oferte. */
class PriceList extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['prices_include_vat' => 'boolean', 'is_active' => 'boolean'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PriceListItem::class);
    }
}
