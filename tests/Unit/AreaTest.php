<?php

declare(strict_types=1);

use App\Enums\Area;
use App\Enums\UserRole;

it('has one area per navigation cluster', function (): void {
    expect(array_column(Area::cases(), 'value'))
        ->toEqualCanonicalizing(['blog', 'budget', 'work', 'library', 'utilities', 'general']);
});

it('gives an admin every area', function (): void {
    expect(UserRole::Admin->areas())->toEqualCanonicalizing(Area::cases());
});

it('gives a member the household areas only', function (): void {
    expect(UserRole::Member->areas())
        ->toEqualCanonicalizing([Area::Budget, Area::Utilities, Area::Library]);
});

it('keeps blog, work and general away from a member', function (Area $area): void {
    expect(UserRole::Member->areas())->not->toContain($area);
})->with([
    'blog' => [Area::Blog],
    'work' => [Area::Work],
    'general' => [Area::General],
]);

it('gives every role at least one area', function (): void {
    foreach (UserRole::cases() as $role) {
        expect($role->areas())->not->toBeEmpty();
    }

    expect(UserRole::cases())->not->toBeEmpty();
});
