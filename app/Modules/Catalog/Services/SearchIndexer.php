<?php

namespace App\Modules\Catalog\Services;

use App\Core\Support\Text;
use App\Modules\Catalog\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Indexul de căutare: nume, marcă, SKU-uri, atribute, etichete vechi și sinonime, fără diacritice. O variantă
 * „compactă” (fără spații) permite potriviri de tipul „rayban” ~ „Ray-Ban”, „0rx6550” ~ „0RX 6550”.
 */
class SearchIndexer
{
    public function index(Product $product, array $extraTerms = []): void
    {
        $product->loadMissing('brand', 'variants', 'attributeValues', 'lens.family');
        $parts = [$product->name, $product->brand?->name, $product->lens?->family?->name, $product->lens?->design];
        foreach ($product->variants as $v) {
            $parts[] = $v->sku;
            $parts[] = $v->name;
        }
        foreach ($product->attributeValues as $v) {
            $parts[] = $v->label;
        }
        $parts[] = match ($product->type) {
            'frame' => 'rama rame ochelari de vedere',
            'lens' => 'lentile lentila',
            default => '',
        };
        $parts = array_merge($parts, $extraTerms);
        $terms = Text::searchable(implode(' ', array_filter($parts)));
        $terms = implode(' ', array_unique(explode(' ', $terms)));
        $compact = str_replace(' ', '', $terms).' '.implode(' ', array_map(fn ($v) => str_replace([' ', '-', '/', '.'], '', Text::fold((string) $v->sku)), $product->variants->all()));

        DB::table('search_index')->updateOrInsert(['product_id' => $product->id], [
            'terms' => $terms, 'compact' => $compact, 'updated_at' => now(),
        ]);
    }

    public function rebuild(): int
    {
        $n = 0;
        Product::query()->with('brand', 'variants', 'attributeValues', 'lens.family')->chunkById(200, function ($products) use (&$n) {
            foreach ($products as $p) {
                $this->index($p);
                $n++;
            }
        });

        return $n;
    }
}
