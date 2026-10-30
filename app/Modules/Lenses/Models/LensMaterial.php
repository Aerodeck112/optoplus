<?php

namespace App\Modules\Lenses\Models;

use Illuminate\Database\Eloquent\Model;

/** Materialul lentilei, identificat prin indicele de refracție (păstrat ×1000: 1600 = 1,60). */
class LensMaterial extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_confirmed' => 'boolean', 'is_active' => 'boolean', 'refractive_index' => 'integer'];
    }

    /** „1,60” */
    public function indexLabel(): string
    {
        return number_format($this->refractive_index / 1000, 2, ',', '');
    }
}
