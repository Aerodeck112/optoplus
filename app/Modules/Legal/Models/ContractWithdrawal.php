<?php

namespace App\Modules\Legal\Models;

use App\Models\User;
use App\Modules\Orders\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** O declarație de retragere trimisă online (funcția de retragere, OUG 34/2014 art. 11¹). */
class ContractWithdrawal extends Model
{
    public const STATUSES = [
        'primita' => 'Primită', 'acceptata' => 'Acceptată (retur în curs)', 'respinsa' => 'Fără drept de retragere (produs personalizat)', 'finalizata' => 'Finalizată (bani restituiți)',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'confirmation_sent_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
