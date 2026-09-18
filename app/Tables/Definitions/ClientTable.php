<?php

declare(strict_types=1);

namespace App\Tables\Definitions;

use App\Models\Client;
use App\Tables\Column;
use App\Tables\ResourceTable;

final class ClientTable extends ResourceTable
{
    protected string $model = Client::class;

    protected array $withCount = ['projects'];

    protected string $defaultSort = 'title';

    protected function columns(): array
    {
        return [
            Column::text('title')->label('Client')->sortable(),
            Column::text('email')->sortable(),
            Column::text('country')->sortable(),
            Column::text('address')->limit(40)->tooltip()->toggleable(hiddenByDefault: true),
            Column::text('tax_no')->label('Tax no'),
            Column::count('projects_count')->label('Projects'),
        ];
    }

    protected function searchable(): array
    {
        return ['title', 'email', 'country', 'tax_no'];
    }

    protected function titleColumn(): string
    {
        return 'title';
    }
}
