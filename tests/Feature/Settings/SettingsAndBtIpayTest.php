<?php

namespace Tests\Feature\Settings;

use App\Core\Audit\AuditLog;
use App\Core\Settings\Settings;
use App\Modules\Identity\Roles;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Models\Payment;
use App\Modules\Pricing\Services\PriceEngine;
use App\Modules\Pricing\Support\PriceLine;
use App\Modules\Pricing\Support\PricingContext;
use App\Modules\Shipping\Models\ShippingMethod;
use Database\Seeders\BusinessSettingsSeeder;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/** Setările din panou și plata BT iPay: confirmarea vine numai din interogarea la BT. Date fictive. */
class SettingsAndBtIpayTest extends TestCase
{
    use BuildsOrders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLenses();
        $this->seed(\Database\Seeders\ShippingPaymentSeeder::class);
        $this->seed(BusinessSettingsSeeder::class);
    }

    private function btConfigured(): void
    {
        $s = app(Settings::class);
        $s->set('payments.btipay.username', 'test_api');
        $s->setSecret('payments.btipay.password', 'parola-test-bt');
        $s->set('payments.card_enabled', true);
        $s->set('company.vat_payer', true);
    }

    private function placeCardOrder(): Order
    {
        $cfg = $this->configureGlasses($this->frame(30000));
        $total = app(PriceEngine::class)->quote([PriceLine::configuration('x', $cfg->toInput())], PricingContext::b2c(['shippingMethodCode' => 'curier']))->totalGross;
        $this->post('/comanda/', $this->checkoutData(['total_afisat' => $total, 'plata' => 'card']));

        return Order::firstOrFail();
    }

    public function test_confirmed_business_defaults_are_applied(): void
    {
        $courier = ShippingMethod::where('code', 'curier')->with('rules')->first();
        $this->assertTrue($courier->is_active);
        $this->assertSame(2200, $courier->rules->first()->price);
        $this->assertSame('OFA SRL', app(Settings::class)->get('company.name'));
        $this->assertSame(2100, app(Settings::class)->get('tax.vat_lenses_bp'));
    }

    public function test_non_vat_payer_charges_same_price_without_vat(): void
    {
        $frame = $this->frame(30000);
        $q = app(PriceEngine::class)->quote([PriceLine::product('a', $frame->id)], PricingContext::b2c());
        $this->assertSame(30000, $q->totalGross);
        $this->assertSame(0, $q->vatTotal);
        app(Settings::class)->set('company.vat_payer', true);
        $q = app(PriceEngine::class)->quote([PriceLine::product('a', $frame->id)], PricingContext::b2c());
        $this->assertSame(30000, $q->totalGross);
        $this->assertSame(5207, $q->vatTotal); // 300 × 21/121
    }

    public function test_cash_on_delivery_and_courier_are_offered_at_checkout(): void
    {
        $this->post('/cos/adauga/', ['varianta' => $this->frame(30000)->id]);
        $this->get('/comanda/')->assertOk()->assertSee('22,00')->assertSee('Ramburs la curier')->assertDontSee('Card online')->assertDontSee('Transfer bancar');
    }

    public function test_settings_save_requires_permission_reauth_and_hides_secret(): void
    {
        $this->actingAsRole(Roles::EDITOR_SITE);
        $this->assertContains($this->get('/panou/setari/plati')->status(), [403, 404]); // fără drept pe secțiunea de plăți
        $this->actingAsRole(Roles::MANAGER);
        $this->post('/panou/setari/plati', ['payments__btipay__username' => 'x'])->assertRedirect(route('panou.reauth'));
        $this->withRecentReauth()->post('/panou/setari/plati', [
            'payments__card_enabled' => '1', 'payments__btipay__environment' => 'test', 'payments__btipay__username' => 'optoplus_api',
            'payments__btipay__password' => 'secret-bt-123', 'payments__btipay__base_url_test' => 'https://ecclients-sandbox.btrl.ro',
            'payments__btipay__base_url_production' => 'https://ecclients.btrl.ro', 'payments__bank_transfer_enabled' => '1',
            'company__iban' => 'RO49 AAAA 1B31 0075 9384 0000', 'company__bank' => 'Banca Fictivă', 'payments__cod_enabled' => '1',
        ])->assertSessionHasNoErrors();
        $s = app(Settings::class);
        $this->assertSame('secret-bt-123', $s->secret('payments.btipay.password'));
        $this->assertStringNotContainsString('secret-bt-123', json_encode($s->all()));
        $this->assertSame('RO49AAAA1B31007593840000', $s->get('company.iban'));
        $this->get('/panou/setari/plati')->assertOk()->assertDontSee('secret-bt-123');
        $log = AuditLog::where('action', 'settings.updated')->latest('id')->first();
        $this->assertStringNotContainsString('secret-bt-123', json_encode($log->context));
    }

    public function test_invalid_iban_keeps_bank_transfer_off(): void
    {
        $this->actingAsRole(Roles::MANAGER);
        $this->withRecentReauth()->post('/panou/setari/plati', ['payments__bank_transfer_enabled' => '1', 'company__iban' => 'RO00AAAA1B31007593840001'])->assertSessionHasErrors('company.iban');
        $this->assertFalse((bool) app(Settings::class)->get('payments.bank_transfer_enabled'));
    }

    public function test_bt_payment_flow_is_confirmed_only_by_status_query(): void
    {
        $this->btConfigured();
        $this->enableCheckout(['payments.card_gateway' => 'btipay']);
        Http::fake([
            '*/payment/rest/register.do' => Http::response(['orderId' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', 'formUrl' => 'https://ecclients-sandbox.btrl.ro/payment/merchants/x/payment_ro.html?mdOrder=aaaaaaaa'], 200),
            '*/payment/rest/getOrderStatusExtended.do' => Http::sequence()
                ->push(['errorCode' => '0', 'orderStatus' => 0, 'amount' => 0], 200)
                ->push(['errorCode' => '0', 'orderStatus' => 2, 'amount' => 999999], 200)
                ->push(['errorCode' => '0', 'orderStatus' => 2, 'amount' => 'SUMA'], 200),
        ]);
        $order = $this->placeCardOrder();
        $payment = Payment::where('order_id', $order->id)->firstOrFail();
        $this->assertSame('btipay', $payment->provider);
        $this->assertSame('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', $payment->provider_reference);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'register.do') && $r['amount'] == $payment->amount && $r['currency'] === '946' && $r['userName'] === 'test_api');

        // Revenirea cu „succes” în URL nu contează: BT spune încă „înregistrată, neplătită”.
        $this->get('/plati/btipay/revenire/?orderId=aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee&status=success')->assertRedirect('/comanda/'.$order->uuid.'/');
        $this->assertSame('in_asteptare', $order->fresh()->payment_status);

        // Sumă diferită la BT: nu se acceptă automat.
        $this->get('/plati/btipay/revenire/?orderId=aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee');
        $this->assertSame('in_asteptare', $order->fresh()->payment_status);
    }

    public function test_bt_callback_marks_paid_after_successful_status_query(): void
    {
        $this->btConfigured();
        $this->enableCheckout(['payments.card_gateway' => 'btipay']);
        $amount = null;
        Http::fake(function ($request) use (&$amount) {
            if (str_contains($request->url(), 'register.do')) {
                $amount = (int) $request['amount'];

                return Http::response(['orderId' => '11111111-2222-3333-4444-555555555555', 'formUrl' => 'https://ecclients-sandbox.btrl.ro/pay'], 200);
            }

            return Http::response(['errorCode' => '0', 'orderStatus' => 2, 'amount' => $amount], 200);
        });
        $order = $this->placeCardOrder();
        $this->get('/webhook/plati/btipay?mdOrder=11111111-2222-3333-4444-555555555555&operation=deposited&status=1')->assertOk();
        $this->get('/webhook/plati/btipay?mdOrder=11111111-2222-3333-4444-555555555555&operation=deposited&status=1')->assertOk(); // dublură
        $order->refresh();
        $this->assertSame('platita', $order->payment_status);
        $this->assertSame('confirmata', $order->commercial_status);
        $this->assertSame($order->total, $order->paid_total);
    }

    public function test_bt_is_not_offered_without_credentials(): void
    {
        app(Settings::class)->set('payments.card_enabled', true);
        $this->post('/cos/adauga/', ['varianta' => $this->frame(30000)->id]);
        $this->get('/comanda/')->assertOk()->assertDontSee('Card online');
    }
}
