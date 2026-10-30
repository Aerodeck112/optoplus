<?php

namespace App\Modules\Cms\Services;

use App\Modules\Cms\Models\Media;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Biblioteca media publică.
 *  - Importul păstrează fișierul original nemodificat și generează variante WebP responsive (aceleași proporții).
 *  - Încărcările noi sunt verificate (tip real cu finfo, extensie, dimensiune) și reencodate pentru a elimina
 *    metadatele (EXIF, GPS). SVG-urile încărcate de utilizatori sunt respinse.
 */
class MediaLibrary
{
    public const WIDTHS = [320, 480, 768, 1024, 1440, 1920];

    public const MAX_UPLOAD_BYTES = 10 * 1024 * 1024;

    public const UPLOAD_MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

    public function importFile(string $sourcePath, ?string $sourceUrl, ?string $alt = null, bool $variants = true, string $folder = 'import'): Media
    {
        $bytes = file_get_contents($sourcePath);
        $sha = hash('sha256', $bytes);
        if ($existing = Media::where('sha256', $sha)->first()) {
            if ($sourceUrl && ! $existing->source_url) {
                $existing->update(['source_url' => $sourceUrl]);
            }
            if ($variants && empty($existing->variants) && is_file($existing->absolutePath())) {
                $existing->update(['variants' => $this->makeVariants($existing)]);
            }

            return $existing;
        }

        $mime = $this->sniff($sourcePath);
        $ext = self::UPLOAD_MIMES[$mime] ?? match ($mime) {
            'image/svg+xml' => 'svg',
            'application/pdf' => 'pdf',
            default => 'bin',
        };
        $relative = $folder.'/'.substr($sha, 0, 2).'/'.substr($sha, 0, 16).'.'.$ext;
        $this->write($relative, $bytes);

        [$width, $height] = $this->dimensions(Media::baseDirectory().'/'.$relative, $mime);
        $name = $sourceUrl ? urldecode(basename(parse_url($sourceUrl, PHP_URL_PATH) ?: '')) : basename($sourcePath);

        $media = Media::create([
            'path' => $relative,
            'original_name' => mb_substr($name, 0, 250),
            'mime' => $mime,
            'size' => strlen($bytes),
            'width' => $width,
            'height' => $height,
            'sha256' => $sha,
            'alt' => $alt,
            'source' => 'import',
            'folder' => $folder,
            'source_url' => $sourceUrl ? mb_substr($sourceUrl, 0, 500) : null,
        ]);
        if ($variants) {
            $media->update(['variants' => $this->makeVariants($media)]);
        }

        return $media;
    }

    public function upload(UploadedFile $file, ?string $alt = null, string $folder = 'general'): Media
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages(['file' => 'Fișierul nu a putut fi încărcat.']);
        }
        if ($file->getSize() > self::MAX_UPLOAD_BYTES) {
            throw ValidationException::withMessages(['file' => 'Fișierul depășește 10 MB.']);
        }
        $mime = $this->sniff($file->getRealPath());
        $ext = strtolower($file->getClientOriginalExtension());
        $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (! isset(self::UPLOAD_MIMES[$mime]) || ! in_array($ext, $allowedExt, true)) {
            throw ValidationException::withMessages(['file' => 'Sunt acceptate doar imagini JPG, PNG, WebP sau GIF. SVG și alte tipuri sunt respinse din motive de securitate.']);
        }
        $info = @getimagesize($file->getRealPath());
        if (! $info || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > 60_000_000) {
            throw ValidationException::withMessages(['file' => 'Imaginea nu este validă sau are dimensiuni prea mari.']);
        }

        // Reencodare: elimină metadatele și orice conținut străin atașat fișierului.
        $image = $this->open($file->getRealPath(), $mime);
        if (! $image) {
            throw ValidationException::withMessages(['file' => 'Imaginea nu a putut fi citită.']);
        }
        $tmp = tempnam(sys_get_temp_dir(), 'med');
        match ($mime) {
            'image/jpeg' => imagejpeg($image, $tmp, 90),
            'image/png' => imagepng($image, $tmp, 6),
            'image/webp' => imagewebp($image, $tmp, 90),
            'image/gif' => imagegif($image, $tmp),
        };
        $bytes = file_get_contents($tmp);
        @unlink($tmp);
        $sha = hash('sha256', $bytes);
        if ($existing = Media::where('sha256', $sha)->first()) {
            return $existing;
        }
        $relative = 'uploads/'.date('Y/m').'/'.substr($sha, 0, 16).'.'.self::UPLOAD_MIMES[$mime];
        $this->write($relative, $bytes);

        $media = Media::create([
            'path' => $relative,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 250),
            'mime' => $mime,
            'size' => strlen($bytes),
            'width' => imagesx($image),
            'height' => imagesy($image),
            'sha256' => $sha,
            'alt' => $alt,
            'source' => 'upload',
            'folder' => $folder,
            'uploaded_by' => Auth::id(),
        ]);
        $media->update(['variants' => $this->makeVariants($media)]);

        return $media;
    }

    /** Tipul real al fișierului, determinat din conținut. */
    public function sniff(string $path): string
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
        if (in_array($mime, ['text/xml', 'application/xml', 'text/plain', 'text/html'], true) && str_contains((string) file_get_contents($path, false, null, 0, 2048), '<svg')) {
            return 'image/svg+xml';
        }

        return $mime;
    }

    /** @return array{0:?int,1:?int} */
    private function dimensions(string $path, string $mime): array
    {
        if ($mime === 'image/svg+xml') {
            $head = (string) file_get_contents($path, false, null, 0, 4096);
            if (preg_match('/<svg[^>]*\swidth="([\d.]+)"[^>]*\sheight="([\d.]+)"/', $head, $m)) {
                return [(int) round($m[1]), (int) round($m[2])];
            }
            if (preg_match('/viewBox="[\d.\-]+ [\d.\-]+ ([\d.]+) ([\d.]+)"/i', $head, $m)) {
                return [(int) round($m[1]), (int) round($m[2])];
            }

            return [null, null];
        }
        $info = @getimagesize($path);

        return $info ? [$info[0], $info[1]] : [null, null];
    }

    /** @return array<string, mixed> */
    public function makeVariants(Media $media): array
    {
        $variants = [];
        $source = $media->absolutePath();

        if (! isset(self::UPLOAD_MIMES[$media->mime]) || ! $media->width) {
            return $variants;
        }
        $image = $this->open($source, $media->mime);
        if (! $image) {
            return $variants;
        }
        // Fotografiile de produs au nevoie de mai puține trepte (grilă de catalog + zoom), ca procesarea să încapă
        // în limitele de timp ale hostingului.
        $steps = $media->folder === 'catalog' ? [320, 640, 960, 1600] : self::WIDTHS;
        $max = $media->folder === 'catalog' ? 1600 : 2560;
        $widths = array_values(array_filter($steps, fn ($w) => $w < $media->width));
        $widths[] = min($media->width, $max);
        foreach (array_unique($widths) as $width) {
            $height = (int) max(1, round($media->height * $width / $media->width));
            $resized = $width === $media->width ? $image : imagescale($image, $width, $height, IMG_BICUBIC);
            if (! $resized) {
                continue;
            }
            imagepalettetotruecolor($resized);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $rel = preg_replace('/\.[a-z]+$/', '', $media->path)."-{$width}.webp";
            $tmp = tempnam(sys_get_temp_dir(), 'wv');
            imagewebp($resized, $tmp, 80);
            $this->write($rel, file_get_contents($tmp));
            @unlink($tmp);
            $variants['webp'][(string) $width] = $rel;
        }

        return $variants;
    }

    private function open(string $path, string $mime): ?GdImage
    {
        $img = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            'image/gif' => @imagecreatefromgif($path),
            default => false,
        };
        if ($img && $mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $orientation = @exif_read_data($path)['Orientation'] ?? 1;
            $img = match ((int) $orientation) {
                3 => imagerotate($img, 180, 0),
                6 => imagerotate($img, -90, 0),
                8 => imagerotate($img, 90, 0),
                default => $img,
            };
        }

        return $img ?: null;
    }

    private function write(string $relative, string $bytes): void
    {
        $full = Media::baseDirectory().'/'.$relative;
        if (! is_dir(dirname($full)) && ! mkdir(dirname($full), 0775, true) && ! is_dir(dirname($full))) {
            throw new RuntimeException('Nu pot crea directorul media.');
        }
        file_put_contents($full, $bytes, LOCK_EX);
    }
}
