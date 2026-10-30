<?php

namespace App\Modules\B2B\Models;

use Illuminate\Database\Eloquent\Model;

/** Raportul unui import CSV de comenzi (fără valorile rețetelor: doar rezultatul validării pe rânduri). */
class B2bImport extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['report' => 'array'];
    }
}
