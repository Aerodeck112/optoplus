<?php

namespace Tests\Feature\Regions;

use App\Core\Settings\Settings;
use App\Core\Support\Money;
use App\Modules\Orders\Models\Order;
use App\Modules\Pricing\Services\PriceEngine;
use App\Modules\Pricing\Support\PriceLine;
use App\Modules\Pricing\Support\PricingContext;
use App\Modules\Regions\Http\Middleware\DetectRegion;
use App\Modules\Regions\Services\ExchangeRates;
use App\Modules\Regions\Services\Regions;
use App\Modules\Shipping\Support\InternationalShipping;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/**
 * 1.0.17: limba și valuta după țară (Ungaria → maghiară + forinți, alte țări → engleză + euro), prețurile
 * calculate din lei la cursul BNR, livrarea pe țări (22 lei / 20 € / 25 €) și rambursul doar în România.
 * Cursurile din test sunt rotunde și fictive (5 lei/EUR, 1,25 lei/100 HUF), ca sumele să se verifice ușor.
 */
class RegionsCurrencyTest extends TestCase
{
    use BuildsOrders;

    private const BROWSER = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();
        $this->rates(500000000, 1250000);
    }

    private function rates(int $eur, int $huf): void
    {
        DB::table('exchange_rates')->delete();
        foreach (['EUR' => $eur, 'HUF' => $huf] as $c => $r) {
            DB::table('exchange_rates')->insert(['currency' => $c, 'rate_date' => '2026-11-19', 'rate_scaled' => $r, 'source' => 'BNR', 'created_at' => now(), 'updated_at' => now()]);
        }
        app(ExchangeRates::class)->forget();
    }

    public function test_bnr_file_is_parsed_and_amounts_convert_with_exact_rounding(): void
    {
        $xml = '<?xml version="1.0"?><DataSet xmlns="https://www.bnr.ro/xsd"><Body><Cube date="2026-11-19"><Rate currency="EUR">5.3442</Rate>'
            .'<Rate currency="HUF" multiplier="100">1.4501</Rate><Rate currency="USD">4.6012</Rate></Cube></Body></DataSet>';
        $this->assertSame(['date' => '2026-11-19', 'rates' => ['EUR' => 534420000, 'HUF' => 1450100]], ExchangeRates::parseBnr($xml));
        $this->assertNull(ExchangeRates::parseBnr('<html>Error</html>'));

        $r = app(ExchangeRates::class);
        $this->assertSame(5000, $r->fromRon(25000, 'EUR'));           // 250 lei = 50,00 €
        $this->assertSame(2000000, $r->fromRon(25000, 'HUF'));        // 250 lei = 20.000 Ft (forinți întregi)
        $this->assertSame(0, $r->fromRon(1, 'HUF') % 100, 'forinți fără zecimale');
        $this->assertSame(800000, $r->convert(2000, 'EUR', 'HUF'));   // 20 € = 100 lei = 8.000 Ft
        $this->assertSame(2500, $r->convert(2500, 'EUR', 'EUR'));
        $this->assertSame('€50.00', Money::format(5000, true, 'EUR'));
        $this->assertSame('20.000 Ft', Money::format(2000000, true, 'HUF'));
        $this->assertSame('250,00 lei', Money::format(25000));

        // adaosul de conversie (2%) și cursul manual
        app(Settings::class)->set('currency.markup_bp', 200);
        $r->forget();
        $this->assertSame(5100, $r->fromRon(25000, 'EUR'));
        app(Settings::class)->set('currency.markup_bp', 0);
        app(Settings::class)->set('currency.source', 'manual');
        app(Settings::class)->set('currency.manual_eur', '4,0000');
        $r->forget();
        $this->assertSame(6250, $r->fromRon(25000, 'EUR'));
        $this->assertNull($r->rate('HUF'), 'fără curs manual pentru forint → forintul nu se folosește');
    }

    public function test_first_visit_proposes_the_language_of_the_visitor_country_without_redirecting(): void
    {
        // Regulamentul (UE) 2018/302, art. 3 alin. (2): fără redirecționare automată după țară; doar propunere (banner)
        $get = fn (string $url, array $headers = []) => $this->flushHeaders()->withHeaders($headers + ['User-Agent' => self::BROWSER, 'Accept' => 'text/html'])->get($url);

        $get('/rame/', ['CF-IPCountry' => 'HU'])->assertOk()->assertCookie(DetectRegion::COOKIE, 'hu', false)->assertCookie(DetectRegion::SUGGESTION, 'hu', false)
            ->assertSee('data-module="region-suggest"', false);
        $get('/rame/', ['CF-IPCountry' => 'DE'])->assertOk()->assertCookie(DetectRegion::SUGGESTION, 'en', false);
        $get('/en/rame/', ['CF-IPCountry' => 'RO'])->assertOk()->assertCookie(DetectRegion::SUGGESTION, 'ro', false);
        $get('/rame/', ['CF-IPCountry' => 'RO'])->assertOk()->assertCookie(DetectRegion::COOKIE, 'ro', false)->assertCookieMissing(DetectRegion::SUGGESTION);

        // a doua vizită (cookie), roboții și panoul: nicio propunere
        $this->flushHeaders()->withUnencryptedCookie(DetectRegion::COOKIE, 'ro')->withHeaders(['User-Agent' => self::BROWSER, 'Accept' => 'text/html', 'CF-IPCountry' => 'HU'])->get('/rame/')
            ->assertOk()->assertCookieMissing(DetectRegion::SUGGESTION);
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];
        $get('/rame/', ['CF-IPCountry' => 'HU', 'User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'])->assertOk()->assertCookieMissing(DetectRegion::SUGGESTION);
        $this->assertNotSame('/hu/panou/autentificare', $get('/panou/autentificare', ['CF-IPCountry' => 'HU'])->headers->get('Location'));

        // limba browserului: doar maghiara e propusă
        $get('/rame/', ['Accept-Language' => 'hu-HU,hu;q=0.9'])->assertOk()->assertCookie(DetectRegion::SUGGESTION, 'hu', false);
        $get('/rame/', ['Accept-Language' => 'en-US,en;q=0.9'])->assertOk()->assertCookieMissing(DetectRegion::SUGGESTION);

        // dezactivat din Setări → Valute și țări
        app(Settings::class)->set('regions.auto_detect', false);
        $get('/rame/', ['CF-IPCountry' => 'HU'])->assertOk()->assertCookieMissing(DetectRegion::SUGGESTION);
    }

    public function test_prices_follow_the_language_currency_and_fall_back_to_lei_without_rate(): void
    {
        $this->seedLenses();
        $frame = $this->frame(25000);
        $slug = $frame->product->slug;
        $this->get('/produs/'.$slug.'/')->assertOk()->assertSee('250,00 lei');
        $this->get('/en/produs/'.$slug.'/')->assertOk()->assertSee('€50.00')->assertDontSee('250,00 lei');
        $this->get('/hu/produs/'.$slug.'/')->assertOk()->assertSee('20.000 Ft');

        DB::table('exchange_rates')->where('currency', 'HUF')->delete();
        app(ExchangeRates::class)->forget();
        $this->get('/hu/produs/'.$slug.'/')->assertOk()->assertSee('250,00')->assertDontSee(' Ft<', false);
    }

    public function test_engine_prices_in_the_context_currency_and_totals_add_up(): void
    {
        $this->seedLenses();
        $frame = $this->frame(30000);
        $line = PriceLine::configuration('c', new \App\Modules\Configurator\Support\ConfigurationInput(
            entry: 'cu_rama', usage: 'distanta', frameVariantId: $frame->id, offeringId: $this->offering('monofocal-premium-160')->id,
            treatments: [['treatment_id' => $this->treatmentId('transitions'), 'option' => 'gri']],
            od: new \App\Modules\Configurator\Support\EyeRx(-200), os: new \App\Modules\Configurator\Support\EyeRx(-200), pdMode: 'binocular', measurements: ['pd' => 630],
        ));
        $ron = app(PriceEngine::class)->quote([$line], new PricingContext);
        $eur = app(PriceEngine::class)->quote([$line], new PricingContext(currency: 'EUR'));
        $this->assertSame('EUR', $eur->currency);
        $this->assertSame(array_sum(array_map(fn ($c) => app(ExchangeRates::class)->fromRon($c->unitPrice, 'EUR') * $c->quantity, $ron->components())), $eur->totalGross);
        $this->assertSame(array_sum(array_map(fn ($c) => $c->gross, $eur->components())), $eur->totalGross, 'totalul este suma liniilor');
        // B2B: mereu în lei
        $this->assertSame('RON', (new PricingContext(channel: 'b2b', currency: 'EUR'))->currency);
    }

    public function test_shipping_and_payment_methods_depend_on_the_country(): void
    {
        $this->seedLenses();
        $this->enableCheckout(['payments.bank_transfer_enabled' => false]);
        InternationalShipping::apply();
        $this->configureGlasses($this->frame(30000));

        // România, în lei: curier 20 lei (tariful din test), ramburs și card
        $html = $this->get('/comanda/')->assertOk()->assertSee('name="tara"', false)->getContent();
        $this->assertStringContainsString('value="ramburs"', $html);
        $this->assertStringContainsString('20,00 lei', $html);

        // Germania, în euro: livrare internațională 25 €, fără ramburs
        $html = $this->get('/en/comanda/?tara=DE')->assertOk()->getContent();
        $this->assertStringContainsString('€25.00', $html);
        $this->assertStringContainsString('value="card"', $html);
        $this->assertStringNotContainsString('value="ramburs"', $html);
        $this->assertStringNotContainsString('value="curier"', $html);

        // Ungaria, în forinți: 20 € = 8.000 Ft
        $html = $this->get('/hu/comanda/?tara=HU')->assertOk()->getContent();
        $this->assertStringContainsString('8.000 Ft', $html);
        $this->assertStringNotContainsString('value="ramburs"', $html);

        // serverul refuză rambursul pentru o comandă în afara României, chiar dacă cineva îl trimite
        $data = $this->checkoutData(['tara' => 'DE', 'plata' => 'ramburs', 'livrare' => InternationalShipping::INTL, 'cod_postal' => '10115']);
        $this->post('/en/comanda/', $data + ['total_afisat' => 1])->assertSessionHasErrors();
        $this->assertSame(0, Order::count());
    }

    public function test_order_from_abroad_keeps_currency_rate_and_lei_equivalent(): void
    {
        $this->seedLenses();
        $this->enableCheckout();
        InternationalShipping::apply();
        $cfg = $this->configureGlasses($this->frame(30000));
        $this->get('/en/comanda/?tara=AT')->assertOk();
        $quote = app(PriceEngine::class)->quote([PriceLine::configuration('x', $cfg->toInput())],
            new PricingContext(currency: 'EUR', shippingMethodCode: InternationalShipping::INTL, shippingCountry: 'AT'));
        $this->assertSame(2500, $quote->shipping['gross']);
        $this->post('/en/comanda/', [
            'email' => 'client.at@demo.optoplus.invalid', 'telefon' => '+43 1 000 0000', 'nume' => 'Kunde Fiktiv', 'tip' => 'pf',
            'adresa' => 'Musterstraße 1', 'localitate' => 'Wien', 'cod_postal' => '1010', 'tara' => 'AT',
            'livrare' => InternationalShipping::INTL, 'plata' => 'card', 'acord_termeni' => '1',
            'cheie' => session('checkout_key'), 'total_afisat' => $quote->totalGross,
        ])->assertSessionHasNoErrors();
        $order = Order::firstOrFail();
        $this->assertSame('EUR', $order->currency);
        $this->assertSame($quote->totalGross, $order->total);
        $this->assertSame($quote->totalGross * 5, $order->total_ron);
        $this->assertSame('AT', $order->shipping_country);
        $this->assertSame('AT', $order->shipping_address['country']);
        $this->assertStringContainsString('1 EUR = 5,0000 lei', (string) $order->rate_note);
        $payment = $order->payments()->firstOrFail();
        $this->assertSame('EUR', $payment->currency);
        $this->assertSame($order->total, $payment->amount);
        $this->assertSame('en', $order->locale);
    }

    public function test_default_country_follows_language(): void
    {
        $regions = app(Regions::class);
        $this->assertSame('HU', $regions->defaultCountry('hu'));
        $this->assertSame('RO', $regions->defaultCountry('ro'));
        $this->assertSame('DE', $regions->defaultCountry('en', 'DE'));
        $this->assertNotContains($regions->defaultCountry('en'), ['RO', 'HU']);
    }
}
