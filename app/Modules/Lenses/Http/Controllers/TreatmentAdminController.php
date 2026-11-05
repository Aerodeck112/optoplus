<?php

namespace App\Modules\Lenses\Http\Controllers;

use App\Core\Audit\Audit;
use App\Http\Controllers\Controller;
use App\Modules\Lenses\Models\LensOfferingTreatment;
use App\Modules\Lenses\Models\LensTreatment;
use App\Modules\Lenses\Models\LensTreatmentOption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Lentile → Tratamente: numele și textele afișate clientului („Ce face” / „Limite”), activarea, nuanțele
 * (ex. fotocromatic Maro 85%). Prețul și disponibilitatea pe fiecare lentilă se stabilesc în pagina lentilei.
 * Comenzile deja plasate păstrează snapshotul; modificările se auditează.
 */
class TreatmentAdminController extends Controller
{
    /** Tipurile pentru care avem explicație ilustrată în configurator. */
    public const KINDS = ['antireflex' => 'Antireflex', 'lumina_albastra' => 'Filtru lumină albastră', 'fotocromatic' => 'Fotocromatic', 'altul' => 'Alt tratament'];

    public function index()
    {
        $treatments = LensTreatment::with('options')->withCount(['offerings as offerings_count'])->orderBy('sort')->orderBy('id')->get();

        return view('lenses::panel.treatments', ['treatments' => $treatments, 'kinds' => self::KINDS]);
    }

    public function edit(?int $id = null)
    {
        $t = $id ? LensTreatment::with('options')->findOrFail($id) : new LensTreatment(['is_active' => false, 'kind' => 'altul', 'sort' => (int) LensTreatment::max('sort') + 1]);

        return view('lenses::panel.treatment', ['t' => $t, 'kinds' => self::KINDS,
            'usedIn' => $id ? LensOfferingTreatment::where('treatment_id', $id)->with('offering.product', 'offering.material')->get() : collect()]);
    }

    public function save(Request $request, ?int $id = null)
    {
        $t = $id ? LensTreatment::with('options')->findOrFail($id) : new LensTreatment;
        $data = $request->validate([
            'nume' => ['required', 'string', 'max:120'],
            'tip' => [$id ? 'nullable' : 'required', Rule::in(array_keys(self::KINDS))],
            'ce_face' => ['required', 'string', 'max:2000'],
            'limite' => ['required', 'string', 'max:2000'],
            'activ' => ['nullable', 'boolean'],
            'cu_nuanta' => ['nullable', 'boolean'],
            'ordine' => ['nullable', 'integer', 'min:0', 'max:999'],
            'nuante' => ['array'], 'nuante.*.eticheta' => ['nullable', 'string', 'max:60'],
            'nuante.*.culoare' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'], 'nuante.*.activ' => ['nullable', 'boolean'],
            'nuanta_noua.eticheta' => ['nullable', 'string', 'max:60'], 'nuanta_noua.culoare' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], ['nuante.*.culoare.regex' => 'Culoarea se scrie ca #RRGGBB (ex. #45494c).', 'nuanta_noua.culoare.regex' => 'Culoarea se scrie ca #RRGGBB (ex. #45494c).']);
        $clean = fn (string $v) => trim(strip_tags($v));

        DB::transaction(function () use ($t, $data, $clean, $id) {
            $before = $t->exists ? $t->only(['name', 'benefits', 'limits', 'is_active', 'requires_option', 'sort']) : [];
            $t->fill([
                'name' => $clean($data['nume']), 'benefits' => $clean($data['ce_face']), 'limits' => $clean($data['limite']),
                'is_active' => (bool) ($data['activ'] ?? false), 'requires_option' => (bool) ($data['cu_nuanta'] ?? false),
                'sort' => (int) ($data['ordine'] ?? $t->sort ?? 0),
            ]);
            if (! $t->exists) {
                $t->kind = $data['tip'];
                $t->code = $this->uniqueCode($data['nume']);
                $t->is_confirmed = false;
            }
            $t->save();
            foreach ((array) ($data['nuante'] ?? []) as $optId => $row) {
                $op = LensTreatmentOption::where('treatment_id', $t->id)->findOrFail((int) $optId);
                $op->update(['label' => $clean((string) ($row['eticheta'] ?? $op->label)) ?: $op->label, 'swatch' => $row['culoare'] ?? $op->swatch, 'is_active' => (bool) ($row['activ'] ?? false)]);
            }
            if ($label = $clean((string) ($data['nuanta_noua']['eticheta'] ?? ''))) {
                $code = Str::of($label)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->limit(40, '')->toString() ?: 'nuanta';
                if ($t->options()->where('code', $code)->exists()) {
                    throw ValidationException::withMessages(['nuanta_noua.eticheta' => "Nuanța „{$label}” există deja."]);
                }
                $t->options()->create(['code' => $code, 'label' => $label, 'swatch' => $data['nuanta_noua']['culoare'] ?? null, 'is_active' => true, 'sort' => (int) $t->options()->max('sort') + 1]);
            }
            Audit::log($id ? 'lenses.treatment_updated' : 'lenses.treatment_created', $t, ['inainte' => $before, 'dupa' => $t->only(['name', 'is_active', 'requires_option', 'sort'])]);
        });
        if ($t->requires_option && ! $t->options()->where('is_active', true)->exists()) {
            return redirect()->route('panou.lenses.treatments.edit', $t->id)->with('warning', 'Tratamentul cere alegerea unei nuanțe, dar nu are nicio nuanță activă: clienții nu îl vor putea comanda. Adăugați sau activați o nuanță.');
        }

        return redirect()->route('panou.lenses.treatments.edit', $t->id)->with('status', $id
            ? 'Tratamentul a fost salvat. Configuratorul folosește imediat noile texte; comenzile plasate își păstrează snapshotul.'
            : 'Tratamentul a fost creat. Adăugați-l la lentilele potrivite (Lentile și matrice → lentila → „Adaugă un tratament”), cu prețul lui.');
    }

    private function uniqueCode(string $name): string
    {
        $base = Str::slug($name) ?: 'tratament';
        $code = $base;
        for ($i = 2; LensTreatment::where('code', $code)->exists(); $i++) {
            $code = $base.'-'.$i;
        }

        return $code;
    }
}
