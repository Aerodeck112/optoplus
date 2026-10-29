<?php

namespace App\Core\Audit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Jurnalul de audit. Înregistrările sunt doar adăugate; aplicația nu oferă editare sau ștergere.
 *
 * Contextul este curățat automat de chei sensibile (parole, tokenuri, valori din rețete, date de card).
 * Pentru datele sensibile ale unui client (rețete), transmiteți $customerId ca să poată fi raportate accesările pe client.
 */
class Audit
{
    private const SENSITIVE_KEYS = [
        'password', 'parola', 'token', 'secret', 'cnp', 'mfa', 'code', 'cod', 'sph', 'cyl', 'axis', 'prescription', 'reteta', 'card', 'pan', 'cvv',
        'content', 'continut', 'body', 'diagnostic', 'observatii', 'notes', 'note', 'api_key', 'remember',
    ];

    private static ?string $requestId = null;

    public static function log(
        string $action,
        ?Model $subject = null,
        array $context = [],
        ?int $customerId = null,
        ?int $userId = null,
    ): AuditLog {
        $request = app()->runningInConsole() ? null : request();

        return AuditLog::create([
            'occurred_at' => now(),
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'customer_id' => $customerId,
            'ip' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 250, '') : 'console',
            'request_id' => self::requestId(),
            'context' => self::sanitize($context) ?: null,
        ]);
    }

    /** Elimină recursiv valorile cheilor sensibile. */
    public static function sanitize(array $context): array
    {
        $out = [];
        foreach ($context as $key => $value) {
            $normalized = strtolower((string) $key);
            foreach (self::SENSITIVE_KEYS as $needle) {
                if ($normalized === $needle || str_contains($normalized, $needle)) {
                    $out[$key] = '[eliminat]';

                    continue 2;
                }
            }
            $out[$key] = is_array($value) ? self::sanitize($value) : (is_string($value) ? Str::limit($value, 500) : $value);
        }

        return $out;
    }

    public static function requestId(): string
    {
        return self::$requestId ??= (string) Str::uuid();
    }

    /** Rulează $callback și auditul în aceeași tranzacție (dacă operația eșuează, nu rămâne audit fals). */
    public static function transaction(callable $callback): mixed
    {
        return DB::transaction($callback);
    }
}
