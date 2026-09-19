<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Debt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class PayDebtRequest extends FormRequest
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
        $debt = $this->route('debt');
        $maximum = $debt instanceof Debt ? (float) $debt->amount : 0.0;

        return [
            'payment_amount' => ['required', 'numeric', 'min:0.01', 'max:'.$maximum],
            'payment_description' => ['required', 'string', 'max:500'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $debt = $this->route('debt');

            if ($debt instanceof Debt && $debt->status === 'paid') {
                $validator->errors()->add('payment_amount', 'This debt is already settled.');
            }
        });
    }
}
