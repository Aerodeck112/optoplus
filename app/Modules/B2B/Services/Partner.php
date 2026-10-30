<?php

namespace App\Modules\B2B\Services;

use App\Modules\B2B\Models\Organization;
use App\Modules\B2B\Models\OrganizationMember;
use App\Modules\Customers\Models\Customer;
use App\Modules\Pricing\Support\PricingContext;

/**
 * Partenerul curent (utilizatorul + organizația aprobată), rezolvat o dată pe cerere de middleware-ul portalului.
 * Toate interogările portalului se filtrează pe `organizationId()`; nimic nu vine din parametrii cererii.
 */
final class Partner
{
    public function __construct(public readonly Customer $customer, public readonly OrganizationMember $membership) {}

    public function organization(): Organization
    {
        return $this->membership->organization;
    }

    public function organizationId(): int
    {
        return $this->membership->organization_id;
    }

    public function can(string $ability): bool
    {
        return $this->membership->can($ability);
    }

    public function pricing(?string $shippingMethodCode = null): PricingContext
    {
        $org = $this->organization();

        return new PricingContext(
            channel: 'b2b', priceList: $org->priceList?->is_active ? $org->priceList : null,
            customerId: $this->customer->id, organizationId: $org->id, shippingMethodCode: $shippingMethodCode,
        );
    }

    /** Prețurile se afișează cu sau fără TVA, după setarea organizației. */
    public function showVat(): bool
    {
        return (bool) $this->organization()->show_prices_with_vat;
    }
}
