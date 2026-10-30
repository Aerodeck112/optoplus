<?php

namespace Tests\Feature\B2B;

use App\Core\Settings\Settings;
use App\Modules\B2B\Models\Organization;
use App\Modules\B2B\Models\OrganizationDocument;
use App\Modules\B2B\Models\OrganizationMember;
use App\Modules\B2B\Models\Quote;
use App\Modules\B2B\Models\QuoteVersion;
use App\Modules\B2B\Services\Invitations;
use App\Modules\Configurator\Models\Prescription;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Order;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Models\PriceListItem;
use App\Modules\Production\Models\ProductionJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/** Portalul B2B: izolare strictă între organizații, prețuri din lista partenerului, comandă rapidă, oferte. */
class PartnerPortalTest extends TestCase
{
    use BuildsOrders;

    private function partner(string $role = 'admin_organizatie', array $org = []): array
    {
        $o = Organization::create(array_merge(['name' => 'Clinica Fictivă '.Str::random(4), 'kind' => 'clinica', 'status' => 'aprobata', 'billing_address' => 'Str. Test 1, Testești'], $org));
        $c = new Customer(['email' => 'partener'.Str::random(5).'@demo.optoplus.invalid', 'first_name' => 'Dana']);
        $c->forceFill(['password' => 'parola-test-1234'])->save();
        OrganizationMember::create(['organization_id' => $o->id, 'customer_id' => $c->id, 'role' => $role, 'accepted_at' => now()]);

        return [$o, $c->fresh()];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLenses();
        $this->enableCheckout();
        app(Settings::class)->set('company.iban', 'RO00TEST0000000000000000');
        app(Settings::class)->set('company.legal_name', 'Firmă Fictivă SRL');
        app(Settings::class)->set('payments.bank_transfer_enabled', true);
    }

    private function row(array $over = []): array
    {
        return array_merge(['cod_pacient' => 'P-001', 'lentila' => 'monofocal-standard-150', 'ochi' => 'ambii', 'od_sph' => '-2,00', 'os_sph' => '-1,50', 'dp' => '63', 'cantitate' => '1'], $over);
    }

    public function test_customer_without_approved_organization_cannot_enter_portal(): void
    {
        [$o, $c] = $this->partner('admin_organizatie', ['status' => 'cerere']);
        $this->actingAs($c, 'client')->get('/portal/')->assertRedirect('/parteneri/');
        $this->get('/portal/comanda-rapida/')->assertRedirect('/parteneri/');
    }

    public function test_quick_order_uses_partner_price_list_and_patient_code(): void
    {
        $list = PriceList::create(['name' => 'Listă test', 'code' => 'test-b2b', 'prices_include_vat' => false, 'is_active' => true]);
        $offering = $this->offering('monofocal-standard-150');
        PriceListItem::create(['price_list_id' => $list->id, 'item_type' => 'offering', 'item_id' => $offering->id, 'price' => 9000]);
        [$o, $c] = $this->partner('comenzi', ['price_list_id' => $list->id]);
        $this->actingAs($c, 'client');
        $this->post('/portal/comanda-rapida/', ['r' => [$this->row(), $this->row(['cod_pacient' => 'P-002', 'od_sph' => '-3,00'])]])->assertRedirect('/portal/cos/');
        $this->get('/portal/cos/')->assertOk()->assertSee('P-001')->assertSee('P-002')->assertSee('90,00');
        $this->assertSame('P-001', Prescription::where('organization_id', $o->id)->orderBy('id')->first()->patient_reference);
    }

    public function test_one_invalid_row_blocks_the_whole_batch(): void
    {
        [$o, $c] = $this->partner('comenzi');
        $this->actingAs($c, 'client');
        $this->post('/portal/comanda-rapida/', ['r' => [$this->row(), $this->row(['od_sph' => '-25,00'])]])->assertSessionHasErrors('r.1');
        $this->assertSame(0, \App\Modules\Shop\Models\CartItem::count());
    }

    public function test_viewer_role_cannot_order(): void
    {
        [$o, $c] = $this->partner('vizualizare');
        $this->actingAs($c, 'client');
        $this->get('/portal/')->assertOk();
        $this->get('/portal/comanda-rapida/')->assertForbidden();
        $this->post('/portal/comanda-rapida/', ['r' => [$this->row()]])->assertForbidden();
        $this->get('/portal/utilizatori/')->assertForbidden();
    }

    public function test_partner_order_flow_with_bank_transfer(): void
    {
        [$o, $c] = $this->partner('comenzi');
        $this->actingAs($c, 'client');
        $this->post('/portal/comanda-rapida/', ['r' => [$this->row()]]);
        $this->get('/portal/finalizare/')->assertOk();
        $total = app(\App\Modules\Shop\Services\CartService::class)->quote(\App\Modules\Shop\Models\Cart::where('organization_id', $o->id)->first(), (new \App\Modules\B2B\Services\Partner($c, OrganizationMember::first()))->pricing('curier'))->totalGross;
        $this->post('/portal/finalizare/', ['livrare' => 'curier', 'plata' => 'transfer_bancar', 'adresa_livrare' => 'Str. Test 1', 'po' => 'PO-77', 'total_afisat' => $total, 'cheie' => session('b2b_checkout_key')])
            ->assertRedirect();
        $order = Order::firstOrFail();
        $this->assertSame($o->id, $order->organization_id);
        $this->assertSame('b2b', $order->channel);
        $this->assertSame('PO-77', $order->purchase_order_ref);
        $this->assertSame('P-001', $order->items->first()->patient_reference);
        $this->assertSame(1, ProductionJob::count());
        $this->get('/portal/comenzi/?q=P-001')->assertSee($order->number);
    }

    public function test_payment_on_terms_requires_explicit_approval_and_respects_credit_limit(): void
    {
        [$o, $c] = $this->partner('comenzi', ['payment_terms_days' => 30, 'credit_limit' => 10000]);
        $this->actingAs($c, 'client');
        $this->post('/portal/comanda-rapida/', ['r' => [$this->row()]]);
        $this->get('/portal/finalizare/')->assertOk()->assertDontSee('La termen');
        $o->update(['terms_approved_at' => now()]);
        $this->get('/portal/finalizare/')->assertOk()->assertSee('La termen');
        $total = app(\App\Modules\Shop\Services\CartService::class)->quote(\App\Modules\Shop\Models\Cart::where('organization_id', $o->id)->first(), (new \App\Modules\B2B\Services\Partner($c, OrganizationMember::first()))->pricing('curier'))->totalGross;
        $this->assertGreaterThan(10000, $total);
        $this->post('/portal/finalizare/', ['livrare' => 'curier', 'plata' => 'termen', 'adresa_livrare' => 'x', 'total_afisat' => $total, 'cheie' => session('b2b_checkout_key')])
            ->assertSessionHasErrors('plata');
    }

    public function test_organizations_are_strictly_isolated(): void
    {
        [$oA, $cA] = $this->partner('admin_organizatie');
        [$oB, $cB] = $this->partner('admin_organizatie');
        $orderB = Order::create(['number' => 'OP-2026-88888', 'uuid' => (string) Str::uuid(), 'channel' => 'b2b', 'organization_id' => $oB->id, 'customer_id' => $cB->id, 'email' => $cB->email,
            'payment_method' => 'transfer_bancar', 'subtotal' => 100, 'total' => 100, 'billing_address' => [], 'idempotency_key' => Str::random(40), 'pricing_version' => '1.0']);
        $quoteB = Quote::create(['number' => 'OF-TEST-B', 'organization_id' => $oB->id, 'status' => 'ceruta']);
        Storage::fake('documente');
        Storage::disk('documente')->put('x.bin', 'secret');
        $docB = OrganizationDocument::create(['organization_id' => $oB->id, 'uuid' => (string) Str::uuid(), 'title' => 'Contract B', 'kind' => 'contract', 'disk_path' => 'x.bin', 'mime' => 'application/pdf', 'size' => 6]);
        $memberB = OrganizationMember::where('organization_id', $oB->id)->first();

        $this->actingAs($cA, 'client');
        $this->get('/portal/comenzi/')->assertDontSee('OP-2026-88888');
        $this->get('/portal/comenzi/'.$orderB->uuid.'/')->assertNotFound();
        $this->post('/portal/comenzi/'.$orderB->uuid.'/din-nou/')->assertNotFound();
        $this->post('/portal/comenzi/'.$orderB->uuid.'/sesizare/', ['tip' => 'reclamatie', 'motiv' => 'altul', 'descriere' => 'x'])->assertNotFound();
        $this->get('/portal/oferte/'.$quoteB->id.'/')->assertNotFound();
        $this->get('/portal/documente/'.$docB->uuid.'/')->assertNotFound();
        $this->post('/portal/utilizatori/'.$memberB->id.'/', ['actiune' => 'elimina'])->assertNotFound();
        $this->assertNotNull($memberB->fresh());
        // Comanda B nu apare nici în contul personal al lui A.
        $this->get('/comanda/'.$orderB->uuid.'/')->assertNotFound();
    }

    public function test_csv_import_reports_rows_and_adds_only_when_all_valid(): void
    {
        [$o, $c] = $this->partner('comenzi');
        $this->actingAs($c, 'client');
        $head = implode(';', \App\Modules\B2B\Services\QuickOrder::COLUMNS);
        $bad = UploadedFile::fake()->createWithContent('comanda.csv', "{$head}\nP-1;monofocal-standard-150;;;ambii;-2,00;;;;-1,50;;;;63;;;;;1\nP-2;nu-exista;;;ambii;-2;;;;-2;;;;63;;;;;1\n");
        $this->post('/portal/import/', ['fisier' => $bad])->assertSessionHasErrors('fisier');
        $this->assertSame(0, \App\Modules\Shop\Models\CartItem::count());
        $import = \App\Modules\B2B\Models\B2bImport::firstOrFail();
        $this->assertSame(1, $import->rows_valid);
        $this->assertStringNotContainsString('-2,00', json_encode($import->report), 'Raportul nu păstrează valorile rețetei.');
        $good = UploadedFile::fake()->createWithContent('comanda.csv', "{$head}\nP-1;monofocal-standard-150;;;ambii;-2,00;;;;-1,50;;;;63;;;;;1\n");
        $this->post('/portal/import/', ['fisier' => $good])->assertRedirect('/portal/cos/');
        $this->assertSame(1, \App\Modules\Shop\Models\CartItem::count());
    }

    public function test_accepting_a_sent_quote_creates_order_with_quote_prices(): void
    {
        [$o, $c] = $this->partner('comenzi', ['payment_terms_days' => 30, 'terms_approved_at' => now()]);
        $quote = Quote::create(['number' => 'OF-2026-TEST', 'organization_id' => $o->id, 'customer_id' => $c->id, 'status' => 'trimisa']);
        $v = QuoteVersion::create(['quote_id' => $quote->id, 'number' => 1, 'items' => [['kind' => 'service', 'description' => 'Lentile volum lunar (fictiv)', 'quantity' => 10, 'unit_price' => 5000, 'line_total' => 50000, 'vat_bp' => 2100, 'vat_amount' => 10500]],
            'subtotal' => 50000, 'vat_total' => 10500, 'total' => 60500, 'prices_include_vat' => false, 'valid_until' => now()->addDays(10), 'sent_at' => now(), 'created_at' => now()]);
        $quote->update(['current_version_id' => $v->id]);
        $this->actingAs($c, 'client');
        $this->get('/portal/oferte/'.$quote->id.'/')->assertOk()->assertSee('Acceptă oferta');
        $this->post('/portal/oferte/'.$quote->id.'/accepta/', ['plata' => 'termen', 'accept' => '1', 'cheie' => session('quote_key_'.$quote->id)])->assertRedirect();
        $order = Order::firstOrFail();
        $this->assertSame(60500, $order->total);
        $this->assertSame('oferta/v1', $order->pricing_version);
        $this->assertSame($quote->id, $order->quote_id);
        $this->assertSame('acceptata', $quote->fresh()->status);
    }

    public function test_invitation_must_be_accepted_with_invited_email(): void
    {
        Notification::fake();
        [$o, $admin] = $this->partner('admin_organizatie');
        $this->actingAs($admin, 'client')->post('/portal/utilizatori/', ['email' => 'coleg@demo.optoplus.invalid', 'rol' => 'comenzi'])->assertSessionHas('status');
        $token = null;
        Notification::assertSentOnDemand(\App\Modules\B2B\Notifications\Invitation::class, function ($n) use (&$token) {
            $token = basename(rtrim($n->url, '/'));

            return true;
        });
        $other = new Customer(['email' => 'altcineva@demo.optoplus.invalid']);
        $other->forceFill(['password' => 'parola-test-1234'])->save();
        $this->actingAs($other, 'client')->post('/portal/invitatie/'.$token.'/')->assertSessionHasErrors('email');
        $colleague = new Customer(['email' => 'coleg@demo.optoplus.invalid']);
        $colleague->forceFill(['password' => 'parola-test-1234'])->save();
        $this->actingAs($colleague, 'client')->post('/portal/invitatie/'.$token.'/')->assertRedirect('/portal/');
        $this->assertSame('comenzi', OrganizationMember::where('customer_id', $colleague->id)->value('role'));
    }
}
