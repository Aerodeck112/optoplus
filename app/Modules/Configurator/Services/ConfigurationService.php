<?php

namespace App\Modules\Configurator\Services;

use App\Core\Audit\Audit;
use App\Modules\Configurator\Models\Configuration;
use App\Modules\Configurator\Models\Prescription;
use App\Modules\Configurator\Support\ConfigurationInput;
use App\Modules\Configurator\Support\EyeRx;
use App\Modules\Configurator\Support\InvalidRxValue;
use App\Modules\Configurator\Support\ValidationResult;
use App\Modules\Customers\Models\Customer;
use App\Modules\Pricing\Services\PriceEngine;
use App\Modules\Pricing\Support\PricingContext;
use App\Modules\Pricing\Support\Quote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Ciclul de viață al unei configurații: creare, acces (proprietar sau sesiune), salvarea pașilor, validare și
 * preț pe server, link securizat de reluare.
 */
class ConfigurationService
{
    private const SESSION_KEY = 'configuratii';

    public function __construct(private ConfigurationValidator $validator, private PriceEngine $prices) {}

    public function create(Request $request, string $entry, ?int $frameVariantId = null): Configuration
    {
        $customer = Auth::guard('client')->user();
        $cfg = Configuration::create([
            'uuid' => (string) Str::uuid(),
            'customer_id' => $customer?->id,
            'guest_key' => $customer ? null : $this->guestKey($request),
            'entry' => $entry,
            'frame_variant_id' => $frameVariantId,
            'step' => 1,
            'status' => 'draft',
        ]);
        $this->remember($request, $cfg);

        return $cfg;
    }

    /** Clientul autentificat proprietar sau sesiunea care a creat/reluat configurația. */
    public function canAccess(Request $request, Configuration $cfg): bool
    {
        $customer = Auth::guard('client')->user();
        if ($cfg->customer_id !== null) {
            return $customer?->id === $cfg->customer_id;
        }

        return in_array($cfg->uuid, (array) $request->session()->get(self::SESSION_KEY, []), true);
    }

    public function remember(Request $request, Configuration $cfg): void
    {
        $list = array_slice(array_values(array_unique([...(array) $request->session()->get(self::SESSION_KEY, []), $cfg->uuid])), -20);
        $request->session()->put(self::SESSION_KEY, $list);
    }

    /** La autentificare, configurațiile din sesiune devin ale contului. */
    public function claimForCustomer(Request $request, Customer $customer): void
    {
        $uuids = (array) $request->session()->get(self::SESSION_KEY, []);
        Configuration::whereIn('uuid', $uuids)->whereNull('customer_id')->get()->each(function (Configuration $c) use ($customer) {
            $c->update(['customer_id' => $customer->id, 'guest_key' => null]);
            $c->prescription?->update(['customer_id' => $customer->id, 'guest_key' => null]);
        });
    }

    public function input(Configuration $cfg, ?Customer $customer = null): ConfigurationInput
    {
        return $cfg->loadMissing('prescription')->toInput($this->channel($customer));
    }

    public function validate(Configuration $cfg, bool $final, ?ConfigurationInput $input = null): ValidationResult
    {
        return $this->validator->validate($input ?? $this->input($cfg), [], $final);
    }

    public function quote(Configuration $cfg, ?ConfigurationInput $input = null): Quote
    {
        return $this->prices->quoteConfiguration($input ?? $this->input($cfg), $this->context(), max(1, (int) $cfg->quantity));
    }

    public function context(): PricingContext
    {
        // Prețurile partenerilor B2B se calculează în portalul B2B, cu lista lor; configuratorul public este B2C.
        return new PricingContext(customerId: Auth::guard('client')->id());
    }

    private function channel(?Customer $customer): string
    {
        return 'b2c';
    }

    /**
     * Salvează datele unui pas. Validarea „pe parcurs” (non-finală) blochează doar valorile greșite, nu pașii
     * necompletați încă.
     *
     * @throws ValidationException
     */
    public function saveStep(Configuration $cfg, int $step, array $data): ValidationResult
    {
        match ($step) {
            1 => $this->saveUsage($cfg, $data),
            2 => $this->savePrescription($cfg, $data),
            3 => $cfg->update(['lens_offering_id' => isset($data['oferta']) && is_numeric($data['oferta']) ? (int) $data['oferta'] : null, 'treatments' => []]),
            4 => $cfg->update(['treatments' => $this->treatmentsFrom($data)]),
            default => null,
        };
        $cfg->refresh();
        $result = $this->validate($cfg, false);
        $relevant = array_filter($result->errors, fn ($e) => $this->belongsToStep($e, $step));
        if ($relevant) {
            throw ValidationException::withMessages(collect($relevant)->mapWithKeys(fn ($e, $i) => [($e['field'] ?? 'general').'.'.$i => $e['message']])->all());
        }
        $cfg->update(['step' => max($cfg->step, min(5, $step + 1)), 'validation' => $result->toArray(), 'version' => $cfg->version + 1]);

        return $result;
    }

    private function saveUsage(Configuration $cfg, array $data): void
    {
        $usage = (string) ($data['utilizare'] ?? '');
        if (! array_key_exists($usage, ConfigurationInput::USAGES)) {
            throw ValidationException::withMessages(['usage' => 'Alegeți pentru ce folosiți ochelarii.']);
        }
        $cfg->update(['usage' => $usage]);
        if ($cfg->entry === 'fara_rama') {
            $eyes = in_array($data['ochi'] ?? 'ambii', ['ambii', 'od', 'os'], true) ? $data['ochi'] : 'ambii';
            $p = $this->prescriptionFor($cfg);
            $p->update(['measurements' => array_merge((array) $p->measurements, ['eyes' => $eyes])]);
        }
    }

    private function savePrescription(Configuration $cfg, array $data): void
    {
        $errors = [];
        $values = [];
        foreach (['od', 'os'] as $eye) {
            try {
                $rx = EyeRx::fromInput((array) ($data[$eye] ?? []));
                $values[$eye] = $rx->toArray();
            } catch (InvalidRxValue $e) {
                $errors["{$eye}.sph"] = 'Valoare greșită: „'.e($e->raw).'”. Scrieți numere ca pe rețetă, de exemplu −2,25 sau +1,50.';
            }
        }
        $pdMode = in_array($data['dp_mod'] ?? null, ['binocular', 'monocular'], true) ? $data['dp_mod'] : null;
        $mm = fn ($v) => ($v === null || $v === '') ? null : (is_numeric(str_replace(',', '.', (string) $v)) ? (int) round(((float) str_replace(',', '.', (string) $v)) * 10) : 'x');
        $measurements = [
            'pd' => $mm($data['dp'] ?? null), 'pd_od' => $mm($data['dp_od'] ?? null), 'pd_os' => $mm($data['dp_os'] ?? null),
            'height_od' => $mm($data['h_od'] ?? null), 'height_os' => $mm($data['h_os'] ?? null),
            'by_store' => ! empty($data['masuratori_specialist']),
        ];
        foreach ($measurements as $k => $v) {
            if ($v === 'x') {
                $errors[$k] = 'Scrieți distanța în milimetri, de exemplu 63 sau 31,5.';
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        $p = $this->prescriptionFor($cfg);
        $eyes = ((array) $p->measurements)['eyes'] ?? 'ambii';
        $p->update([
            'values' => $values,
            'pd_mode' => $pdMode,
            'measurements' => array_merge($measurements, ['eyes' => $eyes]),
            'source' => $p->files()->exists() ? 'upload' : 'manual',
            'confirmed_by_customer_at' => now(),
            'retention_until' => now()->addYears((int) config('optoplus.retention.prescription_years', 3)),
        ]);
        Audit::log('prescription.saved', $p, [], $p->customer_id);
    }

    public function prescriptionFor(Configuration $cfg): Prescription
    {
        if ($cfg->prescription) {
            return $cfg->prescription;
        }
        $p = Prescription::create(['customer_id' => $cfg->customer_id, 'guest_key' => $cfg->guest_key, 'source' => 'manual']);
        $cfg->update(['prescription_id' => $p->id]);
        $cfg->setRelation('prescription', $p);

        return $p;
    }

    private function treatmentsFrom(array $data): array
    {
        $out = [];
        foreach ((array) ($data['tratamente'] ?? []) as $id) {
            if (is_numeric($id)) {
                $option = $data['optiune'][$id] ?? null;
                $out[] = ['treatment_id' => (int) $id, 'option' => is_string($option) && preg_match('/^[a-z0-9_]{1,40}$/', $option) ? $option : null];
            }
        }

        return $out;
    }

    private function belongsToStep(array $error, int $step): bool
    {
        $code = $error['code'];
        $field = (string) ($error['field'] ?? '');

        return match ($step) {
            1 => in_array($code, ['entry', 'entry_own_frame', 'usage', 'frame', 'frame_not_configurable', 'frame_stock'], true),
            2 => str_starts_with($code, 'rx_') || str_starts_with($code, 'pd') || str_starts_with($field, 'od.') || str_starts_with($field, 'os.'),
            3 => in_array($code, ['offering', 'matrix_sph', 'matrix_cyl', 'matrix_combined', 'matrix_add', 'add_missing', 'sale_unit_pair', 'quote_only'], true),
            4 => str_starts_with($code, 'treatment') || str_starts_with($code, 'rule_'),
            default => false,
        };
    }

    /** Link securizat de reluare: tokenul (256 biți) se arată o singură dată; în bază se păstrează doar hash-ul. */
    public function shareLink(Configuration $cfg): string
    {
        $token = Str::random(43);
        $cfg->update(['share_token_hash' => hash('sha256', $token), 'share_expires_at' => now()->addDays((int) config('optoplus.configurator.share_link_days', 30))]);

        return url('/c/'.$token.'/');
    }

    public function findByToken(string $token): ?Configuration
    {
        if (! preg_match('/^[A-Za-z0-9]{43}$/', $token)) {
            return null;
        }

        return Configuration::where('share_token_hash', hash('sha256', $token))->where('share_expires_at', '>', now())->first();
    }

    private function guestKey(Request $request): string
    {
        return hash('sha256', $request->session()->getId());
    }
}
