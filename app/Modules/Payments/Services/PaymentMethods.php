<?php

namespace App\Modules\Payments\Services;

use App\Core\Settings\Settings;
use App\Modules\Payments\Gateways\BtIpayGateway;
use App\Modules\Payments\Gateways\PaymentGateway;
use App\Modules\Payments\Gateways\SimulationGateway;

/**
 * Metodele de plată disponibile la finalizarea comenzii. Toate pornesc dezactivate: fiecare se activează din
 * panou (Setări → Plăți) după confirmarea regulilor (docs/INTREBARI.md #11–13) și a datelor necesare
 * (contract procesator, IBAN, ramburs acceptat de curier).
 */
class PaymentMethods
{
    public function __construct(private Settings $settings) {}

    /** @return array<string, class-string<PaymentGateway>> */
    public static function gateways(): array
    {
        return ['btipay' => BtIpayGateway::class, 'simulare' => SimulationGateway::class];
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
    public function available(?array $shipping, string $channel = 'b2c'): array
    {
        $out = [];
        $gateway = $this->gateway();
        if ($this->settings->get('payments.card_enabled') && $gateway?->isConfigured()) {
            $out['card'] = ['label' => 'Card online', 'note' => 'Plătiți pe pagina securizată a procesatorului; nu vedem și nu păstrăm datele cardului.'];
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
        $name = (string) $this->settings->get('company.legal_name', '');
        if ($iban === '' || $name === '') {
            return null;
        }

        return ['beneficiary' => $name, 'iban' => $iban, 'bank' => (string) $this->settings->get('company.bank', '')];
    }
}
