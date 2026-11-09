<?php

namespace App\Modules\I18n\Services;

use App\Modules\I18n\Support\Locales;

/**
 * Traduce o pagină HTML gata randată (ca TranslatePress): textul dintre etichete și atributele vizibile (alt, title,
 * placeholder, aria-label, meta description), plus adresele interne, care primesc prefixul limbii (/en/…).
 * Restul marcajului rămâne neschimbat, octet cu octet. Nu atinge scripturile, stilurile, câmpurile <textarea>
 * și nici elementele marcate translate="no" (ex. numele limbilor din comutator).
 */
class HtmlTranslator
{
    private const TEXT_ATTRS = ['alt', 'title', 'placeholder', 'aria-label', 'data-caption-3d', 'data-alt', 'data-confirm', 'data-t'];

    private const SKIP_PREFIXES = ['/en/', '/hu/', '/build/', '/media/', '/images/', '/storage/', '/fonts/', '/panou', '/webhook',
        '/favicon', '/robots.txt', '/sitemap.xml', '/up', '/apple-touch', '/recovery.php'];

    /** @var list<string> adresele siteului (cea canonică și cea a cererii curente), pentru linkurile absolute */
    public array $hosts = [];

    public function __construct(private Dictionary $dictionary) {}

    public function translate(string $html, string $locale): string
    {
        $parts = preg_split('~(<!--.*?-->|<script\b[^>]*>.*?</script>|<style\b[^>]*>.*?</style>|<textarea\b[^>]*>.*?</textarea>|<[^>]+>)~is',
            $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$html];
        $skip = []; // elemente translate="no" deschise: [nume, adâncime]
        foreach ($parts as $i => $part) {
            if ($part === '') {
                continue;
            }
            if ($part[0] === '<') {
                if (str_starts_with($part, '<!--') || preg_match('~^<(script|style|textarea)\b~i', $part)) {
                    continue;
                }
                if (preg_match('~^</([a-z0-9-]+)~i', $part, $m)) {
                    if ($skip && strtolower($m[1]) === $skip[count($skip) - 1][0] && --$skip[count($skip) - 1][1] === 0) {
                        array_pop($skip);
                    }

                    continue;
                }
                if (preg_match('~^<([a-z0-9-]+)~i', $part, $m)) {
                    $name = strtolower($m[1]);
                    $void = str_ends_with($part, '/>') || in_array($name, ['img', 'input', 'br', 'hr', 'meta', 'link', 'source', 'area', 'col', 'wbr'], true);
                    if ($skip && $skip[count($skip) - 1][0] === $name && ! $void) {
                        $skip[count($skip) - 1][1]++;
                    } elseif (! $void && preg_match('~\stranslate="no"~i', $part)) {
                        $skip[] = [$name, 1];
                    }
                    $parts[$i] = $this->tag($part, $locale, $name, $skip !== []);
                }

                continue;
            }
            if ($skip === [] && trim($part) !== '') {
                $parts[$i] = $this->text($part, $locale);
            }
        }

        return implode('', $parts);
    }

    /** Un fragment de text (păstrează spațiile de la margini). */
    public function text(string $text, string $locale): string
    {
        $translated = $this->dictionary->lookup($locale, $text);
        if ($translated === null) {
            return $text;
        }
        preg_match('/^\s*/u', $text, $lead);
        preg_match('/\s*$/u', $text, $trail);

        return $lead[0].htmlspecialchars($translated, ENT_QUOTES | ENT_HTML5, 'UTF-8', false).$trail[0];
    }

    /** Text simplu (mesaje JSON, subiect de email): fără escapare HTML. */
    public function plain(string $text, string $locale): string
    {
        return $this->dictionary->lookup($locale, $text) ?? $text;
    }

    public function url(string $url, string $locale): string
    {
        if ($url === '' || $url[0] !== '/' || str_starts_with($url, '//')) {
            foreach (array_unique(array_filter([rtrim((string) config('optoplus.site.canonical_host'), '/'), ...$this->hosts])) as $host) {
                if ($url === $host || str_starts_with($url, $host.'/') || str_starts_with($url, $host.'?')) {
                    return $host.$this->url(substr($url, strlen($host)) ?: '/', $locale);
                }
            }

            return $url;
        }
        foreach (self::SKIP_PREFIXES as $p) {
            if (str_starts_with($url, $p)) {
                return $url;
            }
        }
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        if (preg_match('~\.(webp|jpe?g|png|gif|svg|pdf|css|js|ico|xml|txt|csv|zip|woff2?)$~i', $path)) {
            return $url;
        }

        return Locales::path($locale, $url);
    }

    private function tag(string $tag, string $locale, string $name, bool $skipText): string
    {
        if ($name === 'html') {
            return (string) preg_replace('~\slang="[^"]*"~i', ' lang="'.$locale.'"', $tag);
        }
        if (! $skipText) {
            $tag = (string) preg_replace_callback('~\s('.implode('|', array_map('preg_quote', self::TEXT_ATTRS)).')="([^"]*)"~i',
                fn ($m) => ' '.$m[1].'="'.$this->attr($m[2], $locale).'"', $tag);
            if ($name === 'meta' && preg_match('~\s(name|property)="(description|og:title|og:description|twitter:title|twitter:description)"~i', $tag)) {
                $tag = (string) preg_replace_callback('~\scontent="([^"]*)"~i', fn ($m) => ' content="'.$this->attr($m[1], $locale).'"', $tag);
            }
            if ($name === 'input' && preg_match('~\stype="(submit|button)"~i', $tag)) {
                $tag = (string) preg_replace_callback('~\svalue="([^"]*)"~i', fn ($m) => ' value="'.$this->attr($m[1], $locale).'"', $tag);
            }
        }
        if (str_contains($tag, 'data-no-localize')) {
            return $tag;
        }
        $tag = (string) preg_replace_callback('~\s(href|action|formaction|data-[a-z0-9-]*url)="([^"]*)"~i',
            fn ($m) => ' '.$m[1].'="'.$this->url(html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5), $locale).'"', $tag);
        if ($name === 'link' && str_contains($tag, 'rel="canonical"') || $name === 'meta' && str_contains($tag, 'property="og:url"')) {
            $tag = (string) preg_replace_callback('~\s(href|content)="([^"]*)"~i', fn ($m) => ' '.$m[1].'="'.$this->url($m[2], $locale).'"', $tag);
        }

        return $tag;
    }

    private function attr(string $value, string $locale): string
    {
        if (trim($value) === '') {
            return $value;
        }
        $translated = $this->dictionary->lookup($locale, $value);

        return $translated === null ? $value : htmlspecialchars($translated, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);
    }
}
