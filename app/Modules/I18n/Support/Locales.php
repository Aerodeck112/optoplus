<?php

namespace App\Modules\I18n\Support;

/**
 * Limbile siteului public. Româna e limba de bază, fără prefix; celelalte au prefix în adresă (/en/, /hu/),
 * ca fiecare limbă să aibă propriile adrese (pentru motoarele de căutare, cu hreflang).
 */
final class Locales
{
    public const DEFAULT = 'ro';

    public const ALL = ['ro' => 'Română', 'en' => 'English', 'hu' => 'Magyar'];

    /** @return list<string> */
    public static function translated(): array
    {
        return array_values(array_diff(array_keys(self::ALL), [self::DEFAULT]));
    }

    public static function isTranslated(?string $locale): bool
    {
        return in_array($locale, self::translated(), true);
    }

    /** /rame/ → /en/rame/ (pentru limba română rămâne neschimbat). */
    public static function path(string $locale, string $path): string
    {
        $path = '/'.ltrim($path, '/');

        return $locale === self::DEFAULT ? $path : '/'.$locale.($path === '/' ? '/' : $path);
    }

    /**
     * /en/rame/ → ['en', '/rame/']; /rame/ → ['ro', '/rame/'].
     *
     * @return array{0:string, 1:string}
     */
    public static function split(string $path): array
    {
        if (preg_match('#^/('.implode('|', self::translated()).')(/.*)?$#', $path, $m)) {
            return [$m[1], ($m[2] ?? '') === '' ? '/' : $m[2]];
        }

        return [self::DEFAULT, $path];
    }

    /** Textul normalizat pentru dicționar: spații comprimate, cifrele devin {0}, {1}… */
    public static function normalize(string $text, ?array &$numbers = null): string
    {
        $numbers = [];
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        return (string) preg_replace_callback('/(?<![\p{L}\d{])\d+(?:[.,]\d+)*(?![\p{L}\d}])/u', function ($m) use (&$numbers) {
            $numbers[] = $m[0];

            return '{'.(count($numbers) - 1).'}';
        }, $text);
    }

    public static function hash(string $normalized): string
    {
        return sha1($normalized);
    }
}
