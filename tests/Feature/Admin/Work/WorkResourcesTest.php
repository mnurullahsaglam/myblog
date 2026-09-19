<?php

declare(strict_types=1);

use App\Contracts\SyncsGitHubIssues;
use App\Models\Client;
use App\Models\Project;
use App\Models\Repository;
use App\Models\Task;
use App\Models\User;
use App\Support\Navigation;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));

    $github = Mockery::mock(SyncsGitHubIssues::class);
    $github->shouldIgnoreMissing();

    app()->instance(SyncsGitHubIssues::class, $github);
});

it('lists clients with their project counts', function (): void {
    $client = Client::factory()->create(['title' => 'Acme']);
    Project::factory()->count(2)->create(['client_id' => $client->id]);

    $this->get(route('admin.clients.index'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Work/Clients/Index')
            ->where('rows.data.0.cells.title.display', 'Acme')
            ->where('rows.data.0.cells.projects_count.display', '2')
        );
});

it('searches clients by title and email', function (): void {
    Client::factory()->create(['title' => 'Acme Corp', 'email' => 'hello@acme.test']);
    Client::factory()->create(['title' => 'Globex', 'email' => 'hi@globex.test']);

    $this->get(route('admin.clients.index', ['search' => 'acme']))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->has('rows.data', 1));

    $this->get(route('admin.clients.index', ['search' => 'globex.test']))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->has('rows.data', 1));
});

it('creates a client', function (): void {
    $this->post(route('admin.clients.store'), [
        'title' => 'Acme Corp',
        'email' => 'hello@acme.test',
        'address' => '1 Main Street',
        'country' => 'Türkiye',
        'tax_no' => '1234567890',
    ])->assertRedirect(route('admin.clients.index'));

    $this->assertDatabaseHas('clients', ['title' => 'Acme Corp']);
});

it('requires every client field and a valid email', function (): void {
    $this->from(route('admin.clients.create'))
        ->post(route('admin.clients.store'), ['title' => '', 'email' => 'not-an-email'])
        ->assertSessionHasErrors(['title', 'email', 'address', 'country', 'tax_no']);
});

it('lists projects with their client and counts', function (): void {
    $client = Client::factory()->create(['title' => 'Acme']);
    $project = Project::factory()->create(['name' => 'Website', 'client_id' => $client->id]);
    Task::factory()->count(3)->create(['project_id' => $project->id]);

    $this->get(route('admin.projects.index'))
        ->assertInertia(function (AssertableInertia $page): void {
            $page->component('Work/Projects/Index');

            $cells = $page->toArray()['props']['rows']['data'][0]['cells'];

            expect($cells['name']['display'])->toBe('Website')
                ->and($cells['client.title']['display'])->toBe('Acme')
                ->and($cells['tasks_count']['display'])->toBe('3');
        });
});

it('formats a project due date', function (): void {
    Project::factory()->create(['due_date' => '2026-12-24']);

    $this->get(route('admin.projects.index'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('rows.data.0.cells.due_date.display', '24 Dec 2026')
        );
});

it('shows a dash for a project with no deadline', function (): void {
    Project::factory()->create(['due_date' => null]);

    $this->get(route('admin.projects.index'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('rows.data.0.cells.due_date.display', '—')
        );
});

it('filters projects by whether they have a deadline', function (): void {
    Project::factory()->create(['due_date' => '2026-12-24']);
    Project::factory()->create(['due_date' => null]);

    $this->get(route('admin.projects.index', ['filter' => ['due_date' => 'no']]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->has('rows.data', 1));
});

it('creates a project with no client or deadline', function (): void {
    $this->post(route('admin.projects.store'), ['name' => 'Internal'])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('projects', ['name' => 'Internal', 'client_id' => null]);
});

it('lists repositories and colours visibility', function (): void {
    Repository::factory()->create(['visibility' => 'public']);

    $this->get(route('admin.repositories.index'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Work/Repositories/Index')
            ->where('rows.data.0.cells.visibility.variant', 'success')
        );

    Repository::query()->delete();
    Repository::factory()->create(['visibility' => 'private']);

    $this->get(route('admin.repositories.index'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('rows.data.0.cells.visibility.variant', 'warning')
        );
});

it('shows the commits count', function (): void {
    Repository::factory()->create(['commits_count' => 1384]);

    $this->get(route('admin.repositories.index'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('rows.data.0.cells.commits_count.display', '1,384')
        );
});

it('renders the active flag as a boolean cell', function (): void {
    Repository::factory()->create(['is_active' => true]);

    $this->get(route('admin.repositories.index'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('rows.data.0.cells.is_active.raw', true)
        );
});

it('hides noisy repository columns by default', function (): void {
    $this->get(route('admin.repositories.index'))
        ->assertInertia(function (AssertableInertia $page): void {
            $columns = collect($page->toArray()['props']['schema']['columns'])->keyBy('key');

            expect($columns['last_synced_at']['hiddenByDefault'])->toBeTrue()
                ->and($columns['created_at']['hiddenByDefault'])->toBeTrue()
                ->and($columns['name']['hiddenByDefault'])->toBeFalse();
        });
});

it('filters repositories by visibility', function (): void {
    Repository::factory()->create(['visibility' => 'public']);
    Repository::factory()->create(['visibility' => 'private']);

    $this->get(route('admin.repositories.index', ['filter' => ['visibility' => 'public']]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->has('rows.data', 1));
});

it('renders the repository show page', function (): void {
    $repository = Repository::factory()->create(['name' => 'myblog']);

    $this->get(route('admin.repositories.show', $repository))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Work/Repositories/Show')
            ->where('values.name', 'myblog')
        );
});

it('creates a repository', function (): void {
    $project = Project::factory()->create();

    $this->post(route('admin.repositories.store'), [
        'project_id' => $project->id,
        'name' => 'myblog',
        'full_name' => 'mnurullahsaglam/myblog',
        'owner' => 'mnurullahsaglam',
        'visibility' => 'public',
        'github_url' => 'https://github.com/mnurullahsaglam/myblog',
        'github_id' => '123456',
        'default_branch' => 'main',
        'commits_count' => 42,
    ])->assertRedirect(route('admin.repositories.index'));

    $this->assertDatabaseHas('repositories', ['name' => 'myblog', 'commits_count' => 42]);
});

it('rejects a duplicate github id', function (): void {
    $existing = Repository::factory()->create();

    $this->from(route('admin.repositories.create'))
        ->post(route('admin.repositories.store'), [
            'name' => 'other',
            'full_name' => 'owner/other',
            'owner' => 'owner',
            'visibility' => 'public',
            'github_url' => 'https://github.com/owner/other',
            'github_id' => $existing->github_id,
            'default_branch' => 'main',
        ])
        ->assertSessionHasErrors('github_id');
});

it('lets a repository keep its own github id on update', function (): void {
    $repository = Repository::factory()->create();

    $this->put(route('admin.repositories.update', $repository), [
        'project_id' => $repository->project_id,
        'name' => 'renamed',
        'full_name' => $repository->full_name,
        'owner' => $repository->owner,
        'visibility' => $repository->visibility,
        'github_url' => $repository->github_url,
        'github_id' => $repository->github_id,
        'default_branch' => $repository->default_branch,
    ])->assertSessionHasNoErrors();

    expect($repository->fresh()->name)->toBe('renamed');
});

it('rejects a github url that is not a url', function (): void {
    $this->from(route('admin.repositories.create'))
        ->post(route('admin.repositories.store'), [
            'name' => 'x',
            'full_name' => 'o/x',
            'owner' => 'o',
            'visibility' => 'public',
            'github_url' => 'not a url',
            'github_id' => '99',
            'default_branch' => 'main',
        ])
        ->assertSessionHasErrors('github_url');
});

it('lists the work cluster in navigation', function (): void {
    $work = collect(Navigation::clusters())->firstWhere('label', 'Work');

    expect(collect($work['items'])->pluck('route')->all())
        ->toContain('admin.clients.index', 'admin.projects.index', 'admin.repositories.index');
});
