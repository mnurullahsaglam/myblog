<?php

declare(strict_types=1);

use App\Enums\Area;
use App\Models\User;
use App\Support\Access\AccessProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    config(['app.admin_email' => 'owner@example.test']);
});

function bindProfile(User $user): User
{
    app()->instance(AccessProfile::class, AccessProfile::forUser($user));

    return $user;
}

it('allows an area the user reaches', function (): void {
    $user = User::factory()->member()->create(['email' => 'her@example.test']);

    expect(Gate::forUser(bindProfile($user))->allows('access-area', Area::Budget))->toBeTrue();
});

it('denies an area the user does not reach', function (): void {
    $user = User::factory()->member()->create(['email' => 'her@example.test']);

    expect(Gate::forUser(bindProfile($user))->allows('access-area', Area::Work))->toBeFalse();
});

it('denies as not found rather than forbidden', function (): void {
    $user = User::factory()->member()->create(['email' => 'her@example.test']);

    $response = Gate::forUser(bindProfile($user))->inspect('access-area', Area::Work);

    expect($response->denied())->toBeTrue()
        ->and($response->status())->toBe(404);
});

it('allows the configured owner every area', function (Area $area): void {
    $owner = User::factory()->member()->create(['email' => 'owner@example.test']);

    expect(Gate::forUser(bindProfile($owner))->allows('access-area', $area))->toBeTrue();
})->with(array_map(fn (Area $area): array => [$area], Area::cases()));

it('lets anyone with an area through the panel door', function (): void {
    $member = User::factory()->member()->create(['email' => 'her@example.test']);

    expect(Gate::forUser(bindProfile($member))->allows('access-panel'))->toBeTrue();
});

it('keeps a user with no areas out of the panel entirely', function (): void {
    $stranger = User::factory()->create(['email' => 'stranger@example.test']);
    DB::table('users')->where('id', $stranger->id)->update(['role' => '']);

    expect(Gate::forUser(bindProfile($stranger->fresh()))->allows('access-panel'))->toBeFalse();
});
