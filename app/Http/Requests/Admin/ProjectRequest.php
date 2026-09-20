<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Area;
use Override;

final class ProjectRequest extends AdminRequest
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
            'name' => ['required', 'string', 'max:255'],
            'due_date' => ['nullable', 'date'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
        ];
    }
}
