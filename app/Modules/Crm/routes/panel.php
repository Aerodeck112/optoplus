<?php

use App\Modules\Crm\Http\Controllers\CrmController;
use Illuminate\Support\Facades\Route;

Route::prefix('crm')->name('crm.')->group(function () {
    Route::middleware('permission:crm.view')->group(function () {
        Route::get('cereri', [CrmController::class, 'leads'])->name('leads');
        Route::get('cereri/{id}', [CrmController::class, 'lead'])->whereNumber('id')->name('lead');
        Route::get('sarcini', [CrmController::class, 'tasks'])->name('tasks');
        Route::post('sarcini/{id}/gata', [CrmController::class, 'completeTask'])->whereNumber('id')->name('tasks.complete');
    });
    Route::middleware('permission:crm.manage')->group(function () {
        Route::post('cereri', [CrmController::class, 'storeLead'])->name('leads.store');
        Route::post('cereri/{id}', [CrmController::class, 'updateLead'])->whereNumber('id')->name('lead.update');
        Route::post('cereri/{id}/nota', [CrmController::class, 'note'])->whereNumber('id')->name('lead.note');
        Route::post('cereri/{id}/sarcina', [CrmController::class, 'storeTask'])->whereNumber('id')->name('lead.task');
    });
});
