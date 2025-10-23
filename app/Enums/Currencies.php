<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Currencies: string implements HasLabel
{
    case USD = 'USD';
    case EUR = 'EUR';
    case GBP = 'GBP';
    case TRY = 'TRY';

    case GOLD = 'XAU';
    case SILVER = 'XAG';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::USD => 'US Dollar',
            self::EUR => 'Euro',
            self::GBP => 'British Pound',
            self::TRY => 'Turkish Lira',
            self::GOLD => 'Gold (XAU)',
            self::SILVER => 'Silver (XAG)',
        };
    }

    public function getSymbol(): string
    {
        return match ($this) {
            self::USD => '$',
            self::EUR => '€',
            self::GBP => '£',
            self::TRY => '₺',
            self::GOLD, self::SILVER => 'g',
        };
    }
}
