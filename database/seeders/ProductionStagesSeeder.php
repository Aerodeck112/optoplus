<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Fluxul de producție din cerință, configurabil din panou: textele pentru client se pot edita; etapele se pot
 * dezactiva sau reordona. „Aprobată pentru producție” cere permisiunea specialistului optic.
 */
class ProductionStagesSeeder extends Seeder
{
    public function run(): void
    {
        $stages = [
            ['primita', 'Comandă primită', 'Comanda a fost primită', 'Am primit comanda și o pregătim pentru verificare.', false, null, false],
            ['verificare_reteta', 'Verificare rețetă', 'Verificăm rețeta', 'Un specialist optic verifică rețeta și lentilele alese înainte de producție.', false, null, false],
            ['clarificari', 'Clarificări', 'Avem nevoie de o clarificare', 'Vă contactăm pentru o informație lipsă (de exemplu o măsurătoare).', false, null, false],
            ['aprobata_productie', 'Aprobată pentru producție', 'Rețeta a fost verificată', 'Specialistul a confirmat rețeta; lentilele intră în producție.', false, 'production.approve', false],
            ['in_lucru', 'În lucru', 'Lentilele sunt în producție', 'Lentilele se fac acum în laboratorul nostru.', false, null, false],
            ['control_calitate', 'Control calitate', 'Control de calitate', 'Verificăm lentilele finite.', false, null, false],
            ['montaj', 'Montaj', 'Montăm lentilele în ramă', 'Lentilele sunt montate și ajustate în rama aleasă.', true, null, false],
            ['pregatita', 'Pregătită', 'Comanda e pregătită', 'Comanda este gata de expediere sau de ridicare.', false, null, false],
            ['expediata', 'Expediată', 'Am predat coletul curierului', 'Coletul este la curier; urmăriți-l cu numărul AWB.', false, null, true],
        ];
        foreach ($stages as $i => [$code, $name, $label, $desc, $mounting, $perm, $terminal]) {
            DB::table('production_stages')->updateOrInsert(['code' => $code], [
                'name' => $name, 'customer_label' => $label, 'customer_description' => $desc, 'sort' => ($i + 1) * 10,
                'visible_to_customer' => true, 'only_if_mounting' => $mounting, 'required_permission' => $perm,
                'is_terminal' => $terminal, 'is_active' => true, 'updated_at' => now(), 'created_at' => now(),
            ]);
        }
    }
}
