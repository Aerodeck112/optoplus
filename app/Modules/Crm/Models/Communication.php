<?php

namespace App\Modules\Crm\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Comunicare înregistrată (telefon, email, WhatsApp, întâlnire) cu un client sau o organizație. */
class Communication extends Model
{
    public const CHANNELS = ['telefon' => 'Telefon', 'email' => 'Email', 'whatsapp' => 'WhatsApp', 'sms' => 'SMS', 'intalnire' => 'Întâlnire', 'portal' => 'Portal'];

    protected $table = 'crm_communications';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
