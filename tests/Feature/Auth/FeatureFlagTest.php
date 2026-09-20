<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Access\AccessProfile;
use App\Support\Features;
use Illuminate\Support\Collection;
use Laravel\Pennant\Feature;

beforeEach(function (): void {
    config(['app.admin_email' => 'owner@example.test']);
    $this->owner = User::factory()->admin()->create(['email' => 'owner@example.test']);
    $this->member = User::factory()->member()->create(['email' => 'her@example.test']);
});

/**
 * The rule everything else rests on: a flag means "not ready for her yet", so it
 * can never hide something from the person building it.
 */
it('is on for an admin whatever the store says', function (string $flag): void {
    Feature::for($this->owner)->deactivate($flag);

    expect(AccessProfile::forUser($this->owner->fresh())->feature($flag))->toBeTrue();
})->with(fn (): array => array_map(fn (string $f): array => [$f], Features::ALL));

it('is off for a member until it is turned on', function (string $flag): void {
    expect(AccessProfile::forUser($this->member)->feature($flag))->toBeFalse();

    Feature::for($this->member)->activate($flag);

    expect(AccessProfile::forUser($this->member->fresh())->feature($flag))->toBeTrue();
})->with(fn (): array => array_map(fn (string $f): array => [$f], Features::ALL));

it('is off for a guest', function (): void {
    expect(AccessProfile::forUser(null)->feature(Features::ALL[0]))->toBeFalse();
});

it('answers false for a flag nobody declared', function (): void {
    expect(AccessProfile::forUser($this->owner)->feature('not-a-real-flag'))->toBeFalse();
});

it('declares at least one flag, so these tests are not vacuous', function (): void {
    expect(Features::ALL)->not->toBeEmpty();
});

it('hides a flagged navigation item from a member', function (): void {
    $this->actingAs($this->member)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page->where(
            'navigation',
            fn (Collection $clusters): bool => $clusters
                ->flatMap(fn (array $cluster): array => $cluster['items'])
                ->pluck('label')
                ->doesntContain('Budget limits'),
        ));
});

it('shows it to the owner', function (): void {
    $this->actingAs($this->owner)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page->where(
            'navigation',
            fn (Collection $clusters): bool => $clusters
                ->flatMap(fn (array $cluster): array => $cluster['items'])
                ->pluck('label')
                ->contains('Budget limits'),
        ));
});

it('404s a flagged route for a member and serves it to the owner', function (): void {
    $this->actingAs($this->member)->get(route('admin.budget-limits.index'))->assertNotFound();

    $this->actingAs($this->owner)->get(route('admin.budget-limits.index'))->assertOk();
});

it('opens the flagged route to her once the flag is on', function (): void {
    Feature::for($this->member)->activate(Features::BudgetLimits);

    $this->actingAs($this->member->fresh())->get(route('admin.budget-limits.index'))->assertOk();
});
