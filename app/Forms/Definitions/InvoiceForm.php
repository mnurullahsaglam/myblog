<?php

declare(strict_types=1);

namespace App\Forms\Definitions;

use App\Enums\Currencies;
use App\Forms\Field;
use App\Forms\ResourceForm;
use Override;

final class InvoiceForm extends ResourceForm
{
    #[Override]
    protected int $columns = 2;

    protected function fields(): array
    {
        return [
            Field::relationship('client_id', 'client', 'title')->label('Client')->required()->searchable()->columnSpan(2),
            Field::text('invoice_number')->label('Invoice number')->required(),
            Field::datetime('issued_at')->label('Issued at')->required()->default(now()->format('Y-m-d H:i:s')),
            Field::enum('currency', Currencies::class)->required()->default(Currencies::TRY->value),
            Field::number('amount')->label('Net amount')->required()->min(0)->default(0),
            Field::number('tax_rate')->label('Tax rate (%)')->required()->min(0)->max(100)->default(0),
            Field::number('tax_amount')->label('Tax amount')->disabled()->help('Computed on save.'),
            Field::number('total_amount')->label('Total')->disabled()->help('Computed on save.'),
            Field::file('invoice')->label('Invoice archive')->accept(['application/zip'])->columnSpan(2),
            Field::placeholder('created_at')->label('Created'),
        ];
    }
}
