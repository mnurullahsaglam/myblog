<?php

declare(strict_types=1);

namespace App\Tables\Definitions;

use App\Models\Repository;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\ResourceTable;
use Override;

final class RepositoryTable extends ResourceTable
{
    #[Override]
    protected string $model = Repository::class;

    #[Override]
    protected array $with = ['project'];

    #[Override]
    protected string $defaultSort = 'name';

    protected function columns(): array
    {
        return [
            Column::text('name')->sortable(),
            Column::text('owner')->sortable(),
            Column::badge('visibility')->color(fn (Repository $record): string => match ($record->visibility) {
                'public' => 'success',
                'private' => 'warning',
                default => 'gray',
            }),
            Column::text('language')->sortable()->default('—'),
            Column::count('stars_count')->label('Stars')->sortable(),
            Column::count('issues_count')->label('Issues')->sortable(),
            Column::count('commits_count')->label('Commits')->sortable(),
            Column::boolean('is_active')->label('Active'),
            Column::text('project.name')->label('Project')->toggleable(hiddenByDefault: true),
            Column::datetime('last_synced_at')->label('Last synced')->sortable()->toggleable(hiddenByDefault: true)->default('never'),
            Column::datetime('created_at')->label('Created')->sortable()->toggleable(hiddenByDefault: true),
        ];
    }

    protected function filters(): array
    {
        return [
            Filter::select('visibility', ['public' => 'Public', 'private' => 'Private']),
            Filter::select('is_active', ['1' => 'Active', '0' => 'Inactive'])->label('Status'),
            Filter::relationship('project_id', 'project', 'name')->label('Project')->multiple(),
        ];
    }

    protected function searchable(): array
    {
        return ['name', 'owner', 'full_name', 'language'];
    }
}
