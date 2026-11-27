<?php

use App\Modules\Reviews\Http\Controllers\ReviewAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:cms.submissions')->group(function () {
    Route::get('recenzii', [ReviewAdminController::class, 'index'])->name('reviews.index');
    Route::post('recenzii/{id}', [ReviewAdminController::class, 'update'])->whereNumber('id')->name('reviews.update');
    Route::post('recenzii/{id}/sterge', [ReviewAdminController::class, 'destroy'])->whereNumber('id')->name('reviews.destroy');
});
