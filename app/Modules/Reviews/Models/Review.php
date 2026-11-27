<?php

namespace App\Modules\Reviews\Models;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** O recenzie: pe un produs (product_id) sau despre magazin (product_id gol). Emailul nu se afișează niciodată. */
class Review extends Model
{
    public const STATUSES = ['noua' => 'Nouă (nepublicată)', 'publicata' => 'Publicată', 'respinsa' => 'Respinsă'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['verified' => 'boolean', 'replied_at' => 'datetime', 'moderated_at' => 'datetime', 'rating' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    public function scopePublished($q)
    {
        return $q->where('status', 'publicata');
    }

    /** Numele afișat: prenumele și inițiala numelui („Ana P.”), ca să nu publicăm numele complet. */
    public function displayName(): string
    {
        $parts = preg_split('/\s+/u', trim($this->name), -1, PREG_SPLIT_NO_EMPTY) ?: ['Client'];
        $first = array_shift($parts);

        return $parts ? $first.' '.mb_strtoupper(mb_substr(end($parts), 0, 1)).'.' : $first;
    }
}
