<?php

namespace App\Modules\Orders\Http\Controllers\Panel;

use App\Core\Audit\Audit;
use App\Http\Controllers\Controller;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\Shipment;
use App\Modules\Orders\Services\OrderStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\Refund;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Production\Services\ProductionFlow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Comenzile în panou. Cele trei stări (comercială, plată, producție) se schimbă doar prin acțiunile lor:
 * confirmare/anulare (vânzări), încasare (serviciul de plăți) și etape (fluxul de producție).
 * Fișa comenzii nu afișează valorile rețetei; acestea sunt în fișa de lucru, cu permisiune și audit.
 */
class OrderController extends Controller
{
    public function index(Request $request)
    {
        $q = Order::query()->with('organization:id,name')->withCount('items');
        foreach (['commercial_status', 'payment_status', 'production_status', 'channel', 'payment_method'] as $f) {
            if ($v = $request->query($f)) {
                $q->where($f, $v);
            }
        }
        if ($term = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('number', 'like', '%'.addcslashes($term, '%_').'%')->orWhere('email', $term)->orWhere('phone', $term)->orWhere('purchase_order_ref', $term));
        }
        if ($request->boolean('de_actionat')) {
            // De acționat: comenzi noi neconfirmate, transferuri în așteptare, ramburs/ridicare neîncasate după livrare.
            $q->where(fn ($w) => $w->where('commercial_status', 'noua')->orWhere(fn ($x) => $x->where('payment_method', 'transfer_bancar')->where('payment_status', 'in_asteptare')));
        }

        return view('orders::panel.index', [
            'orders' => $q->latest('id')->paginate(40)->withQueryString(),
            'filters' => $request->only(['commercial_status', 'payment_status', 'production_status', 'channel', 'payment_method', 'q', 'de_actionat']),
        ]);
    }

    public function show(string $uuid, ProductionFlow $flow)
    {
        $order = Order::where('uuid', $uuid)->with(['items.configuration', 'payments.refunds', 'payments.confirmer', 'shipments', 'invoices', 'events.user', 'productionJobs.assignee', 'organization', 'customer'])->firstOrFail();
        Audit::log('order.viewed', $order, [], $order->customer_id);

        return view('orders::panel.show', ['order' => $order, 'stages' => $flow->stages()->pluck('name', 'code')]);
    }

    public function confirm(Request $request, string $uuid, OrderStatus $status)
    {
        $order = Order::where('uuid', $uuid)->firstOrFail();
        $data = $request->validate(['mesaj' => ['nullable', 'string', 'max:500']]);
        $status->commercial($order, 'confirmata', ($data['mesaj'] ?? null) ?: 'Comanda a fost confirmată.');

        return back()->with('status', 'Comanda a fost confirmată.');
    }

    public function cancel(Request $request, string $uuid, OrderStatus $status)
    {
        $order = Order::where('uuid', $uuid)->firstOrFail();
        $data = $request->validate(['motiv' => ['required', 'string', 'min:3', 'max:255']], ['motiv.required' => 'Scrieți motivul anulării (îl vede clientul).']);
        if ($order->productionJobs()->whereNotIn('stage', ['primita', 'verificare_reteta', 'clarificari'])->exists()) {
            return back()->with('warning', 'Lucrările au intrat deja în producție. Anularea cere decizia managerului: opriți întâi lucrările.');
        }
        DB::transaction(function () use ($order, $status, $data) {
            $status->commercial($order, 'anulata', $data['motiv']);
            // Configurațiile se eliberează (clientul le poate comanda din nou); stocul ramelor se readaugă.
            foreach ($order->items as $item) {
                $item->configuration?->update(['status' => 'valid']);
                if ($item->kind === 'product' && $item->variant && $item->variant->stock_qty !== null) {
                    $item->variant->increment('stock_qty', $item->quantity);
                }
            }
        });

        return back()->with('status', 'Comanda a fost anulată.'.($order->paid_total > 0 ? ' Atenție: există încasări — inițiați rambursarea.' : ''));
    }

    public function note(Request $request, string $uuid, OrderStatus $status)
    {
        $order = Order::where('uuid', $uuid)->firstOrFail();
        $data = $request->validate(['mesaj' => ['required', 'string', 'max:500'], 'vizibil' => ['nullable', 'boolean']]);
        $status->note($order, $data['mesaj'], (bool) ($data['vizibil'] ?? false));

        return back()->with('status', ! empty($data['vizibil']) ? 'Mesajul a fost trimis clientului.' : 'Nota internă a fost salvată.');
    }

    public function confirmPayment(Request $request, string $uuid, int $paymentId, PaymentService $payments)
    {
        $order = Order::where('uuid', $uuid)->firstOrFail();
        $payment = Payment::where('order_id', $order->id)->findOrFail($paymentId);
        $data = $request->validate(['nota' => ['required', 'string', 'max:255']], ['nota.required' => 'Scrieți dovada încasării (ex. extras bancar din data …, chitanța nr. …).']);
        $payments->confirmManual($payment, $request->user(), $data['nota']);

        return back()->with('status', 'Încasarea a fost confirmată.');
    }

    public function refund(Request $request, string $uuid, int $paymentId, PaymentService $payments)
    {
        $order = Order::where('uuid', $uuid)->firstOrFail();
        $payment = Payment::where('order_id', $order->id)->findOrFail($paymentId);
        $data = $request->validate(['suma' => ['required', 'string'], 'motiv' => ['required', 'string', 'max:255']]);
        $amount = \App\Core\Support\Money::parse($data['suma']);
        $refund = $payments->refund($payment, (int) $amount, $data['motiv'], $request->user());

        return back()->with($refund->status === 'efectuata' ? 'status' : 'warning', $refund->status === 'efectuata' ? 'Rambursarea a fost înregistrată.' : 'Rambursarea nu a reușit la procesator; verificați jurnalul plății.');
    }

    public function shipment(Request $request, string $uuid, OrderStatus $status)
    {
        $order = Order::where('uuid', $uuid)->firstOrFail();
        $data = $request->validate([
            'curier' => ['required', 'string', 'max:40'], 'awb' => ['nullable', 'string', 'max:80'],
            'urmarire' => ['nullable', 'url:https', 'max:500'], 'stare' => ['required', 'in:'.implode(',', array_keys(Shipment::STATUSES))],
        ]);
        $shipment = $order->shipments()->create([
            'carrier' => $data['curier'], 'awb' => $data['awb'] ?? null, 'tracking_url' => $data['urmarire'] ?? null,
            'status' => $data['stare'], 'shipped_at' => in_array($data['stare'], ['predata', 'in_tranzit', 'livrata'], true) ? now() : null,
            'delivered_at' => $data['stare'] === 'livrata' ? now() : null, 'created_by' => $request->user()->id,
        ]);
        Audit::log('order.shipment', $shipment, ['order' => $order->number]);
        if ($shipment->shipped_at) {
            if ($order->requiresProduction()) {
                $status->production($order, 'expediata', 'AWB '.($shipment->awb ?? '—'));
            } else {
                $status->event($order, 'customer', null, null, 'Coletul a fost predat curierului'.($shipment->awb ? ' (AWB '.$shipment->awb.')' : '').'.', true, null);
            }
        }

        return back()->with('status', 'Livrarea a fost înregistrată.');
    }

    public function updateShipment(Request $request, string $uuid, int $id, OrderStatus $status)
    {
        $order = Order::where('uuid', $uuid)->firstOrFail();
        $shipment = $order->shipments()->findOrFail($id);
        $data = $request->validate(['stare' => ['required', 'in:'.implode(',', array_keys(Shipment::STATUSES))]]);
        $shipment->update(['status' => $data['stare'], 'delivered_at' => $data['stare'] === 'livrata' ? now() : $shipment->delivered_at]);
        if ($data['stare'] === 'livrata' && $order->commercial_status === 'confirmata' && $order->isPaid()) {
            $status->commercial($order, 'finalizata', 'Comanda a fost livrată.');
        }

        return back()->with('status', 'Starea livrării a fost actualizată.');
    }

    /** Referința la factura emisă în sistemul de facturare (aplicația nu emite documente fiscale). */
    public function invoice(Request $request, string $uuid)
    {
        $order = Order::where('uuid', $uuid)->firstOrFail();
        $data = $request->validate([
            'tip' => ['required', 'in:factura,storno,proforma'], 'serie' => ['nullable', 'string', 'max:20'], 'numar' => ['required', 'string', 'max:40'],
            'link' => ['nullable', 'url:https', 'max:500'], 'sistem' => ['required', 'string', 'max:30'],
        ]);
        $inv = $order->invoices()->create(['provider' => $data['sistem'], 'kind' => $data['tip'], 'series' => $data['serie'] ?? null, 'number' => $data['numar'], 'document_url' => $data['link'] ?? null, 'status' => 'emisa']);
        Audit::log('order.invoice_reference', $inv, ['order' => $order->number]);

        return back()->with('status', 'Referința facturii a fost salvată.');
    }

    public function export(Request $request): StreamedResponse
    {
        $from = $request->date('de_la') ?? now()->subMonth();
        $to = $request->date('pana_la') ?? now();
        Audit::log('orders.exported', null, ['from' => $from->toDateString(), 'to' => $to->toDateString()]);

        return response()->streamDownload(function () use ($from, $to) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\u{FEFF}");
            fputcsv($out, ['numar', 'data', 'canal', 'stare_comerciala', 'stare_plata', 'stare_productie', 'metoda_plata', 'subtotal', 'reduceri', 'livrare', 'tva', 'total', 'incasat', 'rambursat', 'partener'], ';', '"', '');
            Order::whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])->with('organization:id,name')->orderBy('id')
                ->chunk(500, function ($orders) use ($out) {
                    foreach ($orders as $o) {
                        // Fără date personale ale clienților persoane fizice și fără date medicale.
                        fputcsv($out, [$o->number, $o->created_at->format('Y-m-d H:i'), $o->channel, $o->commercial_status, $o->payment_status, $o->production_status, $o->payment_method,
                            $o->subtotal / 100, $o->discount_total / 100, $o->shipping_total / 100, $o->vat_total / 100, $o->total / 100, $o->paid_total / 100, $o->refunded_total / 100, $o->organization?->name], ';', '"', '');
                    }
                });
            fclose($out);
        }, 'comenzi-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
