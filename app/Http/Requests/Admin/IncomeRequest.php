<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Currencies;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IncomeRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', Rule::enum(Currencies::class)],
            'date' => ['required', 'date'],
            'source' => ['nullable', 'string', 'max:255'],
            'income_category_id' => ['nullable', 'integer', 'exists:income_categories,id'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'debt_id' => ['nullable', 'integer', 'exists:debts,id'],
            'description' => ['required', 'string', 'max:1000'],
        ];
    }
}
