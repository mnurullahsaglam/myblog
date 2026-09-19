<?php

declare(strict_types=1);

namespace App\Forms\Definitions;

use App\Forms\Field;
use App\Forms\ResourceForm;

final class ProjectForm extends ResourceForm
{
    protected int $columns = 2;

    protected function fields(): array
    {
        return [
            Field::text('name')->required()->columnSpan(2),
            Field::relationship('client_id', 'client', 'title')->label('Client')->searchable(),
            Field::date('due_date')->label('Due date')->help('Optional.'),
            Field::placeholder('created_at')->label('Created'),
            Field::placeholder('updated_at')->label('Last modified'),
        ];
    }
}
