<?php

namespace App\Modules\Cms\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Fișier din biblioteca media publică. Originalul se păstrează nemodificat; variantele WebP responsive
 * (aceleași proporții) sunt în `variants`.
 */
class Media extends Model
{
    protected $table = 'media';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['variants' => 'array'];
    }

    public static function baseDirectory(): string
    {
        return rtrim((string) config('optoplus.media_path', public_path('media')), '/');
    }

    public static function baseUrl(): string
    {
        return rtrim((string) config('optoplus.media_url', '/media'), '/');
    }

    public function url(): string
    {
        return self::baseUrl().'/'.$this->path;
    }

    public function absolutePath(): string
    {
        return self::baseDirectory().'/'.$this->path;
    }

    /** @return array<int, string> lățime => URL, crescător */
    public function webpVariants(): array
    {
        $out = [];
        foreach ((array) ($this->variants['webp'] ?? []) as $width => $path) {
            $out[(int) $width] = self::baseUrl().'/'.$path;
        }
        ksort($out);

        return $out;
    }

    public function srcset(): ?string
    {
        $variants = $this->webpVariants();
        if (! $variants) {
            return null;
        }

        return implode(', ', array_map(fn ($w, $u) => "$u {$w}w", array_keys($variants), $variants));
    }

    /** URL-ul cel mai potrivit pentru o lățime afișată (pentru fundaluri CSS). */
    public function bestUrl(int $width = 1920): string
    {
        foreach ($this->webpVariants() as $w => $url) {
            if ($w >= $width) {
                return $url;
            }
        }
        $variants = $this->webpVariants();

        return $variants ? end($variants) : $this->url();
    }

    /**
     * 1.0.25: imaginea pentru mărire (zoom): fișierul original, dacă e mai mare decât cea mai mare variantă WebP
     * (pozele de produs au variante până la 1600 px, originalele ajung la 2560 px); altfel cea mai mare variantă.
     */
    public function zoomUrl(): string
    {
        $variants = $this->webpVariants();
        $largest = $variants ? (int) array_key_last($variants) : 0;
        if ($this->isImage() && $this->mime !== 'image/gif' && (int) $this->width > $largest) {
            return $this->url();
        }

        return $variants ? end($variants) : $this->url();
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }
}
