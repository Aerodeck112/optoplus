<?php

namespace App\Modules\B2B\Services;

use App\Core\Audit\Audit;
use App\Modules\B2B\Models\Organization;
use App\Modules\B2B\Models\OrganizationInvitation;
use App\Modules\B2B\Models\OrganizationMember;
use App\Modules\B2B\Notifications\Invitation;
use App\Modules\Customers\Models\Customer;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Invitations
{
    public function invite(Organization $org, string $email, string $role, ?int $byCustomerId = null, ?int $byUserId = null): OrganizationInvitation
    {
        if (! array_key_exists($role, OrganizationMember::ROLES)) {
            throw ValidationException::withMessages(['rol' => 'Rol necunoscut.']);
        }
        $email = Str::lower($email);
        if ($org->members()->whereHas('customer', fn ($q) => $q->where('email', $email))->exists()) {
            throw ValidationException::withMessages(['email' => 'Persoana face deja parte din organizație.']);
        }
        $token = Str::random(48);
        $inv = OrganizationInvitation::create([
            'organization_id' => $org->id, 'email' => $email, 'role' => $role, 'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(7), 'invited_by_customer_id' => $byCustomerId, 'invited_by_user_id' => $byUserId,
        ]);
        Notification::route('mail', $email)->notify(new Invitation($org->name, url('/portal/invitatie/'.$token.'/')));
        Audit::log('b2b.invitation_sent', $inv, ['organization' => $org->id, 'role' => $role], null, $byUserId);

        return $inv;
    }

    public function find(string $token): ?OrganizationInvitation
    {
        return OrganizationInvitation::where('token_hash', hash('sha256', $token))->whereNull('accepted_at')->where('expires_at', '>', now())->with('organization')->first();
    }

    public function accept(OrganizationInvitation $inv, Customer $customer): OrganizationMember
    {
        if (Str::lower($customer->email) !== $inv->email) {
            throw ValidationException::withMessages(['email' => 'Invitația a fost trimisă pentru altă adresă de email. Autentificați-vă cu adresa invitată.']);
        }
        $member = OrganizationMember::updateOrCreate(
            ['organization_id' => $inv->organization_id, 'customer_id' => $customer->id],
            ['role' => $inv->role, 'invited_at' => $inv->created_at, 'accepted_at' => now()],
        );
        $inv->update(['accepted_at' => now()]);
        Audit::log('b2b.invitation_accepted', $member, ['organization' => $inv->organization_id], $customer->id);

        return $member;
    }
}
