<?php

namespace App\Modules\Storefront\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Cache-ul paginilor publice pentru vizitatorii anonimi fără coș: HTML-ul randat se păstrează în fișiere,
 * cu tokenul CSRF înlocuit de un marcaj (fiecare vizitator primește tokenul propriu la servire).
 * Invalidare: orice modificare de produs, preț, promoție, pagină sau meniu schimbă „generația” cache-ului.
 */
class PageCache
{
    public const TOKEN_MARK = '__OPTOPLUS_CSRF__';

    public const NONCE_MARK = '__OPTOPLUS_NONCE__';

    public function enabled(): bool
    {
        $setting = config('optoplus.site.page_cache');

        return $setting === null ? app()->isProduction() : (bool) $setting;
    }

    public function key(string $path, array $query): string
    {
        ksort($query);

        return sha1($this->generation().'|'.$path.'|'.http_build_query($query));
    }

    public function get(string $key): ?string
    {
        $file = $this->file($key);
        if (! is_file($file) || filemtime($file) < time() - (int) config('optoplus.site.page_cache_ttl', 900)) {
            return null;
        }

        return (string) file_get_contents($file);
    }

    public function put(string $key, string $html): void
    {
        $file = $this->file($key);
        if (! is_dir(dirname($file))) {
            @mkdir(dirname($file), 0775, true);
        }
        file_put_contents($file, $html, LOCK_EX);
    }

    public function flush(): void
    {
        Cache::forever('pagecache.generation', $this->generation() + 1);
        // Fișierele vechi devin inaccesibile (cheia include generația); le ștergem în lot, fără a bloca cererea.
        foreach (glob($this->dir().'/*/*.html') ?: [] as $f) {
            if (filemtime($f) < time() - 60) {
                @unlink($f);
            }
        }
    }

    private function generation(): int
    {
        return (int) Cache::get('pagecache.generation', 1);
    }

    private function dir(): string
    {
        return storage_path('framework/cache/pages');
    }

    private function file(string $key): string
    {
        return $this->dir().'/'.substr($key, 0, 2).'/'.$key.'.html';
    }
}
