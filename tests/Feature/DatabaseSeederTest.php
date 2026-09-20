<?php

declare(strict_types=1);

use App\Enums\Area;
use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

it('seeds the member when an address is configured', function (): void {
    config(['app.admin_email' => 'owner@example.test', 'app.member_email' => 'her@example.test']);

    $this->seed(DatabaseSeeder::class);

    $member = User::query()->where('email', 'her@example.test')->sole();

    expect($member->role)->toBe(UserRole::Member)
        ->and($member->canAccess(Area::Budget))->toBeTrue()
        ->and($member->canAccess(Area::Work))->toBeFalse();
});

it('seeds only the owner when no member address is configured', function (): void {
    config(['app.admin_email' => 'owner@example.test', 'app.member_email' => null]);

    $this->seed(DatabaseSeeder::class);

    expect(User::query()->count())->toBe(1);
});

it('seeds the owner as an admin in the column, not only by address', function (): void {
    config(['app.admin_email' => 'owner@example.test', 'app.member_email' => null]);

    $this->seed(DatabaseSeeder::class);

    expect(User::query()->where('email', 'owner@example.test')->sole()->role)
        ->toBe(UserRole::Admin);
});
