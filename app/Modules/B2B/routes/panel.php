<?php

use App\Modules\B2B\Http\Controllers\Panel\PartnerAdminController as P;
use Illuminate\Support\Facades\Route;

Route::prefix('parteneri')->name('b2b.')->group(function () {
    Route::middleware('permission:b2b.view')->group(function () {
        Route::get('cereri', [P::class, 'requests'])->name('requests');
        Route::get('/', [P::class, 'organizations'])->name('orgs');
        Route::get('{id}', [P::class, 'organization'])->whereNumber('id')->name('org');
        Route::get('oferte', [P::class, 'quotes'])->name('quotes');
        Route::get('oferte/{id}', [P::class, 'quote'])->whereNumber('id')->name('quote');
        Route::get('sesizari', [P::class, 'complaints'])->name('complaints');
    });
    Route::middleware('permission:b2b.approve')->group(function () {
        Route::post('cereri/{id}/aproba', [P::class, 'approveRequest'])->whereNumber('id')->name('requests.approve');
        Route::post('cereri/{id}/respinge', [P::class, 'rejectRequest'])->whereNumber('id')->name('requests.reject');
        Route::post('{id}', [P::class, 'updateOrganization'])->whereNumber('id')->name('org.update');
        Route::post('{id}/invitatie', [P::class, 'inviteMember'])->whereNumber('id')->name('org.invite');
    });
    Route::post('{id}/termene', [P::class, 'terms'])->whereNumber('id')->middleware(['permission:b2b.terms', 'reauth'])->name('org.terms');
    Route::post('{id}/documente', [P::class, 'uploadDocument'])->whereNumber('id')->middleware('permission:b2b.documents')->name('org.document');
    Route::middleware('permission:b2b.quotes')->group(function () {
        Route::post('oferte/{id}/versiune', [P::class, 'storeVersion'])->whereNumber('id')->name('quote.version');
        Route::post('oferte/{id}/trimite', [P::class, 'sendQuote'])->whereNumber('id')->name('quote.send');
    });
    Route::post('sesizari/{id}', [P::class, 'updateComplaint'])->whereNumber('id')->middleware('permission:b2b.complaints')->name('complaints.update');
});
