<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Cms\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Marcă de rame sau lentile. „Marcă proprie” nu înseamnă că Optoplus produce produsul. */
class Brand extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_own_brand' => 'boolean'];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function logo(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'logo_media_id');
    }

    public function url(): string
    {
        return '/marci/'.$this->slug.'/';
    }
}
