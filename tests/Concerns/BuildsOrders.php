<?php

namespace Tests\Concerns;

use App\Core\Settings\Settings;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Configurator\Models\Configuration;
use App\Modules\Shipping\Models\ShippingMethod;

/** Coș și finalizare prin HTTP, ca un client real (fără JavaScript). Date fictive. */
trait BuildsOrders
{
    use BuildsCatalog;

    protected function enableCheckout(array $settings = []): void
    {
        config(['optoplus.payments.simulation' => true]);
        $this->seed(\Database\Seeders\ShippingPaymentSeeder::class);
        $m = ShippingMethod::where('code', 'curier')->first();
        $m->update(['is_active' => true, 'allows_cod' => true, 'transit_days_min' => 1, 'transit_days_max' => 2]);
        $m->rules()->create(['min_subtotal' => 0, 'price' => 2000]);
        foreach (array_merge(['payments.card_enabled' => true, 'payments.card_gateway' => 'simulare', 'payments.cod_enabled' => true], $settings) as $k => $v) {
            app(Settings::class)->set($k, $v);
        }
    }

    /** Parcurge configuratorul pentru o ramă și adaugă ochelarii în coș. */
    protected function configureGlasses(ProductVariant $frame, string $offering = 'monofocal-standard-150'): Configuration
    {
        $this->post('/configurator/start/', ['intrare' => 'cu_rama', 'varianta' => $frame->id])->assertRedirect();
        $cfg = Configuration::latest('id')->firstOrFail();
        $base = "/configurator/{$cfg->uuid}/pas/";
        $this->post($base.'1/', ['utilizare' => 'distanta'])->assertSessionHasNoErrors();
        $this->post($base.'2/', ['od' => ['sph' => '-2,00'], 'os' => ['sph' => '-1,75'], 'dp_mod' => 'binocular', 'dp' => '63'])->assertSessionHasNoErrors();
        $this->post($base.'3/', ['oferta' => $this->offering($offering)->id])->assertSessionHasNoErrors();
        $this->post($base.'4/', [])->assertSessionHasNoErrors();
        $this->post("/configurator/{$cfg->uuid}/in-cos/", ['acord_reteta' => '1'])->assertRedirect('/cos/');

        return $cfg->fresh();
    }

    protected function checkoutData(array $over = []): array
    {
        $this->get('/comanda/')->assertOk();

        return array_merge([
            'email' => 'client.test@demo.optoplus.invalid', 'telefon' => '0700 000 000', 'nume' => 'Ion Testescu',
            'tip' => 'pf', 'adresa' => 'Str. Fictivă nr. 1', 'localitate' => 'Testești', 'judet' => 'Mureș',
            'livrare' => 'curier', 'plata' => 'card', 'acord_termeni' => '1',
            'cheie' => session('checkout_key'),
        ], $over);
    }
}
