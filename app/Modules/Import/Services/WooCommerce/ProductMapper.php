<?php

namespace App\Modules\Import\Services\WooCommerce;

use App\Core\Support\Html;
use App\Core\Support\Money;
use App\Core\Support\Text;

/**
 * Transformă o înregistrare de produs WooCommerce (+ variațiile ei) în planul de import Optoplus. Nu scrie nimic.
 * Regulile de transformare sunt documentate în docs/MIGRARE.md §2.
 */
class ProductMapper
{
    /** Categoriile WooCommerce care devin atribute (categoriile actuale amestecă publicul, materialul și construcția). */
    private const CATEGORY_ATTRIBUTES = [
        'rame > femei' => ['gen', 'femei'],
        'rame > barbati' => ['gen', 'barbati'],
        'rame > copii' => ['gen', 'copii'],
        'rame > unisex' => ['gen', 'unisex'],
        'rame > metal' => ['material', 'metal'],
        'rame > acetat' => ['material', 'acetat'],
        'rame > plastic' => ['material', 'plastic'],
        'rame > titan' => ['material', 'titan'],
        'clip on acetate' => [['constructie', 'clip-on'], ['material', 'acetat']],
        'clip on acetat' => [['constructie', 'clip-on'], ['material', 'acetat']],
        'clip on metal' => [['constructie', 'clip-on'], ['material', 'metal']],
        'clip on plastic' => [['constructie', 'clip-on'], ['material', 'plastic']],
    ];

    private const SHAPES = [
        'rotund' => 'rotunda', 'oval' => 'ovala', 'dreptunghi' => 'dreptunghiulara', 'patrat' => 'patrata', 'square' => 'patrata',
        'cat' => 'cat-eye', 'aviator' => 'aviator', 'pilot' => 'aviator', 'geometric' => 'geometrica', 'neregulat' => 'neregulata',
    ];

    public function __construct(private DescriptionParser $parser) {}

    /**
     * @param  array<string, mixed>  $record  produsul (simple/variable)
     * @param  list<array<string, mixed>>  $variations
     * @param  array{slug?:string, permalink?:string}|null  $url  din harta URL-urilor (Store API public)
     * @return array{plan: array, warnings: list<string>, errors: list<string>}
     */
    public function map(array $record, array $variations, ?array $url): array
    {
        $warnings = [];
        $errors = [];
        $id = $record['id'];

        if ($record['name'] === '') {
            $errors[] = 'Produs fără nume.';
        }

        $slug = $url['slug'] ?? null;
        if (! $slug) {
            $slug = Text::slug($record['name']).'-'.$id;
            $warnings[] = 'URL-ul vechi nu este cunoscut (lipsește din harta Store API); slug generat din nume.';
        }
        $legacyUrl = isset($url['permalink']) ? (parse_url($url['permalink'], PHP_URL_PATH) ?: null) : null;

        $parsed = $this->parser->parse($record['description'].' '.$record['short_description']);
        foreach (['lens_width_mm' => 'lățimea lentilei', 'bridge_mm' => 'puntea', 'temple_mm' => 'lungimea brațului'] as $k => $label) {
            if ($parsed[$k] === null) {
                $warnings[] = "Dimensiune lipsă: {$label}.";
            }
        }

        // Atribute din categorii, descriere și atributele WooCommerce.
        $attrs = [];
        $categoryKeys = [];
        foreach ($record['categories'] as $cat) {
            $key = Text::fold($cat);
            $categoryKeys[] = $key;
            $key = str_replace('unisex rame', 'unisex', $key);
            foreach ($this->normalizePairs(self::CATEGORY_ATTRIBUTES[$key] ?? []) as [$a, $v]) {
                $attrs[$a][$v] = 'import';
            }
        }
        if (preg_match('/\btr ?90\b/i', $record['name'])) {
            $attrs['material']['tr90'] = 'import';
        }
        $shapeText = $parsed['shape'];
        foreach ($record['attributes'] as $a) {
            if (Text::fold($a['name']) === 'forma ramei' && $a['values']) {
                $shapeText = $a['values'][0];
            }
        }
        if ($shapeText) {
            $shape = null;
            foreach (self::SHAPES as $needle => $code) {
                if (str_contains(Text::fold($shapeText), $needle)) {
                    $shape = $code;
                    break;
                }
            }
            if ($shape) {
                $attrs['forma'][$shape] = 'descriere';
            } else {
                $warnings[] = "Formă nerecunoscută: „{$shapeText}”.";
            }
        }
        if ($parsed['rim_type']) {
            $attrs['tip_rama'][$parsed['rim_type']] = 'descriere';
        }
        if ($parsed['gender'] && ! isset($attrs['gen'])) {
            $g = Text::fold($parsed['gender']);
            foreach (['femei' => 'femei', 'barbati' => 'barbati', 'copii' => 'copii', 'unisex' => 'unisex'] as $n => $code) {
                if (str_contains($g, $n)) {
                    $attrs['gen'][$code] = 'descriere';
                }
            }
        }

        $brand = $record['brands'][0] ?? null;
        if (! $brand) {
            $warnings[] = 'Produs fără marcă.';
        }

        $variants = [];
        if ($record['type'] === 'variable') {
            if ($variations === []) {
                $errors[] = 'Produs variabil fără variații în export.';
            }
            foreach ($variations as $v) {
                $variants[] = $this->variant($v, $record, $warnings, $errors, $v['attributes'][0]['values'][0] ?? null);
            }
        } elseif ($record['type'] === 'simple') {
            $variants[] = $this->variant($record, $record, $warnings, $errors, null);
        } else {
            $errors[] = "Tip de produs neacceptat: {$record['type']}.";
        }

        $description = Html::rich($this->textToHtml($record['description']));
        $short = Html::rich($this->textToHtml($record['short_description']));
        if (preg_match('/\s(class|data-[a-z-]+|id)=/i', $record['short_description'].$record['description'])) {
            // Sanitizarea a eliminat marcaje străine (ex. HTML lipit dintr-o altă aplicație).
            $warnings[] = 'Descrierea conținea marcaje HTML străine (clase, atribute); au fost eliminate.';
        }

        $images = [];
        foreach ($record['images'] as $i => $src) {
            $images[] = ['url' => $src, 'sort' => $i, 'variant_source_id' => null];
        }
        foreach ($variations as $v) {
            foreach ($v['images'] as $i => $src) {
                $images[] = ['url' => $src, 'sort' => 100 + $i, 'variant_source_id' => $v['id']];
            }
        }
        if (! $images) {
            $warnings[] = 'Produs fără imagini.';
        }

        return [
            'plan' => [
                'source_id' => $id,
                'name' => $this->cleanName($record['name']),
                'slug' => $slug,
                'legacy_url' => $legacyUrl,
                'status' => $record['published'] && $record['visibility'] !== 'hidden' ? 'published' : 'draft',
                'brand' => $brand,
                'short_description' => $short ?: null,
                'description' => $description ?: null,
                'categories' => $categoryKeys,
                'attributes' => $attrs,
                'frame_spec' => [
                    'lens_width_mm' => $parsed['lens_width_mm'], 'bridge_mm' => $parsed['bridge_mm'], 'temple_mm' => $parsed['temple_mm'],
                    'size_label' => $parsed['size_label'], 'rim_type' => $parsed['rim_type'], 'color_code' => $parsed['color_code'],
                ],
                'variants' => $variants,
                'images' => $images,
                'tags' => $record['tags'],
                'sort' => $record['position'],
            ],
            'warnings' => array_values(array_unique($warnings)),
            'errors' => $errors,
        ];
    }

    private function variant(array $row, array $parent, array &$warnings, array &$errors, ?string $optionLabel): array
    {
        $regular = $this->money($row['regular_price'], $errors, $row['id']);
        $sale = $row['sale_price'] !== '' ? $this->money($row['sale_price'], $errors, $row['id']) : null;
        if ($regular === null) {
            $errors[] = "Fără preț obișnuit (ID {$row['id']}).";
        }
        if ($sale !== null && $regular !== null && $sale >= $regular) {
            $warnings[] = "Prețul promoțional nu este mai mic decât cel obișnuit (ID {$row['id']}); ignorat.";
            $sale = null;
        }
        $sku = $row['sku'];
        $generated = false;
        if ($sku === '') {
            $sku = 'WC-'.$row['id'];
            $generated = true;
            $warnings[] = "Fără SKU în WooCommerce (ID {$row['id']}): generat {$sku}.";
        }

        return [
            'source_id' => $row['id'],
            'sku' => mb_substr($sku, 0, 80),
            'sku_generated' => $generated,
            'gtin' => $row['gtin'] ?: null,
            'name' => $optionLabel,
            'options' => $optionLabel ? ['culoare' => $optionLabel] : null,
            'regular_price' => $regular,
            'sale_price' => $sale,
            'sale_from' => $row['sale_from'],
            'sale_to' => $row['sale_to'],
            'stock_qty' => $row['stock'],
            'in_stock_flag' => $row['in_stock'],
            'weight_g' => $row['weight'] !== null ? (int) round($row['weight'] * 1000) : null,
            'model_note' => DescriptionParser::normalizeNewlines(strip_tags($row['description'])),
            'sort' => $row['position'],
        ];
    }

    private function money(string $value, array &$errors, int $id): ?int
    {
        if ($value === '') {
            return null;
        }
        try {
            return Money::parse($value);
        } catch (\InvalidArgumentException) {
            $errors[] = "Preț invalid „{$value}” (ID {$id}).";

            return null;
        }
    }

    private function textToHtml(string $text): string
    {
        $text = DescriptionParser::normalizeNewlines($text);
        if (! preg_match('/<(p|br|ul|ol|div|strong|b)\b/i', $text)) {
            return Html::fromText($text);
        }

        return nl2br($text, false);
    }

    private function cleanName(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags(str_replace('<span> - </span>', ' - ', $name))) ?? $name);
    }

    /** @return list<array{0:string,1:string}> */
    private function normalizePairs(array $v): array
    {
        if ($v === []) {
            return [];
        }

        return is_array($v[0]) ? $v : [$v];
    }
}
