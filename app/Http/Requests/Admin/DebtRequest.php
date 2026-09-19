<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Currencies;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class DebtRequest extends FormRequest
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
            'creditor_name' => ['required', 'string', 'max:255'],
            'creditor_type' => ['required', Rule::in(['person', 'institute'])],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', Rule::enum(Currencies::class)],
            'date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['pending', 'paid'])],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
