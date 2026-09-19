<?php

declare(strict_types=1);

namespace App\Actions\Work;

use App\Models\Task;

final class DeleteTask
{
    public function handle(Task $task): void
    {
        $task->delete();
    }
}
