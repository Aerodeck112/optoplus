<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Dimensiunile ramei, în milimetri, cu proveniența fiecărei valori (export, descriere, manual). */
class FrameSpec extends Model
{
    public const RIM_TYPES = [
        'full' => 'Ramă întreagă',
        'semi_nylon' => 'Semi-ramă (fir de nylon)',
        'rimless_screw' => 'Fără ramă (prindere în șuruburi/capse)',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['sources' => 'array'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** „54-17-140” (lățime lentilă – punte – braț), notația gravată pe brațul ramei. */
    public function sizeNotation(): ?string
    {
        if (! $this->lens_width_mm) {
            return null;
        }

        return implode('-', array_filter([$this->lens_width_mm, $this->bridge_mm, $this->temple_mm]));
    }
}
