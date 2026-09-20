<?php

declare(strict_types=1);

use App\Enums\UtilityType;
use App\Support\Contracts\HasColor;
use App\Support\Contracts\HasLabel;

it('labels every type in Turkish', function (UtilityType $type, string $label): void {
    expect($type->getLabel())->toBe($label);
})->with([
    'electricity' => [UtilityType::Electricity, 'Elektrik'],
    'natural gas' => [UtilityType::NaturalGas, 'Doğalgaz'],
    'water' => [UtilityType::Water, 'Su'],
    'internet' => [UtilityType::Internet, 'İnternet'],
    'phone' => [UtilityType::Phone, 'Telefon'],
]);

it('knows which utilities have a meter', function (UtilityType $type, bool $metered): void {
    expect($type->hasMeter())->toBe($metered);
})->with([
    'electricity is metered' => [UtilityType::Electricity, true],
    'gas is metered' => [UtilityType::NaturalGas, true],
    'water is metered' => [UtilityType::Water, true],
    'internet is not' => [UtilityType::Internet, false],
    'phone is not' => [UtilityType::Phone, false],
]);

it('names the unit a meter counts in', function (UtilityType $type, ?string $unit): void {
    expect($type->unit())->toBe($unit);
})->with([
    'electricity in kWh' => [UtilityType::Electricity, 'kWh'],
    'gas in cubic metres' => [UtilityType::NaturalGas, 'm³'],
    'water in cubic metres' => [UtilityType::Water, 'm³'],
    'internet has none' => [UtilityType::Internet, null],
    'phone has none' => [UtilityType::Phone, null],
]);

it('implements the contracts the table reads badges from', function (): void {
    expect(UtilityType::Electricity)->toBeInstanceOf(HasColor::class)
        ->and(UtilityType::Electricity)->toBeInstanceOf(HasLabel::class);
});

/**
 * The point of these three: adding a case without extending every match arm
 * should fail here rather than render a blank badge or an unlabelled option.
 */
it('gives every case a non-empty label', function (): void {
    foreach (UtilityType::cases() as $type) {
        expect($type->getLabel())->not->toBeEmpty();
    }

    expect(UtilityType::cases())->not->toBeEmpty();
});

it('gives every case a semantic colour token', function (): void {
    $tokens = ['primary', 'secondary', 'success', 'warning', 'danger', 'info', 'gray'];

    foreach (UtilityType::cases() as $type) {
        expect($tokens)->toContain($type->getColor());
    }
});

it('gives every metered case a unit and every unmetered case none', function (): void {
    foreach (UtilityType::cases() as $type) {
        $type->hasMeter()
            ? expect($type->unit())->not->toBeNull()
            : expect($type->unit())->toBeNull();
    }
});
