<?php

namespace Tests\Feature\Cms;

use App\Modules\Cms\Models\Page;
use App\Modules\Cms\Services\BlockRegistry;
use App\Modules\Cms\Services\ContentRefresh;
use App\Modules\Cms\Services\PageService;
use Database\Seeders\BusinessSettingsSeeder;
use Database\Seeders\LegalDraftsSeeder;
use Database\Seeders\SiteContentSeeder;
use Tests\Concerns\BuildsCatalog;
use Tests\TestCase;

/** 1.0.5: video YouTube, banda cu rame și actualizarea conținutului fără a suprascrie editările echipei. */
class ContentRefreshTest extends TestCase
{
    use BuildsCatalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLenses();
        $this->seed([\Database\Seeders\ShippingPaymentSeeder::class, BusinessSettingsSeeder::class, SiteContentSeeder::class, LegalDraftsSeeder::class]);
    }

    public function test_youtube_id_is_extracted_only_from_youtube_urls(): void
    {
        $this->assertSame('kB3VeYY-V50', BlockRegistry::youtubeId('https://www.youtube.com/watch?v=kB3VeYY-V50'));
        $this->assertSame('kB3VeYY-V50', BlockRegistry::youtubeId('https://youtu.be/kB3VeYY-V50?t=10'));
        $this->assertSame('kB3VeYY-V50', BlockRegistry::youtubeId('https://www.youtube.com/watch?feature=share&v=kB3VeYY-V50'));
        $this->assertSame('kB3VeYY-V50', BlockRegistry::youtubeId('https://www.youtube-nocookie.com/embed/kB3VeYY-V50'));
        $this->assertNull(BlockRegistry::youtubeId('https://exemplu.invalid/watch?v=kB3VeYY-V50'));
        $this->assertNull(BlockRegistry::youtubeId('javascript:alert(1)'));
    }

    public function test_untouched_pages_get_video_and_frames_marquee(): void
    {
        $refresh = (new ContentRefresh);
        $this->assertSame('publicat', $refresh->home());
        $this->assertSame('publicat', $refresh->production());
        $this->assertSame('neschimbat', $refresh->production(), 'a doua rulare nu adaugă încă un video');

        $this->get('/productia-noastra/')->assertOk()
            ->assertSee('data-video-id="kB3VeYY-V50"', false)
            ->assertSee('data-autoplay', false)
            ->assertSee('https://i.ytimg.com/vi/kB3VeYY-V50/maxresdefault.jpg', false)
            ->assertDontSee('<iframe', false);

        $types = collect(Page::where('path', '/')->firstOrFail()->publishedVersion->blocks)->pluck('type');
        $this->assertContains('frames_marquee', $types);
        $home = $this->get('/')->assertOk()->assertSee('data-video-id="kB3VeYY-V50"', false);
        $this->assertStringNotContainsString('href="/b2b/"', $home->getContent());
    }

    public function test_page_edited_by_team_is_not_overwritten(): void
    {
        $page = Page::with('publishedVersion')->where('path', '/productia-noastra/')->firstOrFail();
        $svc = app(PageService::class);
        $edited = $svc->saveDraft($page, $page->title, [['type' => 'rich_text', 'data' => ['title' => '', 'html' => '<p>Text scris de echipă, fictiv</p>']]], [], 'editare de test');
        $svc->publish($page, $edited);

        $this->assertSame('ciornă', (new ContentRefresh)->production());
        $this->get('/productia-noastra/')->assertSee('Text scris de echipă, fictiv')->assertDontSee('data-video-id', false);
    }

    public function test_cookie_policy_mention_stays_a_draft(): void
    {
        $page = Page::where('path', '/politica-de-cookie-uri/')->firstOrFail();
        $this->assertStringContainsString('YouTube', json_encode($page->currentVersion->blocks, JSON_UNESCAPED_UNICODE));
        $this->assertSame('neschimbat', (new ContentRefresh)->cookies());
        $this->assertTrue($page->fresh()->hasUnpublishedChanges());
    }

    public function test_old_b2b_link_redirects_to_portal(): void
    {
        $this->get('/b2b/')->assertStatus(301)->assertRedirect('/portal/');
    }
}
