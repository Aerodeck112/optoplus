<?php

namespace App\Modules\Configurator\Support;

use InvalidArgumentException;

final class InvalidRxValue extends InvalidArgumentException
{
    public function __construct(public readonly string $raw)
    {
        parent::__construct("Valoare de rețetă invalidă: {$raw}");
    }
}
