<?php

declare(strict_types=1);

use App\Actions\Fortify\CreateNewUser;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Validation\ValidationException;

it('creates a member by default', function (): void {
    $user = app(CreateNewUser::class)->create([
        'name' => 'Someone',
        'email' => 'someone@example.test',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ]);

    expect($user->role)->toBe(UserRole::Member);
});

it('creates the role it is given', function (): void {
    $user = app(CreateNewUser::class)->create([
        'name' => 'Someone',
        'email' => 'someone@example.test',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
        'role' => UserRole::Admin->value,
    ]);

    expect($user->role)->toBe(UserRole::Admin);
});

/**
 * The role is the access decision. An unrecognised value must stop the
 * creation, not fall back to something convenient.
 */
it('refuses a role the enum does not know', function (): void {
    expect(fn (): User => app(CreateNewUser::class)->create([
        'name' => 'Someone',
        'email' => 'someone@example.test',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
        'role' => 'wizard',
    ]))->toThrow(ValidationException::class);

    expect(User::query()->where('email', 'someone@example.test')->exists())->toBeFalse();
});
