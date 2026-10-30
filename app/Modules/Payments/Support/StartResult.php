<?php

namespace App\Modules\Payments\Support;

/** Rezultatul inițierii unei plăți la procesator: pagina de plată găzduită de procesator sau o eroare. */
final class StartResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?string $redirectUrl = null,
        public readonly ?string $providerReference = null,
        public readonly ?string $error = null,
    ) {}
}
