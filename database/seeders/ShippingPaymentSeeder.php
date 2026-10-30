<?php

namespace Database\Seeders;

use App\Modules\Shipping\Models\ShippingMethod;
use Illuminate\Database\Seeder;

/**
 * Metodele de livrare cerute, create **inactive și fără tarife**: curierul, tarifele și punctul de ridicare
 * trebuie confirmate (docs/INTREBARI.md #12–13) și configurate din panou înainte de activare.
 */
class ShippingPaymentSeeder extends Seeder
{
    public function run(): void
    {
        ShippingMethod::updateOrCreate(['code' => 'curier'], [
            'name' => 'Livrare prin curier', 'kind' => 'curier', 'carrier' => 'manual', 'is_active' => false, 'sort' => 1,
            'customer_note' => 'Timpul de transport se adaugă după ce comanda este gata.', 'countries' => ['RO'],
        ]);
        ShippingMethod::updateOrCreate(['code' => 'ridicare'], [
            'name' => 'Ridicare personală', 'kind' => 'ridicare', 'carrier' => null, 'is_active' => false, 'sort' => 2,
            'customer_note' => 'Vă anunțăm când comanda poate fi ridicată.', 'countries' => ['RO'],
        ]);
    }
}
