<?php

declare(strict_types=1);

use App\Actions\Work\DeleteTask;
use App\Models\Task;

it('deletes the task', function (): void {
    $task = Task::factory()->create();

    resolve(DeleteTask::class)->handle($task);

    expect(Task::whereKey($task->getKey())->exists())->toBeFalse();
});

it('leaves the rest of the column in place', function (): void {
    $doomed = Task::factory()->create(['status' => 'todo']);
    $survivors = Task::factory()->count(2)->create(['status' => 'todo']);

    resolve(DeleteTask::class)->handle($doomed);

    expect(Task::query()->whereKey($survivors->modelKeys())->count())->toBe(2);
});
