<?php

use App\Modules\Shop\Http\Controllers\CartController;
use Illuminate\Support\Facades\Route;

Route::get('cos', [CartController::class, 'show'])->name('cart');
Route::post('cos/adauga', [CartController::class, 'add'])->middleware('throttle:30,1')->name('cart.add');
Route::post('cos/actualizeaza', [CartController::class, 'update'])->middleware('throttle:60,1')->name('cart.update');
