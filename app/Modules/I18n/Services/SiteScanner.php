<?php

namespace App\Modules\I18n\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Găsește textele de tradus direct din conținutul siteului (fără să deschidă pagini și fără date ale clienților):
 * produse, categorii, filtre, pagini CMS (în afară de textele juridice), meniuri, întrebări frecvente, lentile,
 * tratamente, livrare și banda de sus. Ce nu are traducere apare în Panou → Traduceri ca „lipsă”.
 * Produsele se parcurg în loturi (cursor), ca rularea din cron să rămână scurtă.
 */
class SiteScanner
{
    /** Chei din blocurile CMS care nu sunt text pentru vizitator. */
    private const SKIP_KEYS = ['url', 'image', 'media', 'icon', 'color', 'background', 'layout', 'gen', 'brand', 'type', 'slug',
        'video', 'youtube', 'id', 'anchor', 'variant', 'compare_a', 'compare_b', 'product', 'products', 'photo', 'limit', 'style'];

    public function __construct(private Dictionary $dictionary) {}

    public function run(int $productLimit = 40): int
    {
        $this->dictionary->collect = true;
        $this->dictionary->context = 'scanare';
        $seen = 0;
        $add = function (?string $text) use (&$seen) {
            if ($text === null || trim($text) === '') {
                return;
            }
            $seen++;
            if (str_contains($text, '<')) {
                foreach ($this->textNodes($text) as $t) {
                    $this->dictionary->lookup('en', $t);
                }
            } else {
                $this->dictionary->lookup('en', $text);
            }
        };

        foreach (DB::table('categories')->where('is_visible', true)->get(['name', 'intro', 'seo_title', 'seo_description']) as $r) {
            array_map($add, [$r->name, $r->intro, $r->seo_title, $r->seo_description]);
        }
        DB::table('attributes')->pluck('label')->each($add);
        DB::table('attribute_values')->pluck('label')->each($add);
        DB::table('cms_menu_items')->where('is_visible', true)->get(['label', 'description'])->each(fn ($r) => [$add($r->label), $add($r->description)]);
        DB::table('cms_faq_items')->where('is_published', true)->get(['question', 'answer'])->each(fn ($r) => [$add($r->question), $add($r->answer)]);
        DB::table('lens_families')->get(['name', 'customer_description'])->each(fn ($r) => [$add($r->name), $add($r->customer_description)]);
        DB::table('lens_treatments')->where('is_active', true)->get(['name', 'benefits', 'limits'])->each(fn ($r) => [$add($r->name), $add($r->benefits), $add($r->limits)]);
        DB::table('lens_treatment_options')->pluck('label')->each($add);
        DB::table('lens_materials')->get(['name', 'customer_description'])->each(fn ($r) => [$add($r->name), $add($r->customer_description)]);
        DB::table('lens_products')->pluck('customer_summary')->each($add);
        DB::table('shipping_methods')->where('is_active', true)->get(['name', 'customer_note'])->each(fn ($r) => [$add($r->name), $add($r->customer_note)]);
        DB::table('brands')->pluck('description')->each($add);
        DB::table('product_variants')->whereNotNull('name')->distinct()->pluck('name')->each($add);
        foreach (['site.topbar_text', 'site.prelaunch_message'] as $key) {
            $v = json_decode((string) DB::table('settings')->where('key', $key)->value('value'), true);
            $add(is_string($v) ? $v : null);
        }

        // Paginile CMS publicate (textele juridice rămân în română, deci nu se traduc).
        foreach (DB::table('cms_pages')->where('status', 'published')->where('kind', '!=', 'policy')->whereNotNull('published_version_id')->get() as $page) {
            $v = DB::table('cms_page_versions')->where('id', $page->published_version_id)->first();
            if (! $v) {
                continue;
            }
            $add($v->title);
            $this->walk(json_decode((string) $v->seo, true) ?: [], $add);
            $this->walk(json_decode((string) $v->blocks, true) ?: [], $add);
        }

        // Produsele, în loturi.
        $cursor = (int) Cache::get('i18n.scan.cursor', 0);
        $products = DB::table('products')->where('status', 'published')->where('id', '>', $cursor)->orderBy('id')->limit($productLimit)
            ->get(['id', 'name', 'short_description', 'description', 'seo_title', 'seo_description']);
        foreach ($products as $p) {
            array_map($add, [$p->name, $p->short_description, $p->description, $p->seo_title, $p->seo_description]);
        }
        Cache::forever('i18n.scan.cursor', $products->count() < $productLimit ? 0 : (int) $products->last()->id);

        return $this->dictionary->flushMissing() / max(1, count(\App\Modules\I18n\Support\Locales::translated()));
    }

    private function walk(array $data, callable $add): void
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array($key, self::SKIP_KEYS, true)) {
                continue;
            }
            if (is_array($value)) {
                $this->walk($value, $add);
            } elseif (is_string($value) && ! preg_match('~^(https?:|/|#|[a-z0-9_-]+$)~', $value)) {
                $add($value);
            }
        }
    }

    /** @return list<string> */
    private function textNodes(string $html): array
    {
        $out = [];
        foreach (preg_split('~<[^>]+>~', $html) ?: [] as $t) {
            if (trim($t) !== '') {
                $out[] = $t;
            }
        }

        return $out;
    }
}
