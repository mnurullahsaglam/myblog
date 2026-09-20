<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\Fortify\PasswordValidationRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Not an AdminRequest: this is the one form in the application filled in by
 * somebody who does not have an account yet.
 */
final class AcceptInviteRequest extends FormRequest
{
    use PasswordValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * `email` is absent on purpose. The invite decides the address, and a
     * submitted one is never read.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'password' => $this->passwordRules(),
        ];
    }
}
