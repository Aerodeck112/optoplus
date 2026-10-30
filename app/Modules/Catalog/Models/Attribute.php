<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Atribut filtrabil al produselor: gen, material, formă, tip ramă, culoare, construcție (ex. clip-on). */
class Attribute extends Model
{
    public const GEN = 'gen';

    public const MATERIAL = 'material';

    public const FORMA = 'forma';

    public const TIP_RAMA = 'tip_rama';

    public const CULOARE = 'culoare';

    public const CONSTRUCTIE = 'constructie';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_filterable' => 'boolean', 'is_indexable' => 'boolean'];
    }

    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class)->orderBy('sort')->orderBy('label');
    }
}
