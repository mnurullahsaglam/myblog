<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\UtilityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UtilityAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::enum(UtilityType::class)],
            'provider' => ['required', 'string', 'max:255'],
            'label' => ['required', 'string', 'max:255'],
            'subscriber_no' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }
}
