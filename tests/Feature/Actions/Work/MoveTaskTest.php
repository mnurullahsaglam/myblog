<?php

declare(strict_types=1);

use App\Actions\Work\MoveTask;
use App\Models\Task;

function taskTitlesIn(string $status): array
{
    return Task::query()->where('status', $status)->orderBy('sort_order')->pluck('title')->all();
}

it('places a task at the requested position and reindexes its new column', function (): void {
    Task::factory()->create(['status' => 'todo', 'title' => 'A', 'sort_order' => 1]);
    Task::factory()->create(['status' => 'todo', 'title' => 'B', 'sort_order' => 2]);
    $moving = Task::factory()->create(['status' => 'in_progress', 'title' => 'M', 'sort_order' => 1]);

    resolve(MoveTask::class)->handle($moving, 'todo', 1);

    expect(taskTitlesIn('todo'))->toBe(['A', 'M', 'B'])
        ->and($moving->refresh()->status)->toBe('todo');
});

it('appends when the position is past the end of the column', function (): void {
    Task::factory()->create(['status' => 'todo', 'title' => 'A', 'sort_order' => 1]);
    $moving = Task::factory()->create(['status' => 'in_progress', 'title' => 'M']);

    resolve(MoveTask::class)->handle($moving, 'todo', 99);

    expect(taskTitlesIn('todo'))->toBe(['A', 'M']);
});

it('places at the head when the position is zero', function (): void {
    Task::factory()->create(['status' => 'todo', 'title' => 'A', 'sort_order' => 1]);
    $moving = Task::factory()->create(['status' => 'in_progress', 'title' => 'M']);

    resolve(MoveTask::class)->handle($moving, 'todo', 0);

    expect(taskTitlesIn('todo'))->toBe(['M', 'A']);
});

it('moves into an empty column', function (): void {
    $moving = Task::factory()->create(['status' => 'in_progress', 'title' => 'M']);

    resolve(MoveTask::class)->handle($moving, 'completed', 0);

    expect(taskTitlesIn('completed'))->toBe(['M'])
        ->and($moving->refresh()->sort_order)->toBe(1);
});

it('reorders within a column without changing the status', function (): void {
    Task::factory()->create(['status' => 'todo', 'title' => 'A', 'sort_order' => 1]);
    $b = Task::factory()->create(['status' => 'todo', 'title' => 'B', 'sort_order' => 2]);

    resolve(MoveTask::class)->handle($b, 'todo', 0);

    expect(taskTitlesIn('todo'))->toBe(['B', 'A'])
        ->and($b->refresh()->status)->toBe('todo');
});

it('leaves sort_order contiguous from one, with no gaps', function (): void {
    $tasks = Task::factory()->count(4)->sequence(
        ['sort_order' => 1],
        ['sort_order' => 5],
        ['sort_order' => 9],
        ['sort_order' => 12],
    )->create(['status' => 'todo']);

    resolve(MoveTask::class)->handle($tasks->last(), 'todo', 0);

    expect(Task::query()->where('status', 'todo')->orderBy('sort_order')->pluck('sort_order')->all())
        ->toBe([1, 2, 3, 4]);
});

it('does not touch tasks in other columns', function (): void {
    $other = Task::factory()->create(['status' => 'completed', 'sort_order' => 7]);
    $moving = Task::factory()->create(['status' => 'in_progress']);

    resolve(MoveTask::class)->handle($moving, 'todo', 0);

    expect($other->refresh()->sort_order)->toBe(7)
        ->and($other->status)->toBe('completed');
});

it('is a no-op in ordering terms when moved to the position it already holds', function (): void {
    $a = Task::factory()->create(['status' => 'todo', 'title' => 'A', 'sort_order' => 1]);
    Task::factory()->create(['status' => 'todo', 'title' => 'B', 'sort_order' => 2]);

    resolve(MoveTask::class)->handle($a, 'todo', 0);

    expect(taskTitlesIn('todo'))->toBe(['A', 'B']);
});
