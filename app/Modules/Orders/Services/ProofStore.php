<?php

namespace App\Modules\Orders\Services;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderProof;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Dovezile pentru codurile de reducere (legitimație, cupon de pensie, adeverință de student). Aceleași reguli ca la
 * rețete: tipul se verifică după conținut, imaginile se re-codifică (fără metadate, inclusiv GPS), PDF-urile cu
 * conținut activ sunt respinse, fișierul stă pe discul privat „dovezi”, sub nume aleator, niciodată în public_html.
 * Spre deosebire de modulul WordPress, dovada nu are adresă publică: se deschide doar din panou, cu audit.
 */
class ProofStore
{
    public const DISK = 'dovezi';

    private const MAX_BYTES = 10 * 1024 * 1024;

    private const ALLOWED = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

    /**
     * Verifică fișierul înainte de plasarea comenzii (ca o comandă să nu rămână fără dovadă).
     *
     * @return array{bytes:string, mime:string}
     */
    public function prepare(UploadedFile $file, string $field): array
    {
        if (! $file->isValid() || $file->getSize() > self::MAX_BYTES || $file->getSize() < 100) {
            throw ValidationException::withMessages([$field => 'Dovada nu a putut fi încărcată sau depășește 10 MB.']);
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath()) ?: '';
        if (! in_array($mime, self::ALLOWED, true)) {
            throw ValidationException::withMessages([$field => 'Dovada trebuie să fie o fotografie (JPG, PNG) sau un PDF.']);
        }
        if ($mime === 'application/pdf') {
            $bytes = (string) file_get_contents($file->getRealPath());
            if (! str_starts_with($bytes, '%PDF-') || preg_match('#/(JavaScript|JS|Launch|EmbeddedFile|OpenAction|AA)\b#', $bytes)) {
                throw ValidationException::withMessages([$field => 'PDF-ul nu este valid sau conține elemente active. Trimiteți o fotografie.']);
            }

            return ['bytes' => $bytes, 'mime' => $mime];
        }

        return ['bytes' => $this->reencode($file->getRealPath(), $mime, $field), 'mime' => 'image/jpeg'];
    }

    /** @param  array{bytes:string, mime:string}  $prepared */
    public function save(Order $order, ?int $promotionId, ?string $code, string $label, array $prepared): OrderProof
    {
        $path = Str::random(48).'.bin';
        Storage::disk(self::DISK)->put($path, $prepared['bytes']);

        return $order->proofs()->create([
            'uuid' => (string) Str::uuid(), 'promotion_id' => $promotionId, 'coupon_code' => $code,
            'label' => mb_substr($label, 0, 160), 'disk_path' => $path, 'mime' => $prepared['mime'],
            'size' => strlen($prepared['bytes']), 'sha256' => hash('sha256', $prepared['bytes']), 'created_at' => now(),
        ]);
    }

    public function contents(OrderProof $proof): string
    {
        return (string) Storage::disk(self::DISK)->get($proof->disk_path);
    }

    private function reencode(string $path, string $mime, string $field): string
    {
        $img = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            default => false,
        };
        if (! $img) {
            throw ValidationException::withMessages([$field => 'Imaginea nu a putut fi citită.']);
        }
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $o = (int) (@exif_read_data($path)['Orientation'] ?? 1);
            $img = match ($o) { 3 => imagerotate($img, 180, 0), 6 => imagerotate($img, -90, 0), 8 => imagerotate($img, 90, 0), default => $img };
        }
        if (imagesx($img) > 2400) {
            $img = imagescale($img, 2400);
        }
        ob_start();
        imagejpeg($img, null, 88);

        return (string) ob_get_clean();
    }
}
