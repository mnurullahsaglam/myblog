<?php

declare(strict_types=1);

use App\Enums\Ability;
use App\Enums\Area;
use App\Models\User;

beforeEach(function (): void {
    config(['app.admin_email' => 'owner@example.test']);
});

/**
 * The assertion this whole plan rests on. The same person must get the same
 * answers whether they arrive with a session cookie or a device token; the day
 * those two diverge is the day one of them is wrong and nothing says so.
 */
it('resolves the same profile for a session and a token', function (string $email, string $state): void {
    $user = User::factory()->{$state}()->create(['email' => $email]);

    $viaSession = $this->actingAs($user)->getJson(route('api.v1.profile-probe'))->json();

    $viaToken = $this->withToken($user->createToken('probe')->plainTextToken)
        ->getJson(route('api.v1.profile-probe'))
        ->json();

    expect($viaToken)->toBe($viaSession);
})->with([
    'admin' => ['owner@example.test', 'admin'],
    'member' => ['her@example.test', 'member'],
]);

it('gives a token-authenticated member her areas and no more', function (): void {
    $member = User::factory()->member()->create(['email' => 'her@example.test']);

    $response = $this->withToken($member->createToken('probe')->plainTextToken)
        ->getJson(route('api.v1.profile-probe'));

    expect($response->json('areas'))->toEqualCanonicalizing(
        array_map(fn (Area $area): string => $area->value, [Area::Budget, Area::Utilities, Area::Library]),
    )->and($response->json('abilities'))->toBe([]);
});

it('gives a token-authenticated admin everything', function (): void {
    $owner = User::factory()->admin()->create(['email' => 'owner@example.test']);

    $response = $this->withToken($owner->createToken('probe')->plainTextToken)
        ->getJson(route('api.v1.profile-probe'));

    expect($response->json('abilities'))->toContain(Ability::SeeClientIdentity->value);
});

it('gives an unauthenticated request nothing', function (): void {
    $this->getJson(route('api.v1.profile-probe'))->assertUnauthorized();
});
