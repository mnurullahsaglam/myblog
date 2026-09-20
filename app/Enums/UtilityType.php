<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Contracts\HasColor;
use App\Support\Contracts\HasLabel;

/**
 * The kinds of utility bill the panel records.
 *
 * This enum is the only place the list lives. utility_accounts.type is a plain
 * string column, deliberately not a database enum like expenses.currency, so
 * adding a case here needs no migration.
 */
enum UtilityType: string implements HasColor, HasLabel
{
    case Electricity = 'electricity';
    case NaturalGas = 'natural_gas';
    case Water = 'water';
    case Internet = 'internet';
    case Phone = 'phone';

    public function getLabel(): string
    {
        return match ($this) {
            self::Electricity => 'Elektrik',
            self::NaturalGas => 'Doğalgaz',
            self::Water => 'Su',
            self::Internet => 'İnternet',
            self::Phone => 'Telefon',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Electricity => 'warning',
            self::NaturalGas => 'danger',
            self::Water => 'info',
            self::Internet => 'primary',
            self::Phone => 'secondary',
        };
    }

    /**
     * Whether this utility is billed against a meter reading (sayaç).
     */
    public function hasMeter(): bool
    {
        return in_array($this, [self::Electricity, self::NaturalGas, self::Water], true);
    }

    /**
     * What the meter counts, or null when there is no meter.
     */
    public function unit(): ?string
    {
        return match ($this) {
            self::Electricity => 'kWh',
            self::NaturalGas, self::Water => 'm³',
            self::Internet, self::Phone => null,
        };
    }
}
