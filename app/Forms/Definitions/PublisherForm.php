<?php

declare(strict_types=1);

namespace App\Forms\Definitions;

use App\Forms\Field;
use App\Forms\ResourceForm;

final class PublisherForm extends ResourceForm
{
    protected function fields(): array
    {
        return [
            Field::text('name')->required(),
            Field::readonlyCode('slug')->slugFrom('name'),
            Field::placeholder('created_at')->label('Created'),
        ];
    }
}
