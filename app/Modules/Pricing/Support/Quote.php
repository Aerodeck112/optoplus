<?php

namespace App\Modules\Pricing\Support;

/**
 * Rezultatul motorului de preț. Este singura sursă pentru sumele afișate în configurator, coș, checkout,
 * ofertele B2B și răspunsurile asistentului; comanda păstrează `toArray()` ca snapshot.
 */
final class Quote
{
    /** @var array<string, array{key:string, kind:string, label:string, quantity:int, components:list<PricedComponent>, errors:list<string>}> */
    public array $lines = [];

    /** @var list<array{promotion_id:int, label:string, amount:int}> reduceri la nivel de comandă */
    public array $orderDiscounts = [];

    /** @var list<string> */
    public array $messages = [];

    /** @var list<string> */
    public array $errors = [];

    public ?array $shipping = null;

    public ?string $couponStatus = null;

    public bool $requiresProofCheck = false;

    /** @var list<int> */
    public array $appliedPromotionIds = [];

    public int $subtotal = 0;

    public int $discountTotal = 0;

    public int $vatTotal = 0;

    public int $totalNet = 0;

    public int $totalGross = 0;

    public function __construct(public readonly bool $basisIncludesVat, public readonly string $version) {}

    /** @return list<PricedComponent> */
    public function components(): array
    {
        $out = [];
        foreach ($this->lines as $line) {
            foreach ($line['components'] as $c) {
                $out[] = $c;
            }
        }

        return $out;
    }

    public function lineTotal(string $key): int
    {
        return array_sum(array_map(fn (PricedComponent $c) => $c->gross, $this->lines[$key]['components'] ?? []));
    }

    /** Cotele de TVA sunt confirmate în setări? Dacă nu, defalcarea TVA nu se arată clientului. */
    public function vatConfirmed(): bool
    {
        return ! in_array('tva_neconfirmat', $this->messages, true);
    }

    /** Mesajele pentru client (fără codurile interne). @return list<string> */
    public function customerMessages(): array
    {
        return array_values(array_filter($this->messages, fn ($m) => ! preg_match('/^[a-z_]+$/', $m)));
    }

    public function isValid(): bool
    {
        if ($this->errors) {
            return false;
        }
        foreach ($this->lines as $line) {
            if ($line['errors']) {
                return false;
            }
        }

        return true;
    }

    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'basis_includes_vat' => $this->basisIncludesVat,
            'lines' => array_map(fn ($l) => [
                'key' => $l['key'], 'kind' => $l['kind'], 'label' => $l['label'], 'quantity' => $l['quantity'],
                'errors' => $l['errors'],
                'components' => array_map(fn (PricedComponent $c) => $c->toArray(), $l['components']),
                'total' => $this->lineTotal($l['key']),
            ], array_values($this->lines)),
            'order_discounts' => $this->orderDiscounts,
            'shipping' => $this->shipping,
            'coupon_status' => $this->couponStatus,
            'requires_proof_check' => $this->requiresProofCheck,
            'applied_promotions' => $this->appliedPromotionIds,
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discountTotal,
            'vat_total' => $this->vatTotal,
            'total_net' => $this->totalNet,
            'total' => $this->totalGross,
            'messages' => $this->messages,
            'errors' => $this->errors,
        ];
    }
}
