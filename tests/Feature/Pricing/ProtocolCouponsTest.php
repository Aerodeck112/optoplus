<?php

namespace Tests\Feature\Pricing;

use App\Core\Audit\AuditLog;
use App\Modules\Configurator\Support\ConfigurationInput;
use App\Modules\Configurator\Support\EyeRx;
use App\Modules\Identity\Roles;
use App\Modules\Orders\Models\Order;
use App\Modules\Pricing\Models\Coupon;
use App\Modules\Pricing\Services\PriceEngine;
use App\Modules\Pricing\Support\PriceLine;
use App\Modules\Pricing\Support\PricingContext;
use App\Modules\Pricing\Support\ProtocolCoupons;
use DateTimeImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/**
 * 1.0.14: codurile din modulul WordPress „Cupon reducere lentile + protocol MAPN”: LENTILE25 (25%, permanent) și
 * STUDENT10 (încă 10%, până la 31.10.2026), doar la lentile și tratamente, cu dovadă încărcată la comandă.
 */
class ProtocolCouponsTest extends TestCase
{
    use BuildsOrders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLenses();
        ProtocolCoupons::run(); // migrarea le-a creat deja; a doua rulare nu dublează nimic
    }

    private function input(int $frameId): ConfigurationInput
    {
        return new ConfigurationInput(
            entry: 'cu_rama', usage: 'distanta', frameVariantId: $frameId, offeringId: $this->offering('monofocal-premium-150')->id,
            treatments: [['treatment_id' => $this->treatmentId('antireflex-hidrofob'), 'option' => null]],
            od: new EyeRx(-200), os: new EyeRx(-200), pdMode: 'binocular', measurements: ['pd' => 630],
        );
    }

    private function quote(int $frameId, ?string $codes, string $when = '2026-11-09 10:00:00')
    {
        return app(PriceEngine::class)->quote([PriceLine::configuration('c', $this->input($frameId))],
            new PricingContext(couponCode: $codes, now: new DateTimeImmutable($when)));
    }

    public function test_codes_exist_once_with_the_module_rules(): void
    {
        $this->assertSame([], ProtocolCoupons::run());
        $this->assertSame(1, Coupon::where('code', 'LENTILE25')->count());
        $student = Coupon::where('code', 'STUDENT10')->firstOrFail();
        $this->assertSame('2026-10-31 23:59:59', $student->expires_at->format('Y-m-d H:i:s'));
        $this->assertTrue($student->promotion->requires_proof);
        $this->assertSame('lenses_treatments', $student->promotion->scope);
    }

    public function test_lentile25_takes_25_percent_off_lenses_and_treatments_but_not_the_frame(): void
    {
        $frame = $this->frame(70000);
        $base = $this->quote($frame->id, null);
        $lensAndTreatments = $base->subtotal - 70000;

        $q = $this->quote($frame->id, 'lentile25');
        $this->assertSame(['LENTILE25' => 'valid'], $q->coupons);
        $this->assertSame((int) round($lensAndTreatments * 0.25), $q->discountTotal);
        $frameComponent = collect($q->components())->firstWhere('type', 'frame');
        $this->assertSame([], $frameComponent->discounts, 'rama nu se reduce');
        $this->assertCount(1, $q->proofs);
        $this->assertSame('LENTILE25', array_values($q->proofs)[0]['code']);
    }

    public function test_both_codes_add_up_from_the_original_price_until_the_student_code_expires(): void
    {
        $frame = $this->frame(70000);
        $lensAndTreatments = $this->quote($frame->id, null)->subtotal - 70000;

        // înainte de 31 octombrie 2026: 25% + 10% din prețul inițial (ca în modul: 300 lei → −75 − 30 = −105)
        $q = $this->quote($frame->id, 'LENTILE25, STUDENT10', '2026-10-20 12:00:00');
        $this->assertSame(['LENTILE25' => 'valid', 'STUDENT10' => 'valid'], $q->coupons);
        $this->assertSame((int) round($lensAndTreatments * 0.25) + (int) round($lensAndTreatments * 0.10), $q->discountTotal);
        $this->assertCount(2, $q->proofs, 'fiecare cod cere dovada lui');

        // de la 1 noiembrie 2026 STUDENT10 a expirat; LENTILE25 rămâne
        $q = $this->quote($frame->id, 'LENTILE25,STUDENT10', '2026-11-01 00:00:01');
        $this->assertSame('expirat', $q->coupons['STUDENT10']);
        $this->assertSame((int) round($lensAndTreatments * 0.25), $q->discountTotal);
    }

    public function test_code_on_a_frame_only_cart_is_not_applied_and_unknown_codes_are_invalid(): void
    {
        $frame = $this->frame(70000);
        $q = app(PriceEngine::class)->quote([PriceLine::product('r', $frame->id)], new PricingContext(couponCode: 'LENTILE25 NUEXISTA'));
        $this->assertSame(['LENTILE25' => 'neaplicat', 'NUEXISTA' => 'invalid'], $q->coupons);
        $this->assertSame(0, $q->discountTotal);
        $this->assertSame([], $q->proofs);
    }

    public function test_checkout_requires_the_proof_stores_it_privately_and_the_panel_opens_it_with_audit(): void
    {
        Storage::fake('dovezi');
        $this->enableCheckout();
        $frame = $this->frame(30000);
        $this->configureGlasses($frame, 'monofocal-premium-150');

        $this->post('/cos/actualizeaza/', ['cupon' => 'lentile25'])->assertRedirect('/cos/');
        $this->post('/cos/actualizeaza/', ['cupon' => 'NUEXISTA'])->assertRedirect('/cos/');
        $this->get('/cos/')->assertSee('LENTILE25')->assertSee('Aplicat.')->assertSee('Codul nu există sau nu este valabil.')
            ->assertSee('legitimație, cupon de pensie');
        $this->post('/cos/actualizeaza/', ['scoate_cupon' => 'NUEXISTA']);
        $this->get('/cos/')->assertDontSee('NUEXISTA');

        $this->get('/comanda/')->assertOk()->assertSee('name="dovada[', false)->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('Reducere MAPN lentile');
        $quote = app(PriceEngine::class)->quote([PriceLine::configuration('x', \App\Modules\Configurator\Models\Configuration::latest('id')->first()->toInput())],
            PricingContext::b2c(['shippingMethodCode' => 'curier', 'couponCode' => 'LENTILE25']));
        $promotionId = array_key_first($quote->proofs);

        // fără dovadă: comanda nu se plasează
        $this->post('/comanda/', $this->checkoutData(['total_afisat' => $quote->totalGross]))->assertSessionHasErrors('dovada.'.$promotionId);
        $this->assertSame(0, Order::count());

        // cu dovadă (fotografie): comanda se plasează, dovada se păstrează privat
        $data = $this->checkoutData(['total_afisat' => $quote->totalGross, 'plata' => 'ramburs']);
        $data['dovada'] = [$promotionId => UploadedFile::fake()->image('legitimatie.jpg', 800, 500)];
        $this->post('/comanda/', $data)->assertSessionHasNoErrors();
        $order = Order::firstOrFail();
        $this->assertSame('LENTILE25', $order->coupon_code);
        $this->assertSame($quote->discountTotal, $order->discount_total);
        $proof = $order->proofs()->firstOrFail();
        $this->assertSame('LENTILE25', $proof->coupon_code);
        $this->assertSame('image/jpeg', $proof->mime);
        Storage::disk('dovezi')->assertExists($proof->disk_path);
        $this->assertStringNotContainsString('legitimatie', $proof->disk_path, 'nume aleator, nu numele trimis de client');
        $this->assertSame(1, Coupon::where('code', 'LENTILE25')->value('uses_count'));

        // panoul: vânzările văd dovada (cu audit) și o pot marca verificată
        $this->actingAsRole(Roles::VANZARI);
        $this->get('/panou/comenzi/'.$order->uuid)->assertOk()->assertSee('Dovezi pentru reduceri')->assertSee('de verificat');
        $this->get('/panou/comenzi/'.$order->uuid.'/dovezi/'.$proof->uuid)->assertOk()->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertTrue(AuditLog::where('action', 'order.proof_downloaded')->exists());
        $this->post('/panou/comenzi/'.$order->uuid.'/dovezi/'.$proof->uuid.'/verificata')->assertRedirect();
        $this->assertNotNull($proof->fresh()->verified_at);
    }

    public function test_beneficiary_promotion_needs_a_code(): void
    {
        $this->actingAsRole(Roles::MANAGER);
        $this->post('/panou/preturi/promotii', ['nume' => 'Pensionari', 'aplicare' => 'lenses_treatments', 'tip' => 'percent', 'valoare' => '5',
            'prioritate' => 10, 'canal' => 'b2c', 'activa' => '1', 'beneficiari' => 'pensionari'])->assertSessionHasErrors('beneficiari');
        $this->post('/panou/preturi/promotii', ['nume' => 'Pensionari', 'aplicare' => 'lenses_treatments', 'tip' => 'percent', 'valoare' => '5',
            'prioritate' => 10, 'canal' => 'b2c', 'activa' => '1', 'beneficiari' => 'pensionari', 'cu_cupon' => '1', 'dovada' => '1',
            'din_initial' => '1', 'dovada_eticheta' => 'Încarcă cuponul de pensie'])->assertSessionHasNoErrors();
    }
}
