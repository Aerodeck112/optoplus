<?php

namespace App\Modules\Crm\Models;

use App\Models\User;
use App\Modules\Customers\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Cerere primită (formular, asistent, telefon). Rezumatul nu conține valori din rețetă. */
class Lead extends Model
{
    public const SOURCES = [
        'ajutor_lentile' => 'Cerere de ajutor pentru lentile', 'contact' => 'Formular de contact', 'parteneriat' => 'Cerere de parteneriat B2B',
        'asistent' => 'Asistentul de pe site', 'configurator' => 'Configurator (verificare specialist)', 'telefon' => 'Telefon', 'manual' => 'Adăugată manual',
    ];

    public const STATUSES = ['noua' => 'Nouă', 'contactat' => 'Contactat', 'calificat' => 'Calificat', 'convertit' => 'Convertit', 'pierdut' => 'Pierdut'];

    protected $table = 'crm_leads';

    protected $guarded = ['id'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'subject')->latest();
    }

    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'subject');
    }
}
