<?php

namespace App\Modules\Reviews\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Reviews\Models\Review;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Media și lista recenziilor publicate; verificarea „cumpărare verificată”. */
class Reviews
{
    /** @return array{count: int, average: float|null, items: Collection<int, Review>} */
    public function summary(?int $productId, int $limit = 20): array
    {
        $q = Review::published()->when($productId, fn ($q) => $q->where('product_id', $productId), fn ($q) => $q->whereNull('product_id'));
        $stats = (clone $q)->selectRaw('COUNT(*) as c, AVG(rating) as a')->first();

        return [
            'count' => (int) $stats->c,
            'average' => $stats->c ? round((float) $stats->a, 1) : null,
            'items' => $q->latest()->limit($limit)->get(),
        ];
    }

    /** Date structurate schema.org (nota medie și ultimele recenzii), doar dacă există recenzii publicate. */
    public static function jsonLd(array $summary): array
    {
        if ($summary['count'] === 0) {
            return [];
        }

        return [
            'aggregateRating' => ['@type' => 'AggregateRating', 'ratingValue' => $summary['average'], 'reviewCount' => $summary['count'], 'bestRating' => 5, 'worstRating' => 1],
            'review' => $summary['items']->take(5)->map(fn (Review $r) => [
                '@type' => 'Review', 'reviewRating' => ['@type' => 'Rating', 'ratingValue' => $r->rating, 'bestRating' => 5],
                'author' => ['@type' => 'Person', 'name' => $r->displayName()], 'datePublished' => $r->created_at->toDateString(),
                'reviewBody' => \Illuminate\Support\Str::limit($r->body, 500),
            ])->values()->all(),
        ];
    }

    /**
     * „Cumpărare verificată”: emailul are o comandă neanulată care conține produsul (ca ramă simplă sau în ochelarii
     * configurați). Pentru părerile despre magazin: orice comandă neanulată cu acest email.
     */
    public function verifiedOrder(?string $email, ?Product $product): ?int
    {
        if (! $email) {
            return null;
        }
        $orders = DB::table('orders')->where('email', mb_strtolower($email))->where('commercial_status', '!=', 'anulata');
        if (! $product) {
            return $orders->orderByDesc('id')->value('id');
        }
        $variants = $product->variants()->pluck('id');
        if ($variants->isEmpty()) {
            return null;
        }

        return DB::table('order_items')->whereIn('order_id', $orders->select('id'))
            ->where(fn ($q) => $q->whereIn('variant_id', $variants)
                ->orWhereIn('configuration_id', DB::table('configurations')->whereIn('frame_variant_id', $variants)->select('id')))
            ->orderByDesc('order_id')->value('order_id');
    }
}
