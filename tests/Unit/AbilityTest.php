<?php

declare(strict_types=1);

use App\Enums\Ability;
use App\Enums\UserRole;

it('gives an admin every ability', function (): void {
    expect(UserRole::Admin->abilities())->toEqualCanonicalizing(Ability::cases());
});

it('gives a member none', function (): void {
    expect(UserRole::Member->abilities())->toBeEmpty();
});

/**
 * Abilities are named for why something is hidden, not for who is excluded, so
 * a value that names a role would be a design error rather than a typo.
 */
it('names abilities after what they permit', function (Ability $ability): void {
    expect($ability->value)->not->toContain('member')
        ->and($ability->value)->not->toContain('admin')
        ->and($ability->value)->toMatch('/^[a-z][a-z-]+$/');
})->with(fn (): array => array_map(fn (Ability $a): array => [$a], Ability::cases()));
