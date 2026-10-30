<?php

namespace App\Modules\Import\Models;

use Illuminate\Database\Eloquent\Model;

class ImportRun extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['counters' => 'array', 'report' => 'array', 'finished_at' => 'datetime'];
    }
}
