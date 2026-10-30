<?php

namespace App\Modules\Shipping\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Metodă de livrare. Durata transportului este separată de timpul de producție. */
class ShippingMethod extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['countries' => 'array', 'allows_cod' => 'boolean', 'is_active' => 'boolean'];
    }

    public function rules(): HasMany
    {
        return $this->hasMany(ShippingRule::class)->orderBy('min_subtotal');
    }
}
