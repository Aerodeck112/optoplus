<?php

namespace App\Modules\Cms\Models;

use App\Modules\Storefront\Services\SiteContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    public const MENUS = ['header' => 'Meniul principal', 'footer' => 'Subsol — Optoplus', 'footer_legal' => 'Subsol — informații legale'];

    protected $table = 'cms_menu_items';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['new_tab' => 'boolean', 'is_visible' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => SiteContext::forgetMenus());
        static::deleted(fn () => SiteContext::forgetMenus());
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort');
    }
}
