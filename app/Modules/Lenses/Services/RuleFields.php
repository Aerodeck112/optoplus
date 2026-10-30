<?php

namespace App\Modules\Lenses\Services;

/**
 * Câmpurile pe care le pot folosi regulile de compatibilitate (editorul din panou le afișează din această listă).
 * Valorile optice sunt în sutimi de dioptrie (2,00 dpt = 200), în notația cu cilindru negativ.
 */
final class RuleFields
{
    /** @return array<string, array{label:string, type:string, hint?:string}> */
    public static function all(): array
    {
        return [
            'entry' => ['label' => 'Tipul comenzii', 'type' => 'enum', 'hint' => 'cu_rama, fara_rama, rama_mea, ajutor'],
            'usage' => ['label' => 'Utilizarea declarată', 'type' => 'enum', 'hint' => 'distanta, aproape, calculator, toate, nu_stiu'],
            'channel' => ['label' => 'Canal', 'type' => 'enum', 'hint' => 'b2c, b2b'],
            'family' => ['label' => 'Familia lentilei', 'type' => 'enum', 'hint' => 'monofocal, progresiv, degresiv, bifocal'],
            'lens_product' => ['label' => 'Produsul-lentilă (slug)', 'type' => 'enum'],
            'material' => ['label' => 'Materialul (cod)', 'type' => 'enum'],
            'index' => ['label' => 'Indicele (×1000, ex. 1600)', 'type' => 'number'],
            'treatment' => ['label' => 'Tratamentele alese (coduri)', 'type' => 'set'],
            'treatment_option' => ['label' => 'Opțiunile tratamentelor (coduri)', 'type' => 'set'],
            'frame.rim_type' => ['label' => 'Tipul ramei', 'type' => 'enum', 'hint' => 'full, semi_nylon, rimless_screw'],
            'frame.lens_width_mm' => ['label' => 'Lățimea lentilei ramei (mm)', 'type' => 'number'],
            'frame.material' => ['label' => 'Materialul ramei (coduri)', 'type' => 'set'],
            'frame.construction' => ['label' => 'Construcția ramei (ex. clip-on)', 'type' => 'set'],
            'rx.max_abs_sph' => ['label' => 'SPH maxim, în valoare absolută (sutimi)', 'type' => 'number'],
            'rx.max_abs_cyl' => ['label' => 'CYL maxim, în valoare absolută (sutimi)', 'type' => 'number'],
            'rx.max_power' => ['label' => 'Puterea maximă pe un meridian (sutimi)', 'type' => 'number'],
            'rx.max_add' => ['label' => 'Adiția maximă (sutimi)', 'type' => 'number'],
            'rx.anisometropia' => ['label' => 'Diferența de putere între ochi (sutimi)', 'type' => 'number'],
            'rx.has_cyl' => ['label' => 'Rețeta are cilindru', 'type' => 'bool'],
            'pd_mode' => ['label' => 'Modul DP', 'type' => 'enum', 'hint' => 'binocular, monocular'],
        ];
    }

    /** @return array<string, string> */
    public static function operators(): array
    {
        return [
            'eq' => 'este egal cu', 'neq' => 'este diferit de', 'in' => 'este unul dintre', 'not_in' => 'nu este unul dintre',
            'gt' => 'mai mare decât', 'gte' => 'mai mare sau egal cu', 'lt' => 'mai mic decât', 'lte' => 'mai mic sau egal cu',
            'has' => 'conține', 'not_has' => 'nu conține', 'empty' => 'lipsește', 'not_empty' => 'este completat',
        ];
    }
}
