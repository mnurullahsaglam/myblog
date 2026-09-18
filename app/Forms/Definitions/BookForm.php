<?php

declare(strict_types=1);

namespace App\Forms\Definitions;

use App\Forms\Field;
use App\Forms\ResourceForm;
use App\Models\Category;

final class BookForm extends ResourceForm
{
    protected int $columns = 2;

    protected function fields(): array
    {
        return [
            Field::relationship('writer_id', 'writer', 'name')->label('Writer')->required()->searchable(),
            Field::relationship('publisher_id', 'publisher', 'name')->label('Publisher')->required()->searchable(),
            Field::text('name')->required()->columnSpan(2),
            Field::readonlyCode('slug')->slugFrom('name')->columnSpan(2),
            Field::text('original_name')->label('Original title')->columnSpan(2),
            Field::multiRelationship('categories', Category::class, 'name')->label('Categories')->columnSpan(2),
            Field::number('page_count')->label('Pages')->min(1),
            Field::number('publication_date')->label('Publication year')->min(0)->max((float) date('Y')),
            Field::text('publication_location')->label('Published in'),
            Field::number('edition_number')->label('Edition')->min(1),
            Field::image('image')->label('Cover')->directory('books')
                ->accept(['image/jpeg', 'image/png', 'image/webp'])->columnSpan(2),
            Field::placeholder('created_at')->label('Created'),
        ];
    }
}
