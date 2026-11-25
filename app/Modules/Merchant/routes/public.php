<?php

use App\Modules\Merchant\Http\Controllers\FeedController;
use Illuminate\Support\Facades\Route;

// 1.0.26: fluxul de produse pentru Google Merchant Center (un flux pe piață: ro, hu, en)
Route::get('feed/google-merchant-{market}.xml', FeedController::class)->where('market', 'ro|hu|en')->middleware('throttle:30,1')->name('merchant.feed');
