<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Lenses\Models\LensProduct;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Produs din catalog: ramă, lentilă, accesoriu sau serviciu. Prețurile stau pe variante (bani, cu TVA).
 * `made_in_house` se folosește doar pentru lentilele produse în laboratorul Optoplus.
 */
class Product extends Model
{
    public const TYPE_FRAME = 'frame';

    public const TYPE_LENS = 'lens';

    public const TYPE_ACCESSORY = 'accessory';

    public const TYPE_SERVICE = 'service';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    /** Moduri de disponibilitate; „neconfirmat” nu afișează nicio promisiune de stoc. */
    public const AVAILABILITY = [
        'stoc_propriu' => 'În stoc',
        'furnizor' => 'Disponibil la furnizor',
        'la_comanda' => 'Disponibil la comandă',
        'indisponibil' => 'Indisponibil momentan',
        'neconfirmat' => 'Disponibilitate de confirmat',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'made_in_house' => 'boolean',
            'configurable_with_lenses' => 'boolean',
            'noindex' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function primaryCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'primary_category_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'product_categories');
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class, 'product_attribute_values')->withPivot('source');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort')->orderBy('id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort')->orderBy('id');
    }

    public function frameSpec(): HasOne
    {
        return $this->hasOne(FrameSpec::class);
    }

    public function lens(): HasOne
    {
        return $this->hasOne(LensProduct::class, 'product_id');
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeFrames(Builder $q): Builder
    {
        return $q->where('type', self::TYPE_FRAME);
    }

    public function isFrame(): bool
    {
        return $this->type === self::TYPE_FRAME;
    }

    public function isLens(): bool
    {
        return $this->type === self::TYPE_LENS;
    }

    public function url(): string
    {
        return $this->isLens() ? '/lentile/'.$this->slug.'/' : '/produs/'.$this->slug.'/';
    }

    /** Valorile unui atribut (ex. „material”) ca listă de etichete; relația trebuie încărcată cu atributul. */
    public function attributeLabels(string $code): array
    {
        return $this->attributeValues
            ->filter(fn (AttributeValue $v) => $v->attribute?->code === $code)
            ->pluck('label')->values()->all();
    }

    public function availabilityLabel(): string
    {
        return self::AVAILABILITY[$this->availability_mode] ?? self::AVAILABILITY['neconfirmat'];
    }

    /** Prima variantă activă (prețul de pornire pentru produsele cu o singură variantă). */
    public function defaultVariant(): ?ProductVariant
    {
        return $this->variants->firstWhere('is_active', true) ?? $this->variants->first();
    }
}
