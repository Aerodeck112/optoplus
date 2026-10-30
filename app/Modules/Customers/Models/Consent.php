<?php

namespace App\Modules\Customers\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Jurnalul acordurilor: termeni, confidențialitate, prelucrarea rețetei, marketing (separat), AI extern.
 * Doar adăugare: retragerea unui acord este o înregistrare nouă cu granted = false.
 */
class Consent extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'customer_consents';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['granted' => 'boolean'];
    }

    public static function record(?int $customerId, string $type, bool $granted, string $source, ?string $subjectKey = null, ?string $version = null): self
    {
        return self::create([
            'customer_id' => $customerId, 'subject_key' => $subjectKey, 'type' => $type, 'granted' => $granted,
            'source' => $source, 'document_version' => $version,
            'ip_hash' => request() ? hash_hmac('sha256', (string) request()->ip(), (string) config('app.key')) : null,
        ]);
    }
}
