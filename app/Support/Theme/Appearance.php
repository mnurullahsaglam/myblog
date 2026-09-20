<?php

declare(strict_types=1);

namespace App\Support\Theme;

use App\Models\Setting;
use App\Models\User;

final class Appearance
{
    public const array SCHEMES = ['light', 'dark', 'system'];

    public const string DEFAULT_SCHEME = 'system';

    public static function accent(): string
    {
        $stored = Setting::get('appearance', 'accent', AccentRamps::DEFAULT);
        $accent = is_string($stored) ? $stored : AccentRamps::DEFAULT;

        return AccentRamps::has($accent) ? $accent : AccentRamps::DEFAULT;
    }

    public static function colorScheme(): string
    {
        $stored = Setting::get('appearance', 'color_scheme', self::DEFAULT_SCHEME);
        $scheme = is_string($stored) ? $stored : self::DEFAULT_SCHEME;

        return in_array($scheme, self::SCHEMES, true) ? $scheme : self::DEFAULT_SCHEME;
    }

    /**
     * @return array{accent: string, colorScheme: string}
     */
    public static function forUser(?User $user): array
    {
        $preferences = $user instanceof User ? ($user->preferences ?? []) : [];
        $accent = $preferences['accent'] ?? null;
        $scheme = $preferences['color_scheme'] ?? null;

        return [
            'accent' => is_string($accent) && AccentRamps::has($accent) ? $accent : self::accent(),
            'colorScheme' => is_string($scheme) && in_array($scheme, self::SCHEMES, true) ? $scheme : self::colorScheme(),
        ];
    }

    /**
     * @return array{accent: string, colorScheme: string}
     */
    public static function toArray(): array
    {
        return [
            'accent' => self::accent(),
            'colorScheme' => self::colorScheme(),
        ];
    }
}
