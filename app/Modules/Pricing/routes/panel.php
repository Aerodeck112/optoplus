<?php

use App\Modules\Pricing\Http\Controllers\PriceListController as L;
use App\Modules\Pricing\Http\Controllers\PromotionController as P;
use Illuminate\Support\Facades\Route;

Route::prefix('preturi')->name('pricing.')->group(function () {
    // 1.0.27: gestionarea prețurilor (modificări în masă, reduceri rapide, istoric cu anulare)
    Route::middleware('permission:catalog.prices')->group(function () {
        Route::get('gestionare', [\App\Modules\Pricing\Http\Controllers\PriceToolController::class, 'index'])->name('tools');
        Route::post('gestionare', [\App\Modules\Pricing\Http\Controllers\PriceToolController::class, 'apply'])->middleware('throttle:20,1')->name('tools.apply');
        Route::post('gestionare/{id}/anuleaza', [\App\Modules\Pricing\Http\Controllers\PriceToolController::class, 'revert'])->whereNumber('id')->name('tools.revert');
    });
    Route::middleware('permission:pricing.view')->group(function () {
        Route::get('promotii', [P::class, 'index'])->name('promotions.index');
        Route::get('promotii/{id}', [P::class, 'edit'])->whereNumber('id')->name('promotions.edit');
        Route::get('liste', [L::class, 'index'])->name('lists.index');
        Route::get('liste/{id}', [L::class, 'show'])->whereNumber('id')->name('lists.show');
    });
    Route::middleware('permission:pricing.manage')->group(function () {
        Route::get('promotii/noua', [P::class, 'create'])->name('promotions.create');
        Route::post('promotii', [P::class, 'save'])->name('promotions.store');
        Route::post('promotii/{id}', [P::class, 'save'])->whereNumber('id')->name('promotions.update');
        Route::post('promotii/{id}/cupoane', [P::class, 'addCoupon'])->whereNumber('id')->name('promotions.coupon');
        Route::post('promotii/{id}/cupoane/{coupon}', [P::class, 'toggleCoupon'])->whereNumber(['id', 'coupon'])->name('promotions.coupon.toggle');
        Route::post('liste', [L::class, 'save'])->name('lists.store');
        Route::post('liste/{id}', [L::class, 'save'])->whereNumber('id')->name('lists.update');
        Route::post('liste/{id}/preturi', [L::class, 'addItems'])->whereNumber('id')->name('lists.items');
        Route::post('liste/{id}/preturi/{item}/sterge', [L::class, 'removeItem'])->whereNumber(['id', 'item'])->name('lists.items.remove');
    });
});
