<?php

namespace App\Modules\Pricing\Support;

/**
 * O componentă prețuită: rama, lentilele, un tratament sau un produs. Sumele sunt în bani, în „baza” calculului
 * (cu TVA pentru B2C și listele cu TVA; fără TVA pentru listele B2B fără TVA).
 */
final class PricedComponent
{
    /** @var list<array{promotion_id:int|null, label:string, amount:int}> */
    public array $discounts = [];

    public int $vat = 0;

    public int $net = 0;

    public int $gross = 0;

    public function __construct(
        public readonly string $type,
        public readonly string $refType,
        public readonly int $refId,
        public readonly string $name,
        public readonly int $unitPrice,
        public readonly int $quantity,
        public readonly string $unitLabel,
        public readonly int $vatBp,
        public readonly string $priceSource,
        public readonly ?int $productId = null,
        public readonly ?int $brandId = null,
        public readonly array $categoryIds = [],
        public readonly ?int $unitCost = null,
        public readonly bool $included = false,
    ) {}

    public function amount(): int
    {
        return $this->unitPrice * $this->quantity;
    }

    public function discountTotal(): int
    {
        return array_sum(array_column($this->discounts, 'amount'));
    }

    public function afterDiscount(): int
    {
        return max(0, $this->amount() - $this->discountTotal());
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type, 'ref_type' => $this->refType, 'ref_id' => $this->refId, 'name' => $this->name,
            'unit_price' => $this->unitPrice, 'quantity' => $this->quantity, 'unit' => $this->unitLabel,
            'amount' => $this->amount(), 'discounts' => $this->discounts, 'total' => $this->afterDiscount(),
            'vat_bp' => $this->vatBp, 'vat' => $this->vat, 'net' => $this->net, 'gross' => $this->gross,
            'price_source' => $this->priceSource, 'included' => $this->included,
        ];
    }
}
