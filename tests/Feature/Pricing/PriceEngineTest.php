<?php

namespace Tests\Feature\Pricing;

use App\Modules\Configurator\Support\ConfigurationInput;
use App\Modules\Configurator\Support\EyeRx;
use App\Modules\Pricing\Models\Coupon;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Models\PriceListItem;
use App\Modules\Pricing\Models\Promotion;
use App\Modules\Pricing\Services\PriceEngine;
use App\Modules\Pricing\Support\PriceLine;
use App\Modules\Pricing\Support\PricingContext;
use App\Modules\Shipping\Models\ShippingMethod;
use Tests\Concerns\BuildsCatalog;
use Tests\TestCase;

/** Prețuri pentru configurații reprezentative și cazuri-limită (docs/TESTE.md). Sumele sunt în bani. */
class PriceEngineTest extends TestCase
{
    use BuildsCatalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLenses();
    }

    private function config(int $frameVariantId, string $offering, array $treatments = [], array $over = []): ConfigurationInput
    {
        return new ConfigurationInput(...array_merge([
            'entry' => 'cu_rama', 'usage' => 'distanta', 'frameVariantId' => $frameVariantId,
            'offeringId' => $this->offering($offering)->id,
            'treatments' => array_map(fn ($t) => ['treatment_id' => $this->treatmentId($t[0]), 'option' => $t[1] ?? null], $treatments),
            'od' => new EyeRx(-200), 'os' => new EyeRx(-200), 'pdMode' => 'binocular', 'measurements' => ['pd' => 630],
        ], $over));
    }

    private function engine(): PriceEngine
    {
        return app(PriceEngine::class);
    }

    public function test_frame_premium_lens_and_antireflex_match_current_public_prices(): void
    {
        $frame = $this->frame(30000);
        $q = $this->engine()->quoteConfiguration($this->config($frame->id, 'monofocal-premium-160', [['antireflex-hidrofob']]), new PricingContext);
        // Ramă 300 + Premium 1,60 (280 + 120) + antireflex 60 = 760 lei, ca în APF.
        $this->assertSame(76000, $q->totalGross);
        $this->assertSame(['frame', 'lens', 'treatment'], array_map(fn ($c) => $c->type, $q->components()));
        $this->assertSame('pereche', $q->components()[1]->unitLabel);
        // TVA provizoriu 21% inclus: 76000 − round(76000/1,21).
        $this->assertSame(76000 - (int) round(76000 / 1.21), $q->vatTotal);
        $this->assertContains('tva_neconfirmat', $q->messages);
    }

    public function test_progressive_174_with_photochromic_and_blue_light(): void
    {
        $frame = $this->frame(76600);
        $q = $this->engine()->quoteConfiguration($this->config($frame->id, 'progresive-174', [['fotocromatic', 'gri_85'], ['filtru-lumina-albastra']], [
            'usage' => 'toate', 'od' => new EyeRx(-100, null, null, 200), 'os' => new EyeRx(-100, null, null, 200),
        ]), new PricingContext);
        // 766 + 1790 + 250 + 90 = 2896 lei.
        $this->assertSame(289600, $q->totalGross);
        $this->assertStringContainsString('Gri 85%', $q->components()[2]->name);
    }

    public function test_lens_only_promotion_does_not_discount_frame_or_treatments(): void
    {
        Promotion::create(['name' => 'Lentile −20% (test)', 'scope' => 'lenses', 'type' => 'percent', 'value' => 2000, 'is_active' => true, 'channel' => 'b2c']);
        $frame = $this->frame(30000);
        $q = $this->engine()->quoteConfiguration($this->config($frame->id, 'monofocal-premium-160', [['antireflex-hidrofob']]), new PricingContext);
        [$f, $l, $t] = $q->components();
        $this->assertSame(0, $f->discountTotal());
        $this->assertSame(8000, $l->discountTotal());
        $this->assertSame(0, $t->discountTotal());
        $this->assertSame(68000, $q->totalGross);
    }

    public function test_non_stackable_promotion_blocks_others_and_stackable_ones_combine(): void
    {
        $frame = $this->frame(21000);
        $a = Promotion::create(['name' => 'Preț fix ramă 99 lei', 'scope' => 'product', 'scope_ids' => [$frame->product_id], 'type' => 'fixed_price', 'value' => 9900, 'is_active' => true, 'priority' => 10]);
        Promotion::create(['name' => 'Rame −10%', 'scope' => 'frames', 'type' => 'percent', 'value' => 1000, 'is_active' => true, 'priority' => 20, 'stackable' => true]);
        $q = $this->engine()->quote([PriceLine::product('r', $frame->id)], new PricingContext);
        $this->assertSame(9900, $q->totalGross, 'prețul fix, necumulabil, oprește reducerea de 10%');

        $a->update(['stackable' => true]);
        $q = $this->engine()->quote([PriceLine::product('r', $frame->id)], new PricingContext);
        $this->assertSame(9900 - 990, $q->totalGross, 'cumulabile: 10% din valoarea rămasă');
    }

    public function test_promotion_window_is_respected(): void
    {
        $frame = $this->frame(30000);
        Promotion::create(['name' => 'Expirată', 'scope' => 'frames', 'type' => 'percent', 'value' => 5000, 'is_active' => true, 'ends_at' => now()->subDay()]);
        Promotion::create(['name' => 'Viitoare', 'scope' => 'frames', 'type' => 'percent', 'value' => 5000, 'is_active' => true, 'starts_at' => now()->addDay()]);
        $this->assertSame(30000, $this->engine()->quote([PriceLine::product('r', $frame->id)], new PricingContext)->totalGross);
    }

    public function test_coupon_is_required_and_validated(): void
    {
        $frame = $this->frame(30000);
        $p = Promotion::create(['name' => 'Cupon 50 lei', 'scope' => 'order', 'type' => 'fixed_amount', 'value' => 5000, 'is_active' => true, 'requires_coupon' => true]);
        Coupon::create(['promotion_id' => $p->id, 'code' => 'TEST50']);
        $line = [PriceLine::product('r', $frame->id)];
        $this->assertSame(30000, $this->engine()->quote($line, new PricingContext)->totalGross);
        $q = $this->engine()->quote($line, new PricingContext(couponCode: ' test50 '));
        $this->assertSame('valid', $q->couponStatus);
        $this->assertSame(25000, $q->totalGross);
        $this->assertSame('invalid', $this->engine()->quote($line, new PricingContext(couponCode: 'NUEXISTA'))->couponStatus);
    }

    public function test_order_discount_is_allocated_proportionally_and_totals_add_up(): void
    {
        $f1 = $this->frame(10000);
        $f2 = $this->frame(30000);
        Promotion::create(['name' => '−10 lei', 'scope' => 'order', 'type' => 'fixed_amount', 'value' => 1000, 'is_active' => true]);
        $q = $this->engine()->quote([PriceLine::product('a', $f1->id), PriceLine::product('b', $f2->id)], new PricingContext);
        $this->assertSame(39000, $q->totalGross);
        $this->assertSame([250, 750], array_map(fn ($c) => $c->discountTotal(), $q->components()));
        $this->assertSame($q->totalGross, array_sum(array_map(fn ($c) => $c->net + $c->vat, $q->components())));
    }

    public function test_minimum_order_value_and_beneficiary_proof(): void
    {
        $frame = $this->frame(30000);
        Promotion::create(['name' => 'Peste 500', 'scope' => 'order', 'type' => 'percent', 'value' => 1000, 'is_active' => true, 'min_order_total' => 50000]);
        Promotion::create(['name' => 'Pensionari −5%', 'scope' => 'lenses', 'type' => 'percent', 'value' => 500, 'is_active' => true, 'beneficiary_category' => 'pensionar', 'requires_proof' => true]);
        $this->assertSame(30000, $this->engine()->quote([PriceLine::product('r', $frame->id)], new PricingContext)->totalGross);

        $cfg = $this->config($frame->id, 'monofocal-premium-160');
        $q = $this->engine()->quoteConfiguration($cfg, new PricingContext(declaredBeneficiary: 'pensionar'));
        // 300 + 400 = 700 ≥ 500 → −10% comandă; lentila −5% înainte.
        $this->assertSame(70000 - 2000 - (int) round((70000 - 2000) * 0.10), $q->totalGross);
        $this->assertTrue($q->requiresProofCheck);
        $this->assertFalse($this->engine()->quoteConfiguration($cfg, new PricingContext)->requiresProofCheck);
    }

    public function test_b2b_price_list_without_vat_with_quantity_threshold_and_fallback(): void
    {
        $frame = $this->frame(30000);
        $offering = $this->offering('monofocal-premium-160');
        $list = PriceList::create(['code' => 'P1', 'name' => 'Partener test', 'channel' => 'b2b', 'prices_include_vat' => false, 'fallback' => 'b2c_discount', 'fallback_discount_bp' => 1000]);
        PriceListItem::create(['price_list_id' => $list->id, 'item_type' => 'offering', 'item_id' => $offering->id, 'min_qty' => 1, 'price' => 20000]);
        PriceListItem::create(['price_list_id' => $list->id, 'item_type' => 'offering', 'item_id' => $offering->id, 'min_qty' => 10, 'price' => 18000]);
        $ctx = new PricingContext(channel: 'b2b', priceList: $list, organizationId: 1);

        $q = $this->engine()->quoteConfiguration($this->config($frame->id, 'monofocal-premium-160', [], ['channel' => 'b2b']), $ctx);
        [$f, $l] = $q->components();
        // Rama fără preț în listă: B2C fără TVA (30000/1,21) − 10%.
        $net = 30000 - (30000 - (int) round(30000 / 1.21));
        $this->assertSame($net - (int) round($net * 0.10), $f->unitPrice);
        $this->assertSame(20000, $l->unitPrice);
        $this->assertSame(20000 + (int) round(20000 * 0.21), $l->gross);
        $this->assertFalse($q->basisIncludesVat);

        $q10 = $this->engine()->quoteConfiguration($this->config($frame->id, 'monofocal-premium-160', [], ['channel' => 'b2b']), $ctx, 10);
        $this->assertSame(18000, $q10->components()[1]->unitPrice, 'pragul de 10 bucăți');

        $list->update(['fallback' => 'none']);
        $q = $this->engine()->quoteConfiguration($this->config($frame->id, 'monofocal-premium-160', [], ['channel' => 'b2b']), $ctx->with(['priceList' => $list->fresh()]));
        $this->assertFalse($q->isValid(), 'fără preț de partener pentru ramă: cerere de ofertă');
    }

    public function test_lenses_sold_per_piece_are_counted_per_eye(): void
    {
        $offering = $this->offering('monofocal-premium-160');
        $offering->product->update(['sale_unit' => 'piece']);
        $cfg = new ConfigurationInput(entry: 'fara_rama', offeringId: $offering->id, od: new EyeRx(-100), os: new EyeRx(-100), eyes: 'od');
        $q = $this->engine()->quoteConfiguration($cfg, new PricingContext);
        $this->assertSame(1, $q->components()[0]->quantity);
        $this->assertSame('buc.', $q->components()[0]->unitLabel);
        $this->assertSame(2, $this->engine()->quoteConfiguration($cfg->with(['eyes' => 'ambii']), new PricingContext)->components()[0]->quantity);
    }

    public function test_shipping_rules_threshold_and_unavailable_method(): void
    {
        $m = ShippingMethod::create(['code' => 'curier-test', 'name' => 'Curier', 'kind' => 'curier', 'is_active' => true]);
        $m->rules()->create(['min_subtotal' => 0, 'max_subtotal' => 50000, 'price' => 2000]);
        $m->rules()->create(['min_subtotal' => 50000, 'price' => 0]);
        $frame = $this->frame(30000);
        $q = $this->engine()->quote([PriceLine::product('r', $frame->id)], new PricingContext(shippingMethodCode: 'curier-test'));
        $this->assertSame(32000, $q->totalGross);
        $q = $this->engine()->quote([PriceLine::product('r', $frame->id, 2)], new PricingContext(shippingMethodCode: 'curier-test'));
        $this->assertSame(60000, $q->totalGross, 'peste prag: transport gratuit');
        $this->assertFalse($this->engine()->quote([PriceLine::product('r', $frame->id)], new PricingContext(shippingMethodCode: 'curier'))->isValid(), 'metoda inactivă');
    }

    public function test_quantity_multiplies_every_component(): void
    {
        $frame = $this->frame(30000);
        $q = $this->engine()->quoteConfiguration($this->config($frame->id, 'monofocal-standard-150'), new PricingContext, 2);
        $this->assertSame(2 * (30000 + 20000), $q->totalGross);
    }
}
