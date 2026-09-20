<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Ability;
use App\Enums\Area;
use App\Enums\Currencies;
use App\Support\Access\AccessProfile;
use Illuminate\Validation\Rule;
use Override;

final class IncomeRequest extends AdminRequest
{
    #[Override]
    protected function area(): Area
    {
        return Area::Budget;
    }

    /**
     * The client is absent from the rules for anyone who may not see it, so a
     * hand-made POST carrying one is never validated and never written. Dropping
     * the field from the form stops it being offered; this stops it being sent.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', Rule::enum(Currencies::class)],
            'date' => ['required', 'date'],
            'source' => ['nullable', 'string', 'max:255'],
            'income_category_id' => ['nullable', 'integer', 'exists:income_categories,id'],
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'debt_id' => ['nullable', 'integer', 'exists:debts,id'],
            'description' => ['required', 'string', 'max:1000'],
        ];

        if (resolve(AccessProfile::class)->allows(Ability::SeeClientIdentity)) {
            $rules['client_id'] = ['nullable', 'integer', 'exists:clients,id'];
        }

        return $rules;
    }
}
