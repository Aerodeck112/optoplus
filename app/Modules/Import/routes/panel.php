<?php

use App\Modules\Import\Http\Controllers\ImportController;
use Illuminate\Support\Facades\Route;

Route::prefix('import')->name('import.')->middleware('permission:import.run')->group(function () {
    Route::get('/', [ImportController::class, 'index'])->name('index');
    Route::post('simulare', [ImportController::class, 'upload'])->middleware('throttle:6,1')->name('upload');
    Route::get('rulari/{id}', [ImportController::class, 'show'])->whereNumber('id')->name('show');
    Route::match(['get', 'post'], 'importa', [ImportController::class, 'commit'])->middleware('reauth')->name('commit');
    Route::post('reconciliere', [ImportController::class, 'reconcile'])->name('reconcile');
    Route::post('imagini/reincearca', [ImportController::class, 'retryImages'])->name('images.retry');
    Route::post('imagini/proceseaza', [ImportController::class, 'processImages'])->middleware('throttle:10,1')->name('images.process');
});
