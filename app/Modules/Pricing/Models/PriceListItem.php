<?php

namespace App\Modules\Pricing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceListItem extends Model
{
    public const TYPES = ['variant' => 'Variantă de produs', 'offering' => 'Ofertă de lentilă', 'offering_treatment' => 'Tratament pe ofertă'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['price' => 'integer', 'min_qty' => 'integer'];
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }
}
