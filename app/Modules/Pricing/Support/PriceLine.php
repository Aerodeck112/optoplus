<?php

namespace App\Modules\Pricing\Support;

use App\Modules\Configurator\Support\ConfigurationInput;

/** O linie de coș de prețuit: o variantă de produs sau o configurație (ramă + lentile + tratamente). */
final class PriceLine
{
    public function __construct(
        public readonly string $key,
        public readonly string $kind,
        public readonly int $quantity = 1,
        public readonly ?int $variantId = null,
        public readonly ?ConfigurationInput $configuration = null,
    ) {}

    public static function product(string $key, int $variantId, int $quantity = 1): self
    {
        return new self($key, 'product', max(1, $quantity), $variantId);
    }

    public static function configuration(string $key, ConfigurationInput $input, int $quantity = 1): self
    {
        return new self($key, 'configuration', max(1, $quantity), null, $input);
    }
}
