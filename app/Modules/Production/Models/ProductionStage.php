<?php

namespace App\Modules\Production\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionStage extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['visible_to_customer' => 'boolean', 'only_if_mounting' => 'boolean', 'is_terminal' => 'boolean', 'is_active' => 'boolean'];
    }
}
