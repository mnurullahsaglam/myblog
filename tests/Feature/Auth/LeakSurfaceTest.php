<?php

declare(strict_types=1);

use App\Actions\Exports\ExportResource;
use App\Enums\Area;
use App\Models\Book;
use App\Models\Client;
use App\Models\Post;
use App\Models\User;
use App\Support\GlobalSearch;
use App\Support\Navigation;
use Illuminate\Support\Collection;

beforeEach(function (): void {
    config(['app.admin_email' => 'owner@example.test']);

    $this->owner = User::factory()->admin()->create(['email' => 'owner@example.test']);
    $this->member = User::factory()->member()->create(['email' => 'her@example.test']);
});

it('shows the member only her clusters', function (): void {
    $labels = collect(Navigation::forUser($this->member))->pluck('label')->all();

    expect($labels)->toEqualCanonicalizing(['Budget', 'Library', 'Utilities']);
});

it('shows the admin every cluster', function (): void {
    expect(Navigation::forUser($this->owner))->toHaveCount(count(Navigation::clusters()));
});

it('keeps every navigation item inside its own cluster area', function (): void {
    foreach (Navigation::forUser($this->member) as $cluster) {
        expect($this->member->canAccess($cluster['area']))->toBeTrue(
            "Cluster {$cluster['label']} leaked to a member",
        );
    }
});

it('does not share a hidden cluster with the browser', function (): void {
    $this->actingAs($this->member)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('navigation', fn (Collection $clusters): bool => $clusters
                ->pluck('label')
                ->doesntContain('Work')));
});

it('hides search results from areas the member cannot reach', function (): void {
    Client::factory()->create(['title' => 'Zzleak Client']);
    Book::factory()->create(['name' => 'Zzleak Book']);

    $groups = collect(GlobalSearch::query('Zzleak', $this->member))->pluck('group');

    expect($groups)->toContain('Books')
        ->and($groups)->not->toContain('Clients');
});

it('still searches everything for the admin', function (): void {
    Client::factory()->create(['title' => 'Zzleak Client']);

    expect(collect(GlobalSearch::query('Zzleak', $this->owner))->pluck('group'))
        ->toContain('Clients');
});

it('returns nothing rather than everything when the term matches a hidden area only', function (): void {
    Post::factory()->create(['title' => 'Zzleak Post']);

    expect(GlobalSearch::query('Zzleak', $this->member))->toBe([]);
});

it('sends the member a dashboard without work or blog panels', function (): void {
    $this->actingAs($this->member)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('budget')
            ->has('library')
            ->missing('work')
            ->missing('recentPosts')
            ->missing('openTasks'));
});

it('sends the admin the whole dashboard', function (): void {
    $this->actingAs($this->owner)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('budget')
            ->has('work')
            ->has('library')
            ->has('recentPosts')
            ->has('openTasks'));
});

/**
 * Every export is Library today, so the member-refusal case has no subject.
 * The moment a Work export is added, areaFor() returns null for it and this
 * fails; fixing that failure means writing the area down, and once it is
 * written down the controller enforces it.
 */
it('gives every export an area', function (string $resource): void {
    expect(ExportResource::areaFor($resource))->toBeInstanceOf(Area::class);
})->with(array_keys(ExportResource::EXPORTS));

it('lets the member export a resource inside her areas', function (): void {
    $this->actingAs($this->member)
        ->post(route('admin.exports.store', 'books'))
        ->assertRedirect();
});

it('lets the admin export everything', function (string $resource): void {
    $this->actingAs($this->owner)
        ->post(route('admin.exports.store', $resource))
        ->assertRedirect();
})->with(array_keys(ExportResource::EXPORTS));

it('refuses an export nobody has registered', function (): void {
    $this->actingAs($this->owner)
        ->post(route('admin.exports.store', 'invoices'))
        ->assertNotFound();
});
