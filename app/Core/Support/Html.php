<?php

namespace App\Core\Support;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Sanitizarea HTML-ului introdus de editori sau importat (HTMLPurifier), pe profiluri cu listă albă.
 *  - inline(): formatare scurtă (bold, italic, link);
 *  - rich(): text de produs, ghiduri, politici (paragrafe, titluri h2–h4, liste, citate, tabele simple, imagini
 *    doar din biblioteca media locală).
 * Nu păstrează clase, stiluri, id-uri, script, iframe, atribute on*, javascript: sau PHP.
 */
final class Html
{
    private static array $instances = [];

    public static function inline(?string $html): string
    {
        return trim(self::purifier('inline')->purify((string) $html));
    }

    public static function rich(?string $html): string
    {
        return trim(self::purifier('rich')->purify((string) $html));
    }

    /** Text simplu cu rânduri noi → paragrafe sanitizate. */
    public static function fromText(?string $text): string
    {
        $paras = preg_split("/\n\s*\n/", trim((string) $text)) ?: [];

        return implode("\n", array_map(fn ($p) => '<p>'.nl2br(e(trim($p)), false).'</p>', array_filter($paras, fn ($p) => trim($p) !== '')));
    }

    public static function plain(?string $html, int $limit = 0): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], ' ', (string) $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');

        return $limit > 0 ? \Illuminate\Support\Str::limit($text, $limit, '…') : $text;
    }

    private static function purifier(string $profile): HTMLPurifier
    {
        if (isset(self::$instances[$profile])) {
            return self::$instances[$profile];
        }
        $config = HTMLPurifier_Config::createDefault();
        $cache = storage_path('framework/cache/htmlpurifier');
        if (! is_dir($cache)) {
            @mkdir($cache, 0775, true);
        }
        $config->set('Cache.SerializerPath', $cache);
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('HTML.Doctype', 'HTML 4.01 Transitional');
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true]);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('HTML.TargetNoopener', true);
        $config->set('Attr.EnableID', false);
        $config->set('AutoFormat.RemoveEmpty', true);
        if ($profile === 'inline') {
            $config->set('HTML.Allowed', 'b,strong,i,em,u,br,sup,sub,small,a[href|title]');
        } else {
            $config->set('HTML.Allowed', 'p,br,b,strong,i,em,u,sup,sub,small,a[href|title|target],h2,h3,h4,ul,ol,li,blockquote,hr,table,thead,tbody,tr,th[colspan|rowspan],td[colspan|rowspan],img[src|alt|width|height]');
            $config->set('URI.DisableExternalResources', true);
        }
        $config->set('HTML.DefinitionID', 'optoplus-'.$profile);
        $config->set('HTML.DefinitionRev', 1);

        return self::$instances[$profile] = new HTMLPurifier($config);
    }
}
