<?php

namespace App\Modules\Cms\Models;

use Illuminate\Database\Eloquent\Model;

/** Întrebare frecventă. Cele aprobate și marcate „pentru asistent” devin surse controlate pentru asistent. */
class FaqItem extends Model
{
    public const TOPICS = [
        'comanda' => 'Comanda', 'reteta' => 'Rețeta', 'lentile' => 'Lentile', 'rame' => 'Rame', 'livrare' => 'Livrare',
        'plata' => 'Plata', 'retur' => 'Retur și garanție', 'b2b' => 'Parteneri B2B', 'general' => 'General',
    ];

    protected $table = 'cms_faq_items';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'use_in_assistant' => 'boolean', 'approved_at' => 'datetime'];
    }
}
