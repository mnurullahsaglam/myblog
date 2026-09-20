<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\UtilityAccount;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

final class UtilityBillRequest extends FormRequest
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
            'utility_account_id' => ['required', 'integer', 'exists:utility_accounts,id'],
            'bill_number' => ['nullable', 'string', 'max:255'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'issued_at' => ['nullable', 'date'],
            'due_date' => ['required', 'date'],
            'meter_start' => ['nullable', 'numeric'],
            'meter_end' => ['nullable', 'numeric'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string'],
            'document_path' => ['nullable', 'file', 'max:5120'],
            'lines' => ['nullable', 'array'],
            'lines.*.label' => ['required', 'string', 'max:255'],
            'lines.*.amount' => ['required', 'numeric'],
        ];
    }

    /**
     * The form has no conditional visibility, so the rule that readings belong
     * only to metered utilities is enforced here, where the account is known.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $account = UtilityAccount::find($this->input('utility_account_id'));

            if (! $account instanceof UtilityAccount || $account->type->hasMeter()) {
                return;
            }

            foreach (['meter_start', 'meter_end'] as $field) {
                if ($this->input($field) !== null) {
                    $validator->errors()->add($field, $account->type->getLabel().' has no meter.');
                }
            }
        });
    }
}
