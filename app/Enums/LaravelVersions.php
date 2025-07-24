<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum LaravelVersions: string implements HasLabel, HasColor
{
    case LARAVEL_8 = '8.x';
    case LARAVEL_9 = '9.x';
    case LARAVEL_10 = '10.x';
    case LARAVEL_11 = '11.x';
    case LARAVEL_12 = '12.x';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::LARAVEL_8 => 'Laravel 8',
            self::LARAVEL_9 => 'Laravel 9',
            self::LARAVEL_10 => 'Laravel 10',
            self::LARAVEL_11 => 'Laravel 11',
            self::LARAVEL_12 => 'Laravel 12',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::LARAVEL_8, self::LARAVEL_9, self::LARAVEL_10 => 'danger',
            self::LARAVEL_11 => 'info',
            self::LARAVEL_12 => 'success',
        };
    }
}
