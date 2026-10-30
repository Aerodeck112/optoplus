<?php

namespace App\Modules\Configurator\Support;

/**
 * Rezultatul validării pe server: erori (blochează), avertismente (informează), cerința de verificare de către
 * specialist și câmpurile suplimentare cerute. Mesajele sunt în limbaj simplu, pentru client.
 */
final class ValidationResult
{
    /** @var list<array{code:string, message:string, field:?string}> */
    public array $errors = [];

    /** @var list<array{code:string, message:string, field:?string}> */
    public array $warnings = [];

    /** @var list<string> motivele pentru care specialistul trebuie să verifice */
    public array $reviewReasons = [];

    /** @var list<string> */
    public array $requiredFields = [];

    /** @var list<int> reguli aplicate (pentru snapshotul comenzii) */
    public array $appliedRules = [];

    public function error(string $code, string $message, ?string $field = null): self
    {
        $this->errors[] = ['code' => $code, 'message' => $message, 'field' => $field];

        return $this;
    }

    public function warn(string $code, string $message, ?string $field = null): self
    {
        $this->warnings[] = ['code' => $code, 'message' => $message, 'field' => $field];

        return $this;
    }

    public function review(string $reason): self
    {
        if (! in_array($reason, $this->reviewReasons, true)) {
            $this->reviewReasons[] = $reason;
        }

        return $this;
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    public function requiresReview(): bool
    {
        return $this->reviewReasons !== [];
    }

    public function hasError(string $code): bool
    {
        foreach ($this->errors as $e) {
            if ($e['code'] === $code) {
                return true;
            }
        }

        return false;
    }

    public function toArray(): array
    {
        return [
            'valid' => $this->isValid(),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'requires_review' => $this->requiresReview(),
            'review_reasons' => $this->reviewReasons,
            'required_fields' => $this->requiredFields,
            'applied_rules' => $this->appliedRules,
        ];
    }
}
