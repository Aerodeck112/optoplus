<?php

namespace App\Modules\Shipping\Services;

use App\Modules\Regions\Services\ExchangeRates;
use App\Modules\Shipping\Models\ShippingMethod;
use Illuminate\Support\Collection;

/**
 * Costul de livrare din regulile editabile (prag de valoare, greutate, canal). Fără reguli = metoda nu se poate
 * folosi (nu presupunem gratuitate).
 *
 * 1.0.17: fiecare metodă are țările ei (România; Ungaria; celelalte țări), iar fiecare tarif are moneda lui
 * (ex. 22 lei în România, 20 € în Ungaria, 25 € în rest). Tariful se convertește în valuta comenzii la cursul zilei;
 * pragurile de valoare se compară în moneda tarifului.
 */
class ShippingCalculator
{
    public function __construct(private ExchangeRates $rates) {}

    /** @return Collection<int, ShippingMethod> */
    public function activeMethods(string $channel = 'b2c', ?string $country = null): Collection
    {
        return ShippingMethod::with('rules')->where('is_active', true)->orderBy('sort')->get()
            ->filter(fn (ShippingMethod $m) => $this->servesCountry($m, $country))
            ->filter(fn (ShippingMethod $m) => $this->ruleFor($m, null, null, $channel, 'RON') !== null || $m->rules->isEmpty() && $m->kind === 'ridicare')
            ->values();
    }

    public function servesCountry(ShippingMethod $m, ?string $country): bool
    {
        if ($country === null) {
            return true;
        }
        $countries = array_map('strtoupper', (array) ($m->countries ?: ['RO']));

        return in_array(strtoupper($country), $countries, true);
    }

    /**
     * @param  int  $goodsGross  valoarea mărfii după reduceri, cu TVA, în valuta $currency
     * @return array{code:string, name:string, price:int, transit_days_min:?int, transit_days_max:?int, allows_cod:bool, currency:string}|null
     */
    public function quote(string $code, int $goodsGross, ?int $weightG = null, string $channel = 'b2c', ?string $country = null, string $currency = 'RON'): ?array
    {
        $method = ShippingMethod::with('rules')->where('code', $code)->where('is_active', true)->first();
        if (! $method || ! $this->servesCountry($method, $country)) {
            return null;
        }
        $rule = $this->ruleFor($method, $goodsGross, $weightG, $channel, $currency);
        if (! $rule && ! ($method->kind === 'ridicare' && $method->rules->isEmpty())) {
            return null;
        }

        return [
            'code' => $method->code,
            'name' => $method->name,
            'kind' => $method->kind,
            'price' => $rule ? $this->rates->convert((int) $rule->price, (string) ($rule->currency ?: 'RON'), $currency) : 0,
            'currency' => strtoupper($currency),
            'transit_days_min' => $method->transit_days_min,
            'transit_days_max' => $method->transit_days_max,
            'allows_cod' => $method->allows_cod,
            'countries' => (array) $method->countries,
        ];
    }

    private function ruleFor(ShippingMethod $m, ?int $goods, ?int $weight, string $channel, string $currency)
    {
        return $m->rules->first(function ($r) use ($goods, $weight, $channel, $currency) {
            if ($r->channel !== 'all' && $r->channel !== $channel) {
                return false;
            }
            if ($goods !== null) {
                $value = $this->rates->convert($goods, $currency, (string) ($r->currency ?: 'RON'));
                if ($value < $r->min_subtotal || ($r->max_subtotal !== null && $value >= $r->max_subtotal)) {
                    return false;
                }
            }

            return $weight === null || $r->max_weight_g === null || $weight <= $r->max_weight_g;
        });
    }
}
