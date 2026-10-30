<?php

namespace App\Modules\Lenses\Models;

use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un rând al matricei de fabricație: produs-lentilă × material, cu intervalele în care se poate produce,
 * prețul și termenul. Dioptriile sunt în sutimi, în notația cu cilindru negativ.
 */
class LensOffering extends Model
{
    public const STATUS_ACTIVE = 'activ';

    public const STATUS_UNCONFIRMED = 'neconfirmat';

    public const STATUS_INACTIVE = 'inactiv';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'requires_review' => 'boolean',
            'price' => 'integer',
            'cost' => 'integer',
            'sph_min' => 'integer', 'sph_max' => 'integer',
            'cyl_min' => 'integer', 'cyl_max' => 'integer',
            'max_combined' => 'integer', 'add_min' => 'integer', 'add_max' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'lens_product_id');
    }

    public function lensProduct(): BelongsTo
    {
        return $this->belongsTo(LensProduct::class, 'lens_product_id', 'product_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(LensMaterial::class, 'material_id');
    }

    public function treatments(): HasMany
    {
        return $this->hasMany(LensOfferingTreatment::class, 'offering_id');
    }

    /** Ofertele care pot fi vândute: active sau (până la confirmarea laboratorului) neconfirmate, dacă e permis. */
    public function scopeSellable(Builder $q): Builder
    {
        $statuses = config('optoplus.lenses.sell_unconfirmed', true)
            ? [self::STATUS_ACTIVE, self::STATUS_UNCONFIRMED]
            : [self::STATUS_ACTIVE];

        return $q->whereIn('status', $statuses);
    }

    public function isSellable(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            || ($this->status === self::STATUS_UNCONFIRMED && config('optoplus.lenses.sell_unconfirmed', true));
    }
}
