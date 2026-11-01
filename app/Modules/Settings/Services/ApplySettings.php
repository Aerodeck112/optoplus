<?php

namespace App\Modules\Settings\Services;

use App\Core\Settings\Settings;
use App\Modules\Shipping\Models\ShippingMethod;
use Illuminate\Support\Facades\DB;

/**
 * Aplică setările care au și o reprezentare în date: metoda „Livrare prin curier” și tarifele ei
 * (un tarif fix în România și, opțional, gratuitate de la un prag).
 */
class ApplySettings
{
    public function __construct(private Settings $settings) {}

    public function apply(): void
    {
        DB::transaction(function () {
            $method = ShippingMethod::firstOrCreate(['code' => 'curier'], ['name' => 'Livrare prin curier', 'kind' => 'curier', 'carrier' => 'manual', 'countries' => ['RO'], 'sort' => 1]);
            $price = $this->settings->get('shipping.courier_price');
            $free = $this->settings->get('shipping.free_from');
            $method->update([
                'is_active' => (bool) $this->settings->get('shipping.courier_enabled') && $price !== null,
                'allows_cod' => true,
                'transit_days_min' => $this->settings->get('shipping.transit_min'),
                'transit_days_max' => $this->settings->get('shipping.transit_max'),
                'customer_note' => 'Livrare prin curier în România. Timpul de transport se adaugă după ce comanda este gata.',
            ]);
            $method->rules()->delete();
            if ($price !== null) {
                $method->rules()->create(['min_subtotal' => 0, 'max_subtotal' => $free ?: null, 'price' => (int) $price, 'channel' => 'all']);
                if ($free) {
                    $method->rules()->create(['min_subtotal' => (int) $free, 'price' => 0, 'channel' => 'all']);
                }
            }
        });
    }
}
