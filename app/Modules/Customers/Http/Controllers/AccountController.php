<?php

namespace App\Modules\Customers\Http\Controllers;

use App\Core\Audit\Audit;
use App\Http\Controllers\Controller;
use App\Modules\Configurator\Models\Configuration;
use App\Modules\Configurator\Models\Prescription;
use App\Modules\Configurator\Services\PrescriptionStore;
use App\Modules\Customers\Models\Consent;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerAddress;
use App\Modules\Customers\Models\DataRequest;
use App\Modules\Orders\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Contul clientului: comenzi, configurații, rețete, adrese, date personale și acorduri. Totul este privat. */
class AccountController extends Controller
{
    private function customer(): Customer
    {
        return Auth::guard('client')->user();
    }

    private function view(string $view, array $data = [])
    {
        return response()->view('customers::account.'.$view, $data + ['customer' => $this->customer()])->header('Cache-Control', 'no-store, private');
    }

    public function orders()
    {
        $orders = Order::where('customer_id', $this->customer()->id)->latest('id')->with('items')->paginate(20);

        return $this->view('orders', ['orders' => $orders]);
    }

    public function configurations()
    {
        $configs = Configuration::where('customer_id', $this->customer()->id)->where('status', '!=', 'in_comanda')
            ->with('frameVariant.product', 'offering.product', 'offering.material')->latest('updated_at')->get();

        return $this->view('configurations', ['configs' => $configs]);
    }

    public function deleteConfiguration(string $uuid)
    {
        $cfg = Configuration::where('uuid', $uuid)->where('customer_id', $this->customer()->id)->where('status', '!=', 'in_comanda')->firstOrFail();
        $cfg->delete();

        return back()->with('status', 'Configurația a fost ștearsă.');
    }

    public function prescriptions()
    {
        $prescriptions = Prescription::where('customer_id', $this->customer()->id)->whereNotNull('values')->latest('updated_at')->withCount('files')->get();
        if ($prescriptions->isNotEmpty()) {
            Audit::log('prescription.viewed', null, ['context' => 'cont', 'count' => $prescriptions->count()], $this->customer()->id);
        }
        $ordered = DB::table('order_items')->whereIn('prescription_id', $prescriptions->pluck('id'))->pluck('prescription_id')->all();

        return $this->view('prescriptions', ['prescriptions' => $prescriptions, 'ordered' => $ordered]);
    }

    public function deletePrescription(int $id, PrescriptionStore $store)
    {
        $p = Prescription::where('customer_id', $this->customer()->id)->whereKey($id)->with('files')->firstOrFail();
        if (DB::table('order_items')->where('prescription_id', $p->id)->exists()) {
            return back()->withErrors(['reteta' => 'Rețeta este legată de o comandă și se păstrează cât timp o cere legea pentru acea comandă. Puteți cere ștergerea din „Datele mele”.']);
        }
        foreach ($p->files as $f) {
            $store->delete($f);
        }
        Configuration::where('prescription_id', $p->id)->update(['prescription_id' => null]);
        $p->delete();
        Audit::log('prescription.deleted_by_customer', null, ['id' => $id], $this->customer()->id);

        return back()->with('status', 'Rețeta a fost ștearsă.');
    }

    public function addresses()
    {
        return $this->view('addresses', ['addresses' => $this->customer()->addresses()->orderByDesc('is_default')->get()]);
    }

    public function storeAddress(Request $request)
    {
        $data = $request->validate([
            'nume' => ['required', 'string', 'max:160'], 'firma' => ['nullable', 'string', 'max:160'], 'cui' => ['nullable', 'string', 'max:30'],
            'telefon' => ['nullable', 'string', 'max:40'], 'adresa' => ['required', 'string', 'max:255'], 'localitate' => ['required', 'string', 'max:120'],
            'judet' => ['required', 'string', 'max:120'], 'cod_postal' => ['nullable', 'string', 'max:20'], 'implicita' => ['nullable', 'boolean'],
        ]);
        $c = $this->customer();
        $default = ! empty($data['implicita']) || ! $c->addresses()->exists();
        if ($default) {
            $c->addresses()->update(['is_default' => false]);
        }
        $c->addresses()->create([
            'name' => $data['nume'], 'company' => $data['firma'] ?? null, 'vat_code' => $data['cui'] ?? null, 'phone' => $data['telefon'] ?? null,
            'line1' => $data['adresa'], 'city' => $data['localitate'], 'county' => $data['judet'], 'postal_code' => $data['cod_postal'] ?? null, 'is_default' => $default,
        ]);

        return back()->with('status', 'Adresa a fost salvată.');
    }

    public function deleteAddress(int $id)
    {
        CustomerAddress::where('customer_id', $this->customer()->id)->whereKey($id)->firstOrFail()->delete();

        return back()->with('status', 'Adresa a fost ștearsă.');
    }

    public function profile()
    {
        $c = $this->customer();

        return $this->view('profile', [
            'requests' => DataRequest::where('customer_id', $c->id)->latest('id')->get(),
            'consents' => Consent::where('customer_id', $c->id)->latest('id')->limit(30)->get(),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'prenume' => ['required', 'string', 'max:100'], 'nume' => ['required', 'string', 'max:100'],
            'telefon' => ['nullable', 'string', 'max:40'], 'marketing' => ['nullable', 'boolean'],
        ]);
        $c = $this->customer();
        $marketing = ! empty($data['marketing']);
        if ($marketing !== ($c->marketing_consent_at !== null)) {
            Consent::record($c->id, 'marketing', $marketing, 'cont');
        }
        $c->update([
            'first_name' => $data['prenume'], 'last_name' => $data['nume'], 'phone' => $data['telefon'] ?? null,
            'marketing_consent_at' => $marketing ? ($c->marketing_consent_at ?? now()) : null, 'marketing_consent_source' => $marketing ? 'cont' : null,
        ]);

        return back()->with('status', 'Datele au fost actualizate.');
    }

    /** Exportul datelor proprii (GDPR, art. 15/20), generat imediat, fără date interne ale personalului. */
    public function export()
    {
        $c = $this->customer();
        $prescriptions = Prescription::where('customer_id', $c->id)->get();
        $data = [
            'generat_la' => now()->toIso8601String(),
            'cont' => Customer::findOrFail($c->id)->only(['email', 'first_name', 'last_name', 'phone', 'type', 'created_at', 'marketing_consent_at']),
            'adrese' => $c->addresses()->get(['name', 'company', 'vat_code', 'phone', 'line1', 'line2', 'city', 'county', 'postal_code', 'country'])->toArray(),
            'acorduri' => Consent::where('customer_id', $c->id)->get(['type', 'granted', 'source', 'document_version', 'created_at'])->toArray(),
            'comenzi' => Order::where('customer_id', $c->id)->with('items:id,order_id,name,quantity,line_total')->get(['id', 'number', 'created_at', 'total', 'commercial_status', 'payment_status', 'production_status', 'billing_address', 'shipping_address'])->toArray(),
            'retete' => $prescriptions->map(fn ($p) => ['creata' => $p->created_at?->toIso8601String(), 'valori' => $p->values, 'dp' => $p->pd_mode, 'masuratori' => $p->measurements, 'fisiere' => $p->files()->count()])->all(),
            'configuratii' => Configuration::where('customer_id', $c->id)->get(['uuid', 'entry', 'usage', 'status', 'created_at'])->toArray(),
        ];
        Audit::log('customer.data_exported', $c, [], $c->id);

        return response()->json($data, 200, [
            'Content-Disposition' => 'attachment; filename="optoplus-datele-mele-'.now()->format('Y-m-d').'.json"',
            'Cache-Control' => 'no-store, private',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /** Cererea de ștergere se tratează de personal: unele date (facturi, comenzi) au termene legale de păstrare. */
    public function requestDeletion(Request $request)
    {
        $request->validate(['confirmare' => ['accepted']], ['confirmare.accepted' => 'Bifați confirmarea pentru a trimite cererea.']);
        $c = $this->customer();
        if (! DataRequest::where('customer_id', $c->id)->where('type', 'stergere')->whereIn('status', ['noua', 'in_lucru'])->exists()) {
            DataRequest::create(['customer_id' => $c->id, 'type' => 'stergere', 'due_at' => now()->addDays(30)]);
            Audit::log('customer.deletion_requested', $c, [], $c->id);
        }

        return back()->with('status', 'Am primit cererea de ștergere. Vă răspundem în cel mult 30 de zile; datele care trebuie păstrate legal (de exemplu facturile) vor fi păstrate doar cât cere legea.');
    }
}
