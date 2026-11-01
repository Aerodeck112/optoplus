<?php

namespace App\Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentService;
use Illuminate\Http\Request;

/**
 * Revenirea clientului de pe pagina BT. Parametrii din URL nu sunt crezuți: se interoghează BT pentru starea
 * reală, apoi clientul ajunge pe pagina comenzii, care arată starea înregistrată (eventual „în așteptare”).
 */
class BtReturnController extends Controller
{
    public function __invoke(Request $request, PaymentService $payments)
    {
        $orderId = (string) $request->query('orderId', '');
        abort_unless(preg_match('/^[0-9a-f-]{20,64}$/i', $orderId), 404);
        $payment = Payment::where('provider', 'btipay')->where('provider_reference', $orderId)->with('order')->firstOrFail();
        $payments->reconcile($payment);

        return redirect('/comanda/'.$payment->order->uuid.'/');
    }
}
