<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PhpVersions: string implements HasLabel, HasColor
{
    case PHP_7_4 = '7.4';
    case PHP_8_0 = '8.0';
    case PHP_8_1 = '8.1';
    case PHP_8_2 = '8.2';
    case PHP_8_3 = '8.3';
    case PHP_8_4 = '8.4';

    public function getLabel(): string
    {
        return match ($this) {
            self::PHP_7_4 => 'PHP 7.4',
            self::PHP_8_0 => 'PHP 8.0',
            self::PHP_8_1 => 'PHP 8.1',
            self::PHP_8_2 => 'PHP 8.2',
            self::PHP_8_3 => 'PHP 8.3',
            self::PHP_8_4 => 'PHP 8.4',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PHP_7_4, self::PHP_8_0 => 'danger',
            self::PHP_8_1, self::PHP_8_2 => 'warning',
            self::PHP_8_3, self::PHP_8_4 => 'success',
        };
    }
}
