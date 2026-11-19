<?php

namespace App\Modules\Regions\Services;

use App\Core\Settings\Settings;
use App\Modules\Regions\Support\Countries;

/**
 * Valuta cererii curente și țările de livrare.
 *
 * Valuta urmează limba siteului: română → lei, maghiară → forinți, engleză → euro (setat de LocalizeSite).
 * Panoul, portalul partenerilor B2B și orice cerere fără limbă străină rămân în lei. Dacă pentru valută nu există
 * curs (BNR încă necitit sau curs manual lipsă), siteul rămâne în lei — nu afișăm prețuri estimate.
 */
class Regions
{
    private string $currency = 'RON';

    public function __construct(private Settings $settings, private ExchangeRates $rates) {}

    public function rates(): ExchangeRates
    {
        return $this->rates;
    }

    /** Prețurile în valuta limbii (euro / forinți) sunt active? */
    public function currenciesEnabled(): bool
    {
        return (bool) $this->settings->get('currency.enabled', true);
    }

    public function forLocale(string $locale): void
    {
        $currency = Countries::currencyFor($locale);
        $this->currency = $currency !== 'RON' && $this->currenciesEnabled() && $this->rates->has($currency) ? $currency : 'RON';
    }

    public function setCurrency(string $currency): void
    {
        $this->currency = strtoupper($currency);
    }

    public function currency(): string
    {
        return $this->currency;
    }

    /** Detectarea țării la prima vizită (limba și valuta se schimbă automat) este activă? */
    public function autoDetect(): bool
    {
        return (bool) $this->settings->get('regions.auto_detect', true);
    }

    /**
     * Țările în care livrăm, pentru formularul de comandă: cod => denumire. România întâi, apoi Ungaria, apoi
     * celelalte (doar dacă livrarea respectivă este activă).
     *
     * @return array<string, string>
     */
    public function shippingCountries(): array
    {
        $out = ['RO' => Countries::name('RO')];
        if ($this->settings->get('shipping.hu_enabled')) {
            $out['HU'] = Countries::name('HU');
        }
        if ($this->settings->get('shipping.intl_enabled')) {
            $intl = [];
            foreach ($this->internationalCountries() as $code) {
                $intl[$code] = Countries::name($code);
            }
            asort($intl);
            $out += $intl;
        }

        return $out;
    }

    /** @return list<string> */
    public function internationalCountries(): array
    {
        $list = Countries::parseList((string) $this->settings->get('shipping.intl_countries', ''));

        return $list ?: Countries::defaultInternational();
    }

    /** Țara implicită din formularul de comandă: după limba siteului (maghiară → Ungaria) sau țara detectată. */
    public function defaultCountry(string $locale, ?string $detected = null): string
    {
        $countries = $this->shippingCountries();
        if ($detected && isset($countries[$detected])) {
            return $detected;
        }
        if ($locale === 'hu' && isset($countries['HU'])) {
            return 'HU';
        }
        if ($locale === 'en') {
            foreach (array_keys($countries) as $code) {
                if (! in_array($code, ['RO', 'HU'], true)) {
                    return $code;
                }
            }
        }

        return 'RO';
    }
}
