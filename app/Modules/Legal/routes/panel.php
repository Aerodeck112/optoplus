<?php

use App\Modules\Legal\Http\Controllers\WithdrawalAdminController;
use Illuminate\Support\Facades\Route;

Route::get('retrageri', [WithdrawalAdminController::class, 'index'])->middleware('permission:orders.view')->name('legal.withdrawals');
Route::post('retrageri/{id}', [WithdrawalAdminController::class, 'update'])->whereNumber('id')->middleware('permission:orders.manage')->name('legal.withdrawals.update');
