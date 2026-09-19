<?php

declare(strict_types=1);

namespace App\Tables\Definitions;

use App\Models\Post;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\ResourceTable;
use Override;

final class PostTable extends ResourceTable
{
    #[Override]
    protected string $model = Post::class;

    #[Override]
    protected array $withCount = ['categories'];

    #[Override]
    protected string $defaultSort = '-created_at';

    protected function columns(): array
    {
        return [
            Column::image('image')->label('')->size(28),
            Column::text('title')->sortable(),
            Column::text('slug')->sortable(),
            Column::count('categories_count')->label('Categories'),
            Column::datetime('created_at')->label('Created')->sortable(),
            Column::datetime('updated_at')->label('Updated')->sortable()->toggleable(hiddenByDefault: true),
        ];
    }

    protected function filters(): array
    {
        return [
            Filter::dateRange('created_at')->label('Created'),
            Filter::boolean('image')->label('Image')->trueLabel('Has image')->falseLabel('No image'),
        ];
    }

    protected function searchable(): array
    {
        return ['title', 'slug', 'content'];
    }

    protected function titleColumn(): string
    {
        return 'title';
    }
}
