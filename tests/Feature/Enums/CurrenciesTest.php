<?php

declare(strict_types=1);

use App\Enums\Currencies;
use App\Support\Contracts\HasLabel;

it('implements the application HasLabel contract, not Filament\'s', function (): void {
    expect(Currencies::TRY)->toBeInstanceOf(HasLabel::class);

    $reflection = new ReflectionEnum(Currencies::class);

    expect($reflection->getInterfaceNames())->not->toContain('Filament\Support\Contracts\HasLabel');
});

it('returns a label and symbol for every case', function (Currencies $currency): void {
    expect($currency->getLabel())->toBeString()->not->toBeEmpty()
        ->and($currency->getSymbol())->toBeString()->not->toBeEmpty();
})->with(Currencies::cases());
