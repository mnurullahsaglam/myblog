<?php

declare(strict_types=1);

use App\Enums\Ability;
use App\Models\User;

beforeEach(function (): void {
    config(['app.admin_email' => 'owner@example.test']);
    $this->owner = User::factory()->admin()->create(['email' => 'owner@example.test']);
    $this->member = User::factory()->member()->create(['email' => 'her@example.test']);
});

it('tells a member her three areas and no abilities', function (): void {
    $body = apiAs($this->member)->get(route('api.v1.me'))->assertOk()->json('data');

    expect($body['areas'])->toEqualCanonicalizing(['budget', 'utilities', 'library'])
        ->and($body['abilities'])->toBe([])
        ->and($body['email'])->toBe('her@example.test');
});

it('tells an admin every area and its abilities', function (): void {
    $body = apiAs($this->owner)->get(route('api.v1.me'))->assertOk()->json('data');

    expect($body['areas'])->toHaveCount(6)
        ->and($body['abilities'])->toContain(Ability::SeeClientIdentity->value);
});

it('turns away a request with no token', function (): void {
    $this->getJson(route('api.v1.me'))->assertUnauthorized();
});

it('reports only areas that really answer', function (): void {
    $areas = apiAs($this->member)->get(route('api.v1.me'))->json('data.areas');

    expect($areas)->not->toContain('work');

    apiAs($this->member)->get(route('api.v1.clients.index'))->assertNotFound();
    apiAs($this->member)->get(route('api.v1.expenses.index'))->assertOk();
});

it('never reports a password or a token', function (): void {
    $body = apiAs($this->owner)->get(route('api.v1.me'))->getContent();

    expect($body)->not->toContain('password')
        ->and($body)->not->toContain('two_factor');
});
