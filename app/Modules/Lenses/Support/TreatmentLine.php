<?php

namespace App\Modules\Lenses\Support;

use App\Modules\Lenses\Models\LensTreatmentOption;

/**
 * Textul tratamentelor unei lucrări, pentru oameni: „Fotocromatic (Gri 85%)”, nu codul intern „gri_85”.
 * Comenzile noi păstrează în snapshot și numele nuanței (option_label); pentru cele vechi îl căutăm după cod.
 */
final class TreatmentLine
{
    /** @var array<string, ?string> */
    private static array $labels = [];

    /** @param list<array{treatment_id?:int, name?:?string, option?:?string, option_label?:?string}> $treatments */
    public static function format(array $treatments, string $none = ''): string
    {
        $parts = array_values(array_filter(array_map([self::class, 'one'], $treatments)));

        return $parts ? implode(', ', $parts) : $none;
    }

    public static function one(array $t): string
    {
        $label = $t['option_label'] ?? null;
        if (! $label && ! empty($t['option'])) {
            $label = self::optionLabel((int) ($t['treatment_id'] ?? 0), (string) $t['option']) ?? $t['option'];
        }

        return trim(($t['name'] ?? '').($label ? ' ('.$label.')' : ''));
    }

    public static function optionLabel(int $treatmentId, string $code): ?string
    {
        $key = $treatmentId.'|'.$code;

        return self::$labels[$key] ??= LensTreatmentOption::where('treatment_id', $treatmentId)->where('code', $code)->value('label');
    }
}
