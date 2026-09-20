<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Area;
use Illuminate\Validation\Rule;
use Override;

final class MoveTaskRequest extends AdminRequest
{
    /** @var array<int, string> */
    public const array STATUSES = ['todo', 'in_progress', 'completed'];

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
            'status' => ['required', Rule::in(self::STATUSES)],
            'position' => ['required', 'integer', 'min:0'],
        ];
    }
}
