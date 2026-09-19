<?php

declare(strict_types=1);

namespace App\Actions\Work;

use App\Models\Task;

final class UpdateTask
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Task $task, array $attributes): Task
    {
        $task->update($attributes);

        return $task;
    }
}
