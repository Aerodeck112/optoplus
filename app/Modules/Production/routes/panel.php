<?php

use App\Modules\Production\Http\Controllers\ProductionController;
use Illuminate\Support\Facades\Route;

Route::prefix('productie')->name('production.')->group(function () {
    Route::middleware('permission:production.view')->group(function () {
        Route::get('/', [ProductionController::class, 'index'])->name('index');
        Route::get('de-verificat', [ProductionController::class, 'review'])->name('review');
        Route::get('{id}', [ProductionController::class, 'show'])->whereNumber('id')->name('show');
    });
    Route::middleware('permission:production.manage')->group(function () {
        Route::post('{id}/etapa', [ProductionController::class, 'move'])->whereNumber('id')->name('move');
        Route::post('{id}/alocare', [ProductionController::class, 'assign'])->whereNumber('id')->name('assign');
    });
    Route::post('{id}/refacere', [ProductionController::class, 'remake'])->whereNumber('id')->middleware('permission:production.remake')->name('remake');
});
Route::get('retete/fisier/{uuid}', [ProductionController::class, 'file'])->whereUuid('uuid')->middleware(['permission:prescriptions.view', 'throttle:60,1'])->name('prescriptions.file');
