<?php

declare(strict_types=1);

namespace App\Support\Theme;

final class AccentRamps
{
    public const DEFAULT = 'khaki';

    /**
     * Text and glyphs on a filled accent. Never white - see the khaki contrast
     * rule in docs/superpowers/design-system.md.
     */
    public const ON_ACCENT = '#141517';

    /**
     * Mirrors resources/js/theme/ramps.js. Keep the two in sync.
     *
     * PHP coerces the numeric stop keys to integers.
     *
     * @var array<string, array<int, string>>
     */
    private const RAMPS = [
        'khaki' => ['50' => '#FBFAF0', '100' => '#F5F1D8', '200' => '#EAE3B0', '300' => '#DCD183', '400' => '#C9BE6E', '500' => '#B3A651', '600' => '#96893F', '700' => '#756A32', '800' => '#5A522A', '900' => '#4A4426', '950' => '#2A2613'],
        'amber' => ['50' => '#FFFBEB', '100' => '#FEF3C7', '200' => '#FDE68A', '300' => '#FCD34D', '400' => '#FBBF24', '500' => '#F59E0B', '600' => '#D97706', '700' => '#B45309', '800' => '#92400E', '900' => '#78350F', '950' => '#451A03'],
        'orange' => ['50' => '#FFF7ED', '100' => '#FFEDD5', '200' => '#FED7AA', '300' => '#FDBA74', '400' => '#FB923C', '500' => '#F97316', '600' => '#EA580C', '700' => '#C2410C', '800' => '#9A3412', '900' => '#7C2D12', '950' => '#431407'],
        'rose' => ['50' => '#FFF1F2', '100' => '#FFE4E6', '200' => '#FECDD3', '300' => '#FDA4AF', '400' => '#FB7185', '500' => '#F43F5E', '600' => '#E11D48', '700' => '#BE123C', '800' => '#9F1239', '900' => '#881337', '950' => '#4C0519'],
        'emerald' => ['50' => '#ECFDF5', '100' => '#D1FAE5', '200' => '#A7F3D0', '300' => '#6EE7B7', '400' => '#34D399', '500' => '#10B981', '600' => '#059669', '700' => '#047857', '800' => '#065F46', '900' => '#064E3B', '950' => '#022C22'],
        'sky' => ['50' => '#F0F9FF', '100' => '#E0F2FE', '200' => '#BAE6FD', '300' => '#7DD3FC', '400' => '#38BDF8', '500' => '#0EA5E9', '600' => '#0284C7', '700' => '#0369A1', '800' => '#075985', '900' => '#0C4A6E', '950' => '#082F49'],
        'indigo' => ['50' => '#EEF2FF', '100' => '#E0E7FF', '200' => '#C7D2FE', '300' => '#A5B4FC', '400' => '#818CF8', '500' => '#6366F1', '600' => '#4F46E5', '700' => '#4338CA', '800' => '#3730A3', '900' => '#312E81', '950' => '#1E1B4B'],
        'violet' => ['50' => '#F5F3FF', '100' => '#EDE9FE', '200' => '#DDD6FE', '300' => '#C4B5FD', '400' => '#A78BFA', '500' => '#8B5CF6', '600' => '#7C3AED', '700' => '#6D28D9', '800' => '#5B21B6', '900' => '#4C1D95', '950' => '#2E1065'],
        'zinc' => ['50' => '#FAFAFA', '100' => '#F4F4F5', '200' => '#E4E4E7', '300' => '#D4D4D8', '400' => '#A1A1AA', '500' => '#71717A', '600' => '#52525B', '700' => '#3F3F46', '800' => '#27272A', '900' => '#18181B', '950' => '#09090B'],
    ];

    /**
     * @return array<string, array<int, string>>
     */
    public static function all(): array
    {
        return self::RAMPS;
    }

    /**
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_keys(self::RAMPS);
    }

    public static function has(string $name): bool
    {
        return array_key_exists($name, self::RAMPS);
    }

    /**
     * The stop used as the accent in each scheme: 500 on light, 400 on dark.
     */
    public static function swatch(string $name): string
    {
        $ramp = self::RAMPS[self::has($name) ? $name : self::DEFAULT];

        return $ramp['400'];
    }

    /**
     * Inline CSS custom properties so the correct accent paints before Vue boots.
     */
    public static function cssVariables(string $name): string
    {
        $ramp = self::RAMPS[self::has($name) ? $name : self::DEFAULT];

        $declarations = '';

        foreach ($ramp as $stop => $hex) {
            $declarations .= "--p-primary-{$stop}:{$hex};";
        }

        return ':root{'.$declarations.'}';
    }
}
