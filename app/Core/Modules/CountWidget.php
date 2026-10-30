<?php

namespace App\Core\Modules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\View\View;

/** Widget simplu: un număr de lucruri de făcut și linkul către lista lor. */
class CountWidget extends DashboardWidget
{
    public function __construct(
        private string $label,
        private string $permission,
        private Closure $count,
        private string $url,
        private ?string $help = null,
        private int $order = 50,
    ) {}

    public function permission(): string
    {
        return $this->permission;
    }

    public function order(): int
    {
        return $this->order;
    }

    public function render(User $user): View
    {
        return view('panou-widgets.count', ['label' => $this->label, 'value' => ($this->count)(), 'url' => $this->url, 'help' => $this->help]);
    }
}
