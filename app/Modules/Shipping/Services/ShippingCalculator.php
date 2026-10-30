<?php

namespace App\Modules\Shipping\Services;

use App\Modules\Shipping\Models\ShippingMethod;
use Illuminate\Support\Collection;

/**
 * Costul de livrare din regulile editabile (prag de valoare, greutate, canal). Fără reguli = metoda nu se poate
 * folosi (nu presupunem gratuitate).
 */
class ShippingCalculator
{
    /** @return Collection<int, ShippingMethod> */
    public function activeMethods(string $channel = 'b2c'): Collection
    {
        return ShippingMethod::with('rules')->where('is_active', true)->orderBy('sort')->get()
            ->filter(fn (ShippingMethod $m) => $this->ruleFor($m, null, null, $channel) !== null || $m->rules->isEmpty() && $m->kind === 'ridicare')
            ->values();
    }

    /**
     * @param  int  $goodsGross  valoarea mărfii după reduceri, cu TVA
     * @return array{code:string, name:string, price:int, transit_days_min:?int, transit_days_max:?int, allows_cod:bool}|null
     */
    public function quote(string $code, int $goodsGross, ?int $weightG = null, string $channel = 'b2c'): ?array
    {
        $method = ShippingMethod::with('rules')->where('code', $code)->where('is_active', true)->first();
        if (! $method) {
            return null;
        }
        $rule = $this->ruleFor($method, $goodsGross, $weightG, $channel);
        if (! $rule && ! ($method->kind === 'ridicare' && $method->rules->isEmpty())) {
            return null;
        }

        return [
            'code' => $method->code,
            'name' => $method->name,
            'kind' => $method->kind,
            'price' => $rule ? (int) $rule->price : 0,
            'transit_days_min' => $method->transit_days_min,
            'transit_days_max' => $method->transit_days_max,
            'allows_cod' => $method->allows_cod,
        ];
    }

    private function ruleFor(ShippingMethod $m, ?int $goods, ?int $weight, string $channel)
    {
        return $m->rules->first(function ($r) use ($goods, $weight, $channel) {
            if ($r->channel !== 'all' && $r->channel !== $channel) {
                return false;
            }
            if ($goods !== null && ($goods < $r->min_subtotal || ($r->max_subtotal !== null && $goods >= $r->max_subtotal))) {
                return false;
            }

            return $weight === null || $r->max_weight_g === null || $weight <= $r->max_weight_g;
        });
    }
}
