<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates an administrator from options', function (): void {
    $this->artisan('make:admin', [
        '--name' => 'Nurullah',
        '--email' => 'owner@example.test',
        '--password' => 'correct-horse-battery',
    ])->assertSuccessful();

    $user = User::query()->where('email', 'owner@example.test')->sole();

    expect($user->name)->toBe('Nurullah')
        ->and($user->role)->toBe(UserRole::Admin)
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('correct-horse-battery', $user->password))->toBeTrue();
});

it('prompts for anything the options leave out', function (): void {
    $this->artisan('make:admin')
        ->expectsQuestion('Name', 'Prompted')
        ->expectsQuestion('Email', 'prompted@example.test')
        ->expectsQuestion('Password', 'correct-horse-battery')
        ->assertSuccessful();

    expect(User::query()->where('email', 'prompted@example.test')->sole()->role)->toBe(UserRole::Admin);
});

it('promotes an existing account once the change is confirmed', function (): void {
    $member = User::factory()->member()->create(['email' => 'member@example.test']);

    $this->artisan('make:admin', [
        '--name' => 'Renamed',
        '--email' => 'member@example.test',
        '--password' => 'correct-horse-battery',
    ])
        ->expectsConfirmation('member@example.test already exists. Reset its password and make it an administrator?', 'yes')
        ->assertSuccessful();

    $member->refresh();

    expect($member->role)->toBe(UserRole::Admin)
        ->and($member->name)->toBe('Renamed')
        ->and(Hash::check('correct-horse-battery', $member->password))->toBeTrue();
});

it('leaves an existing account untouched when the change is declined', function (): void {
    $member = User::factory()->member()->create([
        'name' => 'Untouched',
        'email' => 'member@example.test',
    ]);

    $this->artisan('make:admin', [
        '--name' => 'Renamed',
        '--email' => 'member@example.test',
        '--password' => 'correct-horse-battery',
    ])
        ->expectsConfirmation('member@example.test already exists. Reset its password and make it an administrator?', 'no')
        ->assertFailed();

    $member->refresh();

    expect($member->role)->toBe(UserRole::Member)
        ->and($member->name)->toBe('Untouched');
});

it('refuses a password that does not meet the default rules', function (): void {
    $this->artisan('make:admin', [
        '--name' => 'Nurullah',
        '--email' => 'owner@example.test',
        '--password' => 'short',
    ])->assertFailed();

    expect(User::query()->count())->toBe(0);
});

it('refuses an address that is not an email', function (): void {
    $this->artisan('make:admin', [
        '--name' => 'Nurullah',
        '--email' => 'not-an-email',
        '--password' => 'correct-horse-battery',
    ])->assertFailed();

    expect(User::query()->count())->toBe(0);
});

it('skips the confirmation when forced', function (): void {
    $member = User::factory()->member()->create(['email' => 'member@example.test']);

    $this->artisan('make:admin', [
        '--name' => 'Renamed',
        '--email' => 'member@example.test',
        '--password' => 'correct-horse-battery',
        '--force' => true,
    ])->assertSuccessful();

    $member->refresh();

    expect($member->role)->toBe(UserRole::Admin)
        ->and(Hash::check('correct-horse-battery', $member->password))->toBeTrue();
});
