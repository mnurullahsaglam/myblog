<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Currencies: string implements HasLabel
{
    case USD = 'USD';
    case EUR = 'EUR';
    case GBP = 'GBP';
    case TRY = 'TRY';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::USD => 'US Dollar (USD)',
            self::EUR => 'Euro (EUR)',
            self::GBP => 'British Pound (GBP)',
            self::TRY => 'Turkish Lira (TRY)',
        };
    }

    public function getSymbol(): string
    {
        return match ($this) {
            self::USD => '$',
            self::EUR => '€',
            self::GBP => '£',
            self::TRY => '₺',
        };
    }
}
