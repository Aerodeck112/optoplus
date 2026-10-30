<?php

namespace App\Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payments\Gateways\SimulationGateway;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * „Pagina procesatorului” simulată (doar dezvoltare/staging). La alegerea unui rezultat trimite o notificare
 * semnată exact ca un procesator real, apoi întoarce clientul pe pagina comenzii — care NU marchează nimic.
 */
class SimulationController extends Controller
{
    public function __construct(private SimulationGateway $gateway)
    {
        abort_unless($this->gateway->isConfigured(), 404);
    }

    public function show(string $reference)
    {
        $payment = Payment::where('provider', 'simulare')->where('provider_reference', $reference)->with('order')->firstOrFail();

        return response()->view('payments::simulation', ['payment' => $payment])->header('Cache-Control', 'no-store');
    }

    public function decide(Request $request, string $reference, PaymentService $payments)
    {
        $payment = Payment::where('provider', 'simulare')->where('provider_reference', $reference)->with('order')->firstOrFail();
        $status = $request->input('rezultat') === 'refuza' ? 'failed' : 'paid';
        $body = json_encode(['event_id' => 'evt_'.Str::random(20), 'reference' => $reference, 'status' => $status, 'amount' => $payment->amount]);
        $notification = Request::create('/webhook/plati/simulare', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_'.str_replace('-', '_', strtoupper(SimulationGateway::HEADER)) => SimulationGateway::sign($body)], $body);
        $payments->handleNotification($this->gateway, $this->gateway->parseNotification($notification));

        return redirect('/comanda/'.$payment->order->uuid.'/');
    }
}
