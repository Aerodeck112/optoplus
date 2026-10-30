<?php

namespace App\Modules\B2B\Http\Controllers;

use App\Core\Audit\Audit;
use App\Http\Controllers\Controller;
use App\Modules\B2B\Models\B2bImport;
use App\Modules\B2B\Models\Complaint;
use App\Modules\B2B\Models\OrganizationDocument;
use App\Modules\B2B\Models\OrganizationMember;
use App\Modules\B2B\Models\Quote;
use App\Modules\B2B\Services\Invitations;
use App\Modules\B2B\Services\Partner;
use App\Modules\B2B\Services\QuickOrder;
use App\Modules\B2B\Services\Quotes;
use App\Modules\Configurator\Models\Configuration;
use App\Modules\Configurator\Models\Prescription;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderPlacer;
use App\Modules\Payments\Services\PaymentMethods;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Shipping\Services\ShippingCalculator;
use App\Modules\Shop\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Portalul partenerilor. Fiecare interogare este filtrată pe organizația partenerului curent (rezolvată de
 * middleware); ID-urile din URL care aparțin altei organizații dau 404, nu 403 (nu confirmăm existența lor).
 */
class PortalController extends Controller
{
    /** Rezolvat la fiecare cerere de middleware (instanța controllerului poate fi refolosită de router). */
    private function partner(): Partner
    {
        return app(Partner::class);
    }

    private function view(string $view, array $data = [])
    {
        return view('b2b::portal.'.$view, $data + [
            'partner' => $this->partner(), 'org' => $this->partner()->organization(),
            'fmt' => fn (?int $v) => \App\Core\Support\Money::format($v),
        ]);
    }

    private function orders()
    {
        return Order::where('organization_id', $this->partner()->organizationId());
    }

    public function dashboard(CartService $carts)
    {
        $cart = $carts->forOrganization($this->partner()->organizationId(), $this->partner()->customer->id);

        return $this->view('dashboard', [
            'recent' => $this->orders()->latest('id')->limit(8)->get(),
            'cartCount' => $cart->items()->count(),
            'openQuotes' => Quote::where('organization_id', $this->partner()->organizationId())->whereIn('status', ['ceruta', 'ciorna', 'trimisa'])->count(),
            'inProduction' => $this->orders()->whereNotIn('production_status', ['nu_se_aplica', 'expediata'])->where('commercial_status', '!=', 'anulata')->count(),
        ]);
    }

    public function quickOrder(QuickOrder $quick)
    {
        return $this->view('quick-order', ['offerings' => $quick->offerings(), 'rows' => max(5, count((array) old('r', [])))]);
    }

    public function quickOrderStore(Request $request, QuickOrder $quick)
    {
        $rows = array_filter((array) $request->input('r', []), fn ($r) => is_array($r) && trim(implode('', array_map('strval', array_filter($r, 'is_scalar')))) !== '');
        if (! $rows) {
            return back()->withErrors(['r' => 'Completați cel puțin un rând.']);
        }
        $checked = [];
        $errors = [];
        foreach (array_slice($rows, 0, 50, true) as $i => $row) {
            $checked[$i] = $quick->check($row, $this->partner());
            if (! $checked[$i]['ok']) {
                $errors["r.{$i}"] = 'Rândul '.((int) $i + 1).': '.implode(' ', $checked[$i]['errors']);
            }
        }
        if ($errors) {
            // Nimic nu se adaugă până nu sunt corecte toate rândurile (comanda rămâne coerentă).
            return back()->withInput()->withErrors($errors);
        }
        $added = $quick->addToCart($checked, $this->partner());

        return redirect('/portal/cos/')->with('status', "Am adăugat {$added} lucrări în coș.");
    }

    public function importForm()
    {
        return $this->view('import', ['imports' => B2bImport::where('organization_id', $this->partner()->organizationId())->latest('id')->limit(10)->get()]);
    }

    public function importTemplate()
    {
        $csv = implode(';', QuickOrder::COLUMNS)."\n".'P-0001;monofocal-standard-150;antireflex-hidrofob;;ambii;-2,00;-0,50;90;;-1,75;;;;63;;;;;1'."\n";

        return response("\u{FEFF}".$csv, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="optoplus-comanda-model.csv"']);
    }

    public function importStore(Request $request, QuickOrder $quick)
    {
        $request->validate(['fisier' => ['required', 'file', 'max:2048', 'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel']]);
        $lines = preg_split('/\r\n|\r|\n/', ltrim((string) file_get_contents($request->file('fisier')->getRealPath()), "\u{FEFF}"));
        $delimiter = substr_count($lines[0] ?? '', ';') >= substr_count($lines[0] ?? '', ',') ? ';' : ',';
        $header = array_map(fn ($h) => Str::slug(trim($h), '_'), str_getcsv(array_shift($lines) ?? '', $delimiter, '"', ''));
        $unknown = array_diff($header, QuickOrder::COLUMNS);
        if (! in_array('lentila', $header, true) || $unknown) {
            return back()->withErrors(['fisier' => 'Antetul fișierului nu corespunde modelului'.($unknown ? ' (coloane necunoscute: '.implode(', ', $unknown).')' : '').'. Descărcați modelul CSV.']);
        }
        $checked = [];
        $report = [];
        foreach (array_slice(array_values(array_filter($lines, fn ($l) => trim($l) !== '')), 0, 200) as $n => $line) {
            $row = array_combine($header, array_pad(array_slice(str_getcsv($line, $delimiter, '"', ''), 0, count($header)), count($header), ''));
            $r = $quick->check($row, $this->partner());
            $checked[] = $r;
            $report[] = ['rand' => $n + 2, 'cod_pacient' => $r['patient'], 'ok' => $r['ok'], 'erori' => $r['errors'], 'avertismente' => $r['warnings']];
        }
        $valid = count(array_filter($checked, fn ($r) => $r['ok']));
        $all = $valid === count($checked) && $valid > 0;
        $import = B2bImport::create([
            'organization_id' => $this->partner()->organizationId(), 'customer_id' => $this->partner()->customer->id,
            'original_name' => mb_substr($request->file('fisier')->getClientOriginalName(), 0, 190),
            'rows_total' => count($checked), 'rows_valid' => $valid, 'report' => $report, 'status' => $all ? 'adaugat_in_cos' : 'respins',
        ]);
        if ($all) {
            $quick->addToCart($checked, $this->partner());

            return redirect('/portal/cos/')->with('status', "Importul a reușit: {$valid} lucrări adăugate în coș.");
        }

        return redirect('/portal/import/?raport='.$import->id)->withErrors(['fisier' => 'Fișierul are rânduri cu probleme; nu am adăugat nimic în coș. Corectați rândurile marcate și încărcați din nou.']);
    }

    public function importReport(int $id)
    {
        $import = B2bImport::where('organization_id', $this->partner()->organizationId())->findOrFail($id);

        return $this->view('import', ['imports' => collect(), 'report' => $import]);
    }

    public function cart(CartService $carts)
    {
        $cart = $carts->forOrganization($this->partner()->organizationId(), $this->partner()->customer->id);
        $items = $cart->items()->with('configuration.offering.product', 'configuration.offering.material', 'configuration.frameVariant.product', 'variant.product')->get();

        return $this->view('cart', [
            'cart' => $cart, 'items' => $items,
            'quote' => $items->isNotEmpty() ? $carts->quote($cart, $this->partner()->pricing()) : null,
            'itemErrors' => $carts->validationErrors($cart),
        ]);
    }

    public function cartRemove(Request $request, CartService $carts)
    {
        $cart = $carts->forOrganization($this->partner()->organizationId(), $this->partner()->customer->id);
        $item = $cart->items()->whereKey((int) $request->input('sterge'))->firstOrFail();
        $cfg = $item->configuration;
        $item->delete();
        if ($cfg && $cfg->status !== 'in_comanda') {
            $p = $cfg->prescription;
            $cfg->delete();
            $p?->delete();
        }

        return back()->with('status', 'Lucrarea a fost eliminată din coș.');
    }

    public function checkout(Request $request, CartService $carts, ShippingCalculator $shipping, PaymentMethods $payments)
    {
        $cart = $carts->forOrganization($this->partner()->organizationId(), $this->partner()->customer->id);
        if (! $cart->items()->exists()) {
            return redirect('/portal/cos/');
        }
        $methods = $shipping->activeMethods('b2b');
        $chosen = (string) $request->query('livrare', $methods->first()?->code);
        $quote = $carts->quote($cart, $this->partner()->pricing($methods->contains('code', $chosen) ? $chosen : null));
        $key = $request->session()->get('b2b_checkout_key') ?: Str::random(40);
        $request->session()->put('b2b_checkout_key', $key);

        return $this->view('checkout', [
            'quote' => $quote, 'methods' => $methods, 'chosen' => $chosen, 'key' => $key,
            'paymentMethods' => $payments->available($quote->shipping, 'b2b', $this->partner()->organization()),
            'itemErrors' => $carts->validationErrors($cart),
        ]);
    }

    public function placeOrder(Request $request, CartService $carts, OrderPlacer $placer, PaymentService $paymentService)
    {
        $data = $request->validate([
            'livrare' => ['required', 'string', 'max:40'], 'plata' => ['required', 'string', 'max:30'],
            'adresa_livrare' => ['required', 'string', 'max:500'], 'po' => ['nullable', 'string', 'max:80'],
            'observatii' => ['nullable', 'string', 'max:1000'], 'total_afisat' => ['required', 'integer'], 'cheie' => ['required', 'string', 'size:40'],
        ], ['adresa_livrare.required' => 'Completați adresa de livrare.']);
        abort_unless(hash_equals((string) $request->session()->get('b2b_checkout_key'), $data['cheie']), 419);
        $org = $this->partner()->organization();
        $cart = $carts->forOrganization($org->id, $this->partner()->customer->id);
        $order = $placer->place($cart, [
            'email' => $this->partner()->customer->email, 'phone' => $org->contact_phone ?? $this->partner()->customer->phone,
            'billing' => ['type' => 'pj', 'company' => $org->name, 'tax_id' => $org->vat_code, 'reg_no' => $org->registry_number, 'street' => $org->billing_address, 'country' => 'RO'],
            'shipping' => ['name' => $org->name, 'street' => $data['adresa_livrare'], 'country' => 'RO'],
            'shipping_method' => $data['livrare'], 'payment_method' => $data['plata'], 'note' => $data['observatii'] ?? null, 'po_ref' => $data['po'] ?? null,
        ], $this->partner()->pricing($data['livrare']), $data['cheie'], (int) $data['total_afisat'], $request->ip());
        $request->session()->forget('b2b_checkout_key');
        $paymentService->start($order);

        return redirect('/portal/comenzi/'.$order->uuid.'/')->with('status', 'Comanda '.$order->number.' a fost plasată.');
    }

    public function ordersIndex(Request $request)
    {
        $q = $this->orders()->latest('id')->withCount('items');
        if ($term = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('number', 'like', '%'.$term.'%')->orWhere('purchase_order_ref', 'like', '%'.$term.'%')
                ->orWhereHas('items', fn ($i) => $i->where('patient_reference', $term)));
        }

        return $this->view('orders', ['orders' => $q->paginate(25)->withQueryString(), 'term' => $term]);
    }

    public function orderShow(string $uuid)
    {
        $order = $this->orders()->where('uuid', $uuid)->with('items', 'productionJobs', 'shipments', 'events')->firstOrFail();

        return $this->view('order', [
            'order' => $order,
            'stages' => \App\Modules\Production\Models\ProductionStage::where('is_active', true)->where('visible_to_customer', true)->orderBy('sort')->pluck('customer_label', 'code'),
            'complaints' => Complaint::where('order_id', $order->id)->get(),
            'documents' => OrganizationDocument::where('organization_id', $this->partner()->organizationId())->where('order_id', $order->id)->get(),
        ]);
    }

    /** Comandă din nou: copiază lucrările (cu rețeta aferentă codului de pacient) în coș, re-validate și re-prețuite. */
    public function reorder(string $uuid, CartService $carts)
    {
        $order = $this->orders()->where('uuid', $uuid)->with('items.configuration.prescription')->firstOrFail();
        $cart = $carts->forOrganization($this->partner()->organizationId(), $this->partner()->customer->id);
        DB::transaction(function () use ($order, $cart) {
            foreach ($order->items as $item) {
                if ($item->kind === 'product' && $item->variant_id) {
                    $cart->items()->create(['kind' => 'product', 'variant_id' => $item->variant_id, 'quantity' => $item->quantity]);
                } elseif ($item->configuration && $item->configuration->organization_id === $this->partner()->organizationId()) {
                    $old = $item->configuration;
                    $p = $old->prescription ? Prescription::create(collect($old->prescription->getAttributes())->only(['customer_id', 'organization_id', 'patient_reference', 'pd_mode', 'issued_on'])->all() + [
                        'values' => $old->prescription->values, 'measurements' => $old->prescription->measurements, 'source' => 'manual',
                        'confirmed_by_customer_at' => now(), 'retention_until' => now()->addYears((int) config('optoplus.retention.prescription_years', 3)),
                    ]) : null;
                    $cfg = $old->replicate(['uuid', 'status', 'share_token_hash', 'share_expires_at', 'validation', 'price_snapshot']);
                    $cfg->fill(['uuid' => (string) Str::uuid(), 'status' => 'valid', 'prescription_id' => $p?->id, 'customer_id' => $this->partner()->customer->id, 'version' => 1])->save();
                    $cart->items()->create(['kind' => 'configuration', 'configuration_id' => $cfg->id, 'quantity' => $item->quantity, 'patient_reference' => $item->patient_reference]);
                }
            }
        });
        Audit::log('b2b.reorder', $order, [], $this->partner()->customer->id);

        return redirect('/portal/cos/')->with('status', 'Lucrările din comanda '.$order->number.' au fost copiate în coș. Verificați-le înainte de trimitere.');
    }

    public function complaint(Request $request, string $uuid)
    {
        $order = $this->orders()->where('uuid', $uuid)->firstOrFail();
        $data = $request->validate([
            'linie' => ['nullable', 'integer'], 'motiv' => ['required', 'in:'.implode(',', array_keys(Complaint::REASONS))],
            'descriere' => ['required', 'string', 'max:3000'], 'tip' => ['required', 'in:reclamatie,refacere,retur'],
        ]);
        $itemId = $data['linie'] ? $order->items()->whereKey($data['linie'])->value('id') : null;
        $c = Complaint::create([
            'number' => 'RC-'.now()->format('Y').'-'.Str::upper(Str::random(6)), 'order_id' => $order->id, 'order_item_id' => $itemId,
            'customer_id' => $this->partner()->customer->id, 'organization_id' => $this->partner()->organizationId(),
            'kind' => $data['tip'], 'reason' => $data['motiv'], 'description' => $data['descriere'],
        ]);
        Audit::log('b2b.complaint', $c, [], $this->partner()->customer->id);

        return back()->with('status', 'Am înregistrat sesizarea '.$c->number.'. Vă contactăm după analiză.');
    }

    public function quotesIndex()
    {
        return $this->view('quotes', ['quotes' => Quote::where('organization_id', $this->partner()->organizationId())->with('currentVersion')->latest('id')->paginate(25)]);
    }

    public function quoteRequest(Request $request, Quotes $quotes)
    {
        $data = $request->validate(['cerere' => ['required', 'string', 'min:10', 'max:5000']], ['cerere.min' => 'Descrieți pe scurt ce doriți să vă ofertăm.']);
        $quote = $quotes->request($this->partner(), $data['cerere']);

        return redirect('/portal/oferte/'.$quote->id.'/')->with('status', 'Cererea de ofertă '.$quote->number.' a fost trimisă.');
    }

    public function quoteShow(Request $request, int $id, PaymentMethods $payments)
    {
        $quote = Quote::where('organization_id', $this->partner()->organizationId())->with('currentVersion', 'order')->findOrFail($id);
        $key = $request->session()->get('quote_key_'.$id) ?: Str::random(40);
        $request->session()->put('quote_key_'.$id, $key);

        return $this->view('quote', ['quote' => $quote, 'version' => $quote->status === 'ceruta' ? null : $quote->currentVersion, 'key' => $key,
            'canCredit' => $this->partner()->organization()->hasApprovedCredit(), 'bank' => $payments->bankDetails()]);
    }

    public function quoteAccept(Request $request, int $id, Quotes $quotes, PaymentService $paymentService)
    {
        $quote = Quote::where('organization_id', $this->partner()->organizationId())->findOrFail($id);
        $data = $request->validate(['plata' => ['required', 'in:transfer_bancar,termen'], 'po' => ['nullable', 'string', 'max:80'], 'cheie' => ['required', 'string', 'size:40'], 'accept' => ['accepted']]);
        abort_unless(hash_equals((string) $request->session()->get('quote_key_'.$id), $data['cheie']), 419);
        $order = $quotes->accept($quote, $this->partner(), $data['plata'], $data['po'] ?? null, $data['cheie']);
        $paymentService->start($order);

        return redirect('/portal/comenzi/'.$order->uuid.'/')->with('status', 'Oferta a fost acceptată; am creat comanda '.$order->number.'.');
    }

    public function documents()
    {
        return $this->view('documents', ['documents' => OrganizationDocument::where('organization_id', $this->partner()->organizationId())->latest('id')->paginate(30)]);
    }

    public function documentDownload(string $uuid)
    {
        $doc = OrganizationDocument::where('organization_id', $this->partner()->organizationId())->where('uuid', $uuid)->firstOrFail();
        Audit::log('b2b.document_downloaded', $doc, [], $this->partner()->customer->id);

        return response(Storage::disk(OrganizationDocument::DISK)->get($doc->disk_path), 200, [
            'Content-Type' => $doc->mime, 'Content-Disposition' => 'attachment; filename="'.Str::slug($doc->title).'.'.(['application/pdf' => 'pdf'][$doc->mime] ?? 'bin').'"',
            'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function users()
    {
        return $this->view('users', [
            'members' => OrganizationMember::where('organization_id', $this->partner()->organizationId())->with('customer')->get(),
            'invitations' => \App\Modules\B2B\Models\OrganizationInvitation::where('organization_id', $this->partner()->organizationId())->whereNull('accepted_at')->where('expires_at', '>', now())->get(),
        ]);
    }

    public function invite(Request $request, Invitations $invitations)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:190'], 'rol' => ['required', 'in:'.implode(',', array_keys(OrganizationMember::ROLES))]]);
        $invitations->invite($this->partner()->organization(), $data['email'], $data['rol'], $this->partner()->customer->id);

        return back()->with('status', 'Invitația a fost trimisă.');
    }

    public function updateMember(Request $request, int $id)
    {
        $member = OrganizationMember::where('organization_id', $this->partner()->organizationId())->findOrFail($id);
        if ($member->customer_id === $this->partner()->customer->id) {
            return back()->withErrors(['rol' => 'Nu vă puteți modifica propriul rol.']);
        }
        if ($request->input('actiune') === 'elimina') {
            $member->delete();
            Audit::log('b2b.member_removed', null, ['organization' => $member->organization_id, 'customer' => $member->customer_id], $this->partner()->customer->id);

            return back()->with('status', 'Utilizatorul a fost eliminat din organizație.');
        }
        $role = $request->validate(['rol' => ['required', 'in:'.implode(',', array_keys(OrganizationMember::ROLES))]])['rol'];
        $member->update(['role' => $role]);
        Audit::log('b2b.member_role', $member, ['role' => $role], $this->partner()->customer->id);

        return back()->with('status', 'Rolul a fost actualizat.');
    }
}
