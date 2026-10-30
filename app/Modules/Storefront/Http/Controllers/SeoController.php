<?php

namespace App\Modules\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\AttributeValue;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Product;
use App\Modules\Cms\Models\Page;
use Illuminate\Support\Facades\Cache;

/** sitemap.xml (doar pagini indexabile, cu URL canonic) și robots.txt (zonele private și parametrii excluși). */
class SeoController extends Controller
{
    public function sitemap()
    {
        $xml = Cache::remember('seo.sitemap', 3600, function () {
            $base = rtrim((string) config('optoplus.site.canonical_host'), '/');
            $urls = [['/', now()]];
            foreach (Page::published()->where('path', '!=', '/')->with('publishedVersion')->get() as $p) {
                if (empty($p->publishedVersion?->seo['noindex'])) {
                    $urls[] = [$p->path, $p->updated_at];
                }
            }
            $urls[] = ['/rame/', now()];
            $urls[] = ['/lentile/', now()];
            foreach (AttributeValue::whereHas('attribute', fn ($a) => $a->where('is_indexable', true))
                ->whereHas('products', fn ($p) => $p->where('status', 'published'))->get() as $v) {
                $urls[] = ['/rame/'.$v->slug.'/', null];
            }
            foreach (Brand::whereHas('products', fn ($p) => $p->where('status', 'published'))->get() as $b) {
                $urls[] = [$b->url(), $b->updated_at];
            }
            foreach (Product::published()->where('noindex', false)->get(['slug', 'type', 'updated_at']) as $p) {
                $urls[] = [$p->url(), $p->updated_at];
            }
            $out = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
            foreach ($urls as [$path, $mod]) {
                $out .= '  <url><loc>'.htmlspecialchars($base.$path, ENT_XML1).'</loc>'.($mod ? '<lastmod>'.$mod->toAtomString().'</lastmod>' : '').'</url>'."\n";
            }

            return $out.'</urlset>'."\n";
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots()
    {
        $base = rtrim((string) config('optoplus.site.canonical_host'), '/');
        $lines = config('optoplus.stage') === 'production'
            ? ['User-agent: *', 'Disallow: /panou/', 'Disallow: /cont/', 'Disallow: /b2b/', 'Disallow: /cos/', 'Disallow: /finalizare-comanda/',
                'Disallow: /configurator/c/', 'Disallow: /comanda/', 'Disallow: /cautare/', 'Disallow: /*?*ordine=', 'Disallow: /*?*pret_', '', 'Sitemap: '.$base.'/sitemap.xml']
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
