<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\WakaTimeSummary;

beforeEach(function (): void {
    config(['app.admin_email' => 'owner@example.test']);
    $this->owner = User::factory()->admin()->create(['email' => 'owner@example.test']);
});

function aDayOfCoding(int $seconds = 3600): WakaTimeSummary
{
    $summary = WakaTimeSummary::factory()->create(['date' => today(), 'total_seconds' => $seconds]);

    $summary->entries()->create(['type' => 'language', 'name' => 'PHP', 'seconds' => $seconds, 'percent' => 100]);

    return $summary;
}

/**
 * The panel already aggregates this. The endpoint exposes that same call rather
 * than the rows behind it, so the phone cannot arrive at a different total by
 * counting differently.
 */
it('draws the same aggregate the panel draws', function (): void {
    aDayOfCoding();

    $data = apiAs($this->owner)->get(route('api.v1.waka-time.dashboard'))->assertOk()->json('data');

    expect($data)->toHaveKeys(['range', 'rangeLabel', 'tiles', 'trend', 'weekday', 'breakdowns', 'syncedAt'])
        ->and(array_keys($data['breakdowns']))
        ->toBe(['language', 'editor', 'project', 'category', 'operating_system']);
});

it('answers for each range it offers', function (string $range): void {
    aDayOfCoding();

    expect(apiAs($this->owner)->get(route('api.v1.waka-time.dashboard', ['range' => $range]))->assertOk()->json('data.range'))
        ->toBe($range);
})->with(['7', '14', '30', '90', 'all']);

it('falls back to seven days for a range it does not know', function (): void {
    aDayOfCoding();

    expect(apiAs($this->owner)->get(route('api.v1.waka-time.dashboard', ['range' => 'forever']))->json('data.range'))
        ->toBe('7');
});

it('turns away a request with no token', function (): void {
    $this->getJson(route('api.v1.waka-time.dashboard'))->assertUnauthorized();
});

it('is a 404 for an account without the work area', function (): void {
    apiAs(userWithoutRole('nobody@example.test'))
        ->get(route('api.v1.waka-time.dashboard'))
        ->assertNotFound();
});

/**
 * "2 hours ago" is a rendering decision, and the phone makes it in the reader's
 * own locale rather than being handed one made in PHP.
 */
it('reports when it last synced as a timestamp, not a phrase', function (): void {
    aDayOfCoding();

    expect(apiAs($this->owner)->get(route('api.v1.waka-time.dashboard'))->json('data.syncedAt'))
        ->toMatch('/^\d{4}-\d{2}-\d{2}T/');
});

it('says nothing about a sync that never happened', function (): void {
    expect(apiAs($this->owner)->get(route('api.v1.waka-time.dashboard'))->json('data.syncedAt'))
        ->toBeNull();
});

/**
 * The tile icons are PrimeVue names and mean nothing to a phone.
 */
it('sends no icon names', function (): void {
    aDayOfCoding();

    $tiles = apiAs($this->owner)->get(route('api.v1.waka-time.dashboard'))->json('data.tiles');

    expect($tiles)->not->toBeEmpty();

    foreach ($tiles as $tile) {
        expect($tile)->not->toHaveKey('icon')
            ->and($tile)->toHaveKeys(['label', 'value']);
    }
});

it('does not let the dashboard be read as a summary id', function (): void {
    expect(route('api.v1.waka-time.dashboard'))->toEndWith('/api/v1/waka-time/dashboard');
});
