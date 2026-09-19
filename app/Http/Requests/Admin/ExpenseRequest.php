<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Currencies;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ExpenseRequest extends FormRequest
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
            'expense_category_id' => ['nullable', 'integer', 'exists:expense_categories,id'],
            'debt_id' => ['nullable', 'integer', 'exists:debts,id'],
            'is_recurring' => ['boolean'],
            'is_tax_deductible' => ['boolean'],
            'description' => ['required', 'string', 'max:1000'],
            'receipt_path' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }
}
