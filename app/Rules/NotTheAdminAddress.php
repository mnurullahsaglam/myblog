<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

final readonly class NotTheAdminAddress implements ValidationRule
{
    public function __construct(private ?string $keptBy = null) {}

    /**
     * @param  Closure(string, string|null=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $adminEmail = config('app.admin_email');

        if (! is_string($adminEmail) || $adminEmail === '' || ! is_string($value)) {
            return;
        }

        if (mb_strtolower(trim($value)) !== mb_strtolower($adminEmail)) {
            return;
        }

        if (is_string($this->keptBy) && mb_strtolower($this->keptBy) === mb_strtolower($adminEmail)) {
            return;
        }

        $fail('That address is reserved.');
    }
}
