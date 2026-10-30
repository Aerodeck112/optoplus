<?php

use App\Modules\B2B\Http\Controllers\InvitationController;
use App\Modules\B2B\Http\Controllers\PortalController;
use App\Modules\B2B\Http\Middleware\EnsurePartner;
use Illuminate\Support\Facades\Route;

Route::get('portal/invitatie/{token}', [InvitationController::class, 'show'])->middleware('throttle:20,1');
Route::post('portal/invitatie/{token}', [InvitationController::class, 'accept'])->middleware('throttle:10,1');

Route::prefix('portal')->middleware(['auth:client', EnsurePartner::class])->group(function () {
    Route::get('/', [PortalController::class, 'dashboard'])->name('portal');
    Route::get('comenzi', [PortalController::class, 'ordersIndex'])->name('portal.orders');
    Route::get('comenzi/{uuid}', [PortalController::class, 'orderShow'])->name('portal.order');
    Route::get('oferte', [PortalController::class, 'quotesIndex'])->name('portal.quotes');
    Route::get('oferte/{id}', [PortalController::class, 'quoteShow'])->whereNumber('id');
    Route::get('import/{id}', [PortalController::class, 'importReport'])->whereNumber('id');

    Route::middleware(EnsurePartner::class.':order')->group(function () {
        Route::get('comanda-rapida', [PortalController::class, 'quickOrder'])->name('portal.quick');
        Route::post('comanda-rapida', [PortalController::class, 'quickOrderStore'])->middleware('throttle:30,1');
        Route::get('import', [PortalController::class, 'importForm'])->name('portal.import');
        Route::get('import/model.csv', [PortalController::class, 'importTemplate']);
        Route::post('import', [PortalController::class, 'importStore'])->middleware('throttle:10,1');
        Route::get('cos', [PortalController::class, 'cart'])->name('portal.cart');
        Route::post('cos/elimina', [PortalController::class, 'cartRemove']);
        Route::get('finalizare', [PortalController::class, 'checkout'])->name('portal.checkout');
        Route::post('finalizare', [PortalController::class, 'placeOrder'])->middleware('throttle:15,1');
        Route::post('comenzi/{uuid}/din-nou', [PortalController::class, 'reorder']);
        Route::post('comenzi/{uuid}/sesizare', [PortalController::class, 'complaint'])->middleware('throttle:10,1');
        Route::post('oferte', [PortalController::class, 'quoteRequest'])->middleware('throttle:10,1');
        Route::post('oferte/{id}/accepta', [PortalController::class, 'quoteAccept'])->whereNumber('id');
    });

    Route::middleware(EnsurePartner::class.':documents')->group(function () {
        Route::get('documente', [PortalController::class, 'documents'])->name('portal.documents');
        Route::get('documente/{uuid}', [PortalController::class, 'documentDownload'])->middleware('throttle:60,1');
    });

    Route::middleware(EnsurePartner::class.':manage_users')->group(function () {
        Route::get('utilizatori', [PortalController::class, 'users'])->name('portal.users');
        Route::post('utilizatori', [PortalController::class, 'invite'])->middleware('throttle:10,1');
        Route::post('utilizatori/{id}', [PortalController::class, 'updateMember'])->whereNumber('id');
    });
});
