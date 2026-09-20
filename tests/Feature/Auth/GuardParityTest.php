<?php

declare(strict_types=1);

use App\Enums\Ability;
use App\Models\Income;
use App\Models\User;
use App\Support\Access\AccessProfile;

beforeEach(function (): void {
    config(['app.admin_email' => 'owner@example.test']);
    Income::factory()->count(3)->create();
});

it('returns the same payload for a session and a token', function (string $email, string $state): void {
    $user = User::factory()->{$state}()->create(['email' => $email]);

    $viaSession = $this->actingAs($user)->getJson(route('api.v1.incomes.index'))->json('data');

    $viaToken = apiAs($user)->get(route('api.v1.incomes.index'))->json('data');

    expect($viaToken)->toBe($viaSession);
})->with([
    'admin' => ['owner@example.test', 'admin'],
    'member' => ['her@example.test', 'member'],
]);

it('resolves the same areas and abilities under both guards', function (string $email, string $state): void {
    $user = User::factory()->{$state}()->create(['email' => $email]);

    $this->actingAs($user)->getJson(route('api.v1.incomes.index'));
    $viaSession = resolve(AccessProfile::class);

    $sessionAreas = $viaSession->areas();
    $sessionAllows = $viaSession->allows(Ability::SeeClientIdentity);

    apiAs($user)->get(route('api.v1.incomes.index'));
    $viaToken = resolve(AccessProfile::class);

    expect($viaToken->areas())->toEqualCanonicalizing($sessionAreas)
        ->and($viaToken->allows(Ability::SeeClientIdentity))->toBe($sessionAllows);
})->with([
    'admin' => ['owner@example.test', 'admin'],
    'member' => ['her@example.test', 'member'],
]);

it('gives an unauthenticated request nothing', function (): void {
    $this->getJson(route('api.v1.incomes.index'))->assertUnauthorized();
});
