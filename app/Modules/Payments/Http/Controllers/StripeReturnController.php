<?php

namespace App\Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\I18n\Support\Locales;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentService;
use Illuminate\Http\Request;

/**
 * Revenirea clientului de pe pagina Stripe. Parametrul din adresă nu este crezut: se interoghează Stripe pentru
 * starea reală a sesiunii, apoi clientul ajunge pe pagina comenzii, în limba în care a comandat.
 */
class StripeReturnController extends Controller
{
    public function __invoke(Request $request, PaymentService $payments)
    {
        $sessionId = (string) $request->query('session_id', '');
        abort_unless(preg_match('/^cs_(test|live)_[A-Za-z0-9]{10,200}$/', $sessionId), 404);
        $payment = Payment::where('provider', 'stripe')->where('provider_reference', $sessionId)->with('order')->firstOrFail();
        $payments->reconcile($payment);

        return redirect(Locales::path((string) ($payment->order->locale ?: 'ro'), '/comanda/'.$payment->order->uuid.'/'));
    }
}
