<?php

namespace App\Modules\Configurator\Support;

/**
 * Valorile unui ochi, ca întregi: SPH/CYL/ADD în sutimi de dioptrie, AX în grade.
 * Cilindrul poate fi introdus în notația plus sau minus; producția și matricea folosesc notația minus.
 */
final class EyeRx
{
    public function __construct(
        public readonly ?int $sph,
        public readonly ?int $cyl = null,
        public readonly ?int $axis = null,
        public readonly ?int $add = null,
    ) {}

    /** Din valori introduse de om: „-2,25”, „-2.25”, „+1”, „” → sutimi; axul în grade. */
    public static function fromInput(array $in): self
    {
        return new self(
            self::parseDiopter($in['sph'] ?? null),
            self::parseDiopter($in['cyl'] ?? null),
            self::parseInt($in['axis'] ?? null),
            self::parseDiopter($in['add'] ?? null),
        );
    }

    public static function parseDiopter(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_int($v)) {
            return $v;
        }
        $s = str_replace([',', ' ', '+'], ['.', '', ''], trim((string) $v));
        if (in_array(strtolower($s), ['pl', 'plan', '0', '0.0', '0.00', '-0', '-0.00'], true)) {
            return 0;
        }
        if (! preg_match('/^-?\d{1,2}(\.\d{1,2})?$/', $s)) {
            throw new InvalidRxValue((string) $v);
        }

        return (int) round(((float) $s) * 100);
    }

    private static function parseInt(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (! preg_match('/^\d{1,3}$/', trim((string) $v))) {
            throw new InvalidRxValue((string) $v);
        }

        return (int) $v;
    }

    public function hasCyl(): bool
    {
        return $this->cyl !== null && $this->cyl !== 0;
    }

    /**
     * Transpunerea în notația cu cilindru negativ: SPH' = SPH + CYL, CYL' = −CYL, AX' = AX ± 90.
     * Rețeta clientului nu se modifică; transpunerea se folosește doar pentru comparația cu matricea și fișa de lucru.
     */
    public function toMinusCyl(): self
    {
        if (! $this->hasCyl() || $this->cyl < 0) {
            return $this;
        }
        $axis = $this->axis === null ? null : ($this->axis > 90 ? $this->axis - 90 : $this->axis + 90);

        return new self(($this->sph ?? 0) + $this->cyl, -$this->cyl, $axis, $this->add);
    }

    /** Puterea maximă pe un meridian (în valoare absolută), pe notația minus. */
    public function combinedAbs(): int
    {
        $m = $this->toMinusCyl();

        return max(abs($m->sph ?? 0), abs(($m->sph ?? 0) + ($m->cyl ?? 0)));
    }

    public function toArray(): array
    {
        return ['sph' => $this->sph, 'cyl' => $this->cyl, 'axis' => $this->axis, 'add' => $this->add];
    }

    public static function fromArray(?array $a): ?self
    {
        return $a === null ? null : new self($a['sph'] ?? null, $a['cyl'] ?? null, $a['axis'] ?? null, $a['add'] ?? null);
    }

    public static function format(?int $hundredths, bool $sign = true): string
    {
        if ($hundredths === null) {
            return '—';
        }
        $v = number_format(abs($hundredths) / 100, 2, ',', '');
        if ($hundredths === 0) {
            return '0,00';
        }

        return ($hundredths < 0 ? '−' : ($sign ? '+' : '')).$v;
    }
}
