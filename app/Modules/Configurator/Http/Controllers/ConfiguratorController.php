<?php

namespace App\Modules\Configurator\Http\Controllers;

use App\Core\Audit\Audit;
use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Configurator\Models\Configuration;
use App\Modules\Configurator\Services\ConfigurationService;
use App\Modules\Configurator\Services\LensOptions;
use App\Modules\Configurator\Services\PrescriptionStore;
use App\Modules\Configurator\Support\ConfigurationInput;
use App\Modules\Configurator\Support\EyeRx;
use App\Modules\Crm\Models\Lead;
use App\Modules\Customers\Models\Consent;
use App\Modules\Lenses\Models\LensOffering;
use App\Modules\Shop\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Configuratorul ghidat: 4 intrări, 5 pași, salvare pe server la fiecare pas, prețul și validarea calculate
 * numai pe server. JavaScript-ul (opțional) cere recalculări fără a salva; fără JavaScript totul funcționează.
 */
class ConfiguratorController extends Controller
{
    public function __construct(private ConfigurationService $service) {}

    public function entry(Request $request)
    {
        return response()->view('configurator::entry', [
            'ownFrame' => (bool) config('optoplus.configurator.own_frame_service', false),
            'recent' => $this->recent($request),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function start(Request $request)
    {
        $entry = (string) $request->input('intrare', 'cu_rama');
        abort_unless(in_array($entry, ConfigurationInput::ENTRIES, true), 422);
        if ($entry === 'ajutor') {
            return redirect('/configurator/ajutor/');
        }
        if ($entry === 'rama_mea' && ! config('optoplus.configurator.own_frame_service', false)) {
            return redirect('/configurator/')->with('status', 'Serviciul „lentile pentru rama mea” nu este disponibil momentan. Sunați-ne și vă ajutăm.');
        }
        $variantId = null;
        if ($entry === 'cu_rama') {
            $variant = ProductVariant::with('product')->find((int) $request->input('varianta'));
            if (! $variant || ! $variant->is_active || $variant->product->status !== Product::STATUS_PUBLISHED || ! $variant->product->configurable_with_lenses) {
                return redirect('/rame/')->with('status', 'Alegeți întâi rama pentru care doriți lentile.');
            }
            $variantId = $variant->id;
        }
        $cfg = $this->service->create($request, $entry, $variantId);

        // Din pagina unei lentile: pornim cu familia potrivită, dar clientul alege tot el oferta la pasul 3.
        if ($entry === 'fara_rama' && ($lensId = (int) $request->input('lentila'))) {
            $request->session()->put('cfg_lens_hint.'.$cfg->uuid, $lensId);
        }

        return redirect($cfg->stepUrl(1));
    }

    public function step(Request $request, string $uuid, int $step, LensOptions $lensOptions)
    {
        $cfg = $this->load($request, $uuid);
        $step = max(1, min(5, $step));
        if ($step > $cfg->step) {
            return redirect($cfg->stepUrl($cfg->step));
        }
        $input = $this->service->input($cfg, Auth::guard('client')->user());
        $quote = $this->service->quote($cfg, $input);
        $validation = $this->service->validate($cfg, $step === 5, $input);

        $cfg->loadMissing('customer');
        $data = [
            'cfg' => $cfg, 'step' => $step, 'input' => $input, 'quote' => $quote, 'validation' => $validation,
            'frame' => $cfg->frameVariant?->load('product.images.media', 'product.brand'),
            'offering' => $cfg->offering?->load('product', 'material', 'lensProduct.family', 'treatments.treatment.options'),
            'prescription' => $cfg->prescription,
        ];
        if ($step === 3) {
            $data['lensOptions'] = $lensOptions->forInput($input, $this->service->context());
            $data['lensHint'] = $request->session()->get('cfg_lens_hint.'.$cfg->uuid);
        }
        if ($step === 2 && $cfg->prescription) {
            Audit::log('prescription.viewed', $cfg->prescription, ['context' => 'configurator'], $cfg->prescription->customer_id);
        }

        return response()->view('configurator::step'.$step, $data)->header('Cache-Control', 'no-store, private');
    }

    public function save(Request $request, string $uuid, int $step, PrescriptionStore $store)
    {
        $cfg = $this->load($request, $uuid);
        if ($step === 2 && $request->hasFile('reteta')) {
            if (! $request->boolean('acord_reteta')) {
                return back()->withInput()->withErrors(['reteta' => 'Pentru a încărca rețeta, bifați acordul pentru prelucrarea ei.']);
            }
            $p = $this->service->prescriptionFor($cfg);
            $store->store($p, $request->file('reteta'));
            Consent::record($cfg->customer_id, 'prelucrare_reteta', true, 'configurator', $cfg->guest_key);
        }
        if ($step === 2 && $request->input('actiune') === 'ajutor') {
            return $this->sendToSpecialist($request, $cfg);
        }
        $this->service->saveStep($cfg, $step, $request->all());
        if ($request->input('actiune') === 'inapoi') {
            return redirect($cfg->stepUrl(max(1, $step - 1)));
        }

        return redirect($cfg->stepUrl(min(5, $step + 1)));
    }

    /** Recalcul fără salvare (JavaScript): prețul și mesajele pentru alegerile din formular. */
    public function calc(Request $request, string $uuid): JsonResponse
    {
        $cfg = $this->load($request, $uuid);
        $input = $this->service->input($cfg);
        $step = (int) $request->input('pas', 0);
        if ($step === 3 && is_numeric($request->input('oferta'))) {
            $input = $input->with(['offeringId' => (int) $request->input('oferta'), 'treatments' => []]);
        }
        if ($step === 4) {
            $treatments = [];
            foreach ((array) $request->input('tratamente', []) as $id) {
                if (is_numeric($id)) {
                    $opt = $request->input('optiune.'.$id);
                    $treatments[] = ['treatment_id' => (int) $id, 'option' => is_string($opt) ? $opt : null];
                }
            }
            $input = $input->with(['treatments' => $treatments]);
        }
        $quote = $this->service->quote($cfg, $input);
        $result = $this->service->validate($cfg, false, $input);

        return response()->json([
            'summary' => view('configurator::partials.summary', ['quote' => $quote, 'cfg' => $cfg, 'validation' => $result])->render(),
            'total' => \App\Core\Support\Money::format($quote->totalGross),
            'errors' => array_column(array_filter($result->errors, fn ($e) => str_starts_with($e['code'], 'treatment') || str_starts_with($e['code'], 'rule_')), 'message'),
            'warnings' => array_column($result->warnings, 'message'),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function addToCart(Request $request, string $uuid, CartService $cart)
    {
        $cfg = $this->load($request, $uuid);
        $request->validate(['acord_reteta' => ['accepted']], ['acord_reteta.accepted' => 'Pentru a comanda lentile după rețetă avem nevoie de acordul dumneavoastră pentru prelucrarea ei.']);
        $result = $this->service->validate($cfg, true);
        if (! $result->isValid()) {
            return redirect($cfg->stepUrl(5))->withErrors(collect($result->errors)->pluck('message')->all());
        }
        $quote = $this->service->quote($cfg);
        if (! $quote->isValid()) {
            return redirect($cfg->stepUrl(5))->withErrors($quote->errors ?: ['Prețul nu a putut fi calculat. Reîncercați.']);
        }
        DB::transaction(function () use ($cfg, $result, $quote, $request, $cart) {
            Consent::record($cfg->customer_id, 'prelucrare_reteta', true, 'configurator', $cfg->guest_key);
            $cfg->update(['status' => $result->requiresReview() ? 'necesita_verificare' : 'valid', 'validation' => $result->toArray(), 'price_snapshot' => $quote->toArray()]);
            $cart->addConfiguration($request, $cfg);
        });

        return redirect('/cos/')->with('status', 'Ochelarii configurați au fost adăugați în coș.')->withCookie($cart->cookie($request));
    }

    public function share(Request $request, string $uuid)
    {
        $cfg = $this->load($request, $uuid);
        $link = $this->service->shareLink($cfg);
        Audit::log('configuration.share_link', $cfg);

        return back()->with('share_link', $link);
    }

    public function resume(Request $request, string $token)
    {
        $cfg = $this->service->findByToken($token);
        if (! $cfg) {
            return response()->view('configurator::expired', [], 404);
        }
        if ($cfg->customer_id !== null && Auth::guard('client')->id() !== $cfg->customer_id) {
            $request->session()->put('url.intended', '/c/'.$token.'/');

            return redirect('/cont/autentificare/')->with('status', 'Configurația este salvată în contul dumneavoastră. Autentificați-vă pentru a continua.');
        }
        $this->service->remember($request, $cfg);

        return redirect($cfg->stepUrl($cfg->step));
    }

    public function help()
    {
        return response()->view('configurator::help')->header('Cache-Control', 'no-store, private');
    }

    /** „Nu înțeleg rețeta”: configurația și fișierul ajung la specialist, care contactează clientul. */
    private function sendToSpecialist(Request $request, Configuration $cfg)
    {
        $data = $request->validate([
            'nume' => ['required', 'string', 'max:120'],
            'telefon' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:190'],
        ], ['nume.required' => 'Lăsați-ne numele, ca specialistul să vă poată suna.', 'telefon.required' => 'Lăsați-ne un număr de telefon.']);
        $p = $this->service->prescriptionFor($cfg);
        $p->update(['customer_unsure' => true]);
        $cfg->update(['status' => 'necesita_verificare']);
        Lead::firstOrCreate(['configuration_id' => $cfg->id, 'source' => 'configurator'], [
            'name' => $data['nume'], 'phone' => $data['telefon'], 'email' => $data['email'] ?? null,
            'summary' => 'Clientul nu înțelege rețeta'.($p->files()->exists() ? ' (fișier încărcat)' : ''),
            'customer_id' => $cfg->customer_id,
        ]);

        return redirect($cfg->stepUrl(2))->with('status', 'Am primit cererea. Un specialist vă contactează; configurația rămâne salvată.');
    }

    private function load(Request $request, string $uuid): Configuration
    {
        abort_unless(preg_match('/^[0-9a-f-]{36}$/', $uuid), 404);
        $cfg = Configuration::where('uuid', $uuid)->with('prescription')->first();
        abort_unless($cfg && $this->service->canAccess($request, $cfg), 404);
        abort_if($cfg->status === 'in_comanda', 410, 'Această configurație a fost deja comandată.');

        return $cfg;
    }

    private function recent(Request $request)
    {
        $customer = Auth::guard('client')->user();
        $q = Configuration::query()->where('status', '!=', 'in_comanda')->latest('updated_at')->limit(3);
        if ($customer) {
            $q->where('customer_id', $customer->id);
        } else {
            $q->whereIn('uuid', (array) $request->session()->get('configuratii', []))->whereNull('customer_id');
        }

        return $q->with('frameVariant.product')->get();
    }
}
