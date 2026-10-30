<?php

namespace App\Modules\B2B\Models;

use Illuminate\Database\Eloquent\Model;

/** Document privat al unui partener (contract, ofertă, factură emisă extern). Descărcare doar prin portal, auditată. */
class OrganizationDocument extends Model
{
    public const KINDS = ['contract' => 'Contract', 'oferta' => 'Ofertă', 'factura' => 'Factură', 'certificat' => 'Certificat', 'altul' => 'Altul'];

    public const DISK = 'documente';

    protected $guarded = ['id'];
}
