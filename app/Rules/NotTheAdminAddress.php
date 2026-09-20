<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * The configured admin address is a privilege, so it cannot be claimed.
 *
 * Takes the address the subject already holds rather than the user itself, so a
 * validation rule does not have to reach into the model layer.
 *
 * Whoever holds ADMIN_EMAIL reaches every area whatever the role column says,
 * which is the lockout guarantee. Without this, a member could take that
 * privilege by editing her own profile, held back only by the unique index
 * happening to be occupied by somebody else.
 */
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
