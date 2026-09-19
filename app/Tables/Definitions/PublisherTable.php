<?php

declare(strict_types=1);

namespace App\Tables\Definitions;

use App\Models\Publisher;
use App\Tables\Column;
use App\Tables\ResourceTable;
use Override;

final class PublisherTable extends ResourceTable
{
    #[Override]
    protected string $model = Publisher::class;

    #[Override]
    protected array $withCount = ['books'];

    #[Override]
    protected string $defaultSort = 'name';

    protected function columns(): array
    {
        return [
            Column::text('name')->sortable(),
            Column::count('books_count')->label('Books'),
        ];
    }

    protected function searchable(): array
    {
        return ['name'];
    }
}
