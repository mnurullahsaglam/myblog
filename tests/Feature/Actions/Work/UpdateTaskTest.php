<?php

declare(strict_types=1);

use App\Actions\Work\UpdateTask;
use App\Models\Repository;
use App\Models\Task;
use Illuminate\Support\Facades\Http;

it('applies the attributes', function (): void {
    $task = Task::factory()->create(['title' => 'Before']);

    $updated = resolve(UpdateTask::class)->handle($task, ['title' => 'After']);

    expect($updated->refresh()->title)->toBe('After');
});

it('syncs to GitHub when a mirrored field changes', function (string $field, string $value): void {
    Http::fake(['api.github.com/*' => Http::response([], 200)]);

    $task = Task::factory()->for(Repository::factory())->create([
        'github_issue_number' => '12',
        'title' => 'Original title',
        'description' => 'Original description',
        'status' => 'todo',
    ]);

    resolve(UpdateTask::class)->handle($task, [$field => $value]);

    Http::assertSentCount(1);
})->with([
    'title' => ['title', 'A new title'],
    'description' => ['description', 'A new description'],
    'status' => ['status', 'completed'],
]);

it('does not sync when only an unmirrored field changes', function (): void {
    Http::fake();

    $task = Task::factory()->for(Repository::factory())->create(['github_issue_number' => '12']);

    resolve(UpdateTask::class)->handle($task, ['sort_order' => 42]);

    Http::assertNothingSent();
});

it('does not sync a task that is not a GitHub issue', function (): void {
    Http::fake();

    $task = Task::factory()->create(['github_issue_number' => null]);

    resolve(UpdateTask::class)->handle($task, ['title' => 'Changed']);

    Http::assertNothingSent();
});
