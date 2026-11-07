<?php

namespace Tests\Feature\Settings;

use App\Core\Settings\Settings;
use App\Modules\Settings\Support\CompanyDetails;
use Database\Seeders\SiteContentSeeder;
use Tests\TestCase;

/** 1.0.12: datele firmei OFA SRL (registrul ANAF) în setări și în subsolul siteului. */
class CompanyDetailsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // migrarea 1.0.12 a completat deja datele pe baza de test; pornim ca pe un site 1.0.11
        foreach (['company.name', 'company.vat_code', 'company.registry', 'company.address'] as $key) {
            app(Settings::class)->set($key, null);
        }
    }

    public function test_fills_company_data_and_replaces_the_old_contact_address(): void
    {
        $s = app(Settings::class);
        $s->set('contact.address', CompanyDetails::OLD_CONTACT_ADDRESS);
        $s->set('site.topbar_text', 'Lentile produse în laboratorul nostru, din Târgu Mureș. Livrare prin curier în toată România: 22,00 lei.');

        $changed = CompanyDetails::run();

        $this->assertContains('company.name', $changed);
        $this->assertSame('OFA SRL', $s->get('company.name'));
        $this->assertSame('29223337', $s->get('company.vat_code'));
        $this->assertSame('J2011001019267', $s->get('company.registry'));
        $this->assertSame(CompanyDetails::ADDRESS, $s->get('contact.address'));
        $this->assertSame('Lentile produse în laboratorul nostru. Livrare prin curier în toată România: 22,00 lei.', $s->get('site.topbar_text'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.updated']);
        $this->assertSame([], CompanyDetails::run(), 'a doua rulare nu mai schimbă nimic');
    }

    public function test_keeps_what_the_team_entered(): void
    {
        $s = app(Settings::class);
        $s->set('company.name', 'Denumire scrisă din panou');
        $s->set('contact.address', 'Adresă scrisă din panou');
        $s->set('site.topbar_text', 'Text propriu');

        CompanyDetails::run();

        $this->assertSame('Denumire scrisă din panou', $s->get('company.name'));
        $this->assertSame('Adresă scrisă din panou', $s->get('contact.address'));
        $this->assertSame('Text propriu', $s->get('site.topbar_text'));
        $this->assertSame('29223337', $s->get('company.vat_code'), 'câmpurile goale se completează');
    }

    public function test_footer_shows_the_company_once(): void
    {
        $this->seed(SiteContentSeeder::class);

        $html = $this->get('/rame/')->getContent();

        $this->assertStringContainsString('OFA SRL · CUI 29223337 · Reg. Com. J2011001019267', $html);
        $this->assertSame(1, substr_count($html, 'Sat Crăciunești nr. 235/A'));
        $this->assertStringNotContainsString('Str. Poștei', $html);
    }
}
