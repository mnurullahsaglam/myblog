<?php

declare(strict_types=1);

use App\Actions\Work\CreateTask;
use App\Models\Project;
use App\Models\Task;

it('creates a task', function (): void {
    $project = Project::factory()->create();

    $task = resolve(CreateTask::class)->handle([
        'project_id' => $project->id,
        'title' => 'Write the thing',
        'description' => 'Body',
        'status' => 'todo',
    ]);

    expect($task->exists)->toBeTrue()
        ->and($task->title)->toBe('Write the thing');
});

it('lets the observer assign the next position within the same project', function (): void {
    $project = Project::factory()->create();
    Task::factory()->create(['project_id' => $project->id, 'status' => 'todo', 'sort_order' => 4]);

    $task = resolve(CreateTask::class)->handle([
        'project_id' => $project->id,
        'title' => 'Next',
        'description' => 'Body',
        'status' => 'todo',
    ]);

    expect($task->sort_order)->toBe(5);
});

it('numbers each project independently', function (): void {
    $busy = Project::factory()->create();
    Task::factory()->create(['project_id' => $busy->id, 'status' => 'todo', 'sort_order' => 9]);

    $task = resolve(CreateTask::class)->handle([
        'project_id' => Project::factory()->create()->id,
        'title' => 'Fresh project',
        'description' => 'Body',
        'status' => 'todo',
    ]);

    expect($task->sort_order)->toBe(1);
});

it('starts a previously empty column at position one', function (): void {
    $task = resolve(CreateTask::class)->handle([
        'project_id' => Project::factory()->create()->id,
        'title' => 'First',
        'description' => 'Body',
        'status' => 'completed',
    ]);

    expect($task->sort_order)->toBe(1);
});
