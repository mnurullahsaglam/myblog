<?php

declare(strict_types=1);

namespace App\Support;

final readonly class Isbn
{
    private function __construct(public string $digits) {}

    public static function tryFrom(string $input): ?self
    {
        $normalised = strtoupper((string) preg_replace('/[\s-]+/', '', trim($input)));

        if (preg_match('/^\d{9}[\dX]$/', $normalised) === 1) {
            return self::isValidTen($normalised) ? new self(self::tenToThirteen($normalised)) : null;
        }

        if (preg_match('/^(978|979)\d{10}$/', $normalised) === 1) {
            return self::isValidThirteen($normalised) ? new self($normalised) : null;
        }

        return null;
    }

    public function value(): string
    {
        return $this->digits;
    }

    private static function isValidTen(string $digits): bool
    {
        $sum = 0;

        for ($i = 0; $i < 10; $i++) {
            $char = $digits[$i];
            $value = $char === 'X' ? 10 : (int) $char;
            $sum += $value * (10 - $i);
        }

        return $sum % 11 === 0;
    }

    private static function isValidThirteen(string $digits): bool
    {
        $sum = 0;

        for ($i = 0; $i < 13; $i++) {
            $sum += (int) $digits[$i] * ($i % 2 === 0 ? 1 : 3);
        }

        return $sum % 10 === 0;
    }

    private static function tenToThirteen(string $digits): string
    {
        $body = '978'.substr($digits, 0, 9);
        $sum = 0;

        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $body[$i] * ($i % 2 === 0 ? 1 : 3);
        }

        return $body.((10 - $sum % 10) % 10);
    }
}
