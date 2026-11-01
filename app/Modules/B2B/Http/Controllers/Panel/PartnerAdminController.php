<?php

namespace App\Modules\B2B\Http\Controllers\Panel;

use App\Core\Audit\Audit;
use App\Core\Support\Money;
use App\Http\Controllers\Controller;
use App\Modules\B2B\Models\Complaint;
use App\Modules\B2B\Models\Organization;
use App\Modules\B2B\Models\OrganizationDocument;
use App\Modules\B2B\Models\PartnershipRequest;
use App\Modules\B2B\Models\Quote;
use App\Modules\B2B\Models\QuoteVersion;
use App\Modules\B2B\Services\Invitations;
use App\Modules\Orders\Models\Order;
use App\Modules\Pricing\Models\PriceList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Administrarea partenerilor B2B în panou. Aprobările și condițiile comerciale sunt auditate. */
class PartnerAdminController extends Controller
{
    public function requests(Request $request)
    {
        $status = $request->query('stare', 'noua');

        return view('b2b::panel.requests', ['requests' => PartnershipRequest::when($status !== 'toate', fn ($q) => $q->where('status', $status))->latest('id')->paginate(30)->withQueryString(), 'status' => $status]);
    }

    public function approveRequest(Request $request, int $id, Invitations $invitations)
    {
        $req = PartnershipRequest::whereIn('status', ['noua', 'in_analiza'])->findOrFail($id);
        $data = $request->validate(['tip' => ['required', 'in:'.implode(',', array_keys(Organization::KINDS))], 'lista' => ['nullable', 'integer'], 'cu_tva' => ['nullable', 'boolean']]);
        $org = DB::transaction(function () use ($req, $data, $request) {
            $org = Organization::create([
                'name' => $req->company_name, 'kind' => $data['tip'], 'vat_code' => $req->vat_code, 'status' => Organization::STATUS_APPROVED,
                'contact_email' => $req->email, 'contact_phone' => $req->phone, 'billing_address' => $req->city,
                'price_list_id' => $data['lista'] ?? null, 'show_prices_with_vat' => (bool) ($data['cu_tva'] ?? false),
                'approved_by' => $request->user()->id, 'approved_at' => now(),
            ]);
            $req->update(['status' => 'aprobata', 'organization_id' => $org->id, 'handled_by' => $request->user()->id, 'handled_at' => now()]);
            Audit::log('b2b.request_approved', $org, ['request' => $req->id]);

            return $org;
        });
        $invitations->invite($org, $req->email, 'admin_organizatie', null, $request->user()->id);

        return redirect()->route('panou.b2b.org', $org->id)->with('status', 'Partenerul a fost aprobat; invitația a fost trimisă la '.$req->email.'.');
    }

    public function rejectRequest(Request $request, int $id)
    {
        $req = PartnershipRequest::whereIn('status', ['noua', 'in_analiza'])->findOrFail($id);
        $data = $request->validate(['motiv' => ['required', 'string', 'max:500']]);
        $req->update(['status' => 'respinsa', 'decision_reason' => $data['motiv'], 'handled_by' => $request->user()->id, 'handled_at' => now()]);
        Audit::log('b2b.request_rejected', $req);

        return back()->with('status', 'Cererea a fost respinsă. Anunțați solicitantul (răspunsul nu se trimite automat).');
    }

    public function organizations(Request $request)
    {
        $q = Organization::query()->withCount('members');
        if ($term = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('name', 'like', '%'.addcslashes($term, '%_').'%')->orWhere('vat_code', $term));
        }

        return view('b2b::panel.organizations', ['orgs' => $q->orderBy('name')->paginate(40)->withQueryString(), 'term' => $term]);
    }

    public function organization(int $id)
    {
        $org = Organization::with('members.customer', 'priceList')->findOrFail($id);

        return view('b2b::panel.organization', [
            'org' => $org, 'priceLists' => PriceList::where('channel', 'b2b')->where('is_active', true)->pluck('name', 'id'),
            'orders' => Order::where('organization_id', $org->id)->latest('id')->limit(15)->get(),
            'documents' => OrganizationDocument::where('organization_id', $org->id)->latest('id')->get(),
            'openBalance' => (int) Order::where('organization_id', $org->id)->where('payment_method', 'termen')->where('commercial_status', '!=', 'anulata')->whereNotIn('payment_status', ['platita', 'rambursata'])->sum(DB::raw('total - paid_total')),
        ]);
    }

    public function updateOrganization(Request $request, int $id)
    {
        $org = Organization::findOrFail($id);
        $data = $request->validate([
            'nume' => ['required', 'string', 'max:190'], 'tip' => ['required', 'in:'.implode(',', array_keys(Organization::KINDS))],
            'cui' => ['nullable', 'string', 'max:30'], 'reg_com' => ['nullable', 'string', 'max:40'], 'adresa' => ['nullable', 'string', 'max:500'],
            'telefon' => ['nullable', 'string', 'max:40'], 'email' => ['nullable', 'email', 'max:190'],
            'lista' => ['nullable', 'integer', 'exists:price_lists,id'], 'cu_tva' => ['nullable', 'boolean'],
            'stare' => ['required', 'in:aprobata,suspendata'], 'note' => ['nullable', 'string', 'max:5000'],
        ]);
        $org->update([
            'name' => $data['nume'], 'kind' => $data['tip'], 'vat_code' => $data['cui'] ?? null, 'registry_number' => $data['reg_com'] ?? null,
            'billing_address' => $data['adresa'] ?? null, 'contact_phone' => $data['telefon'] ?? null, 'contact_email' => $data['email'] ?? null,
            'price_list_id' => $data['lista'] ?? null, 'show_prices_with_vat' => (bool) ($data['cu_tva'] ?? false), 'status' => $data['stare'], 'internal_notes' => $data['note'] ?? null,
        ]);
        Audit::log('b2b.organization_updated', $org, ['campuri' => array_keys($org->getChanges())]);

        return back()->with('status', 'Datele partenerului au fost salvate.');
    }

    /** Termenele de plată și limita de credit: decizie comercială explicită, auditată, cu reautentificare. */
    public function terms(Request $request, int $id)
    {
        $org = Organization::findOrFail($id);
        $data = $request->validate(['zile' => ['nullable', 'integer', 'min:1', 'max:120'], 'limita' => ['nullable', 'string'], 'retrage' => ['nullable', 'boolean']]);
        if (! empty($data['retrage'])) {
            $org->update(['payment_terms_days' => null, 'credit_limit' => null, 'terms_approved_by' => null, 'terms_approved_at' => null]);
            Audit::log('b2b.terms_revoked', $org);

            return back()->with('status', 'Plata la termen a fost retrasă.');
        }
        abort_unless($data['zile'] ?? null, 422, 'Completați numărul de zile.');
        $limit = isset($data['limita']) && $data['limita'] !== '' ? Money::parse($data['limita']) : null;
        $org->update(['payment_terms_days' => (int) $data['zile'], 'credit_limit' => $limit, 'terms_approved_by' => $request->user()->id, 'terms_approved_at' => now()]);
        Audit::log('b2b.terms_approved', $org, ['days' => (int) $data['zile'], 'limit' => $limit]);

        return back()->with('status', 'Plata la termen a fost aprobată.');
    }

    public function inviteMember(Request $request, int $id, Invitations $invitations)
    {
        $org = Organization::findOrFail($id);
        $data = $request->validate(['email' => ['required', 'email'], 'rol' => ['required', 'string']]);
        $invitations->invite($org, $data['email'], $data['rol'], null, $request->user()->id);

        return back()->with('status', 'Invitația a fost trimisă.');
    }

    public function uploadDocument(Request $request, int $id)
    {
        $org = Organization::findOrFail($id);
        $data = $request->validate([
            'titlu' => ['required', 'string', 'max:190'], 'tip' => ['required', 'in:'.implode(',', array_keys(OrganizationDocument::KINDS))],
            'fisier' => ['required', 'file', 'max:15360', 'mimes:pdf'], 'comanda' => ['nullable', 'string'],
        ]);
        $file = $request->file('fisier');
        $bytes = (string) file_get_contents($file->getRealPath());
        abort_unless(str_starts_with($bytes, '%PDF-'), 422, 'Fișierul nu este un PDF valid.');
        $path = Str::random(48).'.bin';
        Storage::disk(OrganizationDocument::DISK)->put($path, $bytes);
        $orderId = $data['comanda'] ? Order::where('organization_id', $org->id)->where('number', $data['comanda'])->value('id') : null;
        $doc = OrganizationDocument::create(['organization_id' => $org->id, 'uuid' => (string) Str::uuid(), 'title' => $data['titlu'], 'kind' => $data['tip'], 'disk_path' => $path, 'mime' => 'application/pdf', 'size' => strlen($bytes), 'order_id' => $orderId, 'uploaded_by' => $request->user()->id]);
        Audit::log('b2b.document_uploaded', $doc, ['organization' => $org->id]);

        return back()->with('status', 'Documentul a fost încărcat.');
    }

    public function quotes(Request $request)
    {
        $status = $request->query('stare');

        return view('b2b::panel.quotes', ['quotes' => Quote::with('organization:id,name', 'currentVersion')->when($status, fn ($q) => $q->where('status', $status))->latest('id')->paginate(40)->withQueryString(), 'status' => $status]);
    }

    public function quote(int $id)
    {
        return view('b2b::panel.quote', ['quote' => Quote::with('organization', 'customer', 'versions', 'order')->findOrFail($id)]);
    }

    /** O versiune nouă de ofertă; liniile se introduc fără TVA sau cu TVA, după opțiune. Sumele se calculează aici. */
    public function storeVersion(Request $request, int $id)
    {
        $quote = Quote::whereIn('status', ['ceruta', 'ciorna', 'trimisa'])->findOrFail($id);
        $data = $request->validate([
            'linii' => ['required', 'array', 'min:1'], 'linii.*.descriere' => ['nullable', 'string', 'max:255'],
            'linii.*.cantitate' => ['nullable', 'integer', 'min:1', 'max:10000'], 'linii.*.pret' => ['nullable', 'string'], 'linii.*.tva' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'cu_tva' => ['nullable', 'boolean'], 'valabila' => ['required', 'date', 'after:today'], 'conditii' => ['nullable', 'string', 'max:3000'], 'mesaj' => ['nullable', 'string', 'max:3000'],
        ]);
        $withVat = (bool) ($data['cu_tva'] ?? false);
        $items = [];
        $subtotal = $vat = 0;
        foreach ($data['linii'] as $l) {
            if (empty($l['descriere']) || empty($l['cantitate']) || ($price = Money::parse($l['pret'] ?? '')) === null) {
                continue;
            }
            $bp = (int) ($l['tva'] ?? 2100);
            $line = $price * (int) $l['cantitate'];
            $lineVat = $withVat ? Money::vatIncluded($line, $bp) : Money::percent($line, $bp);
            $items[] = ['kind' => 'service', 'description' => $l['descriere'], 'quantity' => (int) $l['cantitate'], 'unit_price' => $price, 'line_total' => $line, 'vat_bp' => $bp, 'vat_amount' => $lineVat];
            $subtotal += $line;
            $vat += $lineVat;
        }
        if (! $items) {
            return back()->withInput()->withErrors(['linii' => 'Completați cel puțin o linie (descriere, cantitate, preț).']);
        }
        $version = QuoteVersion::create([
            'quote_id' => $quote->id, 'number' => (int) $quote->versions()->max('number') + 1, 'items' => $items,
            'subtotal' => $subtotal, 'vat_total' => $vat, 'total' => $withVat ? $subtotal : $subtotal + $vat, 'prices_include_vat' => $withVat,
            'valid_until' => $data['valabila'], 'terms' => $data['conditii'] ?? null, 'note_to_customer' => $data['mesaj'] ?? null,
            'created_by' => $request->user()->id, 'created_at' => now(),
        ]);
        $quote->update(['current_version_id' => $version->id, 'status' => 'ciorna', 'owner_id' => $quote->owner_id ?? $request->user()->id]);
        Audit::log('b2b.quote_version', $quote, ['version' => $version->number, 'total' => $version->total]);

        return back()->with('status', 'Versiunea '.$version->number.' a fost salvată. Verificați-o și trimiteți-o.');
    }

    public function sendQuote(int $id)
    {
        $quote = Quote::with('currentVersion', 'customer')->where('status', 'ciorna')->findOrFail($id);
        abort_unless($quote->currentVersion, 422);
        $quote->currentVersion->update(['sent_at' => now()]);
        $quote->update(['status' => 'trimisa']);
        $to = $quote->customer?->email ?? $quote->organization?->contact_email;
        if ($to) {
            \App\Modules\Orders\Services\OrderNotifier::safely(fn () => Notification::route('mail', $to)->notify(new \App\Modules\B2B\Notifications\QuoteSent($quote->number, url('/portal/oferte/'.$quote->id.'/'))), 0, 'oferta');
        }
        Audit::log('b2b.quote_sent', $quote);

        return back()->with('status', 'Oferta a fost trimisă partenerului.');
    }

    public function complaints(Request $request)
    {
        $status = $request->query('stare', 'noua');

        return view('b2b::panel.complaints', ['complaints' => Complaint::with('order:id,uuid,number', 'item:id,name,patient_reference')->when($status !== 'toate', fn ($q) => $q->where('status', $status))->latest('id')->paginate(40)->withQueryString(), 'status' => $status]);
    }

    public function updateComplaint(Request $request, int $id)
    {
        $c = Complaint::findOrFail($id);
        $data = $request->validate(['stare' => ['required', 'in:'.implode(',', array_keys(Complaint::STATUSES))], 'rezolutie' => ['nullable', 'string', 'max:3000']]);
        $c->update(['status' => $data['stare'], 'resolution' => $data['rezolutie'] ?? $c->resolution, 'handled_by' => $request->user()->id]);
        Audit::log('b2b.complaint_updated', $c, ['status' => $data['stare']]);

        return back()->with('status', 'Sesizarea a fost actualizată.');
    }
}
