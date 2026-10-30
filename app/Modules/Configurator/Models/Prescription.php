<?php

namespace App\Modules\Configurator\Models;

use App\Models\User;
use App\Modules\Configurator\Support\EyeRx;
use App\Modules\Customers\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rețeta (date privind sănătatea). Valorile și măsurătorile sunt criptate în baza de date; accesul se verifică
 * prin PrescriptionPolicy și se auditează. Aplicația nu modifică valorile introduse sau confirmate de client.
 */
class Prescription extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['values', 'measurements', 'verification_note'];

    protected function casts(): array
    {
        return [
            'values' => 'encrypted:array',
            'measurements' => 'encrypted:array',
            'verification_note' => 'encrypted',
            'issued_on' => 'date',
            'retention_until' => 'date',
            'confirmed_by_customer_at' => 'datetime',
            'verified_at' => 'datetime',
            'customer_unsure' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(PrescriptionFile::class);
    }

    public function configurations(): HasMany
    {
        return $this->hasMany(Configuration::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function eye(string $eye): ?EyeRx
    {
        return EyeRx::fromArray($this->values[$eye] ?? null);
    }
}
