<?php

declare(strict_types=1);

namespace App\Forms\Definitions;

use App\Enums\Currencies;
use App\Forms\Field;
use App\Forms\ResourceForm;
use Override;

final class UtilityBillForm extends ResourceForm
{
    #[Override]
    protected int $columns = 2;

    /**
     * @return array<int, Field>
     */
    protected function fields(): array
    {
        return [
            Field::relationship('utility_account_id', 'account', 'label')->label('Account')->required()->searchable(),
            Field::text('bill_number')->label('Bill no')->help('Fatura numarası.'),
            Field::date('period_start')->label('Period from'),
            Field::date('period_end')->label('Period to'),
            Field::date('issued_at')->label('Issued'),
            Field::date('due_date')->label('Due date')->required()->help('Son ödeme tarihi.'),

            Field::number('meter_start')->label('Meter start')->step(0.001)
                ->help('Only for metered utilities: elektrik, doğalgaz, su.'),
            Field::number('meter_end')->label('Meter end')->step(0.001),

            Field::money('total_amount')->label('Total')->required()->min(0)->step(0.01),
            Field::enum('currency', Currencies::class)->required()->default(Currencies::TRY->value),

            Field::repeater('lines', [
                Field::text('label')->required(),
                Field::money('amount')->required()->step(0.01),
            ])->label('Breakdown')->help('Enter the lines exactly as the bill prints them.')->columnSpan(2),

            Field::image('document_path')->label('Bill document')->directory('utility-bills')
                ->accept(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])->columnSpan(2),
            Field::placeholder('created_at')->label('Created'),
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<int, array{label?: string|null, amount?: float|int|string|null}>
     */
    public function pullLines(array &$values): array
    {
        $lines = $values['lines'] ?? [];
        unset($values['lines']);

        if (! is_array($lines)) {
            return [];
        }

        /** @var array<int, array{label?: string|null, amount?: float|int|string|null}> $rows */
        $rows = array_values(array_filter($lines, is_array(...)));

        return $rows;
    }
}
