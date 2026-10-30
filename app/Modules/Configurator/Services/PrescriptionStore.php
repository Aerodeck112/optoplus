<?php

namespace App\Modules\Configurator\Services;

use App\Modules\Configurator\Models\Prescription;
use App\Modules\Configurator\Models\PrescriptionFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Încărcarea fișierelor de rețetă. Fișierele se verifică după conținut (nu după extensie), imaginile se
 * re-codifică (elimină metadatele, inclusiv GPS), PDF-urile cu JavaScript sau conținut activ sunt respinse.
 * Se păstrează pe discul privat „retete”, sub nume aleator, niciodată sub public_html.
 */
class PrescriptionStore
{
    public const DISK = 'retete';

    private const ALLOWED = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];

    public function store(Prescription $prescription, UploadedFile $file): PrescriptionFile
    {
        $maxKb = (int) config('optoplus.configurator.upload_max_kb', 10240);
        if (! $file->isValid() || $file->getSize() > $maxKb * 1024 || $file->getSize() < 100) {
            throw ValidationException::withMessages(['reteta' => 'Fișierul nu a putut fi încărcat sau depășește '.round($maxKb / 1024).' MB.']);
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath()) ?: '';
        if (! isset(self::ALLOWED[$mime])) {
            throw ValidationException::withMessages(['reteta' => 'Acceptăm fotografii JPG, PNG sau WebP și fișiere PDF.']);
        }
        $bytes = match (true) {
            $mime === 'application/pdf' => $this->checkPdf($file->getRealPath()),
            default => $this->reencode($file->getRealPath(), $mime),
        };
        $path = Str::random(48).'.bin';
        Storage::disk(self::DISK)->put($path, $bytes);

        return $prescription->files()->create([
            'uuid' => (string) Str::uuid(),
            'disk_path' => $path,
            'mime' => $mime === 'application/pdf' ? $mime : 'image/jpeg',
            'size' => strlen($bytes),
            'sha256' => hash('sha256', $bytes),
            'created_at' => now(),
        ]);
    }

    public function contents(PrescriptionFile $file): string
    {
        return (string) Storage::disk(self::DISK)->get($file->disk_path);
    }

    public function delete(PrescriptionFile $file): void
    {
        Storage::disk(self::DISK)->delete($file->disk_path);
        $file->delete();
    }

    private function checkPdf(string $path): string
    {
        $bytes = (string) file_get_contents($path);
        if (! str_starts_with($bytes, '%PDF-')) {
            throw ValidationException::withMessages(['reteta' => 'Fișierul PDF nu este valid.']);
        }
        if (preg_match('#/(JavaScript|JS|Launch|EmbeddedFile|OpenAction|AA)\b#', $bytes)) {
            throw ValidationException::withMessages(['reteta' => 'PDF-ul conține elemente active și a fost respins. Trimiteți o fotografie a rețetei.']);
        }

        return $bytes;
    }

    private function reencode(string $path, string $mime): string
    {
        $img = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            default => false,
        };
        if (! $img) {
            throw ValidationException::withMessages(['reteta' => 'Imaginea nu a putut fi citită.']);
        }
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $o = (int) (@exif_read_data($path)['Orientation'] ?? 1);
            $img = match ($o) { 3 => imagerotate($img, 180, 0), 6 => imagerotate($img, -90, 0), 8 => imagerotate($img, 90, 0), default => $img };
        }
        $w = imagesx($img);
        if ($w > 2400) {
            $img = imagescale($img, 2400);
        }
        ob_start();
        imagejpeg($img, null, 88);

        return (string) ob_get_clean();
    }
}
