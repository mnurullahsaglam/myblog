<?php

declare(strict_types=1);

use App\Models\User;

beforeEach(function (): void {
    config(['app.admin_email' => 'owner@example.test']);
});

/**
 * Changing an address used to save the row and then die building a URL for the
 * verification.verify route, which does not exist because the feature is off.
 * The user got a 500 over a change that had already happened.
 */
it('changes an email address without erroring', function (): void {
    $member = User::factory()->member()->create(['email' => 'her@example.test']);

    $this->actingAs($member)
        ->from(route('admin.profile'))
        ->put(route('user-profile-information.update'), [
            'name' => 'Her',
            'email' => 'her-new@example.test',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($member->fresh()->email)->toBe('her-new@example.test');
});

/**
 * Verification is off, so an address that was verified stays verified rather
 * than being cleared by a flow that cannot re-verify it.
 */
it('leaves the verified timestamp alone while verification is disabled', function (): void {
    $member = User::factory()->member()->create([
        'email' => 'her@example.test',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($member)->put(route('user-profile-information.update'), [
        'name' => 'Her',
        'email' => 'her-new@example.test',
    ]);

    expect($member->fresh()->email_verified_at)->not->toBeNull();
});
