<?php

declare(strict_types=1);

namespace App\Tables\Definitions;

use App\Models\Book;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\ResourceTable;
use Override;

final class BookTable extends ResourceTable
{
    #[Override]
    protected string $model = Book::class;

    #[Override]
    protected array $with = ['writer', 'publisher'];

    #[Override]
    protected array $withCount = ['categories'];

    #[Override]
    protected string $defaultSort = 'name';

    protected function columns(): array
    {
        return [
            Column::image('image')->label('')->size(28),
            Column::text('name')->sortable(),
            // Relationship columns are not sortable: ordering happens on real
            // database columns, and a dotted key would need a join.
            Column::text('writer.name')->label('Writer'),
            Column::text('publisher.name')->label('Publisher'),
            Column::count('page_count')->label('Pages')->sortable(),
            Column::number('publication_date')->label('Year')->sortable(),
            Column::count('categories_count')->label('Categories'),
        ];
    }

    protected function filters(): array
    {
        return [
            Filter::relationship('writer_id', 'writer', 'name')->label('Writer')->multiple(),
            Filter::relationship('publisher_id', 'publisher', 'name')->label('Publisher')->multiple(),
            Filter::boolean('image')->label('Cover')->trueLabel('Has cover')->falseLabel('No cover'),
        ];
    }

    protected function searchable(): array
    {
        return ['name', 'original_name', 'writer.name', 'publisher.name'];
    }
}
