<?php

declare(strict_types=1);

namespace App\Tables\Definitions;

use App\Models\Writer;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\ResourceTable;
use Override;

final class WriterTable extends ResourceTable
{
    #[Override]
    protected string $model = Writer::class;

    #[Override]
    protected array $withCount = ['books'];

    #[Override]
    protected string $defaultSort = 'name';

    protected function columns(): array
    {
        return [
            Column::image('image')->label('')->circular()->size(28),
            Column::text('name')->sortable(),
            Column::number('birth_year')->label('Born')->sortable(),
            Column::number('death_year')->label('Died')->sortable()->default('—'),
            Column::text('birth_place')->label('Birthplace')->toggleable(hiddenByDefault: true),
            Column::count('books_count')->label('Books'),
        ];
    }

    protected function filters(): array
    {
        return [
            Filter::boolean('death_year')->label('Status')->trueLabel('Deceased')->falseLabel('Living'),
            Filter::boolean('image')->label('Portrait')->trueLabel('Has portrait')->falseLabel('No portrait'),
        ];
    }

    protected function searchable(): array
    {
        return ['name', 'birth_place', 'death_place'];
    }
}
