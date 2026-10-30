<?php

namespace App\Modules\Payments\Gateways;

use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Support\Notification;
use App\Modules\Payments\Support\StartResult;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Banca Transilvania iPay — BLOCAT DE DOCUMENTAȚIE.
 *
 * Procesatorul de pe siteul actual pare a fi BT iPay, dar nu am avut acces la documentația oficială
 * (btepos.ro/documentatie răspunde 403 fără cont de comerciant) și nici la un cont de test. Nu implementăm
 * endpointuri, parametri, coduri de stare sau algoritmul de semnătură „din memorie” sau din biblioteci
 * neoficiale: adaptorul rămâne inactiv până primim (docs/ACCESE-SI-INFORMATII-LIPSA.md, C1):
 *   - documentația oficială pentru comercianți și datele contului de test;
 *   - modul de notificare (callback) și cheia de verificare;
 *   - regulile pentru preautorizare/încasare, anulare și rambursare.
 * Contractul de implementat este PaymentGateway; testele din tests/Feature/Payments arată comportamentul cerut.
 */
class BtIpayGateway implements PaymentGateway
{
    public function code(): string
    {
        return 'btipay';
    }

    public function label(): string
    {
        return 'Card — Banca Transilvania iPay';
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function unavailableReason(): ?string
    {
        return 'Blocat de date: lipsesc documentația oficială BT iPay și contul de test (vezi ACCESE, C1).';
    }

    public function start(Payment $payment, Order $order, string $returnUrl): StartResult
    {
        return new StartResult(false, error: $this->unavailableReason());
    }

    public function parseNotification(Request $request): Notification
    {
        // Fără algoritmul oficial de semnătură, orice notificare este considerată nesemnată.
        return new Notification(hash('sha256', $request->getContent().$request->getQueryString()), false, null, null, null, 'neverificat');
    }

    public function fetchStatus(Payment $payment): ?string
    {
        return null;
    }

    public function refund(Payment $payment, int $amount, string $idempotencyKey): array
    {
        throw new RuntimeException((string) $this->unavailableReason());
    }
}
