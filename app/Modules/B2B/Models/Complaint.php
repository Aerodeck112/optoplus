<?php

namespace App\Modules\B2B\Models;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Complaint extends Model
{
    public const REASONS = ['nu_vad_bine' => 'Nu văd bine / neadaptare', 'defect' => 'Defect al lentilei', 'deteriorare_transport' => 'Deteriorare la transport', 'produs_gresit' => 'Produs diferit de comandă', 'altul' => 'Alt motiv'];

    public const STATUSES = ['noua' => 'Nouă', 'in_analiza' => 'În analiză', 'acceptata' => 'Acceptată', 'respinsa' => 'Respinsă', 'rezolvata' => 'Rezolvată'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['attachments' => 'array'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }
}
