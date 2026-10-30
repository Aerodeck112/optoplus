<?php

namespace App\Modules\Crm\Models;

use App\Models\User;
use App\Modules\B2B\Models\Organization;
use App\Modules\Customers\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Opportunity extends Model
{
    public const STAGES = ['nou' => 'Nouă', 'calificat' => 'Calificată', 'oferta' => 'Ofertă trimisă', 'negociere' => 'Negociere', 'castigat' => 'Câștigată', 'pierdut' => 'Pierdută'];

    protected $table = 'crm_opportunities';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['expected_close_on' => 'date'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
