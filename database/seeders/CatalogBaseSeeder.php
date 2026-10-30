<?php

namespace Database\Seeders;

use App\Modules\Catalog\Models\Attribute;
use App\Modules\Catalog\Models\AttributeValue;
use App\Modules\Catalog\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Structura de bază a catalogului: categoria „Rame” și atributele filtrabile. Valorile atributelor provin din
 * categoriile și descrierile siteului actual (gen, material, clip-on, tip ramă, formă).
 */
class CatalogBaseSeeder extends Seeder
{
    public function run(): void
    {
        Category::updateOrCreate(['path' => '/rame/'], [
            'kind' => 'rame', 'name' => 'Ochelari și rame', 'slug' => 'rame', 'sort' => 1,
            'intro' => 'Rame de vedere din catalogul Optoplus. Le poți comanda simple sau cu lentile produse în laboratorul nostru.',
        ]);

        $attributes = [
            [Attribute::GEN, 'Pentru', true, 1, [['femei', 'Femei'], ['barbati', 'Bărbați'], ['copii', 'Copii'], ['unisex', 'Unisex']]],
            [Attribute::MATERIAL, 'Material', true, 2, [['metal', 'Metal'], ['acetat', 'Acetat'], ['plastic', 'Plastic'], ['titan', 'Titan'], ['tr90', 'TR90']]],
            [Attribute::CONSTRUCTIE, 'Construcție', true, 3, [['clip-on', 'Clip-on (cu lentile de soare atașabile)']]],
            [Attribute::TIP_RAMA, 'Tip ramă', false, 4, [['full', 'Ramă întreagă'], ['semi_nylon', 'Semi-ramă (fir de nylon)'], ['rimless_screw', 'Fără ramă (șuruburi/capse)']]],
            [Attribute::FORMA, 'Formă', false, 5, [['rotunda', 'Rotundă'], ['ovala', 'Ovală'], ['dreptunghiulara', 'Dreptunghiulară'], ['patrata', 'Pătrată'], ['cat-eye', 'Cat-eye'], ['aviator', 'Aviator'], ['geometrica', 'Geometrică'], ['neregulata', 'Neregulată']]],
            [Attribute::CULOARE, 'Culoare', false, 6, []],
        ];
        foreach ($attributes as [$code, $label, $indexable, $sort, $values]) {
            $attr = Attribute::updateOrCreate(['code' => $code], [
                'label' => $label, 'type' => 'multiselect', 'is_filterable' => true, 'is_indexable' => $indexable, 'sort' => $sort,
            ]);
            foreach ($values as $i => [$vcode, $vlabel]) {
                AttributeValue::updateOrCreate(['attribute_id' => $attr->id, 'code' => $vcode], [
                    'label' => $vlabel, 'slug' => $vcode, 'sort' => $i,
                ]);
            }
        }
    }
}
