<?php

namespace Tests\Feature\Storefront;

use App\Modules\Storefront\Services\PageCache;
use Tests\TestCase;

/** Paginile din cache nu supraviețuiesc unei actualizări (altfel ar trimite la CSS/JS care nu mai există). */
class PageCacheVersionTest extends TestCase
{
    public function test_cache_key_changes_with_application_version(): void
    {
        $cache = app(PageCache::class);
        config(['optoplus.version' => '1.0.3']);
        $old = $cache->key('/rame/', []);
        config(['optoplus.version' => '1.0.4']);
        $this->assertNotSame($old, $cache->key('/rame/', []));
    }

    public function test_desktop_filters_are_not_hidden_by_a_global_rule(): void
    {
        $css = file_get_contents(resource_path('css/site.css'));
        $this->assertDoesNotMatchRegularExpression('/^\[hidden\]\s*\{\s*display:\s*none\s*!important/m', $css, 'O regulă globală [hidden] !important ascunde filtrele de pe desktop.');
        $this->assertStringContainsString('.filters-panel[hidden] { display: block; }', $css);
    }
}
