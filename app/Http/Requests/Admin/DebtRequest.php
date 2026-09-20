<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Area;
use App\Enums\Currencies;
use Illuminate\Validation\Rule;
use Override;

final class DebtRequest extends AdminRequest
{
    #[Override]
    protected function area(): Area
    {
        return Area::Budget;
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
