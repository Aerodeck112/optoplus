<?php

use App\Modules\Orders\Http\Controllers\Panel\OrderController;
use Illuminate\Support\Facades\Route;

Route::prefix('comenzi')->name('orders.')->group(function () {
    Route::middleware('permission:orders.view')->group(function () {
        Route::get('/', [OrderController::class, 'index'])->name('index');
        Route::get('{uuid}', [OrderController::class, 'show'])->whereUuid('uuid')->name('show');
    });
    Route::middleware('permission:orders.manage')->group(function () {
        Route::post('{uuid}/confirma', [OrderController::class, 'confirm'])->name('confirm');
        Route::post('{uuid}/nota', [OrderController::class, 'note'])->name('note');
        Route::post('{uuid}/livrare', [OrderController::class, 'shipment'])->name('shipment');
        Route::post('{uuid}/livrare/{id}', [OrderController::class, 'updateShipment'])->whereNumber('id')->name('shipment.update');
        Route::post('{uuid}/factura', [OrderController::class, 'invoice'])->name('invoice');
    });
    Route::post('{uuid}/anuleaza', [OrderController::class, 'cancel'])->middleware(['permission:orders.cancel', 'reauth'])->name('cancel');
    Route::post('{uuid}/plati/{payment}/confirma', [OrderController::class, 'confirmPayment'])->whereNumber('payment')->middleware(['permission:payments.confirm', 'reauth'])->name('payment.confirm');
    Route::post('{uuid}/plati/{payment}/ramburseaza', [OrderController::class, 'refund'])->whereNumber('payment')->middleware(['permission:payments.refund', 'reauth'])->name('payment.refund');
    Route::get('export', [OrderController::class, 'export'])->middleware(['permission:orders.export', 'reauth'])->name('export');
});
