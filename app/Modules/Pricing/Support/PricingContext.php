<?php

namespace App\Modules\Pricing\Support;

use App\Modules\Pricing\Models\PriceList;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Contextul unui calcul de preț: canal, lista de preț a partenerului, clientul (pentru limitele de utilizare),
 * cuponul, metoda de livrare și categoria de beneficiar declarată (verificată ulterior de vânzări).
 */
final class PricingContext
{
    public readonly DateTimeInterface $now;

    public function __construct(
        public readonly string $channel = 'b2c',
        public readonly ?PriceList $priceList = null,
        public readonly ?int $customerId = null,
        public readonly ?int $organizationId = null,
        public readonly ?string $couponCode = null,
        public readonly ?string $shippingMethodCode = null,
        public readonly ?string $declaredBeneficiary = null,
        ?DateTimeInterface $now = null,
    ) {
        $this->now = $now ?? new DateTimeImmutable;
    }

    public static function b2c(array $overrides = []): self
    {
        return new self(...$overrides);
    }

    /** Prețurile din calcul conțin TVA (B2C sau listă B2B cu TVA)? */
    public function basisIncludesVat(): bool
    {
        return $this->priceList === null || $this->priceList->prices_include_vat;
    }

    public function with(array $changes): self
    {
        $data = [
            'channel' => $this->channel, 'priceList' => $this->priceList, 'customerId' => $this->customerId,
            'organizationId' => $this->organizationId, 'couponCode' => $this->couponCode,
            'shippingMethodCode' => $this->shippingMethodCode, 'declaredBeneficiary' => $this->declaredBeneficiary,
            'now' => $this->now,
        ];

        return new self(...array_merge($data, $changes));
    }
}
