<?php

declare(strict_types=1);

use App\Models\Book;
use App\Models\Client;
use App\Models\Debt;
use App\Models\Post;
use App\Models\Repository;
use App\Models\User;
use App\Models\WakaTimeSummary;
use App\Models\Writer;
use App\Support\Access\AccessProfile;
use App\Support\GlobalSearch;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);

    $this->owner = User::factory()->admin()->create(['email' => 'admin@example.test']);
    $this->actingAs($this->owner);
});

it('returns nothing for a term that is too short', function (string $term): void {
    Post::factory()->create(['title' => 'Rust']);

    $this->getJson(route('admin.search', ['q' => $term]))
        ->assertOk()
        ->assertExactJson(['results' => []]);
})->with(['' => [''], 'one character' => ['R'], 'whitespace' => ['  ']]);

it('finds records across several resources at once', function (): void {
    Post::factory()->create(['title' => 'Learning Rust']);
    Client::factory()->create(['title' => 'Rust Consulting']);

    $groups = collect($this->getJson(route('admin.search', ['q' => 'rust']))->json('results'))
        ->pluck('group')
        ->unique();

    expect($groups)->toContain('Posts', 'Clients');
});

it('links each result to a page for that record', function (): void {
    $post = Post::factory()->create(['title' => 'Learning Rust']);

    $results = $this->getJson(route('admin.search', ['q' => 'rust']))->json('results');

    expect($results[0]['url'])->toBe(route('admin.posts.edit', $post->id));
});

it('links read-only resources to their show page', function (): void {
    $repository = Repository::factory()->create(['name' => 'zzfindme']);

    $result = collect(GlobalSearch::query('zzfindme', AccessProfile::forUser($this->owner)))->firstWhere('group', 'Repositories');

    expect($result['url'])->toBe(route('admin.repositories.show', $repository->id));
});

it('finds a wakatime summary by date', function (): void {
    $summary = WakaTimeSummary::factory()->create(['date' => '2026-06-01']);

    $result = collect(GlobalSearch::query('2026-06-01', AccessProfile::forUser($this->owner)))->firstWhere('group', 'Daily summaries');

    expect($result['url'])->toBe(route('admin.waka-time-summaries.show', $summary->id));
});

it('caps results per resource', function (): void {
    Post::factory()->count(20)->create(['title' => 'Rust post']);

    $results = $this->getJson(route('admin.search', ['q' => 'rust']))->json('results');

    expect(collect($results)->where('group', 'Posts'))->toHaveCount(5);
});

it('searches across relationships', function (): void {
    $writer = Writer::factory()->create(['name' => 'Zafón']);
    Book::factory()->create(['writer_id' => $writer->id]);

    $groups = collect(GlobalSearch::query('Zafón', AccessProfile::forUser($this->owner)))->pluck('group');

    expect($groups)->toContain('Books', 'Writers');
});

it('searches debts, which take a constructor argument', function (): void {
    Debt::factory()->create(['creditor_name' => 'Zzbank']);

    expect(collect(GlobalSearch::query('Zzbank', AccessProfile::forUser($this->owner)))->pluck('group'))->toContain('Debts');
});

it('gives every result a label, a group and a url', function (): void {
    Post::factory()->create(['title' => 'Learning Rust']);

    foreach (GlobalSearch::query('rust', AccessProfile::forUser($this->owner)) as $result) {
        expect($result)->toHaveKeys(['label', 'group', 'url'])
            ->and($result['label'])->toBeString()->not->toBeEmpty()
            ->and($result['url'])->toStartWith('http');
    }
});

it('requires an authenticated admin', function (): void {
    auth()->logout();

    $this->getJson(route('admin.search', ['q' => 'rust']))->assertUnauthorized();
});

it('forbids a user with no areas', function (): void {
    $this->actingAs(userWithoutRole('nobody@example.test'));

    $this->getJson(route('admin.search', ['q' => 'rust']))->assertForbidden();
});
