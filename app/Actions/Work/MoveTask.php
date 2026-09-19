<?php

declare(strict_types=1);

namespace App\Actions\Work;

use App\Models\Task;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Move a task to a column and a position within it.
 *
 * The moved task is saved through update() so TaskObserver sees the status
 * change and syncs GitHub; its siblings are reordered with updateQuietly() so a
 * pure drag makes no API calls.
 */
final class MoveTask
{
    public function handle(Task $task, string $status, int $position): void
    {
        DB::transaction(function () use ($task, $status, $position): void {
            $task->updateQuietly(['sort_order' => null]);

            /** @var Collection<int, Task> $siblings */
            $siblings = Task::query()
                ->where('status', $status)
                ->whereKeyNot($task->getKey())
                ->orderBy('sort_order')
                ->get();

            $ordered = $siblings->values();
            $ordered->splice(min($position, $ordered->count()), 0, [$task]);

            foreach ($ordered as $index => $sibling) {
                if ($sibling->is($task)) {
                    $task->update(['status' => $status, 'sort_order' => $index + 1]);

                    continue;
                }

                $sibling->updateQuietly(['sort_order' => $index + 1]);
            }
        });
    }
}
