<?php

namespace App\Modules\Pricing\Support;

use App\Modules\Pricing\Models\PriceList;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Contextul unui calcul de preț: canal, lista de preț a partenerului, clientul (pentru limitele de utilizare),
 * cuponul, metoda de livrare și categoria de beneficiar declarată (verificată ulterior de vânzări).
 *
 * 1.0.17: valuta calculului (RON, EUR, HUF) și țara de livrare. Listele de preț B2B sunt mereu în lei.
 */
final class PricingContext
{
    public readonly DateTimeInterface $now;

    public readonly string $currency;

    public function __construct(
        public readonly string $channel = 'b2c',
        public readonly ?PriceList $priceList = null,
        public readonly ?int $customerId = null,
        public readonly ?int $organizationId = null,
        public readonly ?string $couponCode = null,
        public readonly ?string $shippingMethodCode = null,
        public readonly ?string $declaredBeneficiary = null,
        ?DateTimeInterface $now = null,
        string $currency = 'RON',
        public readonly ?string $shippingCountry = null,
    ) {
        $this->now = $now ?? new DateTimeImmutable;
        $this->currency = $priceList !== null || $channel !== 'b2c' ? 'RON' : strtoupper($currency);
    }

    /** Context B2C în valuta vizitatorului (lei, euro sau forinți, după limba siteului). */
    public static function b2c(array $overrides = []): self
    {
        $overrides += ['currency' => app(\App\Modules\Regions\Services\Regions::class)->currency()];

        return new self(...$overrides);
    }

    /**
     * Codurile de reducere introduse (mai multe, separate prin virgulă sau spațiu), normalizate, fără dubluri.
     *
     * @return list<string>
     */
    public function couponCodes(): array
    {
        $codes = preg_split('/[\s,;]+/', (string) $this->couponCode, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_slice(array_values(array_unique(array_map(fn ($c) => strtoupper($c), $codes))), 0, 5);
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
            'now' => $this->now, 'currency' => $this->currency, 'shippingCountry' => $this->shippingCountry,
        ];

        return new self(...array_merge($data, $changes));
    }
}
