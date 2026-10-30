<?php

use App\Modules\Storefront\Http\Controllers\CatalogController;
use App\Modules\Storefront\Http\Controllers\FormController;
use App\Modules\Storefront\Http\Controllers\HomeController;
use App\Modules\Storefront\Http\Controllers\LensController;
use App\Modules\Storefront\Http\Controllers\PageController;
use App\Modules\Storefront\Http\Controllers\ProductController;
use App\Modules\Storefront\Http\Controllers\SearchController;
use App\Modules\Storefront\Http\Controllers\SeoController;
use App\Modules\Storefront\Http\Middleware\CachePublicPages;
use Illuminate\Support\Facades\Route;

Route::middleware(CachePublicPages::class)->group(function () {
    Route::get('/', HomeController::class)->name('home');
    Route::get('rame/{filtru?}', [CatalogController::class, 'index'])->where('filtru', '[a-z0-9-]+')->name('catalog');
    Route::get('marci/{brand}', [CatalogController::class, 'brand'])->where('brand', '[a-z0-9-]+')->name('catalog.brand');
    Route::get('produs/{slug}', [ProductController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('product');
    Route::get('lentile', [LensController::class, 'index'])->name('lenses');
    Route::get('lentile/{slug}', [LensController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('lens');
});
Route::get('cautare', SearchController::class)->name('search');
Route::middleware('throttle:6,1')->group(function () {
    Route::post('formular/contact', [FormController::class, 'contact'])->name('form.contact');
    Route::post('formular/parteneriat', [FormController::class, 'partner'])->name('form.partner');
    Route::post('formular/ajutor', [FormController::class, 'help'])->name('form.help');
});
Route::get('sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('robots.txt', [SeoController::class, 'robots'])->name('robots');

// Paginile CMS (productia-noastra, parteneri, ghiduri, politici...), redirecturile vechi și 404 utile.
Route::fallback(PageController::class);
