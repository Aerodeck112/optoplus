<?php

namespace App\Modules\Payments\Services;

use App\Core\Settings\Settings;
use App\Modules\B2B\Models\Organization;
use App\Modules\Payments\Gateways\BtIpayGateway;
use App\Modules\Payments\Gateways\PaymentGateway;
use App\Modules\Payments\Gateways\SimulationGateway;
use App\Modules\Payments\Gateways\StripeGateway;

/**
 * Metodele de plată disponibile la finalizarea comenzii. Toate pornesc dezactivate: fiecare se activează din
 * panou (Setări → Plăți) după confirmarea regulilor (docs/INTREBARI.md #11–13) și a datelor necesare
 * (contract procesator, IBAN, ramburs acceptat de curier).
 *
 * 1.0.17: cardul depinde de valuta comenzii — în lei prin BT iPay, în euro și forinți prin Stripe. Rambursul și
 * transferul bancar există doar pentru comenzile în lei (rambursul doar cu livrare în România).
 */
class PaymentMethods
{
    public function __construct(private Settings $settings) {}

    /** @return array<string, class-string<PaymentGateway>> */
    public static function gateways(): array
    {
        return ['btipay' => BtIpayGateway::class, 'stripe' => StripeGateway::class, 'simulare' => SimulationGateway::class];
    }

    /** Procesatorul de card pentru o valută: lei → BT iPay (sau cel ales), euro / forinți → Stripe. */
    public function gatewayFor(string $currency): ?PaymentGateway
    {
        $currency = strtoupper($currency);
        $stripe = $this->gateway('stripe');
        $stripeOk = $this->settings->get('payments.stripe.enabled') && $stripe instanceof StripeGateway
            && $stripe->isConfigured() && in_array($currency, $stripe->currencies(), true);
        if ($currency === 'RON') {
            $main = $this->gateway();
            if ($this->settings->get('payments.card_enabled') && $main?->isConfigured()) {
                return $main;
            }

            return $stripeOk ? $stripe : null;
        }
        if ($stripeOk) {
            return $stripe;
        }
        // Simulatorul (doar în dezvoltare) acceptă orice valută.
        $main = $this->gateway();

        return $main instanceof SimulationGateway && $this->settings->get('payments.card_enabled') && $main->isConfigured() ? $main : null;
    }

    public function gateway(?string $code = null): ?PaymentGateway
    {
        $code ??= (string) $this->settings->get('payments.card_gateway', '');
        $class = self::gateways()[$code] ?? null;

        return $class ? app($class) : null;
    }

    /**
     * @param  array{kind:string, allows_cod:bool}|null  $shipping  metoda de livrare aleasă
     * @return array<string, array{label:string, note:string}>
     */
    public function available(?array $shipping, string $channel = 'b2c', ?Organization $organization = null, string $currency = 'RON'): array
    {
        $out = [];
        $currency = strtoupper($currency);
        if ($channel === 'b2b' && $organization?->hasApprovedCredit()) {
            $out['termen'] = ['label' => "La termen, {$organization->payment_terms_days} zile, pe factură", 'note' => 'Conform condițiilor aprobate pentru contul dumneavoastră.'];
        }
        if ($this->gatewayFor($currency)) {
            // Cardul: BT iPay în lei, Stripe în euro / forinți (sau simulatorul, doar în dezvoltare).
            $out['card'] = ['label' => 'Card online', 'note' => 'Plătiți pe pagina securizată a procesatorului; nu vedem și nu păstrăm datele cardului.'];
        }
        if ($currency !== 'RON') {
            return $out; // ramburs, transfer și plata la ridicare: doar în lei
        }
        if ($this->settings->get('payments.bank_transfer_enabled') && $this->bankDetails()) {
            $out['transfer_bancar'] = ['label' => 'Transfer bancar (ordin de plată)', 'note' => 'Primiți datele de plată după plasarea comenzii. Comanda intră în lucru după ce confirmăm încasarea.'];
        }
        if ($shipping && $shipping['allows_cod'] && $this->settings->get('payments.cod_enabled')) {
            $out['ramburs'] = ['label' => 'Ramburs la curier', 'note' => 'Plătiți curierului la livrare.'];
        }
        if ($shipping && $shipping['kind'] === 'ridicare' && $this->settings->get('payments.pickup_payment_enabled')) {
            $out['numerar_ridicare'] = ['label' => 'Plată la ridicare', 'note' => 'Plătiți la ridicarea comenzii.'];
        }

        return $out;
    }

    /** @return array{beneficiary:string, iban:string, bank:string}|null */
    public function bankDetails(): ?array
    {
        $iban = (string) $this->settings->get('company.iban', '');
        $name = (string) ($this->settings->get('company.name') ?: $this->settings->get('company.legal_name', ''));
        if ($iban === '' || $name === '') {
            return null;
        }

        return ['beneficiary' => $name, 'iban' => $iban, 'bank' => (string) $this->settings->get('company.bank', '')];
    }
}
