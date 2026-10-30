<?php

namespace App\Modules\Lenses\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Familia de lentile: monofocal, progresiv, degresiv (birou), bifocal. */
class LensFamily extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['usages' => 'array', 'requires_add' => 'boolean', 'requires_fitting_height' => 'boolean', 'is_active' => 'boolean'];
    }

    public function lensProducts(): HasMany
    {
        return $this->hasMany(LensProduct::class, 'family_id');
    }
}
