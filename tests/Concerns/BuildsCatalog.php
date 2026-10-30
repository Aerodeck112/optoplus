<?php

namespace Tests\Concerns;

use App\Modules\Catalog\Models\Attribute;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Lenses\Models\LensOffering;
use Database\Seeders\CatalogBaseSeeder;
use Database\Seeders\CurrentConfiguratorSeeder;
use Database\Seeders\ProductionStagesSeeder;

/** Catalog de test: lentilele din configuratorul actual + rame fictive. */
trait BuildsCatalog
{
    protected function seedLenses(): void
    {
        $this->seed([CatalogBaseSeeder::class, CurrentConfiguratorSeeder::class, ProductionStagesSeeder::class]);
    }

    protected function frame(int $priceBani = 30000, array $attrs = [], array $spec = []): ProductVariant
    {
        static $n = 0;
        $n++;
        $product = Product::create([
            'type' => Product::TYPE_FRAME, 'name' => "Ramă test fictivă {$n}", 'slug' => "rama-test-{$n}-".uniqid(),
            'status' => Product::STATUS_PUBLISHED, 'configurable_with_lenses' => true, 'availability_mode' => 'la_comanda',
        ]);
        $product->frameSpec()->create(array_merge(['lens_width_mm' => 52, 'bridge_mm' => 18, 'temple_mm' => 140, 'rim_type' => 'full'], $spec));
        foreach ($attrs as $code => $values) {
            $attr = Attribute::where('code', $code)->firstOrFail();
            foreach ((array) $values as $v) {
                $product->attributeValues()->attach($attr->values()->where('code', $v)->firstOrFail()->id);
            }
        }

        return $product->variants()->create(['sku' => 'TEST-'.$n.'-'.uniqid(), 'price' => $priceBani]);
    }

    protected function offering(string $code): LensOffering
    {
        return LensOffering::where('code', $code)->firstOrFail();
    }

    protected function treatmentId(string $code): int
    {
        return (int) \App\Modules\Lenses\Models\LensTreatment::where('code', $code)->value('id');
    }
}
