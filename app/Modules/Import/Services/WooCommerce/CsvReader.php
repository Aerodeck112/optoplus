<?php

namespace App\Modules\Import\Services\WooCommerce;

use RuntimeException;

/**
 * Citește exportul de produse WooCommerce (CSV, antet în română sau engleză) și întoarce înregistrări
 * normalizate. Coloanele se identifică după antet, nu după poziție.
 */
class CsvReader
{
    /** Antetele acceptate pentru fiecare câmp normalizat (export în română și în engleză). */
    private const COLUMNS = [
        'id' => ['ID'],
        'type' => ['Tip', 'Type'],
        'sku' => ['SKU'],
        'gtin' => ['GTIN, UPC, EAN sau ISBN', 'GTIN, UPC, EAN, or ISBN'],
        'name' => ['Nume', 'Name'],
        'published' => ['Publicat', 'Published'],
        'visibility' => ['Vizibilitate în catalog', 'Visibility in catalog'],
        'short_description' => ['Descriere scurtă', 'Short description'],
        'description' => ['Descriere', 'Description'],
        'sale_from' => ['Dată începere promoție', 'Date sale price starts'],
        'sale_to' => ['Dată încheiere promoție', 'Date sale price ends'],
        'tax_status' => ['Stare taxă', 'Tax status'],
        'in_stock' => ['În stoc?', 'In stock?'],
        'stock' => ['Stoc', 'Stock'],
        'weight' => ['Greutate (kg)', 'Weight (kg)'],
        'length' => ['Lungime (cm)', 'Length (cm)'],
        'width' => ['Lățime (cm)', 'Width (cm)'],
        'height' => ['Înălțime (cm)', 'Height (cm)'],
        'sale_price' => ['Preț promoțional', 'Sale price'],
        'regular_price' => ['Preț obișnuit', 'Regular price'],
        'categories' => ['Categorii', 'Categories'],
        'tags' => ['Etichete', 'Tags'],
        'images' => ['Imagini', 'Images'],
        'parent' => ['Părinte', 'Parent'],
        'position' => ['Poziție', 'Position'],
        'brands' => ['Branduri', 'Brands'],
        'upsells' => ['Upsells'],
        'cross_sells' => ['Vânzări încrucișate', 'Cross-sells'],
        'yoast_primary_cat' => ['Meta: _yoast_wpseo_primary_product_cat'],
    ];

    /** @return array{headers: list<string>, records: list<array<string, mixed>>} */
    public function read(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("Fișierul nu poate fi citit: {$path}");
        }
        $fh = fopen($path, 'rb');
        $headers = fgetcsv($fh, escape: '\\');
        if (! $headers) {
            throw new RuntimeException('Fișierul CSV este gol.');
        }
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
        $index = [];
        foreach (self::COLUMNS as $field => $names) {
            foreach ($names as $name) {
                $pos = array_search($name, $headers, true);
                if ($pos !== false) {
                    $index[$field] = $pos;
                    break;
                }
            }
        }
        foreach (['id', 'type', 'name'] as $required) {
            if (! isset($index[$required])) {
                throw new RuntimeException("Coloana obligatorie lipsește din export: {$required}. Exportați din WooCommerce → Produse → Export, cu toate coloanele.");
            }
        }
        $attrCols = [];
        foreach ($headers as $pos => $h) {
            $h = trim((string) $h);
            if (preg_match('/^Nume atribut (\d+)$/u', $h, $m) || preg_match('/^Attribute (\d+) name$/', $h, $m)) {
                $attrCols[(int) $m[1]]['name'] = $pos;
            } elseif (preg_match('/^Valoare \(valori\) atribut (\d+)$/u', $h, $m) || preg_match('/^Attribute (\d+) value\(s\)$/', $h, $m)) {
                $attrCols[(int) $m[1]]['values'] = $pos;
            }
        }

        $records = [];
        $line = 1;
        while (($row = fgetcsv($fh, escape: '\\')) !== false) {
            $line++;
            if ($row === [null]) {
                continue;
            }
            $get = fn (string $f) => isset($index[$f]) ? trim((string) ($row[$index[$f]] ?? '')) : '';
            $attributes = [];
            foreach ($attrCols as $n => $cols) {
                $name = isset($cols['name']) ? trim((string) ($row[$cols['name']] ?? '')) : '';
                if ($name !== '') {
                    $values = isset($cols['values']) ? array_values(array_filter(array_map('trim', explode(',', (string) ($row[$cols['values']] ?? ''))))) : [];
                    $attributes[] = ['name' => $name, 'values' => $values];
                }
            }
            $records[] = [
                'line' => $line,
                'id' => (int) $get('id'),
                'type' => strtolower($get('type')),
                'sku' => $get('sku'),
                'gtin' => $get('gtin'),
                'name' => $get('name'),
                'published' => $get('published') === '1',
                'visibility' => $get('visibility'),
                'short_description' => $get('short_description'),
                'description' => $get('description'),
                'sale_from' => $get('sale_from') ?: null,
                'sale_to' => $get('sale_to') ?: null,
                'in_stock' => $get('in_stock'),
                'stock' => $get('stock') === '' ? null : (int) $get('stock'),
                'weight' => $get('weight') === '' ? null : (float) str_replace(',', '.', $get('weight')),
                'sale_price' => $get('sale_price'),
                'regular_price' => $get('regular_price'),
                'categories' => $this->list($get('categories'), ','),
                'tags' => $this->list($get('tags'), ','),
                'images' => $this->list($get('images'), ','),
                'parent' => preg_match('/^id:(\d+)$/', $get('parent'), $m) ? (int) $m[1] : null,
                'position' => (int) $get('position'),
                'brands' => $this->list($get('brands'), ','),
                'yoast_primary_cat' => $get('yoast_primary_cat'),
                'attributes' => $attributes,
                'hash' => hash('sha256', json_encode($row)),
            ];
        }
        fclose($fh);

        return ['headers' => $headers, 'records' => $records];
    }

    private function list(string $value, string $sep): array
    {
        return array_values(array_filter(array_map('trim', explode($sep, $value)), fn ($v) => $v !== ''));
    }
}
