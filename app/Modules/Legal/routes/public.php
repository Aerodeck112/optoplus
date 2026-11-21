<?php

use App\Modules\Legal\Http\Controllers\WithdrawalController;
use Illuminate\Support\Facades\Route;

// Funcția de retragere din contract (OUG 34/2014 art. 11¹): fără cont, disponibilă permanent.
Route::get('retragere', [WithdrawalController::class, 'form'])->name('legal.withdrawal');
Route::post('retragere', [WithdrawalController::class, 'review'])->middleware('throttle:20,1,retragere')->name('legal.withdrawal.review');
Route::get('retragere/confirmare', [WithdrawalController::class, 'confirmPage'])->name('legal.withdrawal.confirm-page');
Route::post('retragere/confirmare', [WithdrawalController::class, 'confirm'])->middleware('throttle:10,1,retragere')->name('legal.withdrawal.confirm');
Route::get('retragere/trimisa/{uuid}', [WithdrawalController::class, 'done'])->name('legal.withdrawal.done');
Route::get('formular-retragere', [WithdrawalController::class, 'modelForm'])->name('legal.withdrawal.model');
Route::get('garantie-legala', [WithdrawalController::class, 'guarantee'])->name('legal.guarantee');

// Imaginea de previzualizare a videoclipurilor YouTube, servită local (browserul nu contactează Google până la pornire).
Route::get('video-poster/{id}.jpg', \App\Modules\Cms\Http\Controllers\VideoPosterController::class)->where('id', '[A-Za-z0-9_-]{11}')->middleware('throttle:60,1')->name('video.poster');
