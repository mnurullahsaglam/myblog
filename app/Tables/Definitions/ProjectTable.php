<?php

declare(strict_types=1);

namespace App\Tables\Definitions;

use App\Models\Project;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\ResourceTable;
use Override;

final class ProjectTable extends ResourceTable
{
    #[Override]
    protected string $model = Project::class;

    #[Override]
    protected array $with = ['client'];

    #[Override]
    protected array $withCount = ['tasks', 'repositories'];

    #[Override]
    protected string $defaultSort = 'name';

    protected function columns(): array
    {
        return [
            Column::text('name')->sortable(),
            Column::text('client.title')->label('Client'),
            Column::date('due_date')->label('Due')->sortable()->default('—'),
            Column::count('tasks_count')->label('Tasks'),
            Column::count('repositories_count')->label('Repos'),
            Column::datetime('created_at')->label('Created')->sortable()->toggleable(hiddenByDefault: true),
        ];
    }

    protected function filters(): array
    {
        return [
            Filter::relationship('client_id', 'client', 'title')->label('Client')->multiple(),
            Filter::dateRange('due_date')->label('Due'),
            Filter::boolean('due_date')->label('Deadline')->trueLabel('Has deadline')->falseLabel('No deadline'),
        ];
    }

    protected function searchable(): array
    {
        return ['name', 'client.title'];
    }
}
