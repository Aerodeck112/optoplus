<?php

namespace App\Modules\Production\Models;

use App\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Lucrarea de laborator pentru o linie de comandă cu lentile. Fișa de lucru (cu rețeta) este criptată. */
class ProductionJob extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['work_sheet' => 'encrypted:array', 'due_on' => 'date', 'approved_at' => 'datetime', 'requires_mounting' => 'boolean'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ProductionEvent::class)->orderBy('created_at')->orderBy('id');
    }

    public function remakeOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'remake_of_id');
    }
}
