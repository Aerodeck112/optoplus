<?php

namespace App\Modules\Storefront\Http\Controllers;

use App\Core\Audit\Audit;
use App\Http\Controllers\Controller;
use App\Modules\B2B\Models\PartnershipRequest;
use App\Modules\Cms\Models\FormSubmission;
use App\Modules\Configurator\Models\Prescription;
use App\Modules\Configurator\Services\PrescriptionStore;
use App\Modules\Crm\Models\Lead;
use App\Modules\Customers\Models\Consent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Formularele publice. Fiecare creează o cerere în CRM; conținutul mesajelor este criptat, iar rețetele încărcate
 * se păstrează privat (PrescriptionStore). Câmpul „website” ascuns oprește roboții simpli; ruta are limitare.
 */
class FormController extends Controller
{
    public function contact(Request $request)
    {
        if ($this->isBot($request)) {
            return back()->with('status', 'Mesajul a fost trimis. Vă răspundem cât mai curând.');
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'subject' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'max:4000'],
            'consent' => ['accepted'],
        ], ['consent.accepted' => 'Avem nevoie de acordul dumneavoastră ca să vă putem răspunde.']);

        DB::transaction(function () use ($data, $request) {
            $submission = FormSubmission::create([
                'form' => 'contact', 'payload' => $data, 'reference' => 'M-'.strtoupper(Str::random(8)),
                'ip_hash' => $this->ipHash($request), 'source_url' => mb_substr((string) $request->headers->get('referer'), 0, 255),
            ]);
            Lead::create(['source' => 'contact', 'name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null,
                'summary' => $data['subject'], 'form_submission_id' => $submission->id, 'customer_id' => Auth::guard('client')->id()]);
        });

        return back()->with('status', 'Mesajul a fost trimis. Vă răspundem cât mai curând.');
    }

    public function partner(Request $request)
    {
        if ($this->isBot($request)) {
            return redirect('/parteneri/#cerere')->with('status', 'Cererea a fost trimisă.');
        }
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:190'],
            'vat_code' => ['required', 'string', 'max:30', 'regex:/^(RO)?\s?\d{2,10}$/i'],
            'kind' => ['required', 'in:'.implode(',', array_keys(\App\Modules\B2B\Models\Organization::KINDS))],
            'city' => ['nullable', 'string', 'max:120'],
            'contact_name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['required', 'string', 'max:40'],
            'message' => ['nullable', 'string', 'max:4000'],
            'consent' => ['accepted'],
        ], ['vat_code.regex' => 'CUI-ul trebuie să conțină doar cifre, eventual precedate de „RO”.', 'consent.accepted' => 'Avem nevoie de acordul dumneavoastră pentru a analiza cererea.']);

        DB::transaction(function () use ($data, $request) {
            $req = PartnershipRequest::create([
                'company_name' => $data['company_name'], 'kind' => $data['kind'], 'vat_code' => strtoupper(preg_replace('/\s+/', '', $data['vat_code'])),
                'contact_name' => $data['contact_name'], 'email' => $data['email'], 'phone' => $data['phone'], 'city' => $data['city'] ?? null,
                'message' => $data['message'] ?? null, 'ip_hash' => $this->ipHash($request),
            ]);
            Lead::create(['source' => 'parteneriat', 'name' => $data['contact_name'].' — '.$data['company_name'], 'email' => $data['email'],
                'phone' => $data['phone'], 'summary' => 'Cerere de parteneriat #'.$req->id]);
        });

        return redirect('/parteneri/#cerere')->with('status', 'Cererea de parteneriat a fost trimisă. O verificăm manual și vă contactăm.');
    }

    public function help(Request $request, PrescriptionStore $store)
    {
        if ($this->isBot($request)) {
            return back()->with('status', 'Cererea a fost trimisă.');
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'usage' => ['nullable', 'in:'.implode(',', array_keys(\App\Modules\Configurator\Support\ConfigurationInput::USAGES))],
            'message' => ['nullable', 'string', 'max:2000'],
            'reteta' => ['nullable', 'file'],
            'consent' => ['accepted'],
        ], ['consent.accepted' => 'Avem nevoie de acordul dumneavoastră ca să vă putem contacta.']);
        if ($request->hasFile('reteta') && ! $request->boolean('consent_rx')) {
            throw \Illuminate\Validation\ValidationException::withMessages(['consent_rx' => 'Pentru a trimite rețeta, bifați acordul pentru prelucrarea ei.']);
        }

        DB::transaction(function () use ($data, $request, $store) {
            $customerId = Auth::guard('client')->id();
            $guestKey = $customerId ? null : hash('sha256', $request->session()->getId());
            $prescriptionId = null;
            if ($request->hasFile('reteta')) {
                $p = Prescription::create(['customer_id' => $customerId, 'guest_key' => $guestKey, 'source' => 'upload', 'customer_unsure' => true,
                    'retention_until' => now()->addMonths((int) config('optoplus.retention.prescription_lead_months', 6))]);
                $store->store($p, $request->file('reteta'));
                $prescriptionId = $p->id;
                Consent::record($customerId, 'prelucrare_reteta', true, 'formular_ajutor', $guestKey);
            }
            $lead = Lead::create([
                'source' => 'ajutor_lentile', 'name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'],
                'summary' => 'Utilizare: '.(\App\Modules\Configurator\Support\ConfigurationInput::USAGES[$data['usage'] ?? 'nu_stiu'] ?? '—').($prescriptionId ? ' · rețetă încărcată (#'.$prescriptionId.')' : ''),
                'customer_id' => $customerId,
            ]);
            if (! empty($data['message'])) {
                $lead->notes()->create(['body' => mb_substr($data['message'], 0, 2000)]);
            }
            Audit::log('crm.lead.help_request', $lead, ['reteta' => (bool) $prescriptionId]);
        });

        return back()->with('status', 'Cererea a fost trimisă. Un specialist vă contactează cu o recomandare.');
    }

    private function isBot(Request $request): bool
    {
        return trim((string) $request->input('website')) !== '';
    }

    private function ipHash(Request $request): string
    {
        return hash_hmac('sha256', (string) $request->ip(), (string) config('app.key'));
    }
}
