<?php

namespace App\Modules\Merchant\Services;

use App\Core\Settings\Settings;
use App\Core\Support\Html;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImage;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\I18n\Services\HtmlTranslator;
use App\Modules\I18n\Support\Locales;
use App\Modules\Pricing\Models\Promotion;
use App\Modules\Pricing\Services\PriceEngine;
use App\Modules\Pricing\Support\PriceLine;
use App\Modules\Pricing\Support\PricingContext;
use App\Modules\Regions\Services\ExchangeRates;
use App\Modules\Regions\Services\Regions;
use Illuminate\Support\Facades\Cache;

/**
 * 1.0.26: fluxul de produse pentru Google Merchant Center (RSS 2.0 cu atributele „g:”), citit periodic de Google
 * („preluare programată” dintr-un link). Trei fluxuri, unul pe piață:
 *   ro — România, în română, prețuri în lei, livrarea din România;
 *   hu — Ungaria, în maghiară, prețuri în forinți (curs BNR), livrarea în Ungaria;
 *   en — celelalte țări în care livrăm, în engleză, prețuri în euro, livrarea internațională.
 * Prețul vine numai din PriceEngine (cu promoțiile automate active); nimic nu se calculează separat.
 * Intră ramele și accesoriile publicate, câte un articol pe culoare (variantă activă). Lentilele (produse după rețetă)
 * nu intră. Ce nu se poate trimite (fără poză, fără preț, indisponibil) apare în raportul din panou, cu motivul.
 */
class MerchantFeed
{
    public const FEEDS = [
        'ro' => ['label' => 'România', 'locale' => 'ro', 'currency' => 'RON'],
        'hu' => ['label' => 'Ungaria', 'locale' => 'hu', 'currency' => 'HUF'],
        'en' => ['label' => 'Alte țări (engleză, euro)', 'locale' => 'en', 'currency' => 'EUR'],
    ];

    public function __construct(
        private Settings $settings,
        private PriceEngine $prices,
        private HtmlTranslator $translator,
        private ExchangeRates $rates,
        private Regions $regions,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get('merchant.enabled');
    }

    /** Motivul pentru care un flux nu se poate genera (sau null dacă se poate). */
    public function unavailableReason(string $feed): ?string
    {
        if (! isset(self::FEEDS[$feed])) {
            return 'Flux necunoscut.';
        }
        if (! $this->enabled()) {
            return 'Fluxul pentru Google Merchant nu este activ (Setări → Google Merchant).';
        }
        if ($feed === 'hu' && ! $this->settings->get('shipping.hu_enabled')) {
            return 'Livrarea în Ungaria nu este activă (Setări → Livrare și termene).';
        }
        if ($feed === 'en' && ! $this->settings->get('shipping.intl_enabled')) {
            return 'Livrarea în alte țări nu este activă (Setări → Livrare și termene).';
        }
        $currency = self::FEEDS[$feed]['currency'];
        if ($currency !== 'RON' && (! $this->regions->currenciesEnabled() || ! $this->rates->has($currency))) {
            return "Nu există curs pentru {$currency} sau afișarea în valută este oprită (Setări → Valute și țări).";
        }

        return null;
    }

    public static function url(string $feed): string
    {
        return rtrim((string) config('optoplus.site.canonical_host'), '/').'/feed/google-merchant-'.$feed.'.xml';
    }

    /** XML-ul fluxului (păstrat până se schimbă produsele, setările sau cursul; cel mult o oră). */
    public function xml(string $feed): string
    {
        return Cache::remember($this->cacheKey($feed, 'xml'), 3600, fn () => $this->render($feed));
    }

    /** Același conținut ca build(), păstrat în cache (pentru panou). */
    public function cachedBuild(string $feed): array
    {
        return Cache::remember($this->cacheKey($feed, 'build'), 3600, fn () => $this->build($feed));
    }

    private function cacheKey(string $feed, string $kind): string
    {
        return 'merchant.'.$kind.'.'.$feed.'.'.md5(json_encode([
            Product::max('updated_at'), ProductVariant::max('updated_at'), \App\Modules\Catalog\Models\ProductImage::max('updated_at'),
            $this->settingsHash(), $this->rates->rate(self::FEEDS[$feed]['currency']), config('optoplus.version'), now()->format('Y-m-d H'),
        ]));
    }

    /**
     * Articolele fluxului și produsele lăsate pe dinafară.
     *
     * @return array{items: list<array<string, mixed>>, excluded: list<array{product_id: int, name: string, reason: string}>}
     */
    public function build(string $feed): array
    {
        $def = self::FEEDS[$feed];
        $locale = $def['locale'];
        $ctx = new PricingContext(currency: $def['currency']);
        $base = rtrim((string) config('optoplus.site.canonical_host'), '/');
        $unconfirmed = (string) ($this->settings->get('merchant.unconfirmed_stock') ?? 'in_stock');
        $googleCategory = trim((string) $this->settings->get('merchant.google_category'));
        $items = [];
        $excluded = [];

        $products = Product::published()->whereIn('type', [Product::TYPE_FRAME, Product::TYPE_ACCESSORY])
            ->with(['brand', 'variants' => fn ($v) => $v->where('is_active', true), 'images' => fn ($i) => $i->where('status', 'ready')->with('media'), 'categories', 'frameSpec'])
            ->orderBy('id')->get();

        foreach ($products as $product) {
            if ($product->variants->isEmpty()) {
                $excluded[] = ['product_id' => $product->id, 'name' => $product->name, 'reason' => 'nu are nicio variantă activă cu preț'];

                continue;
            }
            if ($product->images->filter(fn ($i) => $i->media)->isEmpty()) {
                $excluded[] = ['product_id' => $product->id, 'name' => $product->name, 'reason' => 'nu are poze (Google cere cel puțin o poză)'];

                continue;
            }
            $several = $product->variants->count() > 1;
            foreach ($product->variants as $variant) {
                $availability = $this->availability($product, $variant, $unconfirmed);
                if ($availability === null) {
                    $excluded[] = ['product_id' => $product->id, 'name' => $product->name, 'reason' => 'disponibilitate neconfirmată (exclus din setări)'.($several ? ' — '.$variant->sku : '')];

                    continue;
                }
                $quote = $this->prices->quote([PriceLine::product('p', $variant->id)], $ctx);
                if (! $quote->isValid() || $quote->totalGross <= 0) {
                    $excluded[] = ['product_id' => $product->id, 'name' => $product->name, 'reason' => 'prețul nu poate fi calculat'.($several ? ' — '.$variant->sku : '')];

                    continue;
                }
                $images = $this->images($product, $variant);
                $title = $this->translator->plain($product->name, $locale);
                if ($variant->name) {
                    $title .= ' — '.$this->translator->plain($variant->name, $locale);
                }
                $description = $this->description($product, $locale);
                $item = [
                    'id' => $variant->sku_generated ? 'OP-'.$variant->id : $variant->sku,
                    'title' => mb_substr($title, 0, 150),
                    'description' => $description,
                    'link' => $base.Locales::path($locale, $product->url()),
                    'image_link' => $images[0],
                    'additional_image_link' => array_slice($images, 1, 10),
                    'availability' => $availability,
                    'condition' => 'new',
                    'brand' => $product->brand?->name,
                    'gtin' => $variant->gtin ?: null,
                    'mpn' => ! $variant->sku_generated && $product->brand ? $variant->sku : null,
                    'item_group_id' => $several ? 'OP-P'.$product->id : null,
                    'color' => $variant->name ? $this->translator->plain($variant->name, $locale) : null,
                    'product_type' => $this->productType($product, $locale),
                    'google_product_category' => $googleCategory !== '' ? $googleCategory : null,
                    'shipping' => $this->shipping($feed, $quote->totalGross),
                ];
                if ($quote->discountTotal > 0 && $quote->subtotal > $quote->totalGross) {
                    $item['price'] = $this->money($quote->subtotal, $def['currency']);
                    $item['sale_price'] = $this->money($quote->totalGross, $def['currency']);
                    $item['sale_price_effective_date'] = $this->saleDates($quote->appliedPromotionIds);
                } else {
                    $item['price'] = $this->money($quote->totalGross, $def['currency']);
                }
                $item['identifier_exists'] = $item['gtin'] || ($item['brand'] && $item['mpn']) ? null : 'no';
                $items[] = $item;
            }
        }

        return ['items' => $items, 'excluded' => $excluded];
    }

    /** Ultima citire a unui flux (de Google sau de altcineva), pentru panou. */
    public static function lastFetch(string $feed): ?array
    {
        return Cache::get('merchant.last_fetch.'.$feed);
    }

    public static function recordFetch(string $feed, ?string $userAgent): void
    {
        Cache::forever('merchant.last_fetch.'.$feed, ['at' => now()->toIso8601String(), 'google' => (bool) preg_match('/google/i', (string) $userAgent)]);
    }

    private function render(string $feed): string
    {
        $data = $this->cachedBuild($feed);
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $out = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">'."\n<channel>\n"
            .'<title>'.$e('Optoplus — '.self::FEEDS[$feed]['label']).'</title>'."\n"
            .'<link>'.$e(rtrim((string) config('optoplus.site.canonical_host'), '/').Locales::path(self::FEEDS[$feed]['locale'], '/')).'</link>'."\n"
            .'<description>'.$e($this->translator->plain('Rame de ochelari Optoplus', self::FEEDS[$feed]['locale'])).'</description>'."\n";
        foreach ($data['items'] as $item) {
            $out .= "<item>\n";
            foreach (['id', 'title', 'description', 'link', 'image_link'] as $k) {
                $out .= "  <g:{$k}>".$e($item[$k])."</g:{$k}>\n";
            }
            foreach ($item['additional_image_link'] as $url) {
                $out .= '  <g:additional_image_link>'.$e($url)."</g:additional_image_link>\n";
            }
            foreach (['availability', 'condition', 'price', 'sale_price', 'sale_price_effective_date', 'brand', 'gtin', 'mpn', 'identifier_exists', 'item_group_id', 'color', 'product_type', 'google_product_category'] as $k) {
                if (($item[$k] ?? null) !== null && $item[$k] !== '') {
                    $out .= "  <g:{$k}>".$e($item[$k])."</g:{$k}>\n";
                }
            }
            foreach ($item['shipping'] as $s) {
                $out .= '  <g:shipping><g:country>'.$e($s['country']).'</g:country><g:price>'.$e($s['price']).'</g:price></g:shipping>'."\n";
            }
            $out .= "</item>\n";
        }

        return $out."</channel>\n</rss>\n";
    }

    private function availability(Product $product, ProductVariant $variant, string $unconfirmed): ?string
    {
        if ($product->availability_mode === 'indisponibil' || ($variant->stock_qty !== null && (int) $variant->stock_qty <= 0)) {
            return 'out_of_stock';
        }
        if ($product->availability_mode === 'stoc_propriu' || ($variant->stock_qty !== null && (int) $variant->stock_qty > 0)) {
            return 'in_stock';
        }

        return $unconfirmed === 'exclude' ? null : 'in_stock';
    }

    /** @return list<string> pozele culorii (sau cele comune), la rezoluție mare */
    private function images(Product $product, ProductVariant $variant): array
    {
        $own = $product->images->filter(fn (ProductImage $i) => (int) $i->variant_id === $variant->id && $i->media);
        $list = $own->isNotEmpty() ? $own : $product->images->filter(fn (ProductImage $i) => $i->variant_id === null && $i->media);
        if ($list->isEmpty()) {
            $list = $product->images->filter(fn (ProductImage $i) => $i->media);
        }
        $base = rtrim((string) config('optoplus.site.canonical_host'), '/');

        return $list->values()->map(fn (ProductImage $i) => str_starts_with($u = $i->media->zoomUrl(), 'http') ? $u : $base.$u)->all();
    }

    private function description(Product $product, string $locale): string
    {
        $html = $product->description ?: $product->short_description;
        if ($html) {
            $text = Html::plain($locale === 'ro' ? $html : $this->translator->translate($html, $locale));
        } else {
            $text = $this->translator->plain('Ramă de ochelari de vedere', $locale).($product->brand ? ' '.$product->brand->name : '').'.';
        }
        if ($product->frameSpec?->sizeNotation()) {
            $text .= ' '.$this->translator->plain('Mărime', $locale).': '.$product->frameSpec->sizeNotation().'.';
        }

        return mb_substr($text, 0, 5000);
    }

    private function productType(Product $product, string $locale): ?string
    {
        $root = $this->translator->plain($product->type === Product::TYPE_FRAME ? 'Ochelari și rame' : 'Accesorii', $locale);
        $sub = $product->categories->pluck('name')->map(fn ($n) => $this->translator->plain((string) $n, $locale))
            ->first(fn ($n) => mb_strtolower($n) !== mb_strtolower($root));

        return implode(' > ', array_filter([$root, $sub]));
    }

    /** @return list<array{country: string, price: string}> */
    private function shipping(string $feed, int $itemTotal): array
    {
        if ($feed === 'ro') {
            $price = (int) ($this->settings->get('shipping.courier_price') ?? 0);
            $free = $this->settings->get('shipping.free_from');
            if ($free && $itemTotal >= (int) $free) {
                $price = 0;
            }

            return $this->settings->get('shipping.courier_enabled') ? [['country' => 'RO', 'price' => $this->money($price, 'RON')]] : [];
        }
        if ($feed === 'hu') {
            $eur = (int) ($this->settings->get('shipping.hu_price') ?? 0);

            return [['country' => 'HU', 'price' => $this->money($this->rates->convert($eur, 'EUR', 'HUF'), 'HUF')]];
        }
        $eur = (int) ($this->settings->get('shipping.intl_price') ?? 0);

        return array_map(fn ($c) => ['country' => $c, 'price' => $this->money($eur, 'EUR')], $this->regions->internationalCountries());
    }

    private function money(int $subunits, string $currency): string
    {
        return number_format($subunits / 100, 2, '.', '').' '.$currency;
    }

    private function saleDates(array $promotionIds): ?string
    {
        $promos = Promotion::whereIn('id', $promotionIds)->get(['starts_at', 'ends_at']);
        $start = $promos->pluck('starts_at')->filter()->max();
        $end = $promos->pluck('ends_at')->filter()->min();
        if (! $start || ! $end) {
            return null;
        }

        return $start->toIso8601String().'/'.$end->toIso8601String();
    }

    private function settingsHash(): string
    {
        return md5(json_encode(array_map(fn ($k) => $this->settings->get($k), [
            'merchant.enabled', 'merchant.unconfirmed_stock', 'merchant.google_category', 'shipping.courier_price', 'shipping.courier_enabled',
            'shipping.free_from', 'shipping.hu_price', 'shipping.intl_price', 'shipping.intl_countries',
        ])));
    }
}
