<?php

declare(strict_types=1);

use App\Contracts\SyncsGitHubIssues;
use App\Models\Repository;
use App\Models\Task;

beforeEach(function (): void {
    $this->github = Mockery::mock(SyncsGitHubIssues::class);
    $this->github->shouldIgnoreMissing();

    app()->instance(SyncsGitHubIssues::class, $this->github);
});

it('assigns the next sort order within a status', function (): void {
    Task::factory()->create(['status' => 'todo', 'sort_order' => 3, 'project_id' => null]);

    $task = Task::factory()->create(['status' => 'todo', 'sort_order' => null, 'project_id' => null]);

    expect($task->sort_order)->toBe(4);
});

it('starts sort order at 1 for an empty status column', function (): void {
    $task = Task::factory()->create(['status' => 'in_progress', 'sort_order' => null, 'project_id' => null]);

    expect($task->sort_order)->toBe(1);
});

it('scopes sort order to the project', function (): void {
    $first = Task::factory()->create(['status' => 'todo', 'sort_order' => 7]);

    $second = Task::factory()->create([
        'status' => 'todo',
        'sort_order' => null,
        'project_id' => $first->project_id,
    ]);

    expect($second->sort_order)->toBe(8);
});

it('respects an explicitly provided sort order', function (): void {
    expect(Task::factory()->create(['status' => 'todo', 'sort_order' => 99])->sort_order)->toBe(99);
});

it('creates a github issue when the task has a repository and no issue number', function (): void {
    $repository = Repository::factory()->create();

    $this->github->shouldReceive('createIssue')->once();

    Task::factory()->create(['repository_id' => $repository->id, 'github_issue_number' => null]);
});

it('does not create a github issue when one already exists', function (): void {
    $repository = Repository::factory()->create();

    $this->github->shouldNotReceive('createIssue');

    Task::factory()->githubIssue()->create(['repository_id' => $repository->id]);
});

it('does not create a github issue for a task with no repository', function (): void {
    $this->github->shouldNotReceive('createIssue');

    Task::factory()->create(['repository_id' => null]);
});

it('syncs to github when the title changes on a github-linked task', function (): void {
    $task = Task::factory()->githubIssue()->create();

    $this->github->shouldReceive('updateIssue')->once();

    $task->update(['title' => 'A new title']);
});

it('syncs to github when the status changes', function (): void {
    $task = Task::factory()->githubIssue()->create(['status' => 'todo']);

    $this->github->shouldReceive('updateIssue')->once();

    $task->update(['status' => 'completed']);
});

it('does not sync when only an irrelevant field changes', function (): void {
    $task = Task::factory()->githubIssue()->create();

    $this->github->shouldNotReceive('updateIssue');

    $task->update(['sort_order' => 42]);
});

it('does not sync a task that is not a github issue', function (): void {
    $task = Task::factory()->create(['github_issue_number' => null]);

    $this->github->shouldNotReceive('updateIssue');

    $task->update(['title' => 'Renamed']);
});

it('swallows github failures so the save still succeeds', function (): void {
    $task = Task::factory()->githubIssue()->create();

    $this->github->shouldReceive('updateIssue')->andThrow(new Exception('API down'));

    $task->update(['title' => 'Still saved']);

    expect($task->fresh()->title)->toBe('Still saved');
});

it('creates a task even when no github token is configured', function (): void {
    app()->forgetInstance(SyncsGitHubIssues::class);
    config(['services.github.token' => null, 'services.github.personal_access_token' => null]);

    $task = Task::factory()->create(['repository_id' => null]);

    expect($task->exists)->toBeTrue();
});

it('swallows a missing github token when syncing a linked task', function (): void {
    app()->forgetInstance(SyncsGitHubIssues::class);
    config(['services.github.token' => null, 'services.github.personal_access_token' => null]);

    $repository = Repository::factory()->create();
    $task = Task::factory()->githubIssue()->create(['repository_id' => $repository->id]);

    $task->update(['title' => 'Renamed without a token']);

    expect($task->fresh()->title)->toBe('Renamed without a token');
});
