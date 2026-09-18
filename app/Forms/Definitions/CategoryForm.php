<?php

declare(strict_types=1);

namespace App\Forms\Definitions;

use App\Forms\Field;
use App\Forms\ResourceForm;

final class CategoryForm extends ResourceForm
{
    protected function fields(): array
    {
        return [
            Field::text('name')->required(),
            Field::readonlyCode('slug')->slugFrom('name')->help('Derived from the name.'),
            Field::placeholder('created_at')->label('Created'),
        ];
    }
}
