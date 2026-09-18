<?php

declare(strict_types=1);

namespace App\Forms\Definitions;

use App\Forms\Field;
use App\Forms\ResourceForm;

final class RepositoryForm extends ResourceForm
{
    protected int $columns = 2;

    protected function fields(): array
    {
        return [
            Field::relationship('project_id', 'project', 'name')->label('Project')->searchable()->columnSpan(2),
            Field::text('name')->required(),
            Field::text('owner')->required(),
            Field::text('full_name')->label('Full name')->required()->help('owner/repo')->columnSpan(2),
            Field::textarea('description')->rows(3)->columnSpan(2),
            Field::select('visibility', ['public' => 'Public', 'private' => 'Private'])->required(),
            Field::text('github_url')->label('GitHub URL')->required(),
            Field::text('github_id')->label('GitHub ID')->required(),
            Field::text('default_branch')->label('Default branch')->required()->default('main'),
            Field::text('language'),
            Field::toggle('is_active')->label('Active')->default(true),
            Field::number('stars_count')->label('Stars')->min(0)->default(0),
            Field::number('forks_count')->label('Forks')->min(0)->default(0),
            Field::number('issues_count')->label('Issues')->min(0)->default(0),
            Field::number('commits_count')->label('Commits')->min(0)->default(0),
            Field::datetime('last_synced_at')->label('Last synced at'),
            Field::placeholder('created_at')->label('Created'),
        ];
    }
}
