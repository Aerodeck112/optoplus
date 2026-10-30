<?php

namespace App\Modules\Configurator\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Configurator\Support\ConfigurationInput;
use App\Modules\Configurator\Support\EyeRx;
use App\Modules\Configurator\Support\ValidationResult;
use App\Modules\Lenses\Models\LensOffering;
use App\Modules\Lenses\Models\LensOfferingTreatment;
use App\Modules\Lenses\Services\RuleEngine;

/**
 * Singura autoritate asupra validității unei configurații: formatul rețetei, matricea de fabricație (intervale pe
 * ofertă), tratamentele permise, măsurătorile cerute și regulile de compatibilitate editabile.
 *
 * Validatorul nu modifică rețeta. Transpunerea cilindrului se face doar pentru comparația cu matricea.
 */
class ConfigurationValidator
{
    public function __construct(private RuleEngine $rules) {}

    /** @param  array{frame?:?ProductVariant, offering?:?LensOffering}  $loaded  obiecte deja încărcate (opțional) */
    public function validate(ConfigurationInput $in, array $loaded = [], bool $final = true): ValidationResult
    {
        $r = new ValidationResult;

        if (! in_array($in->entry, ConfigurationInput::ENTRIES, true)) {
            return $r->error('entry', 'Alegeți cum doriți să comandați.');
        }
        if ($in->entry === 'rama_mea' && ! config('optoplus.configurator.own_frame_service', false)) {
            $r->error('entry_own_frame', 'Serviciul „lentile pentru rama mea” nu este disponibil momentan. Sunați-ne și vă ajutăm.');
        }
        if ($in->usage !== null && ! array_key_exists($in->usage, ConfigurationInput::USAGES)) {
            $r->error('usage', 'Alegeți pentru ce folosiți ochelarii.', 'usage');
        }

        $frame = $loaded['frame'] ?? ($in->frameVariantId ? ProductVariant::with('product.frameSpec', 'product.attributeValues.attribute')->find($in->frameVariantId) : null);
        if ($in->entry === 'cu_rama') {
            if (! $frame || ! $frame->is_active || $frame->product->status !== Product::STATUS_PUBLISHED || ! $frame->product->isFrame()) {
                $r->error('frame', 'Rama aleasă nu mai este disponibilă. Alegeți altă ramă.', 'frame');
                $frame = null;
            } elseif (! $frame->product->configurable_with_lenses) {
                $r->error('frame_not_configurable', 'Această ramă nu poate fi comandată cu lentile.', 'frame');
            } elseif ($frame->isOutOfStock()) {
                $r->error('frame_stock', 'Rama nu mai este în stoc.', 'frame');
            }
        }

        $offering = $loaded['offering'] ?? ($in->offeringId ? LensOffering::with('lensProduct.family', 'material', 'product', 'treatments.treatment.options')->find($in->offeringId) : null);
        if ($in->offeringId !== null && (! $offering || ! $offering->isSellable() || $offering->product?->status !== Product::STATUS_PUBLISHED)) {
            $r->error('offering', 'Varianta de lentilă aleasă nu mai este disponibilă.', 'offering');
            $offering = null;
        }
        if ($final && ! $offering) {
            $r->error('offering_missing', 'Alegeți lentila.', 'offering');
        }

        $this->validateRx($in, $r, $final);
        $this->validatePd($in, $r, $final);

        if ($offering) {
            $this->validateMatrix($in, $offering, $r);
            $this->validateTreatments($in, $offering, $r);
            $this->validateMeasurements($in, $offering, $r, $final);
            if ($offering->requires_review) {
                $r->review('Lentila se produce după verificarea rețetei de către specialist.');
            }
            if ($offering->product?->sale_unit === 'pair' && $in->eyes !== 'ambii') {
                $r->error('sale_unit_pair', 'Această lentilă se vinde doar în pereche. Pentru o singură lentilă, cereți ajutorul specialistului.', 'eyes');
            }
            if ($offering->lensProduct?->quote_only) {
                $r->error('quote_only', 'Pentru această lentilă oferim preț la cerere. Trimiteți o cerere de ofertă.', 'offering');
            }
        }

        $this->applyRules($in, $frame, $offering, $r);

        return $r;
    }

    private function validateRx(ConfigurationInput $in, ValidationResult $r, bool $final): void
    {
        $eyes = ['od' => $in->od, 'os' => $in->os];
        $selected = match ($in->eyes) {
            'od' => ['od'],
            'os' => ['os'],
            default => ['od', 'os'],
        };
        $labels = ['od' => 'ochiul drept (OD)', 'os' => 'ochiul stâng (OS)'];
        foreach ($selected as $eye) {
            /** @var ?EyeRx $rx */
            $rx = $eyes[$eye];
            if ($rx === null || $rx->sph === null) {
                if ($final) {
                    $r->error("rx_{$eye}_sph", "Completați valoarea SPH pentru {$labels[$eye]} (0,00 dacă rețeta scrie „plan”).", "{$eye}.sph");
                }

                continue;
            }
            foreach (['sph' => $rx->sph, 'cyl' => $rx->cyl, 'add' => $rx->add] as $name => $value) {
                if ($value !== null && $value % 25 !== 0) {
                    $r->error("rx_{$eye}_{$name}_step", 'Valorile '.strtoupper($name)." pentru {$labels[$eye]} se scriu în pași de 0,25.", "{$eye}.{$name}");
                }
            }
            if (abs($rx->sph) > 3000 || ($rx->cyl !== null && abs($rx->cyl) > 1000)) {
                $r->error("rx_{$eye}_range", "Valorile pentru {$labels[$eye]} par greșite. Verificați rețeta.", "{$eye}.sph");
            }
            if ($rx->hasCyl() && $rx->axis === null) {
                $r->error("rx_{$eye}_axis", "Rețeta are cilindru (CYL) la {$labels[$eye]}, deci are nevoie și de ax (AX, între 0 și 180).", "{$eye}.axis");
            }
            if ($rx->axis !== null && ($rx->axis < 0 || $rx->axis > 180)) {
                $r->error("rx_{$eye}_axis_range", "Axul (AX) pentru {$labels[$eye]} trebuie să fie între 0 și 180.", "{$eye}.axis");
            }
            if (! $rx->hasCyl() && $rx->axis !== null && $rx->axis !== 0) {
                $r->warn("rx_{$eye}_axis_unused", "Ați completat axul fără cilindru la {$labels[$eye]}; axul se folosește doar împreună cu CYL.", "{$eye}.axis");
            }
            if ($rx->add !== null && ($rx->add < 50 || $rx->add > 400)) {
                $r->error("rx_{$eye}_add_range", "Adiția (ADD) pentru {$labels[$eye]} pare greșită. Verificați rețeta.", "{$eye}.add");
            }
        }
        if ($in->od && $in->os && $in->od->sph !== null && $in->os->sph !== null) {
            $anis = abs($in->od->toMinusCyl()->sph - $in->os->toMinusCyl()->sph);
            if ($anis >= (int) config('optoplus.configurator.anisometropia_review', 300)) {
                $r->review('Diferență mare de dioptrii între ochi.');
            }
        }
        if ($in->od && $in->os && $in->od->add !== null && $in->os->add !== null && $in->od->add !== $in->os->add) {
            $r->review('Adiția diferă între ochi.');
        }
    }

    private function validatePd(ConfigurationInput $in, ValidationResult $r, bool $final): void
    {
        $m = $in->measurements;
        if ($in->pdMode === null) {
            if ($final && ! $in->measurementsByStore) {
                $r->error('pd_missing', 'Completați distanța pupilară (DP) sau alegeți să vă contactăm pentru măsurare.', 'pd');
            }

            return;
        }
        [$min, $max] = config('optoplus.configurator.pd_binocular_range', [500, 800]);
        [$mMin, $mMax] = config('optoplus.configurator.pd_monocular_range', [250, 400]);
        if ($in->pdMode === 'binocular') {
            $pd = $m['pd'] ?? null;
            if ($pd === null) {
                if ($final) {
                    $r->error('pd_missing', 'Completați distanța pupilară (DP).', 'pd');
                }
            } elseif ($pd < $min || $pd > $max) {
                $r->error('pd_range', 'Distanța pupilară trebuie să fie între '.($min / 10).' și '.($max / 10).' mm. Verificați rețeta.', 'pd');
            }
        } elseif ($in->pdMode === 'monocular') {
            foreach (['pd_od' => 'ochiul drept', 'pd_os' => 'ochiul stâng'] as $k => $label) {
                $v = $m[$k] ?? null;
                if ($v === null) {
                    if ($final) {
                        $r->error("{$k}_missing", "Completați DP pentru {$label}.", $k);
                    }
                } elseif ($v < $mMin || $v > $mMax) {
                    $r->error("{$k}_range", "DP pentru {$label} trebuie să fie între ".($mMin / 10).' și '.($mMax / 10).' mm.', $k);
                }
            }
        } else {
            $r->error('pd_mode', 'Alegeți dacă aveți o singură valoare DP sau câte una pe ochi.', 'pd_mode');
        }
        if ($in->measurementsByStore) {
            $r->review('Clientul a cerut să fie contactat pentru măsurători.');
        }
    }

    private function validateMatrix(ConfigurationInput $in, LensOffering $o, ValidationResult $r): void
    {
        $family = $o->lensProduct?->family;
        $name = $o->product?->name ?? 'lentila aleasă';
        foreach (['od' => $in->od, 'os' => $in->os] as $eye => $rx) {
            if ($rx === null || $rx->sph === null || ($in->eyes !== 'ambii' && $in->eyes !== $eye)) {
                continue;
            }
            $m = $rx->toMinusCyl();
            $label = $eye === 'od' ? 'ochiul drept' : 'ochiul stâng';
            if ($m->sph < $o->sph_min || $m->sph > $o->sph_max) {
                $r->error('matrix_sph', "Pentru {$label}, {$name} cu indicele {$o->material?->indexLabel()} se produce între SPH ".EyeRx::format($o->sph_min).' și '.EyeRx::format($o->sph_max).'. Alegeți altă variantă sau cereți ajutorul specialistului.', "{$eye}.sph");
            }
            $cyl = $m->cyl ?? 0;
            if ($cyl < $o->cyl_min || $cyl > $o->cyl_max) {
                $r->error('matrix_cyl', "Pentru {$label}, cilindrul (CYL) depășește intervalul în care producem această variantă (până la ".EyeRx::format($o->cyl_min).').', "{$eye}.cyl");
            }
            if ($o->max_combined !== null && $rx->combinedAbs() > $o->max_combined) {
                $r->error('matrix_combined', "Pentru {$label}, combinația SPH + CYL depășește limita de fabricație a acestei variante.", "{$eye}.sph");
            }
            if ($family?->requires_add) {
                if ($rx->add === null) {
                    $r->error('add_missing', "Lentilele {$family->name} au nevoie de adiție (ADD) pentru {$label}. O găsiți pe rețetă la „ADD” sau „Adiție”.", "{$eye}.add");
                } elseif (($o->add_min !== null && $rx->add < $o->add_min) || ($o->add_max !== null && $rx->add > $o->add_max)) {
                    $r->error('matrix_add', "Pentru {$label}, adiția trebuie să fie între ".EyeRx::format($o->add_min, false).' și '.EyeRx::format($o->add_max, false).' pentru această variantă.', "{$eye}.add");
                }
            } elseif ($rx->add !== null && $rx->add > 0) {
                $r->warn('add_unused', 'Rețeta are adiție (ADD), dar lentila aleasă are o singură zonă de vedere. Pentru vederea la toate distanțele, alegeți lentile progresive.', "{$eye}.add");
            }
        }
    }

    private function validateTreatments(ConfigurationInput $in, LensOffering $o, ValidationResult $r): void
    {
        /** @var \Illuminate\Support\Collection<int, LensOfferingTreatment> $available */
        $available = $o->treatments->keyBy('treatment_id');
        $seen = [];
        foreach ($in->treatments as $t) {
            $id = (int) $t['treatment_id'];
            if (isset($seen[$id])) {
                $r->error('treatment_duplicate', 'Același tratament a fost ales de două ori.', 'treatments');

                continue;
            }
            $seen[$id] = true;
            $ot = $available->get($id);
            $treatment = $ot?->treatment;
            if (! $ot || ! $treatment || ! $treatment->is_active || $ot->status === 'inactiv') {
                $r->error('treatment_unavailable', 'Un tratament ales nu este disponibil pentru această lentilă.', 'treatments');

                continue;
            }
            if ($treatment->requires_option) {
                $option = $t['option'] ?? null;
                $valid = $option !== null && $treatment->options->contains(fn ($op) => $op->code === $option && $op->is_active);
                if (! $valid || ! $ot->allowsOption($option)) {
                    $r->error('treatment_option', "Alegeți o variantă disponibilă pentru „{$treatment->name}”.", 'treatments');
                }
            }
        }
    }

    private function validateMeasurements(ConfigurationInput $in, LensOffering $o, ValidationResult $r, bool $final): void
    {
        $required = (array) ($o->lensProduct?->required_measurements ?? []);
        if ($o->lensProduct?->family?->requires_fitting_height && ! in_array('fitting_height', $required, true)) {
            $required[] = 'fitting_height';
        }
        foreach ($required as $field) {
            $r->requiredFields[] = $field;
            $present = match ($field) {
                'fitting_height' => ($in->measurements['height_od'] ?? null) !== null && ($in->measurements['height_os'] ?? null) !== null,
                'pd_monocular' => $in->pdMode === 'monocular',
                default => true,
            };
            if (! $present) {
                if ($in->measurementsByStore) {
                    $r->review('Măsurătorile suplimentare vor fi luate sau confirmate de specialist.');
                } elseif ($final) {
                    $label = \App\Modules\Lenses\Models\LensProduct::MEASUREMENTS[$field] ?? $field;
                    $r->error("measurement_{$field}", "Pentru această lentilă avem nevoie de: {$label}. Dacă nu o aveți, bifați „Vreau să fiu contactat pentru măsurători”.", $field);
                }
            }
        }
    }

    private function applyRules(ConfigurationInput $in, ?ProductVariant $frame, ?LensOffering $o, ValidationResult $r): void
    {
        $context = $this->context($in, $frame, $o);
        foreach ($this->rules->matching($context) as $rule) {
            $r->appliedRules[] = $rule->id;
            match ($rule->effect) {
                'block' => $r->error('rule_'.$rule->id, $rule->message_customer),
                'warn' => $r->warn('rule_'.$rule->id, $rule->message_customer),
                'require_review' => $r->review($rule->message_customer),
                'require_field' => $this->requireFieldByRule($in, $rule, $r),
                default => null,
            };
        }
    }

    private function requireFieldByRule(ConfigurationInput $in, $rule, ValidationResult $r): void
    {
        $field = (string) $rule->required_field;
        $r->requiredFields[] = $field;
        $present = match ($field) {
            'fitting_height' => ($in->measurements['height_od'] ?? null) !== null,
            'pd_monocular' => $in->pdMode === 'monocular',
            default => false,
        };
        if (! $present) {
            $in->measurementsByStore ? $r->review($rule->message_customer) : $r->error('rule_'.$rule->id, $rule->message_customer, $field);
        }
    }

    /** Contextul plat pentru regulile de compatibilitate (vezi RuleFields). */
    public function context(ConfigurationInput $in, ?ProductVariant $frame, ?LensOffering $o): array
    {
        $eyes = $in->eyesRx();
        $minus = array_values(array_map(fn (EyeRx $e) => $e->toMinusCyl(), array_filter($eyes, fn ($e) => $e->sph !== null)));
        $treatmentCodes = [];
        $optionCodes = [];
        if ($o) {
            $byId = $o->treatments->keyBy('treatment_id');
            foreach ($in->treatments as $t) {
                if ($ot = $byId->get((int) $t['treatment_id'])) {
                    $treatmentCodes[] = $ot->treatment?->code;
                    $treatmentCodes[] = $ot->treatment?->kind;
                    if ($t['option'] ?? null) {
                        $optionCodes[] = $t['option'];
                    }
                }
            }
        }
        $frameProduct = $frame?->product;
        $frameAttr = fn (string $code) => $frameProduct
            ? $frameProduct->attributeValues->filter(fn ($v) => $v->attribute?->code === $code)->pluck('code')->values()->all()
            : [];

        return [
            'entry' => $in->entry,
            'usage' => $in->usage,
            'channel' => $in->channel,
            'family' => $o?->lensProduct?->family?->code,
            'lens_product' => $o?->product?->slug,
            'material' => $o?->material?->code,
            'index' => $o?->material?->refractive_index,
            'treatment' => array_values(array_unique(array_filter($treatmentCodes))),
            'treatment_option' => $optionCodes,
            'frame.rim_type' => $frameProduct?->frameSpec?->rim_type,
            'frame.lens_width_mm' => $frameProduct?->frameSpec?->lens_width_mm,
            'frame.material' => $frameAttr('material'),
            'frame.construction' => $frameAttr('constructie'),
            'rx.max_abs_sph' => $minus ? max(array_map(fn ($e) => abs($e->sph), $minus)) : null,
            'rx.max_abs_cyl' => $minus ? max(array_map(fn ($e) => abs($e->cyl ?? 0), $minus)) : null,
            'rx.max_power' => $eyes ? max(array_map(fn (EyeRx $e) => $e->sph === null ? 0 : $e->combinedAbs(), $eyes)) : null,
            'rx.max_add' => $eyes ? max(array_map(fn (EyeRx $e) => $e->add ?? 0, $eyes)) : null,
            'rx.anisometropia' => count($minus) === 2 ? abs($minus[0]->sph - $minus[1]->sph) : null,
            'rx.has_cyl' => (bool) array_filter($eyes, fn (EyeRx $e) => $e->hasCyl()),
            'pd_mode' => $in->pdMode,
        ];
    }
}
