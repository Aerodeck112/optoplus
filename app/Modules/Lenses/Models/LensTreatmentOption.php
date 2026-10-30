<?php

namespace App\Modules\Lenses\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LensTreatmentOption extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function treatment(): BelongsTo
    {
        return $this->belongsTo(LensTreatment::class, 'treatment_id');
    }
}
