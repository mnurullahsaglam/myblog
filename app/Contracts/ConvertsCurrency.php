<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Currency conversion, narrowed to what the application consumes.
 *
 * The concrete implementation reaches an external rates API, so consumers bind
 * to this rather than to the service and tests substitute it directly.
 */
interface ConvertsCurrency
{
    public function convert(float $amount, string $fromCurrency, string $toCurrency): float;
}
