<?php

use App\Modules\Cms\Http\Controllers\FaqAdminController;
use App\Modules\Cms\Http\Controllers\MenuAdminController;
use App\Modules\Cms\Http\Controllers\PageAdminController as P;
use App\Modules\Cms\Http\Controllers\RedirectAdminController;
use Illuminate\Support\Facades\Route;

Route::prefix('site')->name('cms.')->group(function () {
    Route::middleware('permission:cms.edit')->group(function () {
        Route::get('pagini', [P::class, 'index'])->name('pages.index');
        Route::post('pagini', [P::class, 'create'])->name('pages.create');
        Route::get('pagini/{id}/{version?}', [P::class, 'edit'])->whereNumber(['id', 'version'])->name('pages.edit');
        Route::post('pagini/{id}', [P::class, 'save'])->whereNumber('id')->name('pages.save');
        Route::get('pagini/{id}/previzualizare/{version}', [P::class, 'preview'])->whereNumber(['id', 'version'])->name('pages.preview');
    });
    Route::middleware('permission:cms.publish')->group(function () {
        Route::post('pagini/{id}/publica', [P::class, 'publish'])->whereNumber('id')->name('pages.publish');
        Route::post('pagini/{id}/retrage', [P::class, 'unpublish'])->whereNumber('id')->name('pages.unpublish');
        Route::post('pagini/{id}/restaureaza/{version}', [P::class, 'restore'])->whereNumber(['id', 'version'])->name('pages.restore');
    });
    Route::get('media', [\App\Modules\Cms\Http\Controllers\MediaAdminController::class, 'index'])->middleware('permission:cms.edit')->name('media.index');
    Route::middleware('permission:cms.media')->group(function () {
        Route::post('media', [\App\Modules\Cms\Http\Controllers\MediaAdminController::class, 'upload'])->middleware('throttle:30,1')->name('media.upload');
        Route::post('media/{id}', [\App\Modules\Cms\Http\Controllers\MediaAdminController::class, 'update'])->whereNumber('id')->name('media.update');
        Route::post('media/{id}/sterge', [\App\Modules\Cms\Http\Controllers\MediaAdminController::class, 'destroy'])->whereNumber('id')->name('media.destroy');
    });
    Route::middleware('permission:cms.faq')->group(fn () => FaqAdminController::routes('intrebari', 'faq'));
    Route::middleware('permission:cms.menus')->group(fn () => MenuAdminController::routes('meniuri', 'menus'));
    Route::middleware('permission:cms.redirects')->group(function () {
        RedirectAdminController::routes('redirecturi', 'redirects');
        Route::get('negasite', [RedirectAdminController::class, 'notFound'])->name('notfound');
    });
});
