<?php

namespace App\Modules\Lenses\Models;

use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Extensia unui produs de tip „lens”: familie, design, forma de livrare și măsurătorile cerute.
 */
class LensProduct extends Model
{
    public const SUPPLY_FORMS = [
        'bruta' => 'Lentilă brută (semifabricat)',
        'semifinisata' => 'Lentilă semifinisată',
        'finita_nedebitata' => 'Lentilă finită, nedebitată',
        'personalizata' => 'Lentilă personalizată după rețetă',
        'montata' => 'Lentile montate în ramă',
    ];

    public const MEASUREMENTS = [
        'fitting_height' => 'Înălțimea de montaj',
        'pd_monocular' => 'Distanța pupilară pe fiecare ochi',
    ];

    protected $table = 'lens_products';

    protected $primaryKey = 'product_id';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'required_measurements' => 'array',
            'usages' => 'array',
            'quote_only' => 'boolean',
            'is_confirmed' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(LensFamily::class, 'family_id');
    }

    public function offerings(): HasMany
    {
        return $this->hasMany(LensOffering::class, 'lens_product_id', 'product_id')->orderBy('sort');
    }

    public function supplyFormLabel(): string
    {
        return self::SUPPLY_FORMS[$this->supply_form] ?? $this->supply_form;
    }
}
