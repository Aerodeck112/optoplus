<?php

namespace App\Modules\Cms\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pagină a siteului compusă din blocuri. Conținutul stă în versiuni: `current_version_id` = ultima salvare
 * (ciorna), `published_version_id` = ce vede publicul. Publicarea nu șterge istoricul; o versiune veche se poate
 * restaura ca ciornă nouă.
 */
class Page extends Model
{
    public const KINDS = ['page' => 'Pagină', 'home' => 'Prima pagină', 'guide' => 'Ghid', 'policy' => 'Politică / text juridic'];

    protected $table = 'cms_pages';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['editing_until' => 'datetime', 'published_at' => 'datetime', 'needs_legal_review' => 'boolean'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PageVersion::class)->orderByDesc('number');
    }

    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(PageVersion::class, 'published_version_id');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(PageVersion::class, 'current_version_id');
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_media_id');
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published')->whereNotNull('published_version_id');
    }

    public function hasUnpublishedChanges(): bool
    {
        return $this->current_version_id !== null && $this->current_version_id !== $this->published_version_id;
    }
}
