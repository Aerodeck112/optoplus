<?php

use App\Modules\Catalog\Http\Controllers\Panel\BrandController;
use App\Modules\Catalog\Http\Controllers\Panel\CategoryController;
use App\Modules\Catalog\Http\Controllers\Panel\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('catalog')->name('catalog.')->group(function () {
    Route::get('produse', [ProductController::class, 'index'])->middleware('permission:catalog.view')->name('products');
    Route::get('produse/{id}', [ProductController::class, 'edit'])->whereNumber('id')->middleware('permission:catalog.view')->name('product');
    Route::post('produse/{id}', [ProductController::class, 'update'])->whereNumber('id')->middleware('permission:catalog.edit')->name('product.update');
    Route::post('produse/{id}/stare', [ProductController::class, 'publish'])->whereNumber('id')->middleware('permission:catalog.publish')->name('product.status');
    Route::post('produse/{id}/variante', [ProductController::class, 'variants'])->whereNumber('id')->middleware('permission:catalog.prices')->name('product.variants');
    Route::middleware('permission:catalog.edit')->group(function () {
        BrandController::routes('marci', 'brands');
        CategoryController::routes('categorii', 'categories');
    });
});
