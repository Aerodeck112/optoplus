<?php

use App\Modules\Settings\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::get('setari/{section?}', [SettingsController::class, 'show'])->name('settings');
Route::post('setari/plati/test-bt', [SettingsController::class, 'testBt'])->middleware(['permission:payments.settings', 'throttle:6,1'])->name('settings.test-bt');
Route::post('setari/{section}', [SettingsController::class, 'save'])->middleware('reauth')->name('settings.save');
