<?php

namespace App\Modules\Shop;

use App\Core\Modules\ModuleServiceProvider as BaseProvider;
use App\Modules\Shop\Models\Cart;
use Illuminate\Console\Scheduling\Schedule;

class ModuleServiceProvider extends BaseProvider
{
    public function key(): string
    {
        return 'shop';
    }

    public function label(): string
    {
        return 'Coș';
    }

    public function schedule(Schedule $schedule): void
    {
        // Coșurile expirate (fără comandă) se șterg în loturi; configurațiile rămân la client.
        $schedule->call(fn () => Cart::where('expires_at', '<', now())->limit(500)->delete())
            ->dailyAt('03:40')->name('cos:curatare')->withoutOverlapping(30);
    }
}
