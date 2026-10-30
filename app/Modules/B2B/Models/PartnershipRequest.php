<?php

namespace App\Modules\B2B\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnershipRequest extends Model
{
    public const STATUSES = ['noua' => 'Nouă', 'in_analiza' => 'În analiză', 'aprobata' => 'Aprobată', 'respinsa' => 'Respinsă'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['documents' => 'array', 'handled_at' => 'datetime'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
