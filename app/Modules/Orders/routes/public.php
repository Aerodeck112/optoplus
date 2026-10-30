<?php

use App\Modules\Orders\Http\Controllers\CheckoutController;
use Illuminate\Support\Facades\Route;

Route::get('comanda', [CheckoutController::class, 'show'])->name('checkout');
Route::post('comanda', [CheckoutController::class, 'place'])->middleware('throttle:15,1')->name('checkout.place');
Route::get('comanda/{uuid}', [CheckoutController::class, 'status'])->name('order.status');
Route::post('comanda/{uuid}/plateste', [CheckoutController::class, 'retryPayment'])->middleware('throttle:10,1')->name('order.pay');
