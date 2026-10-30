<?php

namespace App\Modules\Storefront;

use App\Core\Modules\ModuleServiceProvider as BaseProvider;
use App\Modules\Storefront\Services\PageCache;
use App\Modules\Storefront\Services\SiteContext;

/** Siteul public: prima pagină, catalogul, paginile de produs și de lentile, căutarea, sitemap, robots. */
class ModuleServiceProvider extends BaseProvider
{
    public function key(): string
    {
        return 'storefront';
    }

    public function label(): string
    {
        return 'Site public';
    }

    public function register(): void
    {
        parent::register();
        $this->app->scoped(SiteContext::class);
    }

    public function boot(): void
    {
        parent::boot();

        // Orice modificare care schimbă ce vede vizitatorul invalidează paginile din cache.
        $models = [
            \App\Modules\Catalog\Models\Product::class, \App\Modules\Catalog\Models\ProductVariant::class,
            \App\Modules\Catalog\Models\ProductImage::class, \App\Modules\Catalog\Models\Brand::class,
            \App\Modules\Pricing\Models\Promotion::class, \App\Modules\Lenses\Models\LensOffering::class,
            \App\Modules\Lenses\Models\LensOfferingTreatment::class, \App\Modules\Lenses\Models\LensTreatment::class,
            \App\Modules\Cms\Models\MenuItem::class, \App\Modules\Cms\Models\FaqItem::class,
        ];
        foreach ($models as $model) {
            $model::saved(fn () => app(PageCache::class)->flush());
            $model::deleted(fn () => app(PageCache::class)->flush());
        }
    }
}
