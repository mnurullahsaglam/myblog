<?php

declare(strict_types=1);

namespace App\Forms\Definitions;

use App\Forms\Field;
use App\Forms\ResourceForm;

final class WriterForm extends ResourceForm
{
    protected int $columns = 2;

    protected function fields(): array
    {
        return [
            Field::text('name')->required()->columnSpan(2),
            Field::readonlyCode('slug')->slugFrom('name')->columnSpan(2),
            Field::image('image')->label('Portrait')->directory('writers')
                ->accept(['image/jpeg', 'image/png', 'image/webp'])->columnSpan(2),
            Field::number('birth_year')->label('Born')->min(0)->max((float) date('Y')),
            Field::number('death_year')->label('Died')->min(0)->max((float) date('Y')),
            Field::text('birth_place')->label('Birthplace'),
            Field::text('death_place')->label('Place of death'),
            Field::richtext('bio')->label('Biography')->rows(12)->columnSpan(2),
            Field::placeholder('created_at')->label('Created'),
        ];
    }
}
