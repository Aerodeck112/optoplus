<?php

namespace App\Modules\Configurator\Support;

/**
 * Datele unei configurații, așa cum le validează și le prețuiește serverul. Nu conține prețuri:
 * prețul se calculează exclusiv de PriceEngine.
 */
final class ConfigurationInput
{
    public const ENTRIES = ['cu_rama', 'fara_rama', 'rama_mea', 'ajutor'];

    public const USAGES = [
        'distanta' => 'Distanță (condus, privit la depărtare)',
        'aproape' => 'Aproape (citit, lucru de aproape)',
        'calculator' => 'Calculator și birou',
        'toate' => 'Toate distanțele',
        'nu_stiu' => 'Nu știu încă',
    ];

    /**
     * @param  list<array{treatment_id:int, option:?string}>  $treatments
     * @param  array{pd?:?int, pd_od?:?int, pd_os?:?int, height_od?:?int, height_os?:?int}  $measurements  zecimi de mm
     */
    public function __construct(
        public readonly string $entry = 'cu_rama',
        public readonly ?string $usage = null,
        public readonly ?int $frameVariantId = null,
        public readonly ?int $offeringId = null,
        public readonly array $treatments = [],
        public readonly ?EyeRx $od = null,
        public readonly ?EyeRx $os = null,
        public readonly ?string $pdMode = null,
        public readonly array $measurements = [],
        public readonly bool $measurementsByStore = false,
        public readonly string $eyes = 'ambii',
        public readonly int $quantity = 1,
        public readonly string $channel = 'b2c',
    ) {}

    public function with(array $changes): self
    {
        $data = get_object_vars($this);
        foreach ($changes as $k => $v) {
            $data[$k] = $v;
        }

        return new self(...$data);
    }

    /** @return list<EyeRx> ochii configurați */
    public function eyesRx(): array
    {
        return array_values(array_filter(match ($this->eyes) {
            'od' => [$this->od],
            'os' => [$this->os],
            default => [$this->od, $this->os],
        }));
    }

    public function treatmentIds(): array
    {
        return array_map(fn ($t) => (int) $t['treatment_id'], $this->treatments);
    }

    public function toArray(): array
    {
        return [
            'entry' => $this->entry,
            'usage' => $this->usage,
            'frame_variant_id' => $this->frameVariantId,
            'offering_id' => $this->offeringId,
            'treatments' => $this->treatments,
            'od' => $this->od?->toArray(),
            'os' => $this->os?->toArray(),
            'pd_mode' => $this->pdMode,
            'measurements' => $this->measurements,
            'measurements_by_store' => $this->measurementsByStore,
            'eyes' => $this->eyes,
            'quantity' => $this->quantity,
            'channel' => $this->channel,
        ];
    }

    public static function fromArray(array $a): self
    {
        return new self(
            entry: $a['entry'] ?? 'cu_rama',
            usage: $a['usage'] ?? null,
            frameVariantId: isset($a['frame_variant_id']) ? (int) $a['frame_variant_id'] : null,
            offeringId: isset($a['offering_id']) ? (int) $a['offering_id'] : null,
            treatments: array_values(array_map(fn ($t) => ['treatment_id' => (int) $t['treatment_id'], 'option' => $t['option'] ?? null], $a['treatments'] ?? [])),
            od: EyeRx::fromArray($a['od'] ?? null),
            os: EyeRx::fromArray($a['os'] ?? null),
            pdMode: $a['pd_mode'] ?? null,
            measurements: array_map(fn ($v) => $v === null ? null : (int) $v, $a['measurements'] ?? []),
            measurementsByStore: (bool) ($a['measurements_by_store'] ?? false),
            eyes: $a['eyes'] ?? 'ambii',
            quantity: max(1, (int) ($a['quantity'] ?? 1)),
            channel: $a['channel'] ?? 'b2c',
        );
    }
}
