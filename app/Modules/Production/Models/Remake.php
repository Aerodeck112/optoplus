<?php

namespace App\Modules\Production\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Remake extends Model
{
    public const REASONS = ['eroare_productie' => 'Eroare de producție', 'eroare_reteta' => 'Eroare de rețetă', 'deteriorare_transport' => 'Deteriorare la transport', 'adaptare' => 'Neadaptare', 'altul' => 'Alt motiv'];

    protected $guarded = ['id'];

    public function job(): BelongsTo
    {
        return $this->belongsTo(ProductionJob::class, 'production_job_id');
    }
}
