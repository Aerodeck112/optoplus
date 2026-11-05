<?php

namespace App\Modules\Lenses\Models;

use App\Modules\Cms\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Tratament sau opțiune de lentilă (antireflex, filtru lumină albastră, fotocromatic...). */
class LensTreatment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['requires_option' => 'boolean', 'is_confirmed' => 'boolean', 'is_active' => 'boolean'];
    }

    public function options(): HasMany
    {
        return $this->hasMany(LensTreatmentOption::class, 'treatment_id')->orderBy('sort');
    }

    /** Lentilele (ofertele) la care tratamentul este disponibil, cu prețul pe fiecare. */
    public function offerings(): HasMany
    {
        return $this->hasMany(LensOfferingTreatment::class, 'treatment_id');
    }

    public function datasheet(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'datasheet_media_id');
    }
}
