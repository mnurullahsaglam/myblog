<?php

declare(strict_types=1);

namespace App\Support\Theme;

use App\Models\User;

/**
 * The panel's surface colours as flat hex, for the two places Vue never reaches:
 * email and the error pages.
 *
 * Mirrors resources/js/theme/tokens.js. Keep the two in sync.
 */
final readonly class Palette
{
    /**
     * @var array<string, array<string, string>>
     */
    private const array SURFACES = [
        'light' => [
            'canvas' => '#F7F7F5',
            'surface' => '#FFFFFF',
            'elevated' => '#F2F2EF',
            'embedded' => '#FAFAF8',
            'border' => '#D9D9D2',
            'borderSubtle' => '#E8E8E2',
            'text' => '#1A1B1E',
            'textSecondary' => '#5A606E',
            'textMuted' => '#8B909B',
        ],
        'dark' => [
            'canvas' => '#0D0E11',
            'surface' => '#15171C',
            'elevated' => '#1A1D24',
            'embedded' => '#121317',
            'border' => '#272B35',
            'borderSubtle' => '#20232B',
            'text' => '#ECEEF2',
            'textSecondary' => '#9096A2',
            'textMuted' => '#5A606E',
        ],
    ];

    /**
     * The accent stop each scheme paints with, matching resources/js/theme/preset.js.
     *
     * @var array<string, int>
     */
    private const array ACCENT_STOPS = ['light' => 500, 'dark' => 400];

    public const string ALERT = '#D95757';

    public const string FONT_SANS = "Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";

    public const string FONT_MONO = "'JetBrains Mono', ui-monospace, 'SF Mono', Menlo, Consolas, monospace";

    private function __construct(
        public string $scheme,
        public string $canvas,
        public string $surface,
        public string $elevated,
        public string $embedded,
        public string $border,
        public string $borderSubtle,
        public string $text,
        public string $textSecondary,
        public string $textMuted,
        public string $accent,
        public string $accentHover,
        public string $onAccent,
    ) {}

    /**
     * Email cannot ask the client what it prefers, and neither can a response
     * rendered before any script runs, so "system" resolves to light here. That
     * is the fallback the panel's own light theme uses.
     */
    public static function of(string $scheme, string $accent): self
    {
        $key = $scheme === 'dark' ? 'dark' : 'light';
        $surface = self::SURFACES[$key];
        $ramp = AccentRamps::all()[AccentRamps::has($accent) ? $accent : AccentRamps::DEFAULT];
        $stop = self::ACCENT_STOPS[$key];

        return new self(
            scheme: $key,
            canvas: $surface['canvas'],
            surface: $surface['surface'],
            elevated: $surface['elevated'],
            embedded: $surface['embedded'],
            border: $surface['border'],
            borderSubtle: $surface['borderSubtle'],
            text: $surface['text'],
            textSecondary: $surface['textSecondary'],
            textMuted: $surface['textMuted'],
            accent: $ramp[$stop],
            accentHover: $ramp[$key === 'dark' ? 300 : 600],
            onAccent: AccentRamps::ON_ACCENT,
        );
    }

    /**
     * The palette this user reads the panel in.
     *
     * An unknown recipient — an invitee, a signed-out visitor — gets the
     * household's global setting rather than a hardcoded default.
     */
    public static function forUser(?User $user): self
    {
        $appearance = Appearance::forUser($user);

        return self::of($appearance['colorScheme'], $appearance['accent']);
    }

    /**
     * The palette for someone with no account and so no preference of their own:
     * light, which is the instruction for an unknown reader, but the household's
     * accent, so the message still looks like it came from here.
     */
    public static function forUnknownRecipient(): self
    {
        return self::of('light', Appearance::accent());
    }

    /**
     * Whether the caller should also emit a prefers-color-scheme block, which is
     * true only where the resolved preference was "system".
     */
    public static function followsSystem(?User $user): bool
    {
        return Appearance::forUser($user)['colorScheme'] === 'system';
    }

    /**
     * The palettes an error page needs: the resolved one, and its opposite when
     * the reader follows the system rather than choosing.
     *
     * Everything here can fail — an error page renders when the session, the
     * database or both are already gone — so the whole resolution falls back to
     * the built-in light theme rather than turning a 500 into a second one.
     *
     * @return array{palette: self, dark: self|null}
     */
    public static function forErrorPage(): array
    {
        return rescue(
            function (): array {
                $user = auth()->user();
                $appearance = Appearance::forUser($user instanceof User ? $user : null);

                return [
                    'palette' => self::of($appearance['colorScheme'], $appearance['accent']),
                    'dark' => $appearance['colorScheme'] === 'system'
                        ? self::of('dark', $appearance['accent'])
                        : null,
                ];
            },
            ['palette' => self::of('light', AccentRamps::DEFAULT), 'dark' => null],
            report: false,
        );
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'scheme' => $this->scheme,
            'canvas' => $this->canvas,
            'surface' => $this->surface,
            'elevated' => $this->elevated,
            'embedded' => $this->embedded,
            'border' => $this->border,
            'border-subtle' => $this->borderSubtle,
            'text' => $this->text,
            'text-secondary' => $this->textSecondary,
            'text-muted' => $this->textMuted,
            'accent' => $this->accent,
            'accent-hover' => $this->accentHover,
            'on-accent' => $this->onAccent,
        ];
    }

    /**
     * The palette as custom properties, for a stylesheet that can use them.
     */
    public function cssVariables(): string
    {
        $declarations = '';

        foreach ($this->toArray() as $name => $value) {
            if ($name !== 'scheme') {
                $declarations .= "--mb-{$name}:{$value};";
            }
        }

        return $declarations;
    }
}
