<?php

namespace App\Modules\Lenses\Http\Controllers;

use App\Core\Audit\Audit;
use App\Core\Support\Money;
use App\Http\Controllers\Controller;
use App\Modules\Configurator\Support\EyeRx;
use App\Modules\Lenses\Models\CompatibilityRule;
use App\Modules\Lenses\Models\LensOffering;
use App\Modules\Lenses\Models\LensOfferingTreatment;
use App\Modules\Lenses\Services\RuleEngine;
use App\Modules\Lenses\Services\RuleFields;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Lentilele în panou: matricea de fabricație (intervale, prețuri, statusuri) și regulile de compatibilitate.
 * Datele preluate din siteul vechi sunt „neconfirmate” până la confirmarea explicită a laboratorului.
 */
class LensAdminController extends Controller
{
    public function index()
    {
        $offerings = LensOffering::with('product', 'material', 'lensProduct.family', 'treatments.treatment')->orderBy('lens_product_id')->orderBy('sort')->get()->groupBy('lens_product_id');

        return view('lenses::panel.index', ['groups' => $offerings, 'unconfirmed' => LensOffering::where('status', 'neconfirmat')->count(), 'sellUnconfirmed' => (bool) config('optoplus.lenses.sell_unconfirmed')]);
    }

    public function offering(int $id)
    {
        return view('lenses::panel.offering', ['o' => LensOffering::with('product', 'material', 'lensProduct.family', 'treatments.treatment.options')->findOrFail($id)]);
    }

    /** Intervalele se introduc ca pe rețetă (dioptrii, ex. −6,00), se păstrează în sutimi. */
    public function updateOffering(Request $request, int $id)
    {
        $o = LensOffering::findOrFail($id);
        $data = $request->validate([
            'sph_min' => ['required', 'string'], 'sph_max' => ['required', 'string'], 'cyl_min' => ['required', 'string'], 'cyl_max' => ['required', 'string'],
            'max_combined' => ['nullable', 'string'], 'add_min' => ['nullable', 'string'], 'add_max' => ['nullable', 'string'],
            'diametru' => ['nullable', 'integer', 'min:40', 'max:90'], 'pret' => ['required', 'string'], 'cost' => ['nullable', 'string'],
            'zile_min' => ['nullable', 'integer', 'min:0', 'max:120'], 'zile_max' => ['nullable', 'integer', 'min:0', 'max:120'],
            'stare' => ['required', 'in:activ,neconfirmat,inactiv'], 'verificare' => ['nullable', 'boolean'], 'sursa' => ['nullable', 'string', 'max:255'],
            't' => ['array'], 't.*.pret' => ['nullable', 'string'], 't.*.inclus' => ['nullable', 'boolean'], 't.*.stare' => ['nullable', 'in:activ,neconfirmat,inactiv'],
        ]);
        if ($data['stare'] === 'activ' && $o->status !== 'activ' && ! $request->user()->can('lenses.confirm')) {
            throw ValidationException::withMessages(['stare' => 'Doar cine are permisiunea „confirmare laborator” poate marca oferta ca activă (confirmată).']);
        }
        $d = function (?string $v, string $field) {
            if ($v === null || trim($v) === '') {
                return null;
            }
            try {
                return EyeRx::parseDiopter($v);
            } catch (\Throwable) {
                throw ValidationException::withMessages([$field => "Valoare invalidă: „{$v}”."]);
            }
        };
        $values = [
            'sph_min' => $d($data['sph_min'], 'sph_min'), 'sph_max' => $d($data['sph_max'], 'sph_max'),
            'cyl_min' => $d($data['cyl_min'], 'cyl_min'), 'cyl_max' => $d($data['cyl_max'], 'cyl_max'),
            'max_combined' => $d($data['max_combined'] ?? null, 'max_combined'), 'add_min' => $d($data['add_min'] ?? null, 'add_min'), 'add_max' => $d($data['add_max'] ?? null, 'add_max'),
            'diameter_mm' => $data['diametru'] ?? null, 'price' => Money::parse($data['pret']), 'cost' => ($data['cost'] ?? '') !== '' ? Money::parse($data['cost']) : null,
            'lead_time_days_min' => $data['zile_min'] ?? null, 'lead_time_days_max' => $data['zile_max'] ?? null,
            'status' => $data['stare'], 'requires_review' => (bool) ($data['verificare'] ?? false), 'source' => $data['sursa'] ?? $o->source,
        ];
        if ($values['sph_min'] > $values['sph_max'] || $values['cyl_min'] > $values['cyl_max'] || $values['cyl_max'] > 0) {
            throw ValidationException::withMessages(['sph_min' => 'Intervalele nu sunt coerente (minim ≤ maxim; cilindrul în notație minus, maxim 0).']);
        }
        if (! $values['price']) {
            throw ValidationException::withMessages(['pret' => 'Prețul este obligatoriu.']);
        }
        $before = $o->only(array_keys($values));
        DB::transaction(function () use ($o, $values, $data) {
            $o->update($values);
            foreach ((array) ($data['t'] ?? []) as $tid => $row) {
                $ot = LensOfferingTreatment::where('offering_id', $o->id)->findOrFail((int) $tid);
                $ot->update(['price' => Money::parse($row['pret'] ?? '0') ?? 0, 'is_included' => (bool) ($row['inclus'] ?? false), 'status' => $row['stare'] ?? $ot->status]);
            }
        });
        $diff = array_filter(array_keys($values), fn ($k) => $before[$k] != $values[$k]);
        Audit::log('lenses.offering_updated', $o, ['modificari' => array_map(fn ($k) => ['camp' => $k, 'vechi' => $before[$k], 'nou' => $values[$k]], array_values($diff))]);

        return back()->with('status', 'Oferta a fost salvată. Configuratorul folosește imediat noile valori; comenzile plasate își păstrează snapshotul.');
    }

    public function rules()
    {
        return view('lenses::panel.rules', ['rules' => CompatibilityRule::orderBy('priority')->get()]);
    }

    public function rule(?int $id = null)
    {
        return view('lenses::panel.rule', ['rule' => $id ? CompatibilityRule::findOrFail($id) : new CompatibilityRule(['effect' => 'warn', 'priority' => 100, 'is_active' => true, 'conditions' => [[['field' => 'index', 'op' => 'lt', 'value' => 1600]]]]),
            'fields' => RuleFields::all(), 'operators' => RuleFields::operators()]);
    }

    public function saveRule(Request $request, ?int $id = null)
    {
        $data = $request->validate([
            'nume' => ['required', 'string', 'max:160'], 'efect' => ['required', 'in:block,require_review,warn,require_field'],
            'camp_cerut' => ['nullable', 'required_if:efect,require_field', 'string', 'max:40'], 'conditii' => ['required', 'string', 'max:20000'],
            'mesaj_client' => ['required', 'string', 'max:500'], 'mesaj_intern' => ['nullable', 'string', 'max:500'],
            'prioritate' => ['required', 'integer', 'min:0', 'max:9999'], 'activa' => ['nullable', 'boolean'], 'sursa' => ['nullable', 'string', 'max:255'],
        ]);
        $conditions = json_decode($data['conditii'], true);
        $errors = $this->validateConditions($conditions);
        if ($errors) {
            return back()->withInput()->withErrors(['conditii' => $errors]);
        }
        $rule = $id ? CompatibilityRule::findOrFail($id) : new CompatibilityRule;
        $rule->fill([
            'name' => $data['nume'], 'effect' => $data['efect'], 'required_field' => $data['camp_cerut'] ?? null, 'conditions' => $conditions,
            'message_customer' => strip_tags($data['mesaj_client']), 'message_staff' => $data['mesaj_intern'] ?? null,
            'priority' => (int) $data['prioritate'], 'is_active' => (bool) ($data['activa'] ?? false), 'source' => $data['sursa'] ?? null, 'updated_by' => $request->user()->id,
        ])->save();
        Audit::log($id ? 'lenses.rule_updated' : 'lenses.rule_created', $rule, ['effect' => $rule->effect, 'active' => $rule->is_active]);

        return redirect()->route('panou.lenses.rules.index')->with('status', 'Regula a fost salvată.');
    }

    /** @return list<string> */
    private function validateConditions(mixed $groups): array
    {
        if (! is_array($groups) || ! $groups || ! array_is_list($groups)) {
            return ['Condițiile trebuie să fie o listă de grupuri: [[{"field":…,"op":…,"value":…}], …].'];
        }
        $fields = RuleFields::all();
        $ops = RuleFields::operators();
        $errors = [];
        foreach ($groups as $gi => $group) {
            if (! is_array($group) || ! $group || ! array_is_list($group)) {
                $errors[] = 'Grupul '.($gi + 1).' trebuie să fie o listă de condiții.';

                continue;
            }
            foreach ($group as $ci => $c) {
                $where = 'Grupul '.($gi + 1).', condiția '.($ci + 1).': ';
                if (! is_array($c) || ! isset($fields[$c['field'] ?? ''])) {
                    $errors[] = $where.'câmp necunoscut „'.($c['field'] ?? '').'”.';
                } elseif (! isset($ops[$c['op'] ?? ''])) {
                    $errors[] = $where.'operator necunoscut „'.($c['op'] ?? '').'”.';
                } elseif (! in_array($c['op'], ['empty', 'not_empty'], true) && ! array_key_exists('value', $c)) {
                    $errors[] = $where.'lipsește valoarea.';
                }
            }
        }

        return $errors;
    }

    /** Testează o regulă pe un context introdus manual (fără a o salva). */
    public function testRule(Request $request, RuleEngine $engine)
    {
        $groups = json_decode((string) $request->input('conditii'), true);
        $context = json_decode((string) $request->input('context'), true);
        if ($errors = $this->validateConditions($groups)) {
            return response()->json(['ok' => false, 'errors' => $errors]);
        }

        return response()->json(['ok' => true, 'matches' => $engine->matches($groups, is_array($context) ? $context : [])]);
    }
}
