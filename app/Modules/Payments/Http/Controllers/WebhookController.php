<?php

namespace App\Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payments\Services\PaymentMethods;
use App\Modules\Payments\Services\PaymentService;
use Illuminate\Http\Request;

/**
 * Notificările server-la-server de la procesatori. Fără CSRF și fără sesiune; semnătura se verifică în adaptor.
 * Răspunsul nu dezvăluie dacă plata există (procesatorul primește 200 pentru orice notificare înregistrată).
 */
class WebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, PaymentMethods $methods, PaymentService $payments)
    {
        abort_unless(array_key_exists($provider, PaymentMethods::gateways()), 404);
        $gateway = $methods->gateway($provider);
        if (! $gateway->isConfigured()) {
            return response('indisponibil', 503);
        }
        $outcome = $payments->handleNotification($gateway, $gateway->parseNotification($request));

        return response($outcome === 'respins' ? 'respins' : 'ok', $outcome === 'respins' ? 400 : 200)
            ->header('Content-Type', 'text/plain')->header('Cache-Control', 'no-store');
    }
}
