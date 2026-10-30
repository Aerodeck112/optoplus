<?php

namespace App\Modules\Import\Services\WooCommerce;

use App\Core\Support\Text;

/**
 * Extrage datele structurate scrise în textul descrierilor WooCommerce („Lățime Lentilă - 54”, „Punte Nazală - 17”,
 * „Formă ramă: Neregulat”...). Valorile extrase sunt marcate cu sursa „descriere” și pot fi corectate în panou.
 */
class DescriptionParser
{
    /** Secvența literală „\n” din export devine rând nou. */
    public static function normalizeNewlines(string $text): string
    {
        return str_replace(['\\r\\n', '\\n', "\r\n"], "\n", $text);
    }

    /**
     * @return array{lens_width_mm:?int, bridge_mm:?int, temple_mm:?int, size_label:?string, shape:?string,
     *               rim_type:?string, material:?string, gender:?string, color:?string, color_code:?string}
     */
    public function parse(string $description): array
    {
        $t = Text::fold(strip_tags(self::normalizeNewlines(html_entity_decode($description, ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
        $num = function (string $pattern) use ($t): ?int {
            if (preg_match($pattern, $t, $m)) {
                $v = (int) $m[1];

                return $v > 0 && $v < 200 ? $v : null;
            }

            return null;
        };
        $text = function (string $label) use ($t): ?string {
            return preg_match('/'.$label.'\s*:\s*([^\n]+)/u', $t, $m) ? trim($m[1]) : null;
        };

        $rim = $text('tip rama');
        $rimType = match (true) {
            $rim === null => null,
            str_contains($rim, 'intreag') || str_contains($rim, 'complet') => 'full',
            str_contains($rim, 'nylon') || str_contains($rim, 'fir') || str_contains($rim, 'semi') => 'semi_nylon',
            str_contains($rim, 'fara') || str_contains($rim, 'surub') || str_contains($rim, 'capse') => 'rimless_screw',
            default => null,
        };
        if ($rimType === null && preg_match('/\bsurub\/capse\b/', $t)) {
            $rimType = 'rimless_screw';
        }

        return [
            'lens_width_mm' => $num('/latime\s+lentil\w*\s*[-:]\s*(\d{2,3})/u'),
            'bridge_mm' => $num('/punte\s+nazala\s*[-:]\s*(\d{2})/u'),
            'temple_mm' => $num('/lungime\s+brat\w*\s*[-:]\s*(\d{2,3})/u'),
            'size_label' => ($s = $text('marime')) ? mb_strtoupper(mb_substr($s, 0, 10)) : null,
            'shape' => $text('forma rama'),
            'rim_type' => $rimType,
            'material' => $text('material'),
            'gender' => $text('pentru'),
            'color' => $text('culoarea? ramei') ?? $text('culoare'),
            'color_code' => preg_match('/\btip\s*-\s*(c\d+)\b/u', $t, $m) ? strtoupper($m[1]) : null,
        ];
    }
}
