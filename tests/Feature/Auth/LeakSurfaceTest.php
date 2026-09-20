<?php

declare(strict_types=1);

use App\Actions\Exports\ExportResource;
use App\Enums\Area;
use App\Models\Book;
use App\Models\Category;
use App\Models\Client;
use App\Models\Debt;
use App\Models\Post;
use App\Models\User;
use App\Models\UtilityBill;
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

/**
 * The fifth surface, whatever it turns out to be.
 *
 * The matrix test only knows about routes. This walks the navigation the member
 * is actually shown and opens every item in it, so a cluster that lists a screen
 * she cannot reach — or hides one she can — fails here.
 */
it('gives every navigation item a route inside its cluster area', function (): void {
    foreach (Navigation::clusters() as $cluster) {
        foreach ($cluster['items'] as $item) {
            $reachable = $this->member->canAccess($cluster['area']);

            $response = $this->actingAs($this->member)->get(route($item['route']));

            $reachable
                ? expect($response->status())->not->toBe(404, "{$item['label']} is listed but unreachable")
                : expect($response->status())->toBe(404, "{$item['label']} is hidden but still reachable");
        }
    }
});

/**
 * Categories are General, but the books form needs them. She must be able to
 * pick one from inside Library without the Categories screen ever being hers.
 */
it('lets the member use categories from inside the books form', function (): void {
    Category::factory()->create(['name' => 'Zzshared']);

    $this->actingAs($this->member)
        ->get(route('admin.books.create'))
        ->assertOk()
        ->assertSee('Zzshared');

    $this->actingAs($this->member)
        ->get(route('admin.categories.index'))
        ->assertNotFound();
});

/**
 * Reading is not the point of her account. These are the two writes she does
 * most, and they have to work end to end.
 */
it('lets the member pay a utility bill', function (): void {
    $bill = UtilityBill::factory()->create();

    $this->actingAs($this->member)
        ->post(route('admin.utility-bills.pay', $bill))
        ->assertRedirect();

    expect($bill->fresh()->paid_at)->not->toBeNull();
});

it('lets the member record a debt payment', function (): void {
    $debt = Debt::factory()->create(['amount' => 1000, 'status' => 'pending']);

    $this->actingAs($this->member)
        ->post(route('admin.debts.pay', $debt), [
            'payment_amount' => 250,
            'payment_description' => 'First instalment',
        ])
        ->assertRedirect();

    expect((float) $debt->fresh()->amount)->toBe(750.0);
});

it('keeps the member out of every blog and work write route', function (string $routeName): void {
    $this->actingAs($this->member)
        ->post(route($routeName))
        ->assertNotFound();
})->with([
    'admin.posts.store',
    'admin.clients.store',
    'admin.projects.store',
    'admin.invoices.store',
    'admin.tasks.store',
]);
