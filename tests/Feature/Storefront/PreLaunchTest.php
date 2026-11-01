<?php

namespace Tests\Feature\Storefront;

use App\Core\Settings\Settings;
use App\Modules\Identity\Roles;
use Tests\TestCase;

/** „Mod lucru”: vizitatorii văd pagina de pregătire (503), panoul și personalul nu sunt blocați. */
class PreLaunchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $s = app(Settings::class);
        $s->set('site.prelaunch', true);
        $s->set('contact.phone_b2c', '+40 700 000 000');
        $s->set('contact.email_b2c', 'contact@demo.optoplus.invalid');
    }

    public function test_visitors_see_the_prelaunch_page_with_503(): void
    {
        foreach (['/', '/rame/', '/configurator/', '/cont/autentificare/', '/oricare-pagina/'] as $url) {
            $this->get($url)->assertStatus(503)->assertHeader('Retry-After', '86400')
                ->assertSee('Noul magazin')->assertSee('VITIM')->assertSee('+40 700 000 000')->assertSee('noindex', false);
        }
    }

    public function test_panel_webhooks_and_static_files_are_not_blocked(): void
    {
        $this->get('/panou/autentificare')->assertOk();
        $this->assertNotSame(503, $this->post('/webhook/plati/simulare', [])->status());
        $this->assertNotSame(503, $this->get('/robots.txt')->status());
    }

    public function test_logged_in_staff_sees_the_real_site(): void
    {
        $this->actingAsRole(Roles::EDITOR_SITE);
        $this->get('/rame/')->assertOk()->assertDontSee('VITIM');
    }

    public function test_turning_it_off_from_settings_opens_the_site(): void
    {
        $this->actingAsRole(Roles::MANAGER);
        $this->get('/panou/setari/lansare')->assertOk()->assertSee('Mod lucru');
        $this->withRecentReauth()->post('/panou/setari/lansare', ['site__prelaunch' => '0'])->assertSessionHasNoErrors();
        auth()->logout();
        $this->flushSession();
        $this->get('/rame/')->assertOk()->assertDontSee('VITIM');
    }
}
