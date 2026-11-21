<?php

namespace Tests\Feature\Legal;

use App\Core\Audit\AuditLog;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Legal\Models\ContractWithdrawal;
use App\Modules\Legal\Notifications\WithdrawalReceived;
use App\Modules\Legal\Support\LegalDrafts2026;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/** 1.0.22: conformitate — retragerea online (OUG 34/2014 art. 11¹), garanția legală, butonul de comandă, SAL fără SOL. */
class LegalComplianceTest extends TestCase
{
    use BuildsOrders;

    private function order(string $email = 'client.retragere@demo.optoplus.invalid'): Order
    {
        return Order::create([
            'number' => 'OP-2026-77777', 'uuid' => (string) Str::uuid(), 'email' => $email,
            'payment_method' => 'ramburs', 'subtotal' => 1000, 'total' => 1000, 'billing_address' => [], 'idempotency_key' => Str::random(40), 'pricing_version' => '1.0',
        ]);
    }

    public function test_withdrawal_function_has_two_steps_and_sends_a_dated_confirmation(): void
    {
        Notification::fake();
        $order = $this->order();

        $this->get('/retragere/?comanda=OP-2026-77777')->assertOk()->assertSee('Retrageți-vă din contract aici')->assertSee('OP-2026-77777', false)
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->post('/retragere/', [])->assertSessionHasErrors(['nume', 'comanda', 'email']);
        $this->post('/retragere/', ['nume' => 'Ana Fictivă', 'comanda' => 'op-2026-77777 ', 'email' => 'Client.Retragere@demo.optoplus.invalid', 'detalii' => 'Rama fără lentile'])
            ->assertRedirect('/retragere/confirmare/');
        $this->assertSame(0, ContractWithdrawal::count(), 'primul pas nu transmite nimic');

        $this->get('/retragere/confirmare/')->assertOk()->assertSee('Confirmați retragerea')->assertSee('Ana Fictivă');
        $res = $this->post('/retragere/confirmare/');
        $w = ContractWithdrawal::firstOrFail();
        $res->assertRedirect('/retragere/trimisa/'.$w->uuid.'/');

        $this->assertSame($order->id, $w->order_id, 'comanda găsită după număr (fără spații, majuscule) și email');
        $this->assertSame('client.retragere@demo.optoplus.invalid', $w->email);
        $this->assertNotNull($w->confirmation_sent_at);
        $this->assertSame('primita', $w->status);
        Notification::assertSentOnDemand(WithdrawalReceived::class, function ($n, $channels, $notifiable) use ($w) {
            $text = implode("\n", $n->toMail($notifiable)->introLines);

            return $notifiable->routes['mail'] === $w->email && str_contains($text, $w->submitted_at->format('d.m.Y H:i'))
                && str_contains($text, 'Ana Fictivă') && str_contains($text, 'Rama fără lentile');
        });
        $this->assertTrue(AuditLog::where('action', 'legal.withdrawal_received')->exists());

        $this->get('/retragere/trimisa/'.$w->uuid.'/')->assertOk()->assertSee('Am primit declarația de retragere')->assertSee($w->submitted_at->format('d.m.Y'));
        $this->post('/retragere/confirmare/')->assertRedirect('/retragere/'); // nu se retrimite a doua oară
        $this->assertSame(1, ContractWithdrawal::count());
        $this->get('/retragere/trimisa/'.Str::uuid().'/')->assertNotFound();
    }

    public function test_withdrawal_for_an_unknown_order_is_still_recorded(): void
    {
        Notification::fake();
        $this->order();
        $this->post('/retragere/', ['nume' => 'Ion Fictiv', 'comanda' => 'OP-2026-77777', 'email' => 'altcineva@demo.optoplus.invalid']);
        $this->post('/retragere/confirmare/');
        $w = ContractWithdrawal::firstOrFail();
        $this->assertNull($w->order_id, 'emailul diferit nu leagă declarația de comanda altcuiva');
        $this->assertNotNull($w->confirmation_sent_at);
    }

    public function test_withdrawals_in_panel_require_permissions(): void
    {
        $w = ContractWithdrawal::create(['uuid' => (string) Str::uuid(), 'order_reference' => 'OP-2026-1', 'name' => 'Ana Fictivă',
            'email' => 'a@demo.optoplus.invalid', 'locale' => 'ro', 'submitted_at' => now()]);

        $this->actingAsRole('editor_continut');
        $this->get('/panou/retrageri')->assertForbidden();
        $this->actingAsRole('specialist_optic');
        $this->get('/panou/retrageri')->assertOk();
        $this->post('/panou/retrageri/'.$w->id, ['stare' => 'acceptata'])->assertForbidden();

        $this->actingAsRole('manager');
        $this->get('/panou/retrageri')->assertOk()->assertSee('Ana Fictivă');
        $this->post('/panou/retrageri/'.$w->id, ['stare' => 'acceptata', 'nota' => 'Rambursat'])->assertRedirect();
        $this->assertSame('acceptata', $w->fresh()->status);
        $this->post('/panou/retrageri/'.$w->id, ['stare' => 'inventata'])->assertSessionHasErrors('stare');
        $this->assertTrue(AuditLog::where('action', 'legal.withdrawal_updated')->exists());
    }

    public function test_checkout_shows_pre_order_information_and_the_payment_obligation_button(): void
    {
        $this->seedLenses();
        $this->enableCheckout();
        $this->post('/cos/adauga/', ['varianta' => $this->frame(25000)->id]);
        $this->get('/comanda/')->assertOk()
            ->assertSee('Comandă cu obligație de plată')->assertSee('Înainte să comandați')
            ->assertSee('/retragere/', false)->assertSee('/garantie-legala/', false)
            ->assertDontSee('Plasează comanda');
    }

    public function test_legal_guarantee_notice_per_language_and_footer_without_odr(): void
    {
        $this->get('/garantie-legala/')->assertOk()->assertSee('/legal/garantie-legala-ro.jpg', false);
        $this->get('/en/garantie-legala/')->assertOk()->assertSee('/legal/garantie-legala-en.jpg', false);
        $this->get('/hu/garantie-legala/')->assertOk()->assertSee('/legal/garantie-legala-hu.jpg', false);
        foreach (['ro', 'en', 'hu'] as $l) {
            $this->assertFileExists(public_path("legal/garantie-legala-{$l}.jpg"));
        }
        $this->get('/formular-retragere/')->assertOk()->assertSee('Formular tip de retragere');

        $page = $this->get('/rame/')->assertOk();
        $page->assertDontSee('ec.europa.eu/odr', false)->assertDontSee('ec.europa.eu/consumers/odr', false)
            ->assertSee('reclamatiisal.anpc.ro', false)->assertSee('/retragere/', false);
    }

    public function test_product_page_shows_producer_data_and_legal_guarantee(): void
    {
        $this->seedLenses();
        $variant = $this->frame(25000);
        $brand = Brand::create(['name' => 'Marcă Fictivă', 'slug' => 'marca-fictiva', 'manufacturer_name' => 'Producător Fictiv SRL',
            'manufacturer_address' => 'Str. Inventată 1, Testești', 'manufacturer_contact' => 'contact@demo.optoplus.invalid']);
        $variant->product->update(['brand_id' => $brand->id]);
        $this->get('/produs/'.$variant->product->slug.'/')->assertOk()
            ->assertSee('Producător Fictiv SRL')->assertSee('contact@demo.optoplus.invalid')
            ->assertSee('garanția legală de conformitate');
    }

    public function test_video_poster_is_served_locally_and_validates_the_id(): void
    {
        Http::fake(['i.ytimg.com/*' => Http::response('JPEGDATA', 200, ['Content-Type' => 'image/jpeg'])]);
        $id = 'Abc_def-123';
        @unlink(storage_path('app/video-posters/'.$id.'.jpg'));
        $this->get('/video-poster/'.$id.'.jpg')->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->get('/video-poster/'.$id.'.jpg')->assertOk();
        Http::assertSentCount(1);
        $this->get('/video-poster/../../etc.jpg')->assertNotFound();
        $this->get('/video-poster/scurt.jpg')->assertNotFound();
        @unlink(storage_path('app/video-posters/'.$id.'.jpg'));
    }

    public function test_legal_drafts_never_publish_and_skip_pages_edited_by_the_team(): void
    {
        $dir = storage_path('framework/testing/legal-'.uniqid());
        @mkdir($dir, 0775, true);
        file_put_contents($dir.'/pagina-test-a.html', '<p>Text nou {firma}</p>');
        file_put_contents($dir.'/pagina-test-b.html', '<p>Text nou</p>');
        $user = \App\Models\User::factory()->create();
        $mk = function (string $path, ?int $author) {
            $id = DB::table('cms_pages')->insertGetId(['path' => $path, 'kind' => 'policy', 'title' => 'T', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
            $v = DB::table('cms_page_versions')->insertGetId(['page_id' => $id, 'number' => 1, 'title' => 'T', 'blocks' => '[]', 'created_by' => $author, 'created_at' => now(), 'published_at' => now()]);
            DB::table('cms_pages')->where('id', $id)->update(['published_version_id' => $v, 'current_version_id' => $v]);

            return [$id, $v];
        };
        [$a, $va] = $mk('/pagina-test-a/', null);
        [$b, $vb] = $mk('/pagina-test-b/', $user->id);

        $out = LegalDrafts2026::run($dir);
        $this->assertSame('adăugat (ciornă)', $out['/pagina-test-a/']);
        $this->assertStringStartsWith('sărit', $out['/pagina-test-b/']);
        $pa = DB::table('cms_pages')->find($a);
        $this->assertSame($va, (int) $pa->published_version_id, 'versiunea publicată rămâne neschimbată');
        $this->assertNotSame($va, (int) $pa->current_version_id);
        $this->assertSame(1, (int) $pa->needs_legal_review);
        $this->assertNull(DB::table('cms_page_versions')->find($pa->current_version_id)->published_at);
        $this->assertSame($vb, (int) DB::table('cms_pages')->find($b)->current_version_id);
        $this->assertStringStartsWith('sărit', LegalDrafts2026::run($dir)['/pagina-test-a/'], 'idempotent');
        array_map('unlink', glob($dir.'/*'));
        rmdir($dir);
    }
}
