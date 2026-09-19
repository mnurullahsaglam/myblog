<?php

declare(strict_types=1);

namespace App\Forms\Definitions;

use App\Enums\Currencies;
use App\Forms\Field;
use App\Forms\ResourceForm;
use Override;

final class ExpenseForm extends ResourceForm
{
    #[Override]
    protected int $columns = 2;

    protected function fields(): array
    {
        return [
            Field::money('amount')->required()->min(0)->step(0.01),
            Field::enum('currency', Currencies::class)->required()->default(Currencies::TRY->value),
            Field::date('date')->required()->default(now()->toDateString()),
            Field::relationship('expense_category_id', 'expenseCategory', 'name')->label('Category')->searchable(),
            Field::relationship('debt_id', 'debt', 'creditor_name')->label('Debt')->searchable()
                ->help('Set if this expense pays off a debt.'),
            Field::toggle('is_recurring')->label('Recurring subscription'),
            Field::toggle('is_tax_deductible')->label('Tax deductible'),
            Field::textarea('description')->required()->rows(3)->columnSpan(2),
            Field::image('receipt_path')->label('Receipt')->directory('receipts')
                ->accept(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])->columnSpan(2),
            Field::placeholder('created_at')->label('Created'),
        ];
    }
}
