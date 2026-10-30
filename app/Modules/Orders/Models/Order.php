<?php

namespace App\Modules\Orders\Models;

use App\Modules\B2B\Models\Organization;
use App\Modules\Customers\Models\Customer;
use App\Modules\Payments\Models\Payment;
use App\Modules\Production\Models\ProductionJob;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Comanda are trei stări independente: comercială, de plată și de producție. Fiecare schimbare se scrie în
 * `order_events` (cu actorul), iar sumele și liniile sunt un snapshot al motorului de preț la plasare.
 */
class Order extends Model
{
    public const COMMERCIAL = ['noua' => 'Nouă', 'confirmata' => 'Confirmată', 'anulata' => 'Anulată', 'finalizata' => 'Finalizată'];

    public const PAYMENT = [
        'neplatita' => 'Neplătită', 'in_asteptare' => 'Plată în așteptare', 'autorizata' => 'Autorizată',
        'platita' => 'Plătită', 'partial_rambursata' => 'Rambursată parțial', 'rambursata' => 'Rambursată', 'esuata' => 'Plată eșuată',
    ];

    public const PAYMENT_METHODS = [
        'card' => 'Card online', 'transfer_bancar' => 'Transfer bancar (ordin de plată)',
        'ramburs' => 'Ramburs la curier', 'numerar_ridicare' => 'Plată la ridicare',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'billing_address' => 'array', 'shipping_address' => 'array', 'prices_include_vat' => 'boolean',
            'is_archived_import' => 'boolean', 'confirmed_at' => 'datetime', 'cancelled_at' => 'datetime',
            'analytics_purchase_sent_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->orderBy('created_at')->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('id');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class)->orderBy('id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->orderBy('id');
    }

    public function productionJobs(): HasMany
    {
        return $this->hasMany(ProductionJob::class)->orderBy('id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function commercialLabel(): string
    {
        return self::COMMERCIAL[$this->commercial_status] ?? $this->commercial_status;
    }

    public function paymentLabel(): string
    {
        return self::PAYMENT[$this->payment_status] ?? $this->payment_status;
    }

    public function paymentMethodLabel(): string
    {
        return self::PAYMENT_METHODS[$this->payment_method] ?? $this->payment_method;
    }

    public function isPaid(): bool
    {
        return in_array($this->payment_status, ['platita', 'partial_rambursata', 'rambursata'], true);
    }

    public function balanceDue(): int
    {
        return max(0, $this->total - $this->paid_total);
    }

    public function requiresProduction(): bool
    {
        return $this->production_status !== 'nu_se_aplica';
    }
}
