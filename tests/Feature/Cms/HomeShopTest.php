<?php

namespace Tests\Feature\Cms;

use App\Core\Settings\Settings;
use App\Modules\Cms\Models\Page;
use App\Modules\Cms\Services\BlockRegistry;
use App\Modules\Cms\Services\ContentRefresh;
use App\Modules\Cms\Services\HomeShopRefresh;
use App\Modules\Cms\Services\PageService;
use Database\Seeders\BusinessSettingsSeeder;
use Database\Seeders\SiteContentSeeder;
use Tests\Concerns\BuildsCatalog;
use Tests\TestCase;

/** 1.0.12: prima pagină în stil de magazin (bannere, scurtături, categorii, carusele). */
class HomeShopTest extends TestCase
{
    use BuildsCatalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLenses();
        $this->seed([\Database\Seeders\ShippingPaymentSeeder::class, BusinessSettingsSeeder::class, SiteContentSeeder::class]);
        $this->frame();
        (new ContentRefresh)->home(); // ca pe site: 1.0.5 a creat deja o a doua versiune, fără om
    }

    public function test_home_becomes_a_shop_page_even_after_automatic_updates(): void
    {
        $this->assertSame('publicat', (new HomeShopRefresh)->run());
        $this->assertSame('neschimbat', (new HomeShopRefresh)->run());
        $types = collect(Page::where('path', '/')->first()->publishedVersion->blocks)->pluck('type')->all();
        $this->assertSame('banner_carousel', $types[0]);
        $this->assertContains('quick_links', $types);
        $this->assertContains('category_tiles', $types);
        $this->assertContains('hero_lens', $types, 'lentila 3D rămâne, mai jos');

        $html = $this->get('/')->assertOk()
            ->assertSee('class="banners"', false)->assertSee('data-module="carousel"', false)
            ->assertSee('Lentile care se închid la soare')
            ->assertSee('class="topbar"', false)
            ->getContent();
        $this->assertSame(1, substr_count($html, '<h1'), 'o singură h1: titlul primului banner');
        $this->assertStringContainsString('<h1 class="banner__title">', $html);
        $this->assertStringContainsString('<h2 class="hero__title">Lentilele le facem în laboratorul nostru</h2>', $html);
        $this->assertStringContainsString('22,00', (string) app(Settings::class)->get('site.topbar_text'));
    }

    public function test_page_edited_by_a_person_is_not_overwritten(): void
    {
        $page = Page::where('path', '/')->firstOrFail();
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);
        $svc = app(PageService::class);
        $svc->publish($page, $svc->saveDraft($page, $page->title, (array) $page->publishedVersion->blocks, [], 'editare din panou'));
        $this->assertSame('ciornă', (new HomeShopRefresh)->run());
    }

    public function test_media_of_new_blocks_is_loaded(): void
    {
        foreach (['image', 'photo', 'product', 'product2', 'compare_a'] as $key) {
            $this->assertContains($key, BlockRegistry::mediaKeys());
        }
    }

    public function test_topbar_link_accepts_only_safe_urls(): void
    {
        app(Settings::class)->set('site.topbar_text', 'Mesaj de test');
        app(Settings::class)->set('site.topbar_url', 'javascript:alert(1)');
        $this->get('/despre-noi/')->assertSee('Mesaj de test')->assertDontSee('javascript:', false);
    }

    public function test_photochromic_banner_text_follows_the_1016_lineup_on_existing_sites(): void
    {
        (new HomeShopRefresh)->run();
        // un site instalat înainte de 1.0.16 are bannerul vechi „Helio și Xtractive”
        $page = Page::where('path', '/')->first();
        $v = $page->publishedVersion;
        $blocks = $v->blocks;
        $blocks[0]['data']['slides'][1]['title'] = \App\Modules\Lenses\Support\PhotochromicLineup::HOME_SLIDE_OLD;
        $v->update(['blocks' => $blocks]);
        $this->seed(\Database\Seeders\CatalogBaseSeeder::class);
        $this->seed(\Database\Seeders\CurrentConfiguratorSeeder::class); // rulează și PhotochromicLineup

        $this->assertSame('neschimbat', \App\Modules\Lenses\Support\PhotochromicLineup::run()['home']);
        $this->get('/')->assertOk()->assertSee('Lentile care se închid la soare')->assertDontSee('Helio și Xtractive');
    }
}
