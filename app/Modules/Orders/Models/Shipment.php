<?php

namespace App\Modules\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shipment extends Model
{
    public const STATUSES = ['pregatita' => 'Pregătită', 'predata' => 'Predată curierului', 'in_tranzit' => 'În tranzit', 'livrata' => 'Livrată', 'returnata' => 'Returnată', 'esuata' => 'Livrare eșuată'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['events' => 'array', 'shipped_at' => 'datetime', 'delivered_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
