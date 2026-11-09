<?php

namespace Tests\Feature\I18n;

use App\Modules\I18n\Services\Dictionary;
use App\Modules\I18n\Services\SiteScanner;
use App\Modules\I18n\Support\TranslationSeed;
use App\Modules\Identity\Roles;
use Database\Seeders\SiteContentSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsCatalog;
use Tests\TestCase;

/** 1.0.15: siteul în engleză (/en/) și maghiară (/hu/), cu dicționarul de traduceri. */
class LocalizedSiteTest extends TestCase
{
    use BuildsCatalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLenses();
        $this->seed(SiteContentSeeder::class);
        Dictionary::forget();
    }

    public function test_english_page_is_translated_with_prefixed_links_and_hreflang(): void
    {
        $html = $this->get('/en/lentile/')->assertOk()->getContent();

        $this->assertStringContainsString('<html lang="en">', $html);
        $this->assertStringContainsString('Lenses made by us', $html);
        $this->assertStringNotContainsString('Lentile produse de noi', $html);
        $this->assertStringContainsString('href="/en/configurator/"', $html, 'linkurile interne păstrează limba');
        $this->assertStringContainsString('hreflang="hu" href="https://optoplus.ro/hu/lentile/"', $html);
        $this->assertStringContainsString('rel="canonical" href="https://optoplus.ro/en/lentile/"', $html);
        // comutatorul: „RO” duce la pagina românească, fără prefix
        $this->assertMatchesRegularExpression('~<a href="/lentile/" hreflang="ro"~', $html);
    }

    public function test_hungarian_and_romanian_pages(): void
    {
        $this->get('/hu/lentile/')->assertOk()->assertSee('Saját gyártású lencsék')->assertSee('lang="hu"', false);
        $this->get('/lentile/')->assertOk()->assertSee('Lentile produse de noi')->assertSee('<html lang="ro">', false);
    }

    public function test_redirects_and_forms_keep_the_language(): void
    {
        $this->assertStringEndsWith('/en/rame/', (string) $this->get('/en/rame')->assertRedirect()->headers->get('Location'));
        $frame = $this->frame(30000);
        $this->post('/en/cos/adauga/', ['varianta' => $frame->id])->assertRedirect();
        $this->assertStringContainsString('/en/cos', (string) $this->post('/en/cos/adauga/', ['varianta' => $frame->id])->headers->get('Location'));
        $this->get('/en/panou/')->assertRedirect('/panou/');
    }

    public function test_legal_texts_stay_in_romanian_with_a_notice(): void
    {
        $page = DB::table('cms_pages')->where('kind', 'policy')->where('status', 'published')->first();
        $this->assertNotNull($page);
        $html = $this->get('/en'.$page->path)->assertOk()->getContent();
        $this->assertStringContainsString('This legal text is only available in Romanian', $html);
        $this->assertStringContainsString('translate="no" lang="ro"', $html);
    }

    public function test_outgoing_mail_is_translated_in_the_order_language(): void
    {
        config(['mail.default' => 'array']);
        app()->setLocale('en');
        Mail::raw('Echipa Optoplus', fn ($m) => $m->to('client.test@demo.optoplus.invalid')->subject('Setarea parolei pentru contul Optoplus'));
        app()->setLocale('ro');
        $sent = app('mailer')->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        $this->assertSame('Setting the password for your Optoplus account', $sent->getSubject());
        $this->assertStringContainsString('The Optoplus team', $sent->getTextBody());
    }

    public function test_panel_edits_translations_and_keeps_placeholders(): void
    {
        $row = DB::table('translations')->where('locale', 'en')->where('source', 'Indice {0} (standard)')->first();
        $this->actingAsRole(Roles::EDITOR_SITE);
        $this->get('/panou/traduceri?limba=en&q=Indice')->assertOk()->assertSee('Indice {0} (standard)');
        $this->post('/panou/traduceri/'.$row->id, ['traducere' => 'Standard index'])->assertSessionHasErrors('traducere');
        $this->post('/panou/traduceri/'.$row->id, ['traducere' => 'Index {0} — standard', 'verificat' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('verificat', DB::table('translations')->where('id', $row->id)->value('status'));
        $lens = DB::table('products')->where('type', 'lens')->where('status', 'published')->whereIn('id', DB::table('lens_offerings')->join('lens_materials', 'lens_materials.id', '=', 'lens_offerings.material_id')->where('lens_materials.code', '150')->pluck('lens_offerings.lens_product_id'))->value('slug');
        $this->get('/en/lentile/'.$lens.'/')->assertOk()->assertSee('Index 1,50 — standard');

        // o actualizare ulterioară nu suprascrie traducerea verificată
        TranslationSeed::run();
        $this->assertSame('Index {0} — standard', DB::table('translations')->where('id', $row->id)->value('target'));
    }

    public function test_scanner_finds_new_content_without_customer_data(): void
    {
        DB::table('lens_treatments')->where('code', 'antireflex-hidrofob')->update(['benefits' => 'Un text nou, încă netradus.']);
        app(SiteScanner::class)->run(5);
        $this->assertSame(2, DB::table('translations')->where('source', 'Un text nou, încă netradus.')->where('status', 'lipsa')->count());
    }

    public function test_translatepress_export_is_imported_without_overwriting_verified_texts(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'trp');
        file_put_contents($file, "INSERT INTO `wp_trp_dictionary_ro_ro_hu_hu` (`id`, `original`, `translated`, `status`, `block_type`) VALUES\n"
            ."(1, 'Coșul este gol.', 'A kosár üres.', 2, 0),\n(2, 'Comandă la telefon: +40 771 653 905', 'Telefonos rendelés: +40 771 653 905', 1, 0),\n"
            ."(3, 'Netradus', NULL, 0, 0),\n(4, 'Rama aleasă:', 'A választott keret:', 2, 0);");
        DB::table('translations')->where('locale', 'hu')->where('source', 'Rama aleasă:')->update(['status' => 'verificat', 'target' => 'Kiválasztott keret:']);

        $this->artisan('optoplus:i18n:import-translatepress', ['file' => $file, '--locale' => 'hu'])->assertSuccessful();

        $this->assertSame('A kosár üres.', DB::table('translations')->where('locale', 'hu')->where('source', 'Coșul este gol.')->value('target'));
        $this->assertSame('Telefonos rendelés: +{0} {1} {2} {3}', DB::table('translations')->where('locale', 'hu')->where('source', 'Comandă la telefon: +{0} {1} {2} {3}')->value('target'));
        $this->assertSame('Kiválasztott keret:', DB::table('translations')->where('locale', 'hu')->where('source', 'Rama aleasă:')->value('target'));
        @unlink($file);
    }

    public function test_sales_role_cannot_edit_translations(): void
    {
        $this->actingAsRole(Roles::VANZARI);
        $this->get('/panou/traduceri')->assertForbidden();
    }
}
