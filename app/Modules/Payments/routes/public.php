<?php

use App\Modules\Payments\Http\Controllers\SimulationController;
use App\Modules\Payments\Http\Controllers\WebhookController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

Route::post('webhook/plati/{provider}', WebhookController::class)
    ->withoutMiddleware([PreventRequestForgery::class])->middleware('throttle:120,1')->name('payments.webhook');

// BT iPay poate trimite notificarea (callback) prin GET; ambele declanșează doar o interogare la BT.
Route::get('webhook/plati/{provider}', WebhookController::class)->middleware('throttle:120,1');
Route::get('plati/btipay/revenire', \App\Modules\Payments\Http\Controllers\BtReturnController::class)->middleware('throttle:30,1')->name('payments.btipay.return');

Route::get('plati/simulare/{reference}', [SimulationController::class, 'show'])->name('payments.simulation');
Route::post('plati/simulare/{reference}', [SimulationController::class, 'decide'])->middleware('throttle:20,1');
