<?php

declare(strict_types=1);

use App\Actions\Work\SyncTaskToGitHub;
use App\Models\Repository;
use App\Models\Task;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * The token is deliberately not read from the environment: CI copies
 * .env.example, which leaves GITHUB_TOKEN empty, and SyncsGitHubIssues refuses to
 * build a request without one.
 */
beforeEach(function (): void {
    config(['services.github.token' => 'test-token']);
});

it('refuses a task that is not linked to a GitHub issue', function (): void {
    $task = Task::factory()->create(['github_issue_number' => null]);

    expect(fn () => resolve(SyncTaskToGitHub::class)->handle($task))
        ->toThrow(AccessDeniedHttpException::class, 'This task is not linked to a GitHub issue.');
});

it('reports success when GitHub accepts the update', function (): void {
    Http::fake(['api.github.com/*' => Http::response(['number' => 12], 200)]);

    $task = Task::factory()->for(Repository::factory())->create(['github_issue_number' => '12']);

    expect(resolve(SyncTaskToGitHub::class)->handle($task))->toBeTrue();
});

it('reports failure when GitHub rejects the update', function (): void {
    Http::fake(['api.github.com/*' => Http::response(['message' => 'Not Found'], 404)]);

    $task = Task::factory()->for(Repository::factory())->create(['github_issue_number' => '12']);

    expect(resolve(SyncTaskToGitHub::class)->handle($task))->toBeFalse();
});

it('reports failure rather than throwing when the transport fails', function (): void {
    Http::fake(fn () => throw new ConnectionException('network down'));

    $task = Task::factory()->for(Repository::factory())->create(['github_issue_number' => '12']);

    expect(resolve(SyncTaskToGitHub::class)->handle($task))->toBeFalse();
});

it('reports failure when the task has an issue number but no repository', function (): void {
    Http::fake();

    $task = Task::factory()->create(['repository_id' => null, 'github_issue_number' => '12']);

    expect(resolve(SyncTaskToGitHub::class)->handle($task))->toBeFalse();

    Http::assertNothingSent();
});

it('closes the issue when the task moves to completed', function (): void {
    Http::fake(['api.github.com/*' => Http::response([], 200)]);

    $task = Task::factory()->for(Repository::factory())->create([
        'github_issue_number' => '12',
        'status' => 'completed',
    ]);

    resolve(SyncTaskToGitHub::class)->handle($task);

    Http::assertSent(fn ($request): bool => $request['state'] === 'closed');
});

it('reopens the issue for a task that is not completed', function (string $status): void {
    Http::fake(['api.github.com/*' => Http::response([], 200)]);

    $task = Task::factory()->for(Repository::factory())->create([
        'github_issue_number' => '12',
        'status' => $status,
    ]);

    resolve(SyncTaskToGitHub::class)->handle($task);

    Http::assertSent(fn ($request): bool => $request['state'] === 'open');
})->with(['todo', 'in_progress']);
