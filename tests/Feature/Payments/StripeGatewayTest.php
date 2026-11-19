<?php

namespace Tests\Feature\Payments;

use App\Core\Settings\Settings;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Gateways\StripeGateway;
use App\Modules\Payments\Models\PaymentEvent;
use App\Modules\Payments\Services\PaymentMethods;
use App\Modules\Pricing\Services\PriceEngine;
use App\Modules\Pricing\Support\PriceLine;
use App\Modules\Pricing\Support\PricingContext;
use App\Modules\Shipping\Support\InternationalShipping;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/**
 * 1.0.17: plata cu cardul pentru comenzile în euro / forinți prin Stripe Checkout. Răspunsurile Stripe sunt
 * simulate (Http::fake); cheile sunt fictive. Plata se confirmă doar cu semnătură validă + interogarea sesiunii.
 */
class StripeGatewayTest extends TestCase
{
    use BuildsOrders;

    private const WHSEC = 'whsec_testfictiv123';

    private array $session = ['id' => 'cs_test_fictiv1234567', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_fictiv1234567',
        'payment_status' => 'unpaid', 'status' => 'open', 'currency' => 'eur', 'amount_total' => 0, 'payment_intent' => 'pi_test_fictiv'];

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('exchange_rates')->insert(['currency' => 'EUR', 'rate_date' => '2026-11-19', 'rate_scaled' => 500000000, 'source' => 'BNR', 'created_at' => now(), 'updated_at' => now()]);
        $this->seedLenses();
        $this->enableCheckout(['payments.card_gateway' => 'btipay', 'payments.stripe.enabled' => true, 'payments.stripe.currencies' => 'EUR, HUF']);
        $s = app(Settings::class);
        $s->setSecret('payments.stripe.secret_key', 'sk_test_fictiv123');
        $s->setSecret('payments.stripe.webhook_secret', self::WHSEC);
        InternationalShipping::apply();
        Http::fake(function (HttpRequest $r) {
            if (str_ends_with($r->url(), '/v1/checkout/sessions') && $r->method() === 'POST') {
                $this->session['amount_total'] = (int) $r['line_items'][0]['price_data']['unit_amount'];
                $this->session['currency'] = $r['line_items'][0]['price_data']['currency'];

                return Http::response($this->session);
            }
            if (str_contains($r->url(), '/v1/checkout/sessions/cs_test_fictiv1234567')) {
                return Http::response($this->session);
            }
            if (str_ends_with($r->url(), '/v1/refunds')) {
                return Http::response(['id' => 're_test_fictiv', 'status' => 'succeeded']);
            }
            if (str_ends_with($r->url(), '/v1/balance')) {
                return Http::response(['object' => 'balance']);
            }

            return Http::response(['error' => ['message' => 'necunoscut']], 404);
        });
    }

    private function placeEuroOrder(): Order
    {
        $cfg = $this->configureGlasses($this->frame(30000));
        $this->get('/en/comanda/?tara=DE')->assertOk()->assertSee('value="card"', false);
        $total = app(PriceEngine::class)->quote([PriceLine::configuration('x', $cfg->toInput())],
            new PricingContext(currency: 'EUR', shippingMethodCode: InternationalShipping::INTL, shippingCountry: 'DE'))->totalGross;
        $response = $this->post('/en/comanda/', [
            'email' => 'client.de@demo.optoplus.invalid', 'telefon' => '+49 30 000000', 'nume' => 'Kunde Fiktiv', 'tip' => 'pf',
            'adresa' => 'Beispielstraße 1', 'localitate' => 'Berlin', 'cod_postal' => '10115', 'tara' => 'DE',
            'livrare' => InternationalShipping::INTL, 'plata' => 'card', 'acord_termeni' => '1',
            'cheie' => session('checkout_key'), 'total_afisat' => $total,
        ]);
        $response->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_fictiv1234567');

        return Order::firstOrFail();
    }

    private function webhook(array $event, ?string $secret = null, ?int $time = null)
    {
        $payload = json_encode($event);
        $t = $time ?? time();
        $sig = 't='.$t.',v1='.hash_hmac('sha256', $t.'.'.$payload, $secret ?? self::WHSEC);

        return $this->call('POST', '/webhook/plati/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $sig], $payload);
    }

    public function test_euro_order_uses_stripe_and_lei_order_uses_bt(): void
    {
        $methods = app(PaymentMethods::class);
        $this->assertSame('stripe', $methods->gatewayFor('EUR')?->code());
        $this->assertSame('stripe', $methods->gatewayFor('HUF')?->code());
        $this->assertNull($methods->gatewayFor('RON'), 'BT nu e configurat în test; Stripe nu are RON în listă');
        $this->assertArrayNotHasKey('ramburs', $methods->available(['allows_cod' => true, 'kind' => 'curier'], 'b2c', null, 'EUR'));
        $this->assertArrayHasKey('ramburs', $methods->available(['allows_cod' => true, 'kind' => 'curier'], 'b2c', null, 'RON'));

        $order = $this->placeEuroOrder();
        $payment = $order->payments()->firstOrFail();
        $this->assertSame('stripe', $payment->provider);
        $this->assertSame('EUR', $payment->currency);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/v1/checkout/sessions')
            && $r['line_items'][0]['price_data']['currency'] === 'eur'
            && (int) $r['line_items'][0]['price_data']['unit_amount'] === $order->total
            && $r->hasHeader('Idempotency-Key') && $r->hasHeader('Authorization', 'Bearer sk_test_fictiv123')
            && $r['locale'] === 'en');
    }

    public function test_signed_webhook_marks_paid_only_after_stripe_confirms(): void
    {
        $order = $this->placeEuroOrder();
        $event = ['id' => 'evt_test_1', 'type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_test_fictiv1234567', 'object' => 'checkout.session']]];

        // semnătură greșită: înregistrat, nimic schimbat
        $this->webhook($event, 'whsec_altul')->assertStatus(400);
        $this->assertFalse($order->fresh()->isPaid());
        // semnătură veche (peste 5 minute): respinsă
        $this->webhook(['id' => 'evt_test_old'] + $event, null, time() - 600)->assertStatus(400);

        // semnătură corectă, dar Stripe spune încă „unpaid”: nu se marchează
        $this->webhook(['id' => 'evt_test_2'] + $event)->assertOk();
        $this->assertFalse($order->fresh()->isPaid());

        // Stripe confirmă plata, aceeași sumă și valută
        $this->session['payment_status'] = 'paid';
        $this->session['status'] = 'complete';
        $this->webhook(['id' => 'evt_test_3'] + $event)->assertOk();
        $order->refresh();
        $this->assertTrue($order->isPaid());
        $this->assertSame($order->total, $order->paid_total);
        $this->assertSame(1, PaymentEvent::where('provider_event_id', 'stripe:evt_test_3')->count());
        // același eveniment, din nou: duplicat
        $this->webhook(['id' => 'evt_test_3'] + $event)->assertOk();
        $this->assertSame($order->total, $order->fresh()->paid_total);
    }

    public function test_different_amount_or_currency_is_not_accepted(): void
    {
        $order = $this->placeEuroOrder();
        $this->session['payment_status'] = 'paid';
        $this->session['amount_total'] = 100;
        $this->webhook(['id' => 'evt_test_x', 'type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_test_fictiv1234567', 'object' => 'checkout.session']]]);
        $this->assertFalse($order->fresh()->isPaid());
    }

    public function test_return_from_stripe_checks_the_session_and_goes_to_the_order_in_its_language(): void
    {
        $order = $this->placeEuroOrder();
        $this->session['payment_status'] = 'paid';
        $this->get('/plati/stripe/revenire/?session_id=cs_test_fictiv1234567')->assertRedirect('/en/comanda/'.$order->uuid.'/');
        $this->assertTrue($order->fresh()->isPaid());
        $this->get('/plati/stripe/revenire/?session_id=nu-e-sesiune')->assertNotFound();
    }

    public function test_signature_helper_and_connection_test(): void
    {
        $payload = '{"id":"evt_1"}';
        $t = 1700000000;
        $header = 't='.$t.',v1='.hash_hmac('sha256', $t.'.'.$payload, self::WHSEC);
        $this->assertTrue(StripeGateway::verifySignature($payload, $header, self::WHSEC, $t + 10));
        $this->assertFalse(StripeGateway::verifySignature($payload.' ', $header, self::WHSEC, $t + 10));
        $this->assertFalse(StripeGateway::verifySignature($payload, $header, self::WHSEC, $t + 1000));
        $this->assertTrue(app(StripeGateway::class)->testConnection()['ok']);
    }
}
