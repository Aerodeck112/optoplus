<?php

namespace App\Modules\Pricing\Services;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Pricing\Models\Promotion;
use App\Modules\Pricing\Support\PriceLine;
use App\Modules\Pricing\Support\PricingContext;
use App\Modules\Pricing\Support\Quote;
use App\Modules\Regions\Services\ExchangeRates;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * 1.0.27: prețul „barat” la o reducere, după regula din Directiva (UE) 2019/2161 (Omnibus), transpusă în Legea
 * 363/2007 / OG 21/1992: prețul anterior anunțat este cel mai mic preț practicat în ultimele 30 de zile înainte de
 * reducere. Se afișează doar când îl putem dovedi:
 *   - toate promoțiile aplicate au dată de început și de sfârșit (o „promoție” permanentă este de fapt prețul) și nu
 *     cer cupon;
 *   - istoricul prețurilor variantei acoperă cele 30 de zile dinaintea reducerii;
 *   - cel mai mic preț din acel interval este mai mare decât prețul de acum.
 * Altfel se afișează doar prețul de acum (fără „era”, fără procent).
 */
class ReducedPrice
{
    public const WINDOW_DAYS = 30;

    public function __construct(private PriceEngine $engine, private ExchangeRates $rates) {}

    /** @return array{was: int, now: int, percent: int}|null sume în valuta contextului */
    public function forVariant(ProductVariant $variant, PricingContext $ctx): ?array
    {
        return $this->fromQuote($variant->id, $this->engine->quote([PriceLine::product('p', $variant->id)], $ctx), $ctx);
    }

    /** @return array{was: int, now: int, percent: int}|null */
    public function fromQuote(int $variantId, Quote $quote, PricingContext $ctx): ?array
    {
        if ($quote->discountTotal <= 0 || $quote->appliedPromotionIds === []) {
            return null;
        }
        $promotions = Promotion::whereIn('id', $quote->appliedPromotionIds)->get(['id', 'starts_at', 'ends_at', 'requires_coupon']);
        if ($promotions->isEmpty() || $promotions->contains(fn ($p) => ! $p->starts_at || ! $p->ends_at || $p->requires_coupon)) {
            return null;
        }
        $lowest = $this->lowestBefore($variantId, $promotions->min('starts_at'));
        if ($lowest === null) {
            return null;
        }
        $was = $ctx->currency === 'RON' ? $lowest : $this->rates->fromRon($lowest, $ctx->currency);
        if ($was <= $quote->totalGross) {
            return null;
        }

        return ['was' => $was, 'now' => $quote->totalGross, 'percent' => (int) round((1 - $quote->totalGross / $was) * 100)];
    }

    /** Cel mai mic preț de bază (lei, bani) din cele 30 de zile dinaintea datei; null dacă istoricul nu acoperă intervalul. */
    public function lowestBefore(int $variantId, CarbonInterface $start): ?int
    {
        $from = $start->copy()->subDays(self::WINDOW_DAYS);
        $rows = DB::table('variant_price_history')->where('variant_id', $variantId)->where('valid_from', '<=', $start)
            ->orderBy('valid_from')->get(['price', 'valid_from']);
        if ($rows->isEmpty() || $rows->first()->valid_from > $from->toDateTimeString()) {
            return null; // nu știm ce preț era la începutul intervalului
        }
        $inWindow = $rows->filter(fn ($r) => $r->valid_from > $from->toDateTimeString())->pluck('price');
        $atStart = $rows->filter(fn ($r) => $r->valid_from <= $from->toDateTimeString())->last()->price;

        return (int) $inWindow->push($atStart)->min();
    }

    /** Înregistrează prețul de bază nou al unei variante (apelat la orice modificare a prețului). */
    public static function record(ProductVariant $variant, string $source): void
    {
        DB::table('variant_price_history')->insert(['variant_id' => $variant->id, 'price' => (int) $variant->price, 'valid_from' => now(), 'source' => mb_substr($source, 0, 40)]);
    }
}
