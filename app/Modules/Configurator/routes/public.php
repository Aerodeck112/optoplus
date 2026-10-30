<?php

use App\Modules\Configurator\Http\Controllers\ConfiguratorController;
use Illuminate\Support\Facades\Route;

Route::get('configurator', [ConfiguratorController::class, 'entry'])->name('configurator');
Route::get('configurator/ajutor', [ConfiguratorController::class, 'help'])->name('configurator.help');
Route::post('configurator/start', [ConfiguratorController::class, 'start'])->middleware('throttle:30,1')->name('configurator.start');
Route::get('configurator/{uuid}/pas/{step}', [ConfiguratorController::class, 'step'])->whereNumber('step')->name('configurator.step');
Route::post('configurator/{uuid}/pas/{step}', [ConfiguratorController::class, 'save'])->whereNumber('step')->middleware('throttle:60,1')->name('configurator.save');
Route::post('configurator/{uuid}/calcul', [ConfiguratorController::class, 'calc'])->middleware('throttle:120,1')->name('configurator.calc');
Route::post('configurator/{uuid}/in-cos', [ConfiguratorController::class, 'addToCart'])->name('configurator.cart');
Route::post('configurator/{uuid}/link', [ConfiguratorController::class, 'share'])->middleware('throttle:10,1')->name('configurator.share');
Route::get('c/{token}', [ConfiguratorController::class, 'resume'])->middleware('throttle:20,1')->name('configurator.resume');
