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

it('names abilities after what they permit', function (Ability $ability): void {
    expect($ability->value)->not->toContain('member')
        ->and($ability->value)->not->toContain('admin')
        ->and($ability->value)->toMatch('/^[a-z][a-z-]+$/');
})->with(fn (): array => array_map(fn (Ability $a): array => [$a], Ability::cases()));
