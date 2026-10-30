<?php

namespace App\Modules\Lenses\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tratamentul disponibil pentru o ofertă: preț adițional, inclus sau nu, opțiunile permise. */
class LensOfferingTreatment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_included' => 'boolean', 'option_codes' => 'array', 'price' => 'integer'];
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(LensOffering::class, 'offering_id');
    }

    public function treatment(): BelongsTo
    {
        return $this->belongsTo(LensTreatment::class, 'treatment_id');
    }

    public function allowsOption(?string $code): bool
    {
        return $this->option_codes === null || in_array($code, $this->option_codes, true);
    }
}
