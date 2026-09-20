<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\Project;
use App\Models\Repository;
use App\Models\Task;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

it('nulls a task project reference rather than orphaning the row', function (): void {
    $project = Project::factory()->create();
    $task = Task::factory()->create(['project_id' => $project->id]);

    $project->delete();

    expect(Task::whereKey($task->getKey())->exists())->toBeTrue()
        ->and($task->fresh()->project_id)->toBeNull();
});

it('nulls a task repository reference rather than orphaning the row', function (): void {
    $repository = Repository::factory()->create();
    $task = Task::factory()->create(['repository_id' => $repository->id]);

    $repository->delete();

    expect(Task::whereKey($task->getKey())->exists())->toBeTrue()
        ->and($task->fresh()->repository_id)->toBeNull();
});

it('nulls a project client reference rather than orphaning the row', function (): void {
    $client = Client::factory()->create();
    $project = Project::factory()->create(['client_id' => $client->id]);

    $client->delete();

    expect(Project::whereKey($project->getKey())->exists())->toBeTrue()
        ->and($project->fresh()->client_id)->toBeNull();
});

it('refuses a task pointing at a project that does not exist', function (): void {
    expect(fn () => Task::factory()->create(['project_id' => 999_999]))
        ->toThrow(QueryException::class);
});

it('indexes the columns the board filters and orders by', function (): void {
    expect(Schema::hasIndex('tasks', 'tasks_status_sort_order_index'))->toBeTrue();
});
