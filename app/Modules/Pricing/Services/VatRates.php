<?php

namespace App\Modules\Pricing\Services;

use App\Core\Settings\Settings;

/**
 * Cotele de TVA pe tipuri de produs, din setări (puncte de bază). Valorile implicite sunt doar provizorii:
 * regimul TVA pentru lentile și rame (cotă standard, redusă sau scutire) trebuie confirmat de contabil — vezi
 * docs/INTREBARI.md. Prețurile B2C sunt cu TVA inclus, deci cota schimbă doar defalcarea, nu prețul final.
 */
class VatRates
{
    public const DEFAULT_BP = 2100;

    public function __construct(private Settings $settings) {}

    /** O firmă neînregistrată în scopuri de TVA nu colectează TVA: cota efectivă este 0, prețul rămâne același. */
    public function collectsVat(): bool
    {
        return $this->settings->get('company.vat_payer') !== false;
    }

    public function forType(string $productType): int
    {
        if (! $this->collectsVat()) {
            return 0;
        }
        $key = match ($productType) {
            'frame' => 'tax.vat_frames_bp',
            'lens', 'treatment' => 'tax.vat_lenses_bp',
            default => 'tax.vat_default_bp',
        };

        return (int) ($this->settings->get($key) ?? $this->settings->get('tax.vat_default_bp') ?? self::DEFAULT_BP);
    }

    public function shipping(): int
    {
        if (! $this->collectsVat()) {
            return 0;
        }
        return (int) ($this->settings->get('tax.vat_shipping_bp') ?? $this->settings->get('tax.vat_default_bp') ?? self::DEFAULT_BP);
    }

    public function confirmed(): bool
    {
        return ! $this->collectsVat() || (bool) $this->settings->get('tax.confirmed', false);
    }
}
