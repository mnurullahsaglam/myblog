<?php

declare(strict_types=1);

use App\Actions\Fortify\CreateNewUser;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Validation\ValidationException;

it('creates a member by default', function (): void {
    $user = resolve(CreateNewUser::class)->create([
        'name' => 'Someone',
        'email' => 'someone@example.test',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ]);

    expect($user->role)->toBe(UserRole::Member);
});

it('creates the role it is given', function (): void {
    $user = resolve(CreateNewUser::class)->create([
        'name' => 'Someone',
        'email' => 'someone@example.test',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
        'role' => UserRole::Admin->value,
    ]);

    expect($user->role)->toBe(UserRole::Admin);
});

it('refuses a role the enum does not know', function (): void {
    expect(fn (): User => resolve(CreateNewUser::class)->create([
        'name' => 'Someone',
        'email' => 'someone@example.test',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
        'role' => 'wizard',
    ]))->toThrow(ValidationException::class)
        ->and(User::query()->where('email', 'someone@example.test')->exists())->toBeFalse();
});
