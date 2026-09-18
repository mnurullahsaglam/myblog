<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\Repository;
use App\Models\Task;
use App\Models\User;
use App\Services\GitHubService;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));

    $this->github = Mockery::mock(GitHubService::class);
    $this->github->shouldIgnoreMissing();
    app()->instance(GitHubService::class, $this->github);
});

it('renders three columns in order', function (): void {
    $this->get(route('admin.tasks.board'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Work/TasksBoard')
            ->has('columns', 3)
            ->where('columns.0.key', 'todo')
            ->where('columns.1.key', 'in_progress')
            ->where('columns.2.key', 'completed')
        );
});

it('groups tasks into their status column', function (): void {
    Task::factory()->count(2)->create(['status' => 'todo']);
    Task::factory()->create(['status' => 'completed']);

    $this->get(route('admin.tasks.board'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('columns.0.tasks', 2)
            ->has('columns.1.tasks', 0)
            ->has('columns.2.tasks', 1)
        );
});

it('orders tasks within a column by sort order', function (): void {
    $second = Task::factory()->create(['status' => 'todo', 'sort_order' => 2, 'project_id' => null]);
    $first = Task::factory()->create(['status' => 'todo', 'sort_order' => 1, 'project_id' => null]);

    $this->get(route('admin.tasks.board'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('columns.0.tasks.0.id', $first->id)
            ->where('columns.0.tasks.1.id', $second->id)
        );
});

it('presents github metadata and labels', function (): void {
    Task::factory()->githubIssue()->create(['status' => 'todo']);

    $this->get(route('admin.tasks.board'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('columns.0.tasks.0.isGithubIssue', true)
            ->has('columns.0.tasks.0.labels', 1)
            ->where('columns.0.tasks.0.labels.0.name', 'bug')
        );
});

it('tolerates malformed github labels', function (): void {
    Task::factory()->create([
        'status' => 'todo',
        'github_issue_number' => '1',
        'github_issue_labels' => [['name' => 'ok'], ['colour' => 'nope'], ['name' => '']],
    ]);

    $this->get(route('admin.tasks.board'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('columns.0.tasks.0.labels', 1)
            ->where('columns.0.tasks.0.labels.0.name', 'ok')
        );
});

it('filters the board by project and by search', function (): void {
    $project = Project::factory()->create();
    Task::factory()->create(['status' => 'todo', 'project_id' => $project->id, 'title' => 'Fix the parser']);
    Task::factory()->create(['status' => 'todo', 'title' => 'Something else']);

    $this->get(route('admin.tasks.board', ['project' => $project->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('columns.0.tasks', 1));

    $this->get(route('admin.tasks.board', ['search' => 'parser']))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('columns.0.tasks', 1));
});

it('moves a task to another column at a position', function (): void {
    $task = Task::factory()->create(['status' => 'todo', 'sort_order' => 1, 'project_id' => null]);
    Task::factory()->create(['status' => 'in_progress', 'sort_order' => 1, 'project_id' => null]);

    $this->patch(route('admin.tasks.move', $task), ['status' => 'in_progress', 'position' => 0])
        ->assertRedirect();

    $task->refresh();

    expect($task->status)->toBe('in_progress');
    expect($task->sort_order)->toBe(1);
});

it('reindexes the destination column so positions stay contiguous', function (): void {
    $a = Task::factory()->create(['status' => 'in_progress', 'sort_order' => 1, 'project_id' => null]);
    $b = Task::factory()->create(['status' => 'in_progress', 'sort_order' => 2, 'project_id' => null]);
    $moving = Task::factory()->create(['status' => 'todo', 'sort_order' => 1, 'project_id' => null]);

    $this->patch(route('admin.tasks.move', $moving), ['status' => 'in_progress', 'position' => 1]);

    $ordered = Task::where('status', 'in_progress')->orderBy('sort_order')->pluck('id')->all();

    expect($ordered)->toBe([$a->id, $moving->id, $b->id]);
    expect(Task::where('status', 'in_progress')->orderBy('sort_order')->pluck('sort_order')->all())
        ->toBe([1, 2, 3]);
});

it('reorders within the same column', function (): void {
    $a = Task::factory()->create(['status' => 'todo', 'sort_order' => 1, 'project_id' => null]);
    $b = Task::factory()->create(['status' => 'todo', 'sort_order' => 2, 'project_id' => null]);
    $c = Task::factory()->create(['status' => 'todo', 'sort_order' => 3, 'project_id' => null]);

    $this->patch(route('admin.tasks.move', $c), ['status' => 'todo', 'position' => 0]);

    expect(Task::where('status', 'todo')->orderBy('sort_order')->pluck('id')->all())
        ->toBe([$c->id, $a->id, $b->id]);
});

it('clamps a position beyond the end of the column', function (): void {
    Task::factory()->create(['status' => 'in_progress', 'sort_order' => 1, 'project_id' => null]);
    $moving = Task::factory()->create(['status' => 'todo', 'sort_order' => 1, 'project_id' => null]);

    $this->patch(route('admin.tasks.move', $moving), ['status' => 'in_progress', 'position' => 99]);

    expect($moving->fresh()->sort_order)->toBe(2);
});

it('rejects an unknown status or a negative position', function (): void {
    $task = Task::factory()->create(['status' => 'todo']);

    $this->from(route('admin.tasks.board'))
        ->patch(route('admin.tasks.move', $task), ['status' => 'archived', 'position' => -1])
        ->assertSessionHasErrors(['status', 'position']);
});

it('syncs to github when a linked task changes column', function (): void {
    $task = Task::factory()->githubIssue()->create(['status' => 'todo']);

    $this->github->shouldReceive('updateIssue')->once();

    $this->patch(route('admin.tasks.move', $task), ['status' => 'completed', 'position' => 0]);
});

it('does not call github when only reordering siblings', function (): void {
    $a = Task::factory()->githubIssue()->create(['status' => 'todo', 'sort_order' => 1, 'project_id' => null]);
    $b = Task::factory()->githubIssue()->create(['status' => 'todo', 'sort_order' => 2, 'project_id' => null]);

    // Only the moved task talks to GitHub; the sibling is reindexed quietly.
    $this->github->shouldReceive('updateIssue')->atMost()->once();

    $this->patch(route('admin.tasks.move', $b), ['status' => 'todo', 'position' => 0]);

    expect($a->fresh()->sort_order)->toBe(2);
});

it('creates a task from the dialog', function (): void {
    $project = Project::factory()->create();

    $this->post(route('admin.tasks.store'), [
        'title' => 'Write the migration',
        'status' => 'todo',
        'project_id' => $project->id,
    ])->assertRedirect(route('admin.tasks.board'));

    $this->assertDatabaseHas('tasks', ['title' => 'Write the migration']);
});

it('updates a task from the dialog', function (): void {
    $repository = Repository::factory()->create();
    $task = Task::factory()->create(['title' => 'Old title']);

    $this->put(route('admin.tasks.update', $task), [
        'title' => 'New title',
        'description' => 'Updated',
        'status' => 'in_progress',
        'repository_id' => $repository->id,
    ])->assertRedirect(route('admin.tasks.board'));

    expect($task->fresh()->title)->toBe('New title');
});

it('requires a title and a valid status', function (): void {
    $task = Task::factory()->create();

    $this->from(route('admin.tasks.board'))
        ->put(route('admin.tasks.update', $task), ['title' => '', 'status' => 'nope'])
        ->assertSessionHasErrors(['title', 'status']);
});

it('deletes a task', function (): void {
    $task = Task::factory()->create();

    $this->delete(route('admin.tasks.destroy', $task))->assertRedirect(route('admin.tasks.board'));

    expect(Task::find($task->id))->toBeNull();
});

it('syncs a github issue on demand', function (): void {
    $task = Task::factory()->githubIssue()->create();

    $this->github->shouldReceive('updateIssue')->once()->andReturn(true);

    $this->post(route('admin.tasks.sync-github', $task))->assertRedirect(route('admin.tasks.board'));

    expect(session('flash.notification'))->toHaveKey('variant', 'success');
});

it('reports a failed github sync without throwing', function (): void {
    $task = Task::factory()->githubIssue()->create();

    $this->github->shouldReceive('updateIssue')->once()->andThrow(new Exception('API down'));

    $this->post(route('admin.tasks.sync-github', $task));

    expect(session('flash.notification'))->toHaveKey('variant', 'danger');
});

it('refuses to sync a task that is not a github issue', function (): void {
    $task = Task::factory()->create(['github_issue_number' => null]);

    $this->github->shouldNotReceive('updateIssue');

    $this->post(route('admin.tasks.sync-github', $task))->assertForbidden();
});

it('lists the board in the work cluster', function (): void {
    $work = collect(App\Support\Navigation::clusters())->firstWhere('label', 'Work');

    expect(collect($work['items'])->pluck('route'))->toContain('admin.tasks.board');
});
