<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
});

it('marks the configured admin email as admin', function (): void {
    expect(User::factory()->create(['email' => 'admin@example.test'])->isAdmin())->toBeTrue()
        ->and(User::factory()->create(['email' => 'someone@example.test'])->isAdmin())->toBeFalse();
});

it('gates admin access on the same rule', function (): void {
    $admin = User::factory()->create(['email' => 'admin@example.test']);
    $other = User::factory()->create(['email' => 'someone@example.test']);

    expect(Gate::forUser($admin)->allows('access-admin'))->toBeTrue()
        ->and(Gate::forUser($other)->allows('access-admin'))->toBeFalse();
});

it('does not implement any Filament contract', function (): void {
    expect(class_implements(User::class))->not->toHaveKey('Filament\Models\Contracts\FilamentUser');
});

it('is nobody when no admin email is configured', function (): void {
    config(['app.admin_email' => null]);

    expect(User::factory()->create(['email' => 'someone@example.test'])->isAdmin())->toBeFalse();
});
