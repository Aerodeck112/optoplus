<?php

namespace App\Modules\Lenses\Services;

use App\Modules\Lenses\Models\CompatibilityRule;
use Illuminate\Support\Collection;

/**
 * Evaluează regulile de compatibilitate asupra unui context plat (vezi RuleFields). Fără cod executabil în date:
 * doar câmpuri, operatori și valori dintr-o listă închisă.
 */
class RuleEngine
{
    /** @return Collection<int, CompatibilityRule> regulile active, în ordinea priorității */
    public function activeRules(): Collection
    {
        return CompatibilityRule::query()->where('is_active', true)->orderBy('priority')->orderBy('id')->get();
    }

    /** @return list<CompatibilityRule> regulile ale căror condiții sunt îndeplinite */
    public function matching(array $context, ?Collection $rules = null): array
    {
        $out = [];
        foreach ($rules ?? $this->activeRules() as $rule) {
            if ($this->matches((array) $rule->conditions, $context)) {
                $out[] = $rule;
            }
        }

        return $out;
    }

    /** Grupurile sunt legate prin SAU; condițiile dintr-un grup prin ȘI. Un grup gol nu potrivește nimic. */
    public function matches(array $groups, array $context): bool
    {
        foreach ($groups as $group) {
            if (! is_array($group) || $group === []) {
                continue;
            }
            $all = true;
            foreach ($group as $condition) {
                if (! $this->check($condition, $context)) {
                    $all = false;
                    break;
                }
            }
            if ($all) {
                return true;
            }
        }

        return false;
    }

    public function check(array $c, array $context): bool
    {
        $field = (string) ($c['field'] ?? '');
        if (! array_key_exists($field, RuleFields::all())) {
            return false; // câmp necunoscut: regula nu se aplică (și e semnalată în panou)
        }
        $actual = $context[$field] ?? null;
        $expected = $c['value'] ?? null;

        return match ($c['op'] ?? 'eq') {
            'eq' => $this->scalar($actual) === $this->scalar($expected),
            'neq' => $this->scalar($actual) !== $this->scalar($expected),
            'in' => in_array($this->scalar($actual), array_map([$this, 'scalar'], (array) $expected), true),
            'not_in' => ! in_array($this->scalar($actual), array_map([$this, 'scalar'], (array) $expected), true),
            'gt' => is_numeric($actual) && $actual > (float) $expected,
            'gte' => is_numeric($actual) && $actual >= (float) $expected,
            'lt' => is_numeric($actual) && $actual < (float) $expected,
            'lte' => is_numeric($actual) && $actual <= (float) $expected,
            'has' => array_intersect((array) $actual, (array) $expected) !== [],
            'not_has' => array_intersect((array) $actual, (array) $expected) === [],
            'empty' => $actual === null || $actual === '' || $actual === [],
            'not_empty' => ! ($actual === null || $actual === '' || $actual === []),
            default => false,
        };
    }

    private function scalar(mixed $v): mixed
    {
        if (is_bool($v)) {
            return $v ? 'true' : 'false';
        }

        return is_numeric($v) ? (string) (0 + $v) : (is_string($v) ? $v : json_encode($v));
    }
}
