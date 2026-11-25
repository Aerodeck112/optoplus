<?php

namespace App\Modules\Merchant\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Merchant\Services\MerchantFeed;
use Illuminate\Http\Request;

/** Fluxul de produse citit de Google Merchant Center (/feed/google-merchant-ro.xml, -hu, -en). */
class FeedController extends Controller
{
    public function __invoke(Request $request, MerchantFeed $feed, string $market)
    {
        if ($reason = $feed->unavailableReason($market)) {
            return response($reason, 404, ['Content-Type' => 'text/plain; charset=utf-8', 'X-Robots-Tag' => 'noindex']);
        }
        MerchantFeed::recordFetch($market, $request->userAgent());

        return response($feed->xml($market), 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=900',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
