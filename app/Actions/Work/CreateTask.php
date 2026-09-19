<?php

declare(strict_types=1);

namespace App\Actions\Work;

use App\Models\Task;

final class CreateTask
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes): Task
    {
        return Task::create($attributes);
    }
}
