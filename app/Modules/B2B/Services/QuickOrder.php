<?php

namespace App\Modules\B2B\Services;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Configurator\Models\Configuration;
use App\Modules\Configurator\Models\Prescription;
use App\Modules\Configurator\Services\ConfigurationValidator;
use App\Modules\Configurator\Support\ConfigurationInput;
use App\Modules\Configurator\Support\EyeRx;
use App\Modules\Configurator\Support\InvalidRxValue;
use App\Modules\Lenses\Models\LensOffering;
use App\Modules\Lenses\Models\LensTreatment;
use App\Modules\Pricing\Services\PriceEngine;
use App\Modules\Pricing\Support\PriceLine;
use App\Modules\Shop\Services\CartService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Comanda rapidă B2B: un rând = o lucrare (lentile, opțional cu ramă) pentru un cod intern de pacient.
 * Același parser servește tabelul din portal și importul CSV; fiecare rând trece prin ConfigurationValidator
 * și PriceEngine (lista de preț a partenerului). Nu se cere numele pacientului.
 */
class QuickOrder
{
    /** Coloanele acceptate (și antetul CSV-ului model). */
    public const COLUMNS = ['cod_pacient', 'lentila', 'tratamente', 'rama_sku', 'ochi', 'od_sph', 'od_cyl', 'od_ax', 'od_add', 'os_sph', 'os_cyl', 'os_ax', 'os_add', 'dp', 'dp_od', 'dp_os', 'h_od', 'h_os', 'cantitate'];

    public function __construct(private ConfigurationValidator $validator, private PriceEngine $prices, private CartService $carts) {}

    /**
     * Interpretează și validează un rând. Nu scrie nimic.
     *
     * @return array{ok:bool, errors:list<string>, warnings:list<string>, input:?ConfigurationInput, patient:?string, quantity:int, price:?int, review:bool}
     */
    public function check(array $row, Partner $partner): array
    {
        $row = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $row);
        $errors = [];
        $patient = mb_substr((string) ($row['cod_pacient'] ?? ''), 0, 60) ?: null;
        if ($patient !== null && ! preg_match('/^[\pL\pN ._\/-]{1,60}$/u', $patient)) {
            $errors[] = 'Codul pacientului poate conține doar litere, cifre, spațiu și . _ / -';
        }
        $offering = $this->offering((string) ($row['lentila'] ?? ''));
        if (! $offering) {
            $errors[] = 'Lentila nu a fost găsită (folosiți codul din lista de lentile).';
        }
        $frameId = null;
        if (($sku = (string) ($row['rama_sku'] ?? '')) !== '') {
            $frameId = ProductVariant::where('sku', $sku)->value('id');
            if (! $frameId) {
                $errors[] = "Rama cu codul „{$sku}” nu a fost găsită.";
            }
        }
        $eyes = in_array($row['ochi'] ?? 'ambii', ['ambii', 'od', 'os'], true) ? ($row['ochi'] ?? 'ambii') : 'ambii';
        $rx = [];
        foreach (['od', 'os'] as $eye) {
            $values = ['sph' => $row["{$eye}_sph"] ?? null, 'cyl' => $row["{$eye}_cyl"] ?? null, 'axis' => $row["{$eye}_ax"] ?? null, 'add' => $row["{$eye}_add"] ?? null];
            if (($eyes === 'od' && $eye === 'os') || ($eyes === 'os' && $eye === 'od')) {
                $rx[$eye] = null;

                continue;
            }
            try {
                $rx[$eye] = EyeRx::fromInput($values);
            } catch (InvalidRxValue $e) {
                $errors[] = strtoupper($eye).': valoare greșită „'.$e->raw.'”.';
                $rx[$eye] = null;
            }
        }
        $mm = fn ($v) => ($v === null || $v === '') ? null : (is_numeric(str_replace(',', '.', (string) $v)) ? (int) round((float) str_replace(',', '.', (string) $v) * 10) : -1);
        $measurements = array_filter(['pd' => $mm($row['dp'] ?? null), 'pd_od' => $mm($row['dp_od'] ?? null), 'pd_os' => $mm($row['dp_os'] ?? null), 'height_od' => $mm($row['h_od'] ?? null), 'height_os' => $mm($row['h_os'] ?? null)], fn ($v) => $v !== null);
        if (in_array(-1, $measurements, true)) {
            $errors[] = 'DP și înălțimile se scriu în milimetri (ex. 63 sau 31,5).';
        }
        $pdMode = isset($measurements['pd_od']) || isset($measurements['pd_os']) ? 'monocular' : (isset($measurements['pd']) ? 'binocular' : null);
        $treatments = [];
        foreach (array_filter(preg_split('/[;|]/', (string) ($row['tratamente'] ?? ''))) as $t) {
            [$code, $option] = array_pad(explode(':', trim($t), 2), 2, null);
            $id = LensTreatment::where('code', $code)->value('id');
            if (! $id) {
                $errors[] = "Tratamentul „{$code}” nu există.";

                continue;
            }
            $treatments[] = ['treatment_id' => (int) $id, 'option' => $option ?: null];
        }
        $qty = max(1, min(50, (int) ($row['cantitate'] ?? 1) ?: 1));

        if ($errors) {
            return ['ok' => false, 'errors' => $errors, 'warnings' => [], 'input' => null, 'patient' => $patient, 'quantity' => $qty, 'price' => null, 'review' => false];
        }
        $input = new ConfigurationInput(
            entry: $frameId ? 'cu_rama' : 'fara_rama', usage: null, frameVariantId: $frameId, offeringId: $offering->id,
            treatments: $treatments, od: $rx['od'], os: $rx['os'], pdMode: $pdMode, measurements: $measurements,
            eyes: $eyes, quantity: $qty, channel: 'b2b',
        );
        $result = $this->validator->validate($input, [], true);
        $quote = $this->prices->quote([PriceLine::configuration('r', $input, $qty)], $partner->pricing());

        return [
            'ok' => $result->isValid() && $quote->isValid(),
            'errors' => array_merge(array_column($result->errors, 'message'), $quote->lines['r']['errors'] ?? []),
            'warnings' => array_column($result->warnings, 'message'),
            'input' => $input, 'patient' => $patient, 'quantity' => $qty,
            'price' => $quote->isValid() ? $quote->totalGross : null, 'review' => $result->requiresReview(),
        ];
    }

    /** Adaugă în coșul partenerului rândurile valide (fiecare devine o configurație cu rețeta criptată). */
    public function addToCart(array $checked, Partner $partner): int
    {
        $cart = $this->carts->forOrganization($partner->organizationId(), $partner->customer->id);
        $added = 0;
        DB::transaction(function () use ($checked, $partner, $cart, &$added) {
            foreach ($checked as $r) {
                if (! $r['ok']) {
                    continue;
                }
                /** @var ConfigurationInput $in */
                $in = $r['input'];
                $p = Prescription::create([
                    'customer_id' => $partner->customer->id, 'organization_id' => $partner->organizationId(),
                    'patient_reference' => $r['patient'], 'source' => 'manual',
                    'values' => ['od' => $in->od?->toArray(), 'os' => $in->os?->toArray()],
                    'pd_mode' => $in->pdMode, 'measurements' => $in->measurements + ['eyes' => $in->eyes],
                    'confirmed_by_customer_at' => now(),
                    'retention_until' => now()->addYears((int) config('optoplus.retention.prescription_years', 3)),
                ]);
                $cfg = Configuration::create([
                    'uuid' => (string) Str::uuid(), 'customer_id' => $partner->customer->id, 'organization_id' => $partner->organizationId(),
                    'entry' => $in->entry, 'frame_variant_id' => $in->frameVariantId, 'lens_offering_id' => $in->offeringId,
                    'treatments' => $in->treatments, 'prescription_id' => $p->id, 'step' => 5, 'quantity' => $r['quantity'],
                    'status' => $r['review'] ? 'necesita_verificare' : 'valid',
                ]);
                $cart->items()->create(['kind' => 'configuration', 'configuration_id' => $cfg->id, 'quantity' => $r['quantity'], 'patient_reference' => $r['patient']]);
                $added++;
            }
        });

        return $added;
    }

    private function offering(string $code): ?LensOffering
    {
        if ($code === '') {
            return null;
        }

        return LensOffering::query()->sellable()->where('code', $code)->first();
    }

    /** Ofertele disponibile pentru selectorul din portal. */
    public function offerings()
    {
        return LensOffering::query()->sellable()->with('product', 'material')->orderBy('lens_product_id')->orderBy('sort')->get();
    }
}
