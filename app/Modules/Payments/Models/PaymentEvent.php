<?php

namespace App\Modules\Payments\Models;

use Illuminate\Database\Eloquent\Model;

/** Jurnalul notificărilor de la procesator; unicitatea (furnizor, id eveniment) face procesarea idempotentă. */
class PaymentEvent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'signature_valid' => 'boolean', 'processed_at' => 'datetime'];
    }
}
