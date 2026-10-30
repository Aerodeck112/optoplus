<?php

namespace App\Modules\Customers\Models;

use App\Modules\B2B\Models\Organization;
use App\Modules\B2B\Models\OrganizationMember;
use App\Modules\Customers\Notifications\CustomerResetPassword;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Contul unui client (persoană fizică sau utilizator al unui partener B2B). Separat de conturile personalului
 * (gardă „client”): un client nu poate accesa panoul, iar personalul nu se autentifică prin site.
 */
class Customer extends Authenticatable
{
    use Notifiable;

    protected $guarded = ['id', 'password', 'legacy_password_hash', 'remember_token'];

    protected $hidden = ['password', 'legacy_password_hash', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'email_verified_at' => 'datetime',
            'marketing_consent_at' => 'datetime',
            'last_login_at' => 'datetime',
            'anonymized_at' => 'datetime',
        ];
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function fullName(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? '')) ?: $this->email;
    }

    /** Organizația B2B activă (aprobată) și rolul clientului în ea; null pentru clienții persoane fizice. */
    public function activeMembership(): ?OrganizationMember
    {
        return $this->memberships()->with('organization')->whereNotNull('accepted_at')
            ->whereHas('organization', fn ($q) => $q->where('status', Organization::STATUS_APPROVED))
            ->orderBy('id')->first();
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new CustomerResetPassword($token));
    }
}
