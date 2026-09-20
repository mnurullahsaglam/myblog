<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Area;
use App\Models\Repository;
use Illuminate\Validation\Rule;
use Override;

final class RepositoryRequest extends AdminRequest
{
    #[Override]
    protected function area(): Area
    {
        return Area::Work;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $repository = $this->route('repository');
        $repositoryId = $repository instanceof Repository ? $repository->id : null;

        return [
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'name' => ['required', 'string', 'max:255'],
            'owner' => ['required', 'string', 'max:255'],
            'full_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'visibility' => ['required', Rule::in(['public', 'private'])],
            'github_url' => ['required', 'url', 'max:255'],
            'github_id' => ['required', 'string', 'max:255', Rule::unique('repositories', 'github_id')->ignore($repositoryId)],
            'default_branch' => ['required', 'string', 'max:255'],
            'language' => ['nullable', 'string', 'max:255'],
            'stars_count' => ['nullable', 'integer', 'min:0'],
            'forks_count' => ['nullable', 'integer', 'min:0'],
            'issues_count' => ['nullable', 'integer', 'min:0'],
            'commits_count' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'last_synced_at' => ['nullable', 'date'],
        ];
    }
}
