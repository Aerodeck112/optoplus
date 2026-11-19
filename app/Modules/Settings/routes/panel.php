<?php

use App\Modules\Settings\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::get('setari/{section?}', [SettingsController::class, 'show'])->name('settings');
Route::post('setari/plati/test-bt', [SettingsController::class, 'testBt'])->middleware(['permission:payments.settings', 'throttle:6,1'])->name('settings.test-bt');
Route::post('setari/plati/test-stripe', [SettingsController::class, 'testStripe'])->middleware(['permission:payments.settings', 'throttle:6,1'])->name('settings.test-stripe');
Route::post('setari/valute/curs', [SettingsController::class, 'fetchRates'])->middleware(['permission:pricing.manage', 'throttle:6,1'])->name('settings.rates');
Route::post('setari/valute/geoip', [SettingsController::class, 'updateGeo'])->middleware(['permission:pricing.manage', 'throttle:3,1'])->name('settings.geoip');
Route::post('setari/email/test', [SettingsController::class, 'testMail'])->middleware(['permission:settings.manage', 'throttle:6,1'])->name('settings.test-mail');
Route::post('setari/{section}', [SettingsController::class, 'save'])->middleware('reauth')->name('settings.save');
