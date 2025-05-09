<?php

namespace App\Enums;

enum LaravelVersions: string
{
    case LARAVEL_8 = '8.x';
    case LARAVEL_9 = '9.x';
    case LARAVEL_10 = '10.x';
    case LARAVEL_11 = '11.x';
    case LARAVEL_12 = '12.x';

    public function label(): string
    {
        return match ($this) {
            self::LARAVEL_8 => 'Laravel 8',
            self::LARAVEL_9 => 'Laravel 9',
            self::LARAVEL_10 => 'Laravel 10',
            self::LARAVEL_11 => 'Laravel 11',
            self::LARAVEL_12 => 'Laravel 12',
        };
    }
}
