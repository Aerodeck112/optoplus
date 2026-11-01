<?php

namespace Database\Seeders;

use App\Core\Settings\Settings;
use App\Modules\Lenses\Models\LensOffering;
use App\Modules\Settings\Services\ApplySettings;
use Illuminate\Database\Seeder;

/**
 * Datele comerciale confirmate de Optoplus (01.11.2026) și datele firmei din registrul public ANAF.
 * Se completează doar setările încă goale: ce s-a modificat din panou nu se suprascrie la reinstalare.
 */
class BusinessSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = app(Settings::class);
        $defaults = [
            // ANAF, serviciul public PlatitorTvaRest v9, interogare din 01.11.2026 pentru CUI 29223337.
            'company.name' => 'OFA SRL',
            'company.vat_code' => '29223337',
            'company.registry' => 'J2011001019267',
            'company.address' => 'Sat Crăciunești nr. 235/A, C5, com. Crăciunești, jud. Mureș',
            // ANAF indică firma ca neînregistrată în scopuri de TVA la data interogării; se schimbă din Setări → Firma.
            'company.vat_payer' => false,
            // Confirmat de Optoplus: TVA 21%, modificabil din Setări → TVA.
            'tax.vat_lenses_bp' => 2100, 'tax.vat_frames_bp' => 2100, 'tax.vat_default_bp' => 2100, 'tax.vat_shipping_bp' => 2100,
            'tax.confirmed' => true,
            // Confirmat: livrare prin orice curier, 22 lei în România; termen de execuție 2–5 zile lucrătoare.
            'shipping.courier_enabled' => true, 'shipping.courier_price' => 2200,
            'orders.production_days_min' => 2, 'orders.production_days_max' => 5,
            // Confirmat: numerar la livrare, card (BT iPay), transfer bancar. Cardul și transferul pornesc după
            // completarea credențialelor BT și a IBAN-ului în Setări → Plăți.
            'payments.cod_enabled' => true,
            'payments.card_enabled' => false, 'payments.card_gateway' => 'btipay',
            'payments.btipay.environment' => 'test', 'payments.btipay.force_3ds' => true,
            'payments.btipay.base_url_test' => 'https://ecclients-sandbox.btrl.ro',
            'payments.btipay.base_url_production' => 'https://ecclients.btrl.ro',
            'payments.bank_transfer_enabled' => false,
        ];
        foreach ($defaults as $key => $value) {
            if (! array_key_exists($key, $settings->all())) {
                $settings->set($key, $value);
            }
        }
        // Termenul de execuție confirmat se aplică ofertelor de lentile care nu au încă un termen propriu.
        LensOffering::whereNull('lead_time_days_min')->update(['lead_time_days_min' => 2, 'lead_time_days_max' => 5]);
        app(ApplySettings::class)->apply();
    }
}
