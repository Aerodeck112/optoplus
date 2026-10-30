<?php

namespace App\Modules\Cms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageVersion extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'cms_page_versions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['seo' => 'array', 'blocks' => 'array', 'published_at' => 'datetime'];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
