<?php

declare(strict_types=1);

namespace App\Tables\Definitions;

use App\Models\Category;
use App\Tables\Column;
use App\Tables\ResourceTable;
use Override;

final class CategoryTable extends ResourceTable
{
    #[Override]
    protected string $model = Category::class;

    #[Override]
    protected array $withCount = ['posts', 'books'];

    #[Override]
    protected string $defaultSort = 'name';

    protected function columns(): array
    {
        return [
            Column::text('name')->sortable(),
            Column::text('slug')->sortable(),
            Column::count('posts_count')->label('Posts'),
            Column::count('books_count')->label('Books'),
        ];
    }

    protected function searchable(): array
    {
        return ['name', 'slug'];
    }
}
