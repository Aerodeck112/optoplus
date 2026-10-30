<?php

namespace App\Modules\Cms\Models;

use Illuminate\Database\Eloquent\Model;

/** Mesaj primit prin formularele siteului; conținutul este criptat. */
class FormSubmission extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'handled_at' => 'datetime'];
    }
}
