<?php

declare(strict_types=1);

namespace App\Forms\Definitions;

use App\Forms\Field;
use App\Forms\ResourceForm;
use Override;

final class ClientForm extends ResourceForm
{
    #[Override]
    protected int $columns = 2;

    protected function fields(): array
    {
        return [
            Field::text('title')->label('Client name')->required()->columnSpan(2),
            Field::text('email')->required(),
            Field::text('country')->required(),
            Field::text('address')->required()->columnSpan(2),
            Field::text('tax_no')->label('Tax number')->required(),
            Field::placeholder('created_at')->label('Created'),
        ];
    }
}
