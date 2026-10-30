<?php

namespace App\Modules\Lenses\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Regulă de compatibilitate editabilă din panou. `conditions` = listă de grupuri (SAU), fiecare grup = listă de
 * condiții (ȘI): {"field": "treatment", "op": "has", "value": "fotocromatic"}. Câmpurile disponibile sunt
 * descrise în App\Modules\Lenses\Services\RuleFields.
 */
class CompatibilityRule extends Model
{
    public const EFFECTS = [
        'block' => 'Blochează combinația',
        'require_review' => 'Necesită verificarea specialistului',
        'warn' => 'Avertizează clientul',
        'require_field' => 'Cere o măsurătoare suplimentară',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['conditions' => 'array', 'is_active' => 'boolean'];
    }
}
