<?php

namespace App\Modules\Shipping\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingRule extends Model
{
    protected $guarded = ['id'];

    public function method(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class, 'shipping_method_id');
    }
}
