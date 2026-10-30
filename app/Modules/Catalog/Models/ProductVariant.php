<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_active' => 'boolean',
            'sku_generated' => 'boolean',
            'price' => 'integer',
            'compare_at_price' => 'integer',
            'cost' => 'integer',
            'stock_qty' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'variant_id')->orderBy('sort');
    }

    public function displayName(): string
    {
        return $this->product->name.($this->name ? ' — '.$this->name : '');
    }

    /** Stocul gestionat și epuizat (null = negestionat, nu înseamnă „în stoc”). */
    public function isOutOfStock(): bool
    {
        return $this->stock_qty !== null && $this->stock_qty <= 0;
    }
}
