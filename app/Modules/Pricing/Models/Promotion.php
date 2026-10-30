<?php

namespace App\Modules\Pricing\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Promoție configurabilă. Regula de cumul (documentată în docs/PRETURI.md): promoțiile se aplică în ordinea
 * priorității; una necumulabilă oprește aplicarea altora pe aceeași componentă.
 */
class Promotion extends Model
{
    public const SCOPES = [
        'order' => 'Întreaga comandă', 'frames' => 'Rame', 'lenses' => 'Lentile', 'treatments' => 'Tratamente',
        'category' => 'Categorii', 'brand' => 'Mărci', 'product' => 'Produse',
    ];

    public const TYPES = ['percent' => 'Procent', 'fixed_amount' => 'Sumă fixă', 'fixed_price' => 'Preț fix'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'scope_ids' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'stackable' => 'boolean',
            'requires_coupon' => 'boolean',
            'requires_proof' => 'boolean',
        ];
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    public function scopeRunning(Builder $q, \DateTimeInterface $now): Builder
    {
        return $q->where('is_active', true)
            ->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->where(fn ($w) => $w->whereNull('max_uses')->orWhereColumn('uses_count', '<', 'max_uses'));
    }

    public function label(): string
    {
        return $this->customer_label ?: $this->name;
    }
}
