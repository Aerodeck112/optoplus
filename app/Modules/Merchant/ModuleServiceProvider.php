<?php

namespace App\Modules\Merchant;

use App\Core\Modules\ModuleServiceProvider as BaseProvider;

/**
 * Google Merchant Center: fluxurile de produse (România, Ungaria, alte țări) și codul de verificare a siteului.
 * Se configurează din Setări → Google Merchant.
 */
class ModuleServiceProvider extends BaseProvider
{
    public function key(): string
    {
        return 'merchant';
    }

    public function label(): string
    {
        return 'Google Merchant';
    }
}
