<?php

use App\Modules\Customers\Http\Controllers\AccountController;
use App\Modules\Customers\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('cont')->group(function () {
    Route::middleware('guest:client')->group(function () {
        Route::get('autentificare', [AuthController::class, 'showLogin'])->name('cont.login');
        Route::post('autentificare', [AuthController::class, 'login'])->middleware('throttle:20,1');
        Route::get('inregistrare', [AuthController::class, 'showRegister'])->name('cont.register');
        Route::post('inregistrare', [AuthController::class, 'register'])->middleware('throttle:6,1');
        Route::get('parola', [AuthController::class, 'showForgot'])->name('cont.parola');
        Route::post('parola', [AuthController::class, 'sendReset'])->middleware('throttle:5,1');
        Route::get('parola/{token}', [AuthController::class, 'showReset'])->name('cont.parola.reset');
        Route::post('parola/reset', [AuthController::class, 'reset'])->middleware('throttle:10,1');
    });

    Route::middleware('auth:client')->group(function () {
        Route::get('/', [AccountController::class, 'orders'])->name('cont');
        Route::get('configuratii', [AccountController::class, 'configurations'])->name('cont.configuratii');
        Route::post('configuratii/{uuid}/sterge', [AccountController::class, 'deleteConfiguration']);
        Route::get('retete', [AccountController::class, 'prescriptions'])->name('cont.retete');
        Route::post('retete/{id}/sterge', [AccountController::class, 'deletePrescription'])->whereNumber('id');
        Route::get('adrese', [AccountController::class, 'addresses'])->name('cont.adrese');
        Route::post('adrese', [AccountController::class, 'storeAddress']);
        Route::post('adrese/{id}/sterge', [AccountController::class, 'deleteAddress'])->whereNumber('id');
        Route::get('date', [AccountController::class, 'profile'])->name('cont.date');
        Route::post('date', [AccountController::class, 'updateProfile']);
        Route::get('date/export', [AccountController::class, 'export'])->middleware('throttle:5,1');
        Route::post('date/stergere', [AccountController::class, 'requestDeletion'])->middleware('throttle:3,1');
        Route::post('iesire', [AuthController::class, 'logout'])->name('cont.logout');
    });
});
