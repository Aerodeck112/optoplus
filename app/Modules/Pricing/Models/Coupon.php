<?php

namespace App\Modules\Pricing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Coupon extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'is_active' => 'boolean'];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public static function normalize(string $code): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($code)) ?? '');
    }

    public function isUsable(\DateTimeInterface $now): bool
    {
        return $this->is_active
            && ($this->expires_at === null || $this->expires_at >= $now)
            && ($this->max_uses === null || $this->uses_count < $this->max_uses);
    }
}
