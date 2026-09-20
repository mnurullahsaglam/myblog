<?php

declare(strict_types=1);

use App\Actions\People\CreateInvite;
use App\Enums\Area;
use App\Enums\UserRole;
use App\Models\Invite;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    config(['app.admin_email' => 'owner@example.test']);
    Mail::fake();
});

/**
 * The configured admin address is the lockout guarantee: whoever holds it
 * reaches every area whatever the role column says. That makes it a privilege,
 * and a privilege must not be claimable by editing your own profile.
 *
 * Before this was closed, a member could PUT her own email to ADMIN_EMAIL and
 * become an admin, held back only by the unique index happening to be occupied.
 */
it('refuses to let a member take the configured admin address', function (): void {
    $member = User::factory()->member()->create(['email' => 'her@example.test']);

    $this->actingAs($member)
        ->put(route('user-profile-information.update'), [
            'name' => 'Her',
            'email' => 'owner@example.test',
        ])
        ->assertSessionHasErrors('email', errorBag: 'updateProfileInformation');

    $fresh = $member->fresh();

    expect($fresh->email)->toBe('her@example.test')
        ->and($fresh->isAdmin())->toBeFalse()
        ->and($fresh->canAccess(Area::Work))->toBeFalse();
});

it('refuses even when no account holds that address yet', function (): void {
    config(['app.admin_email' => 'nobody-has-this@example.test']);

    $member = User::factory()->member()->create(['email' => 'her@example.test']);

    $this->actingAs($member)
        ->put(route('user-profile-information.update'), [
            'name' => 'Her',
            'email' => 'nobody-has-this@example.test',
        ])
        ->assertSessionHasErrors('email', errorBag: 'updateProfileInformation');

    expect($member->fresh()->isAdmin())->toBeFalse();
});

it('refuses a case variant of it', function (): void {
    $member = User::factory()->member()->create(['email' => 'her@example.test']);

    $this->actingAs($member)
        ->put(route('user-profile-information.update'), [
            'name' => 'Her',
            'email' => 'OWNER@Example.TEST',
        ])
        ->assertSessionHasErrors('email', errorBag: 'updateProfileInformation');

    expect($member->fresh()->email)->toBe('her@example.test');
});

it('still lets the owner keep their own address', function (): void {
    $owner = User::factory()->admin()->create(['email' => 'owner@example.test']);

    $this->actingAs($owner)
        ->put(route('user-profile-information.update'), [
            'name' => 'Renamed Owner',
            'email' => 'owner@example.test',
        ])
        ->assertSessionHasNoErrors();

    expect($owner->fresh()->name)->toBe('Renamed Owner');
});

it('lets anyone change to an ordinary address', function (): void {
    $member = User::factory()->member()->create(['email' => 'her@example.test']);

    $this->actingAs($member)
        ->put(route('user-profile-information.update'), [
            'name' => 'Her',
            'email' => 'her-new@example.test',
        ])
        ->assertSessionHasNoErrors();

    expect($member->fresh()->email)->toBe('her-new@example.test');
});

/**
 * The same address must not be reachable through the other door either.
 */
it('refuses to invite the configured admin address', function (): void {
    $owner = User::factory()->admin()->create(['email' => 'owner@example.test']);

    expect(fn (): array => resolve(CreateInvite::class)->handle('owner@example.test', UserRole::Member, $owner))
        ->toThrow(ValidationException::class)
        ->and(Invite::query()->count())->toBe(0);
});

it('refuses to invite a case variant of it', function (): void {
    $owner = User::factory()->admin()->create(['email' => 'owner@example.test']);

    expect(fn (): array => resolve(CreateInvite::class)->handle('OWNER@example.test', UserRole::Member, $owner))
        ->toThrow(ValidationException::class);
});
