<?php

declare(strict_types=1);

use App\Enums\Area;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    config(['app.admin_email' => 'owner@example.test']);
});

it('allows an area the user reaches', function (): void {
    $user = User::factory()->member()->create(['email' => 'her@example.test']);

    expect(Gate::forUser($user)->allows('access-area', Area::Budget))->toBeTrue();
});

it('denies an area the user does not reach', function (): void {
    $user = User::factory()->member()->create(['email' => 'her@example.test']);

    expect(Gate::forUser($user)->allows('access-area', Area::Work))->toBeFalse();
});

/**
 * The refusal has to be a 404 rather than a 403, and it has to come from
 * Laravel's own deny mechanism so the exception handler renders it properly.
 */
it('denies as not found rather than forbidden', function (): void {
    $user = User::factory()->member()->create(['email' => 'her@example.test']);

    $response = Gate::forUser($user)->inspect('access-area', Area::Work);

    expect($response->denied())->toBeTrue()
        ->and($response->status())->toBe(404);
});

it('allows the configured owner every area', function (Area $area): void {
    $owner = User::factory()->member()->create(['email' => 'owner@example.test']);

    expect(Gate::forUser($owner)->allows('access-area', $area))->toBeTrue();
})->with(array_map(fn (Area $area): array => [$area], Area::cases()));

it('lets anyone with an area through the panel door', function (): void {
    $member = User::factory()->member()->create(['email' => 'her@example.test']);

    expect(Gate::forUser($member)->allows('access-panel'))->toBeTrue();
});

it('keeps a user with no areas out of the panel entirely', function (): void {
    $stranger = User::factory()->create(['email' => 'stranger@example.test']);
    DB::table('users')->where('id', $stranger->id)->update(['role' => '']);

    expect(Gate::forUser($stranger->fresh())->allows('access-panel'))->toBeFalse();
});
