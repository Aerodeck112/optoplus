<?php

use App\Modules\I18n\Http\Controllers\TranslationController;
use Illuminate\Support\Facades\Route;

Route::prefix('traduceri')->name('i18n.')->middleware('permission:i18n.manage')->group(function () {
    Route::get('/', [TranslationController::class, 'index'])->name('index');
    Route::post('{id}', [TranslationController::class, 'update'])->whereNumber('id')->name('update');
    Route::post('scaneaza', [TranslationController::class, 'scan'])->name('scan');
});
