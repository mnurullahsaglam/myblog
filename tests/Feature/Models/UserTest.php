<?php

declare(strict_types=1);

use App\Enums\Area;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    config(['app.admin_email' => 'owner@example.test']);
});

it('defaults a new row to the least privileged role', function (): void {
    DB::table('users')->insert([
        'name' => 'No Role',
        'email' => 'someone@example.test',
        'password' => 'irrelevant',
    ]);

    $user = User::query()->where('email', 'someone@example.test')->sole();

    expect($user->role)->toBe(UserRole::Member);
});

it('gives a factory-made user the member role', function (): void {
    expect(User::factory()->create(['email' => 'someone@example.test'])->role)
        ->toBe(UserRole::Member);
});

it('casts the role to the enum', function (): void {
    expect(User::factory()->admin()->create()->role)->toBe(UserRole::Admin);
});

it('gives a member the household areas', function (Area $area): void {
    $user = User::factory()->member()->create(['email' => 'her@example.test']);

    expect($user->canAccess($area))->toBeTrue();
})->with([
    'budget' => [Area::Budget],
    'utilities' => [Area::Utilities],
    'library' => [Area::Library],
]);

it('keeps a member out of the rest', function (Area $area): void {
    $user = User::factory()->member()->create(['email' => 'her@example.test']);

    expect($user->canAccess($area))->toBeFalse();
})->with([
    'blog' => [Area::Blog],
    'work' => [Area::Work],
    'general' => [Area::General],
]);

it('gives a role-admin every area', function (): void {
    $user = User::factory()->admin()->create(['email' => 'other@example.test']);

    foreach (Area::cases() as $area) {
        expect($user->canAccess($area))->toBeTrue();
    }
});

it('lets the configured owner reach everything whatever the column says', function (): void {
    $owner = User::factory()->member()->create(['email' => 'owner@example.test']);

    foreach (Area::cases() as $area) {
        expect($owner->canAccess($area))->toBeTrue();
    }
});

it('lets the configured owner in even with an empty role column', function (): void {
    $owner = User::factory()->create(['email' => 'owner@example.test']);
    DB::table('users')->where('id', $owner->id)->update(['role' => '']);

    foreach (Area::cases() as $area) {
        expect($owner->fresh()->canAccess($area))->toBeTrue();
    }
});
