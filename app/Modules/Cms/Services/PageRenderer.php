<?php

namespace App\Modules\Cms\Services;

use App\Modules\Cms\Models\Media;
use App\Modules\Cms\Models\Page;
use App\Modules\Cms\Models\PageVersion;
use Illuminate\Contracts\View\View;

/** Randarea unei pagini CMS (versiunea publicată sau, în previzualizare, o versiune anume). */
class PageRenderer
{
    public function render(Page $page, ?PageVersion $version = null, bool $preview = false): View
    {
        $version ??= $page->publishedVersion;
        $blocks = array_values(array_filter((array) ($version?->blocks ?? []), fn ($b) => BlockRegistry::exists($b['type'] ?? '')));

        return view('cms::page', [
            'page' => $page,
            'title' => $version?->title ?? $page->title,
            'seo' => (array) ($version?->seo ?? []),
            'kind' => $page->kind,
            'blocks' => $blocks,
            'media' => self::mediaFor($blocks),
            'preview' => $preview,
            'previewLabel' => $version ? 'versiunea '.$version->number.($version->id === $page->published_version_id ? ' (publicată)' : ' (ciornă)') : '',
            'breadcrumbs' => $this->breadcrumbs($page, $version?->title ?? $page->title),
        ]);
    }

    /** @return array<int, Media> toate imaginile folosite de blocuri, încărcate într-o singură interogare */
    public static function mediaFor(array $blocks): array
    {
        $ids = [];
        $walk = function ($value, $key = null) use (&$walk, &$ids) {
            if (is_array($value)) {
                foreach ($value as $k => $v) {
                    $walk($v, $k);
                }
            } elseif (is_int($value) && is_string($key) && (in_array($key, ['image', 'media', 'compare_a', 'compare_b'], true))) {
                $ids[] = $value;
            }
        };
        $walk($blocks);

        return $ids ? Media::whereIn('id', array_unique($ids))->get()->keyBy('id')->all() : [];
    }

    private function breadcrumbs(Page $page, string $title): array
    {
        return match ($page->kind) {
            'guide' => ['Ghiduri' => '/ghiduri/', $title => null],
            default => [$title => null],
        };
    }
}
