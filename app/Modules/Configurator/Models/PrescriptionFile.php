<?php

namespace App\Modules\Configurator\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Fișier de rețetă: stocat privat, sub nume aleator; se descarcă doar prin controller, cu autorizare și audit. */
class PrescriptionFile extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $hidden = ['disk_path'];

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }
}
