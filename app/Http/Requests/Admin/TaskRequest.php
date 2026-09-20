<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Area;
use Illuminate\Validation\Rule;
use Override;

final class TaskRequest extends AdminRequest
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
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(MoveTaskRequest::STATUSES)],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'repository_id' => ['nullable', 'integer', 'exists:repositories,id'],
        ];
    }
}
