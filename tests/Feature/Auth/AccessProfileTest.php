<?php

declare(strict_types=1);

use App\Enums\Ability;
use App\Enums\Area;
use App\Enums\UserRole;
use App\Models\User;
use App\Support\Access\AccessProfile;

beforeEach(function (): void {
    config(['app.admin_email' => 'owner@example.test']);
});

it('answers for an admin', function (): void {
    $profile = AccessProfile::forUser(User::factory()->admin()->create(['email' => 'other@example.test']));

    expect($profile->areas())->toEqualCanonicalizing(Area::cases())
        ->and($profile->allows(Ability::SeeClientIdentity))->toBeTrue()
        ->and($profile->isPreviewing())->toBeFalse();
});

it('answers for a member', function (): void {
    $profile = AccessProfile::forUser(User::factory()->member()->create(['email' => 'her@example.test']));

    expect($profile->areas())->toEqualCanonicalizing([Area::Budget, Area::Utilities, Area::Library])
        ->and($profile->allows(Ability::SeeClientIdentity))->toBeFalse();
});

it('answers nothing for a guest', function (): void {
    $profile = AccessProfile::forUser(null);

    expect($profile->areas())->toBe([])
        ->and($profile->allows(Ability::SeeClientIdentity))->toBeFalse()
        ->and($profile->user())->toBeNull();
});

/**
 * The lockout guarantee, carried into abilities: the configured owner is an
 * admin whatever the column says.
 */
it('keeps the configured owner privileged whatever the column says', function (): void {
    $owner = User::factory()->member()->create(['email' => 'owner@example.test']);

    expect(AccessProfile::forUser($owner)->allows(Ability::SeeClientIdentity))->toBeTrue();
});

it('answers as the previewed role, and says it is previewing', function (): void {
    $owner = User::factory()->admin()->create(['email' => 'owner@example.test']);

    $profile = AccessProfile::preview($owner, UserRole::Member);

    expect($profile->areas())->toEqualCanonicalizing([Area::Budget, Area::Utilities, Area::Library])
        ->and($profile->allows(Ability::SeeClientIdentity))->toBeFalse()
        ->and($profile->isPreviewing())->toBeTrue();
});

/**
 * A preview must not be able to preview an admin: that would be a way to climb
 * out of a restricted session rather than a way to look into one.
 */
it('refuses to preview a role that is not narrower', function (): void {
    $owner = User::factory()->admin()->create(['email' => 'owner@example.test']);

    expect(fn (): AccessProfile => AccessProfile::preview($owner, UserRole::Admin))
        ->toThrow(InvalidArgumentException::class);
});

it('is bound once per request', function (): void {
    $owner = User::factory()->admin()->create(['email' => 'owner@example.test']);

    $this->actingAs($owner)->get(route('admin.dashboard'));

    expect(app(AccessProfile::class))->toBe(app(AccessProfile::class));
});
