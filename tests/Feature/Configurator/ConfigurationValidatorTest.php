<?php

namespace Tests\Feature\Configurator;

use App\Modules\Configurator\Services\ConfigurationValidator;
use App\Modules\Configurator\Support\ConfigurationInput;
use App\Modules\Configurator\Support\EyeRx;
use Tests\Concerns\BuildsCatalog;
use Tests\TestCase;

class ConfigurationValidatorTest extends TestCase
{
    use BuildsCatalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLenses();
    }

    private function input(array $over = []): ConfigurationInput
    {
        return new ConfigurationInput(...array_merge([
            'entry' => 'cu_rama',
            'usage' => 'distanta',
            'frameVariantId' => $this->frame()->id,
            'offeringId' => $this->offering('monofocal-premium-160')->id,
            'od' => new EyeRx(-225, -50, 90),
            'os' => new EyeRx(-200),
            'pdMode' => 'binocular',
            'measurements' => ['pd' => 630],
        ], $over));
    }

    private function validate(ConfigurationInput $in)
    {
        return app(ConfigurationValidator::class)->validate($in);
    }

    public function test_valid_monofocal_configuration_requires_specialist_review_but_no_errors(): void
    {
        $r = $this->validate($this->input());
        $this->assertTrue($r->isValid(), json_encode($r->errors));
        $this->assertTrue($r->requiresReview(), 'ofertele neconfirmate cer verificarea specialistului');
    }

    public function test_sph_outside_manufacturing_matrix_is_blocked_with_explanation(): void
    {
        $r = $this->validate($this->input(['od' => new EyeRx(-650)]));
        $this->assertTrue($r->hasError('matrix_sph'));
        $this->assertStringContainsString('−6,00', $r->errors[0]['message']);
    }

    public function test_plus_cylinder_is_transposed_before_matrix_check(): void
    {
        // +1,00 / +2,00 × 90 = +3,00 / −2,00 × 180 — în matrice (SPH ±6, CYL până la −2,50).
        $r = $this->validate($this->input(['od' => new EyeRx(100, 200, 90)]));
        $this->assertTrue($r->isValid(), json_encode($r->errors));
        // −5,00 / +2,50 × 10 → −2,50 / −2,50 × 100: valid; dar +5,00 / +2,00 → +7,00 / −2,00: în afara SPH.
        $r = $this->validate($this->input(['od' => new EyeRx(500, 200, 10)]));
        $this->assertTrue($r->hasError('matrix_sph'));
    }

    public function test_cylinder_beyond_range_and_missing_axis_are_reported(): void
    {
        $r = $this->validate($this->input(['od' => new EyeRx(-100, -300, 45)]));
        $this->assertTrue($r->hasError('matrix_cyl'));
        $r = $this->validate($this->input(['od' => new EyeRx(-100, -75, null)]));
        $this->assertTrue($r->hasError('rx_od_axis'));
    }

    public function test_quarter_steps_are_enforced(): void
    {
        $r = $this->validate($this->input(['os' => new EyeRx(-210)]));
        $this->assertTrue($r->hasError('rx_os_sph_step'));
    }

    public function test_progressive_requires_add_and_fitting_height_or_contact_request(): void
    {
        $base = ['offeringId' => $this->offering('progresive-160')->id, 'usage' => 'toate'];
        $r = $this->validate($this->input($base));
        $this->assertTrue($r->hasError('add_missing'));
        $this->assertTrue($r->hasError('measurement_fitting_height'));

        $r = $this->validate($this->input($base + ['od' => new EyeRx(-100, null, null, 200), 'os' => new EyeRx(-100, null, null, 200), 'measurementsByStore' => true]));
        $this->assertTrue($r->isValid(), json_encode($r->errors));
        $this->assertContains('Măsurătorile suplimentare vor fi luate sau confirmate de specialist.', $r->reviewReasons);

        $r = $this->validate($this->input($base + ['od' => new EyeRx(-100, null, null, 425), 'os' => new EyeRx(-100, null, null, 200), 'measurements' => ['pd' => 630, 'height_od' => 200, 'height_os' => 200]]));
        $this->assertTrue($r->hasError('rx_od_add_range') || $r->hasError('matrix_add'));
    }

    public function test_photochromic_is_blocked_for_reading_glasses_by_editable_rule(): void
    {
        $in = $this->input(['usage' => 'aproape', 'treatments' => [['treatment_id' => $this->treatmentId('fotocromatic'), 'option' => 'gri_85']]]);
        $r = $this->validate($in);
        $this->assertFalse($r->isValid());
        $this->assertStringContainsString('ochelarii de citit', $r->errors[0]['message']);

        // Regula dezactivată din panou → combinația devine permisă, fără modificări de cod.
        \App\Modules\Lenses\Models\CompatibilityRule::where('name', 'like', 'Fără fotocromatic%')->update(['is_active' => false]);
        $this->assertTrue($this->validate($in)->isValid());
    }

    public function test_photochromic_option_must_be_allowed_for_the_index(): void
    {
        // 1,60: APF oferea doar 85%.
        $r = $this->validate($this->input(['treatments' => [['treatment_id' => $this->treatmentId('fotocromatic'), 'option' => 'gri_65']]]));
        $this->assertTrue($r->hasError('treatment_option'));
        $r = $this->validate($this->input(['treatments' => [['treatment_id' => $this->treatmentId('fotocromatic'), 'option' => null]]]));
        $this->assertTrue($r->hasError('treatment_option'));
    }

    public function test_standard_lens_has_no_treatments(): void
    {
        $r = $this->validate($this->input([
            'offeringId' => $this->offering('monofocal-standard-150')->id,
            'treatments' => [['treatment_id' => $this->treatmentId('antireflex-hidrofob'), 'option' => null]],
        ]));
        $this->assertTrue($r->hasError('treatment_unavailable'));
    }

    public function test_pd_monocular_and_binocular_ranges(): void
    {
        $this->assertTrue($this->validate($this->input(['measurements' => ['pd' => 900]]))->hasError('pd_range'));
        $r = $this->validate($this->input(['pdMode' => 'monocular', 'measurements' => ['pd_od' => 315, 'pd_os' => 320]]));
        $this->assertTrue($r->isValid(), json_encode($r->errors));
        $this->assertTrue($this->validate($this->input(['pdMode' => 'monocular', 'measurements' => ['pd_od' => 315]]))->hasError('pd_os_missing'));
    }

    public function test_nylon_frame_with_standard_index_requires_review_and_high_power_warns(): void
    {
        $frame = $this->frame(30000, [], ['rim_type' => 'semi_nylon']);
        $r = $this->validate($this->input(['frameVariantId' => $frame->id, 'offeringId' => $this->offering('monofocal-premium-150')->id, 'od' => new EyeRx(-400), 'os' => new EyeRx(-350)]));
        $this->assertTrue($r->isValid());
        $this->assertNotEmpty(array_filter($r->reviewReasons, fn ($m) => str_contains($m, 'rame pe fir')));
        $this->assertNotEmpty(array_filter($r->warnings, fn ($w) => str_contains($w['message'], 'subțiate')));
    }

    public function test_unpublished_frame_and_own_frame_service_are_rejected(): void
    {
        $frame = $this->frame();
        $frame->product->update(['status' => 'draft']);
        $this->assertTrue($this->validate($this->input(['frameVariantId' => $frame->id]))->hasError('frame'));
        $this->assertTrue($this->validate($this->input(['entry' => 'rama_mea', 'frameVariantId' => null]))->hasError('entry_own_frame'));
    }

    public function test_draft_validation_does_not_require_missing_steps(): void
    {
        $in = new ConfigurationInput(entry: 'cu_rama', usage: 'distanta', frameVariantId: $this->frame()->id);
        $this->assertTrue(app(ConfigurationValidator::class)->validate($in, final: false)->isValid());
        $this->assertFalse(app(ConfigurationValidator::class)->validate($in)->isValid());
    }
}
