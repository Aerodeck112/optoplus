<?php

namespace App\Modules\Configurator\Models;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Configurator\Support\ConfigurationInput;
use App\Modules\Customers\Models\Customer;
use App\Modules\Lenses\Models\LensOffering;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Configurația salvată a unui client (ciornă reluabilă). Valorile rețetei stau în Prescription (criptate);
 * aici sunt doar alegerile, rezultatul ultimei validări și ultimul calcul de preț (pentru afișare rapidă —
 * la adăugarea în coș și la comandă totul se recalculează pe server).
 */
class Configuration extends Model
{
    public const STEPS = [1 => 'Utilizare', 2 => 'Rețeta', 3 => 'Lentila', 4 => 'Tratamente', 5 => 'Rezumat'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'treatments' => 'array',
            'validation' => 'array',
            'price_snapshot' => 'array',
            'share_expires_at' => 'datetime',
        ];
    }

    public function frameVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'frame_variant_id');
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(LensOffering::class, 'lens_offering_id');
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** Datele de intrare pentru validator și motorul de preț (rețeta decriptată doar în memorie). */
    public function toInput(string $channel = 'b2c'): ConfigurationInput
    {
        $p = $this->prescription;
        $measurements = (array) ($p?->measurements ?? []);

        return new ConfigurationInput(
            entry: $this->entry,
            usage: $this->usage,
            frameVariantId: $this->frame_variant_id,
            offeringId: $this->lens_offering_id,
            treatments: array_values((array) ($this->treatments ?? [])),
            od: $p?->eye('od'),
            os: $p?->eye('os'),
            pdMode: $p?->pd_mode,
            measurements: array_intersect_key($measurements, array_flip(['pd', 'pd_od', 'pd_os', 'height_od', 'height_os'])),
            measurementsByStore: (bool) ($measurements['by_store'] ?? false),
            eyes: $measurements['eyes'] ?? 'ambii',
            quantity: max(1, (int) $this->quantity),
            channel: $channel,
        );
    }

    public function stepUrl(int $step): string
    {
        return '/configurator/'.$this->uuid.'/pas/'.$step.'/';
    }
}
