<?php

declare(strict_types=1);

use App\Models\User;

beforeEach(function (): void {
    config(['app.admin_email' => 'owner@example.test']);
    $this->owner = User::factory()->admin()->create(['email' => 'owner@example.test']);
    $this->member = User::factory()->member()->create(['email' => 'her@example.test']);
});

it('lists every device on the people screen', function (): void {
    $this->owner->createToken('Owner MacBook');
    $this->member->createToken('Her iPhone');

    $this->actingAs($this->owner)
        ->get(route('admin.people.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('devices', 2));
});

it('lets the owner revoke any device', function (): void {
    $token = $this->member->createToken('Her iPhone');

    $this->actingAs($this->owner)
        ->delete(route('admin.people.revoke-device', $token->accessToken->getKey()))
        ->assertRedirect(route('admin.people.index'));

    expect($this->member->fresh()->tokens()->count())->toBe(0);
});

/**
 * Revocation has to bite immediately, or a stolen phone keeps working until the
 * token would have expired anyway.
 */
it('stops the revoked token working at once', function (): void {
    $plain = $this->member->createToken('Her iPhone');

    $this->withToken($plain->plainTextToken)->getJson(route('api.v1.profile-probe'))->assertOk();

    $this->actingAs($this->owner)
        ->delete(route('admin.people.revoke-device', $plain->accessToken->getKey()));

    $this->app->make('auth')->forgetGuards();

    $this->withToken($plain->plainTextToken)->getJson(route('api.v1.profile-probe'))->assertUnauthorized();
});

it('hides device revocation from a member', function (): void {
    $token = $this->owner->createToken('Owner MacBook');

    $this->actingAs($this->member)
        ->delete(route('admin.people.revoke-device', $token->accessToken->getKey()))
        ->assertNotFound();

    expect($this->owner->fresh()->tokens()->count())->toBe(1);
});

/**
 * The panel lists devices; it must never hand back anything that could be used
 * as one.
 */
it('never sends a token value to the browser', function (): void {
    $plain = $this->member->createToken('Her iPhone');

    $html = $this->actingAs($this->owner)->get(route('admin.people.index'))->getContent();

    expect($html)->not->toContain($plain->plainTextToken)
        ->and($html)->not->toContain($plain->accessToken->getAttribute('token'));
});
