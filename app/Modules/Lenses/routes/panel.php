<?php

use App\Modules\Lenses\Http\Controllers\LensAdminController as L;
use App\Modules\Lenses\Http\Controllers\TreatmentAdminController as T;
use Illuminate\Support\Facades\Route;

Route::prefix('lentile')->name('lenses.')->group(function () {
    Route::middleware('permission:lenses.view')->group(function () {
        Route::get('/', [L::class, 'index'])->name('index');
        Route::get('oferte/{id}', [L::class, 'offering'])->whereNumber('id')->name('offering');
        Route::get('reguli', [L::class, 'rules'])->name('rules.index');
        Route::get('reguli/noua', [L::class, 'rule'])->name('rules.create');
        Route::get('reguli/{id}', [L::class, 'rule'])->whereNumber('id')->name('rules.edit');
        Route::post('reguli/test', [L::class, 'testRule'])->name('rules.test');
        Route::get('tratamente', [T::class, 'index'])->name('treatments.index');
        Route::get('tratamente/{id}', [T::class, 'edit'])->whereNumber('id')->name('treatments.edit');
    });
    Route::middleware('permission:lenses.edit')->group(function () {
        Route::post('oferte/{id}', [L::class, 'updateOffering'])->whereNumber('id')->name('offering.update');
        Route::post('reguli', [L::class, 'saveRule'])->name('rules.store');
        Route::post('reguli/{id}', [L::class, 'saveRule'])->whereNumber('id')->name('rules.update');
        Route::get('tratamente/nou', [T::class, 'edit'])->name('treatments.create');
        Route::post('tratamente', [T::class, 'save'])->name('treatments.store');
        Route::post('tratamente/{id}', [T::class, 'save'])->whereNumber('id')->name('treatments.update');
    });
});
