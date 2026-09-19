<?php

declare(strict_types=1);

namespace App\Forms\Definitions;

use App\Enums\Currencies;
use App\Forms\Field;
use App\Forms\ResourceForm;
use Override;

final class IncomeForm extends ResourceForm
{
    #[Override]
    protected int $columns = 2;

    protected function fields(): array
    {
        return [
            Field::money('amount')->required()->min(0)->step(0.01),
            Field::enum('currency', Currencies::class)->required()->default(Currencies::TRY->value),
            Field::date('date')->required()->default(now()->toDateString()),
            Field::text('source')->help('Where the money came from.'),
            Field::relationship('income_category_id', 'incomeCategory', 'name')->label('Category')->searchable(),
            Field::relationship('client_id', 'client', 'title')->label('Client')->searchable(),
            Field::relationship('invoice_id', 'invoice', 'invoice_number')->label('Invoice')->searchable()
                ->help('Set if this is an invoice payment.'),
            Field::relationship('debt_id', 'debt', 'creditor_name')->label('Debt')->searchable()
                ->help('Set if this is a debt repayment.'),
            Field::textarea('description')->required()->rows(3)->columnSpan(2),
            Field::placeholder('created_at')->label('Created'),
        ];
    }
}
