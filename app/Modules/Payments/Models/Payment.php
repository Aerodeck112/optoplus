<?php

namespace App\Modules\Payments\Models;

use App\Models\User;
use App\Modules\Orders\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** O încercare de plată. Nu conține niciodată date de card (acestea rămân la procesator). */
class Payment extends Model
{
    public const STATUSES = [
        'initiata' => 'Inițiată', 'in_asteptare' => 'În așteptare', 'autorizata' => 'Autorizată', 'platita' => 'Plătită',
        'esuata' => 'Eșuată', 'anulata' => 'Anulată', 'rambursata' => 'Rambursată',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime', 'last_checked_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function isFinal(): bool
    {
        return in_array($this->status, ['platita', 'esuata', 'anulata', 'rambursata'], true);
    }
}
