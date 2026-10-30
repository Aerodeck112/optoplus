<?php

namespace App\Modules\Import\Services\WooCommerce;

use App\Core\Audit\Audit;
use App\Core\Support\Text;
use App\Modules\Catalog\Models\Attribute;
use App\Modules\Catalog\Models\AttributeValue;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImage;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Services\SearchIndexer;
use App\Modules\Cms\Models\Redirect;
use App\Modules\Import\Models\ImportItem;
use App\Modules\Import\Models\ImportRun;
use App\Modules\Pricing\Models\Promotion;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Importul produselor dintr-un export WooCommerce.
 *
 *  - simulare (implicit): construiește planul și raportul complet, fără nicio scriere în catalog;
 *  - import: loturi tranzacționale, reluare după întrerupere (cursorul rulării), mapare sursă → țintă în
 *    `import_items` (rerularea nu creează duplicate; înregistrările neschimbate sunt sărite pe baza hash-ului);
 *  - imaginile sunt înregistrate „în așteptare” și descărcate separat, în loturi (ImageDownloader).
 */
class WooCommerceImporter
{
    public const SOURCE = 'woocommerce';

    private array $brandCache = [];

    private array $valueCache = [];

    public function __construct(
        private CsvReader $reader,
        private ProductMapper $mapper,
        private SearchIndexer $indexer,
    ) {}

    /**
     * @param  array{commit?:bool, batch?:int, resume?:bool, urls?:?string, user_id?:?int, max_batches?:?int, force?:bool}  $options
     */
    public function run(string $csvPath, array $options = []): ImportRun
    {
        $commit = (bool) ($options['commit'] ?? false);
        $batch = max(1, (int) ($options['batch'] ?? 50));
        $data = $this->reader->read($csvPath);
        $sha = hash_file('sha256', $csvPath);
        $urls = $this->loadUrls($options['urls'] ?? null);

        [$parents, $variationsByParent, $orphans] = $this->group($data['records']);

        $run = null;
        if ($commit && ($options['resume'] ?? false)) {
            $run = ImportRun::where('source', 'woocommerce_csv')->where('source_sha256', $sha)
                ->where('mode', 'import')->whereIn('status', ['in_curs', 'intrerupt'])->latest('id')->first();
        }
        $run ??= ImportRun::create([
            'source' => 'woocommerce_csv', 'source_name' => basename($csvPath), 'source_sha256' => $sha,
            'mode' => $commit ? 'import' : 'simulare', 'status' => 'in_curs', 'total' => count($parents),
            'started_by' => $options['user_id'] ?? null,
            'counters' => ['creat' => 0, 'actualizat' => 0, 'neschimbat' => 0, 'eroare' => 0, 'avertismente' => 0],
            'report' => ['produse' => [], 'orfani' => $orphans],
        ]);

        $report = $run->report ?? ['produse' => []];
        $counters = $run->counters ?? [];
        $processedBatches = 0;

        for ($offset = $run->cursor; $offset < count($parents); $offset += $batch) {
            $slice = array_slice($parents, $offset, $batch);
            $work = function () use ($slice, $variationsByParent, $urls, $commit, $run, $options, &$report, &$counters) {
                foreach ($slice as $record) {
                    $entry = $this->processOne($record, $variationsByParent[$record['id']] ?? [], $urls[(string) $record['id']] ?? null, $commit, $run, (bool) ($options['force'] ?? false));
                    $counters[$entry['outcome']] = ($counters[$entry['outcome']] ?? 0) + 1;
                    $counters['avertismente'] += count($entry['warnings']);
                    $report['produse'][(string) $record['id']] = $entry;
                }
            };
            $commit ? DB::transaction($work) : $work();

            $run->update(['cursor' => min($offset + $batch, count($parents)), 'counters' => $counters, 'report' => $report]);
            $processedBatches++;
            if (isset($options['max_batches']) && $processedBatches >= $options['max_batches'] && $run->cursor < count($parents)) {
                $run->update(['status' => 'intrerupt']);

                return $run;
            }
        }

        if ($commit) {
            $report['promotii'] = $this->importSalePrices($data['records']);
            $report['redirecturi'] = $this->legacyRedirects();
            Audit::log('import.woocommerce', $run, ['contori' => $counters]);
        }
        $run->update(['status' => 'finalizat', 'finished_at' => now(), 'counters' => $counters, 'report' => $report]);

        return $run;
    }

    /** @return array{0: list<array>, 1: array<int, list<array>>, 2: list<int>} */
    private function group(array $records): array
    {
        $parents = [];
        $variations = [];
        foreach ($records as $r) {
            if ($r['type'] === 'variation') {
                $variations[$r['parent']][] = $r;
            } else {
                $parents[] = $r;
            }
        }
        $ids = array_column($parents, 'id');
        $orphans = array_values(array_diff(array_keys($variations), $ids));
        usort($parents, fn ($a, $b) => $a['id'] <=> $b['id']);

        return [$parents, $variations, $orphans];
    }

    private function loadUrls(?string $path): array
    {
        if (! $path) {
            return [];
        }
        if (! is_file($path)) {
            throw new RuntimeException("Harta URL-urilor nu există: {$path}");
        }
        $json = json_decode((string) file_get_contents($path), true);

        return $json['products'] ?? [];
    }

    private function processOne(array $record, array $variations, ?array $url, bool $commit, ImportRun $run, bool $force): array
    {
        $mapped = $this->mapper->map($record, $variations, $url);
        $plan = $mapped['plan'];
        $hash = hash('sha256', $record['hash'].implode('', array_column($variations, 'hash')).json_encode($url));
        $entry = [
            'nume' => $plan['name'], 'slug' => $plan['slug'], 'variante' => count($plan['variants']),
            'imagini' => count($plan['images']), 'warnings' => $mapped['warnings'], 'errors' => $mapped['errors'],
        ];

        $item = ImportItem::where(['source' => self::SOURCE, 'source_type' => 'product', 'source_id' => (string) $record['id']])->first();
        if ($mapped['errors']) {
            $entry['outcome'] = 'eroare';
            if ($commit) {
                $this->mapItem($item, $record['id'], 'product', null, $hash, $run, 'eroare', $mapped['errors']);
            }

            return $entry;
        }
        if ($item && $item->source_hash === $hash && $item->target_id && ! $force && Product::whereKey($item->target_id)->exists()) {
            $entry['outcome'] = 'neschimbat';
            $entry['product_id'] = $item->target_id;

            return $entry;
        }
        $entry['outcome'] = $item?->target_id ? 'actualizat' : 'creat';
        if (! $commit) {
            return $entry;
        }

        $product = $this->write($plan, $item?->target_id);
        $entry['product_id'] = $product->id;
        $this->mapItem($item, $record['id'], 'product', $product->id, $hash, $run, 'importat', $mapped['warnings']);

        return $entry;
    }

    private function mapItem(?ImportItem $item, int|string $sourceId, string $type, ?int $targetId, string $hash, ImportRun $run, string $status, array $messages): void
    {
        $data = ['target_type' => $type === 'product' ? Product::class : ProductVariant::class, 'target_id' => $targetId,
            'source_hash' => $hash, 'last_run_id' => $run->id, 'status' => $status, 'messages' => $messages];
        if ($item) {
            $item->update($data);
        } else {
            ImportItem::create(['source' => self::SOURCE, 'source_type' => $type, 'source_id' => (string) $sourceId] + $data);
        }
    }

    private function write(array $plan, ?int $existingId): Product
    {
        $product = $existingId ? Product::find($existingId) : null;
        $slug = $this->uniqueSlug($plan['slug'], $product?->id);
        $category = Category::where('path', '/rame/')->first();
        $attributes = [
            'type' => Product::TYPE_FRAME,
            'name' => $plan['name'],
            'slug' => $slug,
            'brand_id' => $this->brandId($plan['brand']),
            'primary_category_id' => $category?->id,
            'status' => $plan['status'],
            'short_description' => $plan['short_description'],
            'description' => $plan['description'],
            'configurable_with_lenses' => true,
            'availability_mode' => 'neconfirmat',
            'legacy_wc_id' => $plan['source_id'],
            'legacy_url' => $plan['legacy_url'],
            'sort' => $plan['sort'],
            'published_at' => $plan['status'] === 'published' ? ($product?->published_at ?? now()) : null,
        ];
        if ($product) {
            // Câmpurile editate în panou după import nu se suprascriu: doar datele care vin din sursă.
            $product->update($attributes);
        } else {
            $product = Product::create($attributes);
        }
        if ($category) {
            $product->categories()->syncWithoutDetaching([$category->id]);
        }

        // Atributele marcate „import” / „descriere” se refac; cele adăugate manual rămân.
        $product->attributeValues()->wherePivotIn('source', ['import', 'descriere'])->detach();
        foreach ($plan['attributes'] as $code => $values) {
            foreach ($values as $valueCode => $source) {
                if ($valueId = $this->valueId($code, $valueCode)) {
                    $product->attributeValues()->syncWithoutDetaching([$valueId => ['source' => $source]]);
                }
            }
        }

        $spec = array_filter($plan['frame_spec'], fn ($v) => $v !== null);
        $sources = array_fill_keys(array_keys($spec), 'descriere');
        $product->frameSpec()->updateOrCreate([], $spec + ['sources' => $sources]);

        $keep = [];
        foreach ($plan['variants'] as $v) {
            $variant = ProductVariant::where('legacy_wc_id', $v['source_id'])->first()
                ?? ProductVariant::where('product_id', $product->id)->where('sku', $v['sku'])->first();
            $data = [
                'product_id' => $product->id,
                'sku' => $this->uniqueSku($v['sku'], $variant?->id),
                'sku_generated' => $v['sku_generated'],
                'gtin' => $v['gtin'],
                'name' => $v['name'],
                'options' => $v['options'],
                'price' => $v['regular_price'],
                'stock_qty' => $v['stock_qty'],
                'weight_g' => $v['weight_g'],
                'is_active' => true,
                'sort' => $v['sort'],
                'legacy_wc_id' => $v['source_id'],
            ];
            if ($variant) {
                $variant->update($data);
            } else {
                $variant = ProductVariant::create($data);
            }
            $keep[] = $variant->id;
        }
        ProductVariant::where('product_id', $product->id)->whereNotIn('id', $keep)->whereNotNull('legacy_wc_id')->update(['is_active' => false]);

        $variantBySource = ProductVariant::where('product_id', $product->id)->pluck('id', 'legacy_wc_id');
        foreach ($plan['images'] as $img) {
            $existing = ProductImage::where('product_id', $product->id)->where('source_url', $img['url'])->first();
            $data = ['sort' => $img['sort'], 'variant_id' => $img['variant_source_id'] ? ($variantBySource[$img['variant_source_id']] ?? null) : null,
                'alt' => $plan['name']];
            if ($existing) {
                $existing->update($data);
            } else {
                ProductImage::create($data + ['product_id' => $product->id, 'source_url' => $img['url'], 'status' => 'pending']);
            }
        }

        $this->indexer->index($product->fresh(['brand', 'variants', 'attributeValues']), $plan['tags']);

        return $product;
    }

    private function brandId(?string $name): ?int
    {
        if (! $name) {
            return null;
        }
        $slug = Text::slug($name);
        if (isset($this->brandCache[$slug])) {
            return $this->brandCache[$slug];
        }
        $brand = Brand::firstOrCreate(['slug' => $slug], [
            'name' => $name,
            'legacy_url' => '/brand/'.$slug.'/',
            // Siteul actual: „Optofarm — marcă proprie Optoplus”. Marcă proprie ≠ producție proprie.
            'is_own_brand' => $slug === 'optofarm',
        ]);

        return $this->brandCache[$slug] = $brand->id;
    }

    private function valueId(string $attribute, string $code): ?int
    {
        $key = $attribute.':'.$code;
        if (! array_key_exists($key, $this->valueCache)) {
            $attr = Attribute::where('code', $attribute)->first();
            $this->valueCache[$key] = $attr ? AttributeValue::where('attribute_id', $attr->id)->where('code', $code)->value('id') : null;
        }

        return $this->valueCache[$key];
    }

    private function uniqueSlug(string $slug, ?int $ignoreId): string
    {
        $candidate = $slug;
        $i = 2;
        while (Product::where('slug', $candidate)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $candidate = $slug.'-'.$i++;
        }

        return $candidate;
    }

    private function uniqueSku(string $sku, ?int $ignoreId): string
    {
        $candidate = $sku;
        $i = 2;
        while (ProductVariant::where('sku', $candidate)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $candidate = $sku.'-'.$i++;
        }

        return $candidate;
    }

    /**
     * Prețurile promoționale din export (fără perioadă) reproduc comportamentul actual al magazinului: o promoție
     * „preț fix” pe produsele respective, activă, marcată ca preluată. Prețul vechi nu se afișează tăiat până la
     * confirmare (anunțarea unei reduceri cere un preț de referință verificabil).
     *
     * @param  list<array<string, mixed>>  $records
     * @return list<array{promotion_id:int, pret:int, produse:int}>
     */
    private function importSalePrices(array $records): array
    {
        $groups = [];
        foreach ($records as $r) {
            if ($r['sale_price'] === '' || $r['type'] === 'variable') {
                continue;
            }
            try {
                $sale = \App\Core\Support\Money::parse($r['sale_price']);
                $regular = \App\Core\Support\Money::parse($r['regular_price']);
            } catch (\InvalidArgumentException) {
                continue;
            }
            $productId = ProductVariant::where('legacy_wc_id', $r['id'])->value('product_id');
            if ($productId && $sale !== null && $regular !== null && $sale < $regular) {
                $groups[$sale][] = $productId;
            }
        }
        $out = [];
        foreach ($groups as $price => $productIds) {
            $name = 'Preț promoțional preluat din magazinul vechi: '.number_format($price / 100, 2, ',', '.').' lei';
            $promo = Promotion::updateOrCreate(['name' => $name, 'source' => 'import WooCommerce'], [
                'customer_label' => 'Preț redus', 'scope' => 'product', 'scope_ids' => array_values(array_unique($productIds)),
                'type' => 'fixed_price', 'value' => $price, 'is_active' => true, 'stackable' => false, 'priority' => 50, 'channel' => 'b2c',
            ]);
            $out[] = ['promotion_id' => $promo->id, 'pret' => $price, 'produse' => count(array_unique($productIds))];
        }

        return $out;
    }

    /**
     * Redirecturile 301 individuale pentru URL-urile vechi care nu se păstrează (categorii, mărci, pagini) și 410 pentru
     * conținutul demonstrativ al temei. Produsele își păstrează URL-ul /produs/<slug>/.
     *
     * @return array{create:int, total:int}
     */
    public function legacyRedirects(): array
    {
        $map = LegacyUrlMap::rules();
        $created = 0;
        foreach (Brand::whereNotNull('legacy_url')->get() as $brand) {
            $map[$brand->legacy_url] = [$brand->url(), 301];
        }
        foreach (Product::whereNotNull('legacy_url')->get(['slug', 'legacy_url', 'type']) as $p) {
            $new = '/produs/'.$p->slug.'/';
            if (rtrim($p->legacy_url, '/').'/' !== $new) {
                $map[$p->legacy_url] = [$new, 301];
            }
        }
        $sitemap = base_path('database/content/woocommerce/sitemap-2026-10-29.json');
        if (is_file($sitemap)) {
            $tags = LegacyUrlMap::tagTargets();
            foreach ((array) (json_decode((string) file_get_contents($sitemap), true)['product_tag'] ?? []) as $url) {
                $path = Redirect::normalizePath($url);
                $slug = basename(rtrim($path, '/'));
                $brand = Brand::where('slug', $slug)->first();
                $map[$path] = [$tags[$slug] ?? ($brand ? $brand->url() : '/cautare/?q='.rawurlencode(str_replace('-', ' ', $slug))), 301];
            }
        }
        foreach ($map as $source => [$target, $code]) {
            $r = Redirect::firstOrNew(['source_path' => $source]);
            if (! $r->exists || $r->origin === 'import') {
                $r->fill(['target' => $target, 'status_code' => $code, 'origin' => 'import', 'is_active' => true,
                    'note' => 'Generat la importul WooCommerce'])->save();
                $created += $r->wasRecentlyCreated ? 1 : 0;
            }
        }

        return ['create' => $created, 'total' => count($map)];
    }
}
