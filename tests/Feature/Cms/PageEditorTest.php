<?php

namespace Tests\Feature\Cms;

use App\Modules\Cms\Models\Page;
use App\Modules\Identity\Roles;
use Database\Seeders\BusinessSettingsSeeder;
use Database\Seeders\LegalDraftsSeeder;
use Database\Seeders\SiteContentSeeder;
use Tests\Concerns\BuildsCatalog;
use Tests\TestCase;

/** Editorul de pagini: ciornele nu ajung pe site până la publicare; textele juridice cer verificare. */
class PageEditorTest extends TestCase
{
    use BuildsCatalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLenses();
        $this->seed([\Database\Seeders\ShippingPaymentSeeder::class, BusinessSettingsSeeder::class, SiteContentSeeder::class, LegalDraftsSeeder::class]);
    }

    public function test_legal_drafts_are_loaded_but_not_published(): void
    {
        $page = Page::where('path', '/termeni-si-conditii/')->firstOrFail();
        $this->assertTrue($page->needs_legal_review);
        $this->assertTrue($page->hasUnpublishedChanges());
        $draft = json_encode($page->currentVersion->blocks, JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('OFA SRL', $draft);
        $this->assertStringContainsString('J2011001019267', $draft);
        $this->assertStringContainsString('2–5 zile lucrătoare', $draft);
        $this->assertStringNotContainsString('{firma}', $draft);
        $this->get('/termeni-si-conditii/')->assertOk()->assertDontSee('OFA SRL, cod de identificare', false)->assertSee('în curs de actualizare');
    }

    public function test_draft_does_not_change_public_page_until_published(): void
    {
        $page = Page::where('path', '/despre-noi/')->firstOrFail();
        $this->actingAsRole(Roles::EDITOR_SITE);
        $this->get('/panou/site/pagini/'.$page->id)->assertOk();
        $this->post('/panou/site/pagini/'.$page->id, [
            'titlu' => 'Despre noi', 'lock' => $page->lock_version,
            'blocks' => [['type' => 'rich_text', 'data' => ['title' => 'Test', 'html' => '<p>Text nou de test fictiv</p><script>alert(1)</script>']]],
        ])->assertSessionHasNoErrors();
        $page->refresh();
        $this->assertStringNotContainsString('<script>', json_encode($page->currentVersion->blocks));
        $this->get('/despre-noi/')->assertDontSee('Text nou de test fictiv');
        $this->get('/panou/site/pagini/'.$page->id.'/previzualizare/'.$page->current_version_id)->assertOk()->assertSee('Text nou de test fictiv');
        $this->post('/panou/site/pagini/'.$page->id.'/publica', ['versiune' => $page->current_version_id])->assertSessionHasNoErrors();
        $this->get('/despre-noi/')->assertSee('Text nou de test fictiv');
    }

    public function test_concurrent_edit_is_rejected(): void
    {
        $page = Page::where('path', '/despre-noi/')->firstOrFail();
        $this->actingAsRole(Roles::EDITOR_SITE);
        $this->post('/panou/site/pagini/'.$page->id, ['titlu' => 'X', 'lock' => $page->lock_version - 1, 'blocks' => []])->assertSessionHasErrors('blocks');
    }

    public function test_legal_page_publication_needs_legal_permission_and_confirmation(): void
    {
        $page = Page::where('path', '/termeni-si-conditii/')->firstOrFail();
        $this->actingAsRole(Roles::EDITOR_SITE);
        $this->post('/panou/site/pagini/'.$page->id.'/publica')->assertForbidden();
        $this->actingAsRole(Roles::MANAGER);
        $this->post('/panou/site/pagini/'.$page->id.'/publica')->assertSessionHasErrors('verificat');
        $this->post('/panou/site/pagini/'.$page->id.'/publica', ['verificat' => '1'])->assertSessionHasNoErrors();
        $this->assertFalse($page->fresh()->needs_legal_review);
        $this->get('/termeni-si-conditii/')->assertSee('OFA SRL');
    }

    public function test_redirect_to_homepage_is_refused(): void
    {
        $this->actingAsRole(Roles::MANAGER);
        $this->post('/panou/site/redirecturi', ['source_path' => '/produs/vechi/', 'target' => '/', 'status_code' => 301, 'is_active' => 1])->assertSessionHasErrors('target');
        $this->post('/panou/site/redirecturi', ['source_path' => '/produs/vechi/', 'target' => '/rame/', 'status_code' => 301, 'is_active' => 1])->assertSessionHasNoErrors();
        $this->get('/produs/vechi/')->assertRedirect('/rame/');
    }

    public function test_panel_screens_open(): void
    {
        $this->actingAsRole(Roles::MANAGER);
        foreach (['/panou/site/pagini', '/panou/site/intrebari', '/panou/site/meniuri', '/panou/site/redirecturi', '/panou/site/negasite', '/panou/setari', '/panou/setari/plati', '/panou/setari/livrare', '/panou/setari/tva'] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
