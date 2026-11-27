<?php

use App\Modules\Reviews\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

// 1.0.27: recenzii fără cont (pe produse și despre magazin); se publică după moderare
Route::post('recenzii', [ReviewController::class, 'store'])->middleware('throttle:5,10,recenzii')->name('reviews.store');
