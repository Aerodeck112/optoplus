<?php

namespace App\Modules\Import\Services;

use App\Modules\Catalog\Models\ProductImage;
use App\Modules\Cms\Services\MediaLibrary;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Descarcă imaginile produselor importate, în loturi (din cron sau din comandă). Sursa preferată este arhiva
 * `wp-content/uploads` primită de la client (--uploads-dir); altfel, URL-ul public, doar de pe domeniile permise.
 * Fiecare fișier e verificat (tip real, dimensiune) și deduplicat pe SHA-256 în biblioteca media.
 */
class ImageDownloader
{
    public const MAX_BYTES = 20 * 1024 * 1024;

    public function __construct(private MediaLibrary $media) {}

    /** @return array{ready:int, failed:int, remaining:int} */
    /**
     * Directorul local cu imaginile vechiului site, în ordinea: setarea „import.uploads_dir”, pachetul de imagini
     * dezarhivat în shared/wp-uploads (instalarea cPanel), public_html/wp-content/uploads, storage/app/wp-uploads.
     */
    public static function defaultUploadsDir(): ?string
    {
        $layout = app(\App\Modules\Updates\Support\DeploymentLayout::class);
        $candidates = [
            app(\App\Core\Settings\Settings::class)->get('import.uploads_dir'),
            $layout->isDeployed() ? $layout->sharedPath('wp-uploads') : null,
            $layout->publicPath() ? $layout->publicPath().'/wp-content/uploads' : null,
            storage_path('app/wp-uploads'),
        ];
        foreach ($candidates as $dir) {
            if ($dir && is_dir($dir)) {
                return $dir;
            }
        }

        return null;
    }

    public function process(int $limit = 25, ?string $uploadsDir = null): array
    {
        $uploadsDir ??= self::defaultUploadsDir();
        $ready = 0;
        $failed = 0;
        $images = ProductImage::where('status', 'pending')->orderBy('id')->limit($limit)->get();
        foreach ($images as $image) {
            try {
                $tmp = $this->fetch((string) $image->source_url, $uploadsDir);
                try {
                    $mime = $this->media->sniff($tmp);
                    if (! isset(MediaLibrary::UPLOAD_MIMES[$mime])) {
                        throw new \RuntimeException("Tip de fișier neacceptat: {$mime}");
                    }
                    $media = $this->media->importFile($tmp, $image->source_url, $image->alt, true, 'catalog');
                } finally {
                    @unlink($tmp);
                }
                $image->update(['media_id' => $media->id, 'status' => 'ready']);
                $ready++;
            } catch (Throwable $e) {
                $image->update(['status' => 'failed']);
                \App\Modules\Import\Models\ImportItem::updateOrCreate(
                    ['source' => 'woocommerce', 'source_type' => 'image', 'source_id' => mb_substr((string) $image->source_url, 0, 120)],
                    ['target_type' => ProductImage::class, 'target_id' => $image->id, 'status' => 'eroare', 'messages' => [mb_substr($e->getMessage(), 0, 300)]],
                );
                $failed++;
            }
        }

        return ['ready' => $ready, 'failed' => $failed, 'remaining' => ProductImage::where('status', 'pending')->count()];
    }

    private function fetch(string $url, ?string $uploadsDir): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'img');
        $path = (string) parse_url($url, PHP_URL_PATH);
        if ($uploadsDir && str_contains($path, '/wp-content/uploads/')) {
            $relative = substr($path, strpos($path, '/wp-content/uploads/') + strlen('/wp-content/uploads/'));
            $local = rtrim($uploadsDir, '/').'/'.rawurldecode($relative);
            $real = realpath($local);
            if ($real && str_starts_with($real, realpath($uploadsDir).'/') && is_file($real)) {
                if (filesize($real) > self::MAX_BYTES) {
                    throw new \RuntimeException('Fișier prea mare.');
                }
                copy($real, $tmp);

                return $tmp;
            }
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $allowed = (array) config('optoplus.import.image_hosts', ['optoplus.ro', 'www.optoplus.ro']);
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || ! in_array($host, $allowed, true)) {
            throw new \RuntimeException("Domeniu nepermis pentru descărcare: {$host}");
        }
        $response = Http::timeout(30)->withOptions(['sink' => $tmp, 'allow_redirects' => false])
            ->withHeaders(['User-Agent' => 'Optoplus-Import/1.0 (migrare catalog, doar citire)'])->get($url);
        if (! $response->successful()) {
            throw new \RuntimeException('HTTP '.$response->status());
        }
        if (filesize($tmp) > self::MAX_BYTES || filesize($tmp) === 0) {
            throw new \RuntimeException('Fișier gol sau prea mare.');
        }

        return $tmp;
    }
}
