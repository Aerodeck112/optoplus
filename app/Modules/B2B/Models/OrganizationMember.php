<?php

namespace App\Modules\B2B\Models;

use App\Modules\Customers\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationMember extends Model
{
    /** Rolurile în organizație și ce pot face (verificate pe server de politici). */
    public const ROLES = [
        'admin_organizatie' => 'Administrator (utilizatori, comenzi, documente)',
        'comenzi' => 'Comenzi și oferte',
        'financiar' => 'Documente financiare și plăți',
        'vizualizare' => 'Doar vizualizare',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['invited_at' => 'datetime', 'accepted_at' => 'datetime'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function can(string $ability): bool
    {
        return match ($ability) {
            'order', 'quote' => in_array($this->role, ['admin_organizatie', 'comenzi'], true),
            'manage_users' => $this->role === 'admin_organizatie',
            'documents' => in_array($this->role, ['admin_organizatie', 'financiar', 'comenzi'], true),
            'view' => true,
            default => false,
        };
    }
}
