<?php

namespace Tests\Feature\B2B;

use App\Core\Settings\Settings;
use App\Modules\B2B\Models\Organization;
use App\Modules\B2B\Models\OrganizationMember;
use App\Modules\Cms\Models\Page;
use App\Modules\Cms\Services\PageService;
use App\Modules\Cms\Services\PartnersPageRefresh;
use App\Modules\Customers\Models\Customer;
use Database\Seeders\BusinessSettingsSeeder;
use Database\Seeders\SiteContentSeeder;
use Tests\Concerns\BuildsCatalog;
use Tests\TestCase;

/** 1.0.6: pagina de prezentare pentru parteneri, autentificarea dedicată și panoul portalului. */
class PartnerLandingTest extends TestCase
{
    use BuildsCatalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLenses();
        $this->seed([\Database\Seeders\ShippingPaymentSeeder::class, BusinessSettingsSeeder::class, SiteContentSeeder::class]);
    }

    private function customer(bool $partner): Customer
    {
        $c = new Customer(['email' => ($partner ? 'partener' : 'client').'-landing@demo.optoplus.invalid', 'first_name' => 'Dana']);
        $c->forceFill(['password' => 'parola-test-1234'])->save();
        if ($partner) {
            $o = Organization::create(['name' => 'Cabinet Fictiv Test', 'kind' => 'cabinet', 'status' => 'aprobata', 'billing_address' => 'Str. Test 1, Testești']);
            OrganizationMember::create(['organization_id' => $o->id, 'customer_id' => $c->id, 'role' => 'admin_organizatie', 'accepted_at' => now()]);
        }

        return $c->fresh();
    }

    public function test_guest_portal_visit_goes_to_partner_login_not_staff_login(): void
    {
        $this->get('/portal/comenzi/')->assertRedirect()->assertRedirectContains('/portal/autentificare');
        $this->get('/portal/autentificare/')->assertOk()
            ->assertSee('Intră în portal')
            ->assertSee('action="/cont/autentificare/"', false)
            ->assertDontSee('Autentificare personal');
    }

    public function test_partner_login_returns_to_requested_portal_page(): void
    {
        $this->customer(true);
        $this->get('/portal/comenzi/');
        $this->get('/portal/autentificare/')->assertOk();
        $this->post('/cont/autentificare/', ['email' => 'partener-landing@demo.optoplus.invalid', 'parola' => 'parola-test-1234'])
            ->assertRedirect('/portal/comenzi/');
        $this->get('/portal/autentificare/')->assertRedirect('/portal/');
    }

    public function test_customer_without_organization_sees_explanation(): void
    {
        $this->actingAs($this->customer(false), 'client');
        $this->get('/portal/autentificare/')->assertOk()->assertSee('nu face parte dintr-o organizație parteneră');
    }

    public function test_partners_page_gets_new_landing_when_untouched(): void
    {
        app(Settings::class)->set('orders.production_days_min', 2);
        app(Settings::class)->set('orders.production_days_max', 5);
        $this->assertSame('publicat', (new PartnersPageRefresh)->run());
        $this->assertSame('neschimbat', (new PartnersPageRefresh)->run(), 'a doua rulare nu schimbă nimic');

        $html = $this->get('/parteneri/')->assertOk()
            ->assertSee('class="b2b-hero"', false)
            ->assertSee('data-module="b2b-tour"', false)
            ->assertSee('role="tablist"', false)
            ->assertSee('Producție în 2–5 zile lucrătoare')
            ->assertSee('id="cerere"', false)
            ->assertSee('href="/portal/autentificare/"', false)
            ->getContent();
        $this->assertSame(1, substr_count($html, '<h1'), 'o singură h1 pe pagină');
        // machetele sunt decorative și nu conțin prețuri
        preg_match_all('~<div class="pm pm--[^"]+" aria-hidden="true">(.*?)</div>\s*</div>~s', $html, $m);
        $this->assertNotEmpty($m[0]);
        foreach ($m[1] as $mock) {
            $this->assertDoesNotMatchRegularExpression('~\d+,\d{2}\s*lei~', $mock);
        }
    }

    public function test_edited_partners_page_is_not_overwritten(): void
    {
        $page = Page::where('path', '/parteneri/')->firstOrFail();
        $svc = app(PageService::class);
        $svc->publish($page, $svc->saveDraft($page, $page->title, [['type' => 'rich_text', 'data' => ['title' => '', 'html' => '<p>Text scris de echipă, fictiv</p>']]], [], 'editare de test'));

        $this->assertSame('ciornă', (new PartnersPageRefresh)->run());
        $this->get('/parteneri/')->assertSee('Text scris de echipă, fictiv')->assertDontSee('class="b2b-hero"', false);
    }

    public function test_new_blocks_survive_editor_sanitizing(): void
    {
        $page = Page::where('path', '/parteneri/')->firstOrFail();
        $blocks = [PartnersPageRefresh::hero(), PartnersPageRefresh::audience(), PartnersPageRefresh::tour()];
        $blocks[1]['data']['items'][0]['icon'] = 'cod-necunoscut';
        $blocks[2]['data']['tabs'][0]['screen'] = '<script>';
        $version = app(PageService::class)->saveDraft($page, $page->title, $blocks, []);
        $saved = $version->blocks;
        $this->assertSame(['b2b_hero', 'b2b_audience', 'b2b_tour'], array_column($saved, 'type'));
        $this->assertSame('/parteneri/#cerere', $saved[0]['data']['primary']['url']);
        $this->assertSame('building', $saved[1]['data']['items'][0]['icon']);
        $this->assertSame('quick', $saved[2]['data']['tabs'][0]['screen']);
    }

    public function test_portal_dashboard_shows_empty_state_and_vat_status(): void
    {
        app(Settings::class)->set('company.vat_payer', false);
        $this->actingAs($this->customer(true), 'client');
        $this->get('/portal/')->assertOk()
            ->assertSee('Prima comandă, în câteva minute')
            ->assertSee('Fă prima comandă rapidă')
            ->assertSee('firma nu este plătitoare de TVA')
            ->assertDontSee('se afișează fără TVA');
    }
}
