<?php

namespace App\Modules\Cms\Services;

use App\Core\Audit\Audit;
use App\Modules\Cms\Models\Page;
use App\Modules\Cms\Models\PageVersion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Operațiile asupra paginilor: salvare ca ciornă (versiune nouă), publicare, retragere, restaurare.
 * Fiecare salvare creează o versiune; nimic nu se suprascrie. Blocurile trec prin BlockRegistry::sanitize().
 */
class PageService
{
    public function saveDraft(Page $page, string $title, array $blocks, array $seo, ?string $note = null, ?int $expectedLock = null): PageVersion
    {
        return DB::transaction(function () use ($page, $title, $blocks, $seo, $note, $expectedLock) {
            $page = Page::whereKey($page->id)->lockForUpdate()->first();
            if ($expectedLock !== null && $page->lock_version !== $expectedLock) {
                throw ValidationException::withMessages(['blocks' => 'Pagina a fost modificată între timp de altcineva. Reîncărcați pagina; modificările dumneavoastră nu au fost salvate.']);
            }
            $clean = array_values(array_filter(array_map([BlockRegistry::class, 'sanitize'], $blocks)));
            $version = PageVersion::create([
                'page_id' => $page->id,
                'number' => (int) PageVersion::where('page_id', $page->id)->max('number') + 1,
                'title' => mb_substr(trim($title), 0, 255),
                'seo' => [
                    'title' => mb_substr(trim((string) ($seo['title'] ?? '')), 0, 255) ?: null,
                    'description' => mb_substr(trim((string) ($seo['description'] ?? '')), 0, 320) ?: null,
                    'noindex' => (bool) ($seo['noindex'] ?? false),
                ],
                'blocks' => $clean,
                'note' => $note,
                'created_by' => Auth::id(),
                'created_at' => now(),
            ]);
            $page->update(['current_version_id' => $version->id, 'title' => $version->title, 'lock_version' => $page->lock_version + 1, 'updated_by' => Auth::id()]);
            Audit::log('cms.page.saved', $page, ['versiune' => $version->number]);

            return $version;
        });
    }

    public function publish(Page $page, ?PageVersion $version = null): void
    {
        $version ??= $page->currentVersion;
        if (! $version || $version->page_id !== $page->id) {
            throw ValidationException::withMessages(['page' => 'Nu există o versiune de publicat.']);
        }
        DB::transaction(function () use ($page, $version) {
            $version->update(['published_at' => now()]);
            $page->update(['published_version_id' => $version->id, 'status' => 'published', 'title' => $version->title,
                'published_at' => $page->published_at ?? now()]);
        });
        Audit::log('cms.page.published', $page, ['versiune' => $version->number]);
        $this->flush();
    }

    public function unpublish(Page $page): void
    {
        $page->update(['status' => 'draft']);
        Audit::log('cms.page.unpublished', $page);
        $this->flush();
    }

    /** Restaurarea unei versiuni vechi creează o ciornă nouă cu conținutul ei (istoricul rămâne intact). */
    public function restore(Page $page, PageVersion $version): PageVersion
    {
        abort_unless($version->page_id === $page->id, 404);

        return $this->saveDraft($page, $version->title, (array) $version->blocks, (array) $version->seo, 'Restaurare din versiunea '.$version->number);
    }

    /** Creează sau actualizează o pagină din cod (seedere), publicând-o opțional. */
    public function upsert(string $path, string $kind, string $title, array $blocks, array $seo = [], bool $publish = true, bool $legal = false): Page
    {
        $page = Page::firstOrCreate(['path' => $path], ['kind' => $kind, 'title' => $title, 'status' => 'draft', 'needs_legal_review' => $legal]);
        $current = $page->currentVersion;
        $same = $current && $current->title === $title && json_encode($current->blocks) === json_encode(array_values(array_filter(array_map([BlockRegistry::class, 'sanitize'], $blocks))));
        if (! $same) {
            $this->saveDraft($page, $title, $blocks, $seo, 'Conținut inițial');
        }
        if ($publish && $page->fresh()->published_version_id !== $page->fresh()->current_version_id) {
            $this->publish($page->fresh());
        }

        return $page->fresh();
    }

    public function flush(): void
    {
        Cache::forget('seo.sitemap');
        app(\App\Modules\Storefront\Services\PageCache::class)->flush();
    }
}
