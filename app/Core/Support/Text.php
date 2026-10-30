<?php

namespace App\Core\Support;

use Illuminate\Support\Str;

/** Normalizări de text pentru URL-uri și căutare (fără diacritice, litere mici). */
final class Text
{
    public static function slug(string $text): string
    {
        return Str::slug(self::fold($text));
    }

    /** „Ramă Ochelari Ș/Ț” → „rama ochelari s/t”. Include ș/ț cu sedilă (scrierea veche). */
    public static function fold(string $text): string
    {
        $map = ['ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't',
            'Ă' => 'a', 'Â' => 'a', 'Î' => 'i', 'Ș' => 's', 'Ş' => 's', 'Ț' => 't', 'Ţ' => 't'];

        return mb_strtolower(Str::ascii(strtr($text, $map)));
    }

    /** Text pentru indexul de căutare: fără diacritice, fără punctuație, spații simple. */
    public static function searchable(string $text): string
    {
        $t = self::fold(strip_tags($text));
        $t = preg_replace('/[^a-z0-9]+/', ' ', $t) ?? '';

        return trim(preg_replace('/\s+/', ' ', $t) ?? '');
    }
}
