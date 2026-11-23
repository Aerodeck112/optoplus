<?php

use App\Modules\Catalog\Http\Controllers\Panel\BrandController;
use App\Modules\Catalog\Http\Controllers\Panel\CategoryController;
use App\Modules\Catalog\Http\Controllers\Panel\ProductController;
use App\Modules\Catalog\Http\Controllers\Panel\ProductEditorController;
use Illuminate\Support\Facades\Route;

Route::prefix('catalog')->name('catalog.')->group(function () {
    Route::get('produse', [ProductController::class, 'index'])->middleware('permission:catalog.view')->name('products');
    Route::get('produse/{id}', [ProductController::class, 'edit'])->whereNumber('id')->middleware('permission:catalog.view')->name('product');
    Route::post('produse/{id}', [ProductController::class, 'update'])->whereNumber('id')->middleware('permission:catalog.edit')->name('product.update');
    Route::post('produse/{id}/stare', [ProductController::class, 'publish'])->whereNumber('id')->middleware('permission:catalog.publish')->name('product.status');
    // 1.0.24: ștergerea definitivă (doar nepublicate și fără comenzi)
    Route::post('produse/{id}/sterge', [\App\Modules\Catalog\Http\Controllers\Panel\ProductEditorController::class, 'destroy'])->whereNumber('id')->middleware('permission:catalog.publish')->name('product.delete');
    Route::post('produse/{id}/variante', [ProductController::class, 'variants'])->whereNumber('id')->middleware('permission:catalog.prices')->name('product.variants');
    Route::middleware('permission:catalog.edit')->group(function () {
        // 1.0.20: produse noi din panou, copiere, variante, poze
        Route::get('produse/nou', [ProductEditorController::class, 'create'])->name('product.create');
        Route::post('produse', [ProductEditorController::class, 'store'])->middleware('throttle:30,1')->name('product.store');
        Route::post('produse/{id}/copie', [ProductEditorController::class, 'duplicate'])->whereNumber('id')->name('product.duplicate');
        Route::post('produse/{id}/varianta-noua', [ProductEditorController::class, 'addVariant'])->whereNumber('id')->name('product.variant.add');
        Route::post('produse/{id}/poze', [ProductEditorController::class, 'uploadImages'])->whereNumber('id')->middleware('throttle:30,1')->name('product.images');
        Route::post('produse/{id}/poze/{imageId}', [ProductEditorController::class, 'imageAction'])->whereNumber('id')->whereNumber('imageId')->name('product.image');
        BrandController::routes('marci', 'brands');
        CategoryController::routes('categorii', 'categories');
    });
});
