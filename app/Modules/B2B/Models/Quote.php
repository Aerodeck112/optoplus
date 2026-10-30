<?php

namespace App\Modules\B2B\Models;

use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Oferta de preț B2B, cu versiuni. Doar o versiune trimisă și nevalidă încă poate fi acceptată de partener. */
class Quote extends Model
{
    public const STATUSES = ['ceruta' => 'Cerută', 'ciorna' => 'În pregătire', 'trimisa' => 'Trimisă', 'acceptata' => 'Acceptată', 'respinsa' => 'Respinsă', 'expirata' => 'Expirată', 'anulata' => 'Anulată'];

    protected $guarded = ['id'];

    public function versions(): HasMany
    {
        return $this->hasMany(QuoteVersion::class)->orderBy('number');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(QuoteVersion::class, 'current_version_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
