<?php

namespace App\Modules\Cms\Models;

use Illuminate\Database\Eloquent\Model;

/** Bloc refolosibil: editat o singură dată, afișat pe toate paginile care îl includ. */
class ReusableBlock extends Model
{
    protected $table = 'cms_reusable_blocks';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }
}
