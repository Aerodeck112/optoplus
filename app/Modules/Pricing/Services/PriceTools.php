<?php

namespace App\Modules\Pricing\Services;

use App\Core\Audit\Audit;
use App\Core\Support\Money;
use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Pricing\Models\Promotion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 1.0.27: Prețuri → Gestionare prețuri. Modificări în masă ale prețului de bază (crește / scade cu procent sau cu lei,
 * preț fix, rotunjire), cu previzualizare, istoric și anulare; reduceri temporare (promoții datate) pentru aceeași
 * selecție. Prețul afișat clienților rămâne calculat numai de PriceEngine.
 */
class PriceTools
{
    public const TARGETS = ['rame' => 'Toate ramele', 'toate' => 'Ramele și accesoriile', 'marci' => 'Anumite mărci', 'categorii' => 'Anumite categorii', 'produse' => 'Anumite produse (coduri sau linkuri)'];

    public const OPERATIONS = ['plus_proc' => 'Crește cu %', 'minus_proc' => 'Scade cu %', 'plus_lei' => 'Crește cu lei', 'minus_lei' => 'Scade cu lei', 'fix' => 'Setează prețul (lei)'];

    public const ROUNDING = ['nu' => 'Fără rotunjire', 'leu' => 'La leu întreg (ex. 349,00)', '90' => 'Terminat în ,90 (ex. 348,90)', '99' => 'Terminat în ,99 (ex. 348,99)', '9' => 'Terminat în 9 lei (ex. 349,00)', '5' => 'Din 5 în 5 lei (ex. 350,00)', '10' => 'Din 10 în 10 lei (ex. 350,00)'];

    /** Validează și normalizează parametrii formularului. */
    public function params(array $in): array
    {
        $p = [
            'tinta' => array_key_exists($in['tinta'] ?? '', self::TARGETS) ? $in['tinta'] : 'rame',
            'marci' => array_values(array_map('intval', (array) ($in['marci'] ?? []))),
            'categorii' => array_values(array_map('intval', (array) ($in['categorii'] ?? []))),
            'produse' => mb_substr(trim((string) ($in['produse'] ?? '')), 0, 20000),
            'pret_min' => ($in['pret_min'] ?? '') !== '' ? Money::parse((string) $in['pret_min']) : null,
            'pret_max' => ($in['pret_max'] ?? '') !== '' ? Money::parse((string) $in['pret_max']) : null,
            'toate_starile' => ! empty($in['toate_starile']),
            'mod' => ($in['mod'] ?? 'baza') === 'reducere' ? 'reducere' : 'baza',
            'operatie' => array_key_exists($in['operatie'] ?? '', self::OPERATIONS) ? $in['operatie'] : 'plus_proc',
            'valoare' => trim((string) ($in['valoare'] ?? '')),
            'rotunjire' => array_key_exists($in['rotunjire'] ?? '', self::ROUNDING) ? $in['rotunjire'] : 'nu',
            'tip_reducere' => ($in['tip_reducere'] ?? 'percent') === 'fixed_amount' ? 'fixed_amount' : 'percent',
            'valoare_reducere' => trim((string) ($in['valoare_reducere'] ?? '')),
            'de_la' => $in['de_la'] ?? null, 'pana_la' => $in['pana_la'] ?? null,
            'eticheta' => mb_substr(trim((string) ($in['eticheta'] ?? '')), 0, 160),
        ];
        $errors = [];
        if ($p['tinta'] === 'marci' && ! $p['marci']) {
            $errors['marci'] = 'Alegeți cel puțin o marcă.';
        }
        if ($p['tinta'] === 'categorii' && ! $p['categorii']) {
            $errors['categorii'] = 'Alegeți cel puțin o categorie.';
        }
        if ($p['tinta'] === 'produse' && $p['produse'] === '') {
            $errors['produse'] = 'Scrieți codurile (SKU) sau linkurile produselor, câte unul pe rând.';
        }
        if ($p['mod'] === 'baza') {
            $v = $this->number($p['valoare']);
            if ($v === null || $v <= 0 || (str_ends_with($p['operatie'], 'proc') && $v > ($p['operatie'] === 'minus_proc' ? 90 : 500))) {
                $errors['valoare'] = str_ends_with($p['operatie'], 'proc') ? 'Procent invalid (ex. 10 sau 7,5; la scădere cel mult 90%).' : 'Sumă invalidă (lei, ex. 25 sau 19,90).';
            }
        } else {
            $v = $this->number($p['valoare_reducere']);
            if ($v === null || $v <= 0 || ($p['tip_reducere'] === 'percent' && $v >= 100)) {
                $errors['valoare_reducere'] = $p['tip_reducere'] === 'percent' ? 'Procent între 0 și 100.' : 'Sumă invalidă (lei).';
            }
            try {
                $from = $p['de_la'] ? Carbon::parse($p['de_la']) : null;
                $to = $p['pana_la'] ? Carbon::parse($p['pana_la']) : null;
            } catch (\Throwable) {
                $from = $to = null;
            }
            if (! $from || ! $to) {
                $errors['pana_la'] = 'Alegeți data de început și data de sfârșit a reducerii (o reducere fără termen este, de fapt, un preț nou: folosiți „Schimbă prețul de bază”).';
            } elseif ($to->lt($from)) {
                $errors['pana_la'] = 'Data de sfârșit trebuie să fie după data de început.';
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $p;
    }

    /** Variantele selectate (cu produsul), ordonate. */
    public function variants(array $p): Collection
    {
        return ProductVariant::query()->with('product:id,name,slug,type,status,brand_id')
            ->whereHas('product', function (Builder $q) use ($p) {
                $q->whereIn('type', $p['tinta'] === 'rame' ? [Product::TYPE_FRAME] : [Product::TYPE_FRAME, Product::TYPE_ACCESSORY]);
                if (! $p['toate_starile']) {
                    $q->where('status', Product::STATUS_PUBLISHED);
                }
                match ($p['tinta']) {
                    'marci' => $q->whereIn('brand_id', $p['marci']),
                    'categorii' => $q->whereHas('categories', fn ($c) => $c->whereIn('categories.id', $p['categorii'])),
                    'produse' => $q->whereIn('id', $this->productIds($p['produse'])),
                    default => null,
                };
            })
            ->when($p['pret_min'] !== null, fn ($q) => $q->where('price', '>=', $p['pret_min']))
            ->when($p['pret_max'] !== null, fn ($q) => $q->where('price', '<=', $p['pret_max']))
            ->where('price', '>', 0)
            ->orderBy('product_id')->orderBy('sort')->orderBy('id')->get();
    }

    /** Prețul nou (bani) pentru modificarea prețului de bază. */
    public function newPrice(int $old, array $p): int
    {
        $v = (float) $this->number($p['valoare']);
        $new = match ($p['operatie']) {
            'plus_proc' => $old * (1 + $v / 100),
            'minus_proc' => $old * (1 - $v / 100),
            'plus_lei' => $old + $v * 100,
            'minus_lei' => $old - $v * 100,
            'fix' => $v * 100,
        };

        return max(0, $this->round((int) round($new), $p['rotunjire']));
    }

    /** Prețul estimat după reducere (doar pentru previzualizare; prețul real îl calculează PriceEngine). */
    public function discounted(int $old, array $p): int
    {
        $v = (float) $this->number($p['valoare_reducere']);

        return max(0, (int) round($p['tip_reducere'] === 'percent' ? $old * (1 - $v / 100) : $old - $v * 100));
    }

    public function round(int $bani, string $mode): int
    {
        return match ($mode) {
            'leu' => (int) (round($bani / 100) * 100),
            '90' => (int) (round(($bani - 90) / 100) * 100 + 90),
            '99' => (int) (round(($bani - 99) / 100) * 100 + 99),
            '9' => (int) (round(($bani - 900) / 1000) * 1000 + 900),
            '5' => (int) (round($bani / 500) * 500),
            '10' => (int) (round($bani / 1000) * 1000),
            default => $bani,
        };
    }

    /** Previzualizarea: rândurile (cel mult $limit) și totalurile. */
    public function preview(array $p, int $limit = 200): array
    {
        $variants = $this->variants($p);
        $rows = [];
        $skipped = 0;
        foreach ($variants as $v) {
            $new = $p['mod'] === 'baza' ? $this->newPrice((int) $v->price, $p) : $this->discounted((int) $v->price, $p);
            if ($new <= 0) {
                $skipped++;

                continue;
            }
            $rows[] = ['variant' => $v, 'old' => (int) $v->price, 'new' => $new, 'below_cost' => $v->cost !== null && $new < (int) $v->cost];
        }

        return ['total' => count($rows), 'skipped' => $skipped, 'products' => collect($rows)->pluck('variant.product_id')->unique()->count(),
            'rows' => array_slice($rows, 0, $limit), 'below_cost' => collect($rows)->where('below_cost', true)->count()];
    }

    /** Aplică modificarea prețului de bază: un lot auditat, care se poate anula. */
    public function applyBase(array $p, User $user): array
    {
        $variants = $this->variants($p);

        return DB::transaction(function () use ($variants, $p, $user) {
            $batchId = DB::table('price_change_batches')->insertGetId([
                'description' => $this->describe($p), 'params' => json_encode($p, JSON_UNESCAPED_UNICODE), 'items_count' => 0,
                'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $count = 0;
            foreach ($variants as $v) {
                $old = (int) $v->price;
                $new = $this->newPrice($old, $p);
                if ($new <= 0 || $new === $old) {
                    continue;
                }
                DB::table('price_change_items')->insert(['batch_id' => $batchId, 'variant_id' => $v->id, 'old_price' => $old, 'new_price' => $new]);
                $v->update(['price' => $new]); // istoricul prețurilor se scrie automat
                $count++;
            }
            DB::table('price_change_batches')->where('id', $batchId)->update(['items_count' => $count]);
            Product::whereIn('id', $variants->pluck('product_id')->unique())->update(['updated_at' => now()]); // golește cache-ul paginilor
            Audit::log('pricing.bulk_price_change', null, ['lot' => $batchId, 'descriere' => $this->describe($p), 'variante' => $count]);

            return ['batch' => $batchId, 'count' => $count];
        });
    }

    /** Anulează un lot: readuce prețul vechi doar unde prețul nu a mai fost schimbat între timp. */
    public function revert(int $batchId, User $user): array
    {
        return DB::transaction(function () use ($batchId, $user) {
            $batch = DB::table('price_change_batches')->lockForUpdate()->find($batchId);
            if (! $batch || $batch->reverted_at) {
                return ['restored' => 0, 'kept' => 0];
            }
            $restored = 0;
            $kept = 0;
            foreach (DB::table('price_change_items')->where('batch_id', $batchId)->get() as $item) {
                $variant = ProductVariant::find($item->variant_id);
                if (! $variant || (int) $variant->price !== (int) $item->new_price) {
                    $kept++;

                    continue;
                }
                $variant->update(['price' => (int) $item->old_price]);
                Product::whereKey($variant->product_id)->update(['updated_at' => now()]);
                $restored++;
            }
            DB::table('price_change_batches')->where('id', $batchId)->update(['reverted_at' => now(), 'reverted_by' => $user->id, 'updated_at' => now()]);
            Audit::log('pricing.bulk_price_reverted', null, ['lot' => $batchId, 'readuse' => $restored, 'pastrate' => $kept]);

            return ['restored' => $restored, 'kept' => $kept];
        });
    }

    /** Creează reducerea temporară (o promoție datată) pentru produsele selectate. */
    public function createDiscount(array $p, User $user): Promotion
    {
        $productIds = $this->variants($p)->pluck('product_id')->unique()->values()->all();
        if (! $productIds) {
            throw ValidationException::withMessages(['tinta' => 'Selecția nu conține niciun produs.']);
        }
        $v = (float) $this->number($p['valoare_reducere']);
        $label = $p['eticheta'] !== '' ? $p['eticheta'] : ($p['tip_reducere'] === 'percent' ? 'Reducere '.rtrim(rtrim(number_format($v, 2, ',', ''), '0'), ',').'%' : 'Reducere '.Money::format((int) round($v * 100)));
        $promotion = Promotion::create([
            'name' => $label.' ('.Carbon::parse($p['de_la'])->format('d.m').'–'.Carbon::parse($p['pana_la'])->format('d.m.Y').')',
            'customer_label' => $label, 'scope' => 'product', 'scope_ids' => $productIds,
            'type' => $p['tip_reducere'], 'value' => $p['tip_reducere'] === 'percent' ? (int) round($v * 100) : (int) round($v * 100),
            'starts_at' => Carbon::parse($p['de_la'])->startOfDay(), 'ends_at' => Carbon::parse($p['pana_la'])->endOfDay(),
            'is_active' => true, 'stackable' => false, 'priority' => 50, 'requires_coupon' => false, 'channel' => 'b2c',
            'source' => 'Gestionare prețuri', 'updated_by' => $user->id,
        ]);
        Product::whereIn('id', $productIds)->update(['updated_at' => now()]);
        Audit::log('pricing.promotion_created', $promotion, ['din' => 'gestionare prețuri', 'produse' => count($productIds)]);

        return $promotion;
    }

    public function describe(array $p): string
    {
        $what = match ($p['tinta']) {
            'marci' => 'mărcile alese', 'categorii' => 'categoriile alese', 'produse' => 'produsele alese', 'toate' => 'rame și accesorii', default => 'toate ramele',
        };
        $op = self::OPERATIONS[$p['operatie']].' '.$p['valoare'];

        return $op.' · '.$what.($p['pret_min'] !== null || $p['pret_max'] !== null ? ' · preț '.($p['pret_min'] !== null ? 'de la '.Money::format($p['pret_min']).' ' : '').($p['pret_max'] !== null ? 'până la '.Money::format($p['pret_max']) : '') : '')
            .($p['rotunjire'] !== 'nu' ? ' · rotunjire: '.mb_strtolower(self::ROUNDING[$p['rotunjire']]) : '');
    }

    private function number(string $s): ?float
    {
        $s = str_replace([' ', ','], ['', '.'], $s);

        return is_numeric($s) ? (float) $s : null;
    }

    /** @return list<int> produsele după cod (SKU), slug sau link */
    private function productIds(string $list): array
    {
        $ids = [];
        foreach (preg_split('/[\r\n,;]+/', $list, -1, PREG_SPLIT_NO_EMPTY) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('~/produs/([^/?#]+)~', $line, $m)) {
                $line = $m[1];
            }
            $id = Product::where('slug', $line)->value('id') ?? ProductVariant::where('sku', $line)->value('product_id');
            if ($id) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique($ids));
    }
}
