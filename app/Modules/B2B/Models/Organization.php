<?php

namespace App\Modules\B2B\Models;

use App\Modules\Pricing\Models\PriceList;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Organizație parteneră (clinică, cabinet, magazin de optică). Accesul la portal doar după aprobare manuală. */
class Organization extends Model
{
    public const STATUS_REQUEST = 'cerere';

    public const STATUS_APPROVED = 'aprobata';

    public const STATUS_SUSPENDED = 'suspendata';

    public const STATUS_REJECTED = 'respinsa';

    public const KINDS = ['clinica' => 'Clinică oftalmologică', 'cabinet' => 'Cabinet oftalmologic / optometrie', 'magazin_optica' => 'Magazin de optică', 'altul' => 'Altul'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['show_prices_with_vat' => 'boolean', 'approved_at' => 'datetime', 'terms_approved_at' => 'datetime'];
    }

    public function members(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /** Termenele de plată și creditul se aplică doar dacă au fost aprobate explicit. */
    public function hasApprovedCredit(): bool
    {
        return $this->isApproved() && $this->terms_approved_at !== null && $this->payment_terms_days !== null;
    }
}
