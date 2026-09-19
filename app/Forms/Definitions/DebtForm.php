<?php

declare(strict_types=1);

namespace App\Forms\Definitions;

use App\Enums\Currencies;
use App\Forms\Field;
use App\Forms\ResourceForm;
use Override;

final class DebtForm extends ResourceForm
{
    #[Override]
    protected int $columns = 2;

    protected function fields(): array
    {
        return [
            Field::text('creditor_name')->label('Creditor')->required(),
            Field::select('creditor_type', ['person' => 'Person', 'institute' => 'Institute'])
                ->label('Creditor type')->required()->default('person'),
            Field::money('amount')->required()->min(0)->step(0.01),
            Field::enum('currency', Currencies::class)->required()->default(Currencies::TRY->value),
            Field::date('date')->label('Incurred on')->required()->default(now()->toDateString()),
            Field::date('due_date')->label('Due date')->help('Optional.'),
            Field::select('status', ['pending' => 'Pending', 'paid' => 'Paid'])->required()->default('pending'),
            Field::textarea('description')->rows(3)->help('Optional.')->columnSpan(2),
            Field::placeholder('created_at')->label('Created'),
        ];
    }
}
