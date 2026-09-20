<?php

declare(strict_types=1);

namespace App\Contracts;

interface ConvertsCurrency
{
    public function convert(float $amount, string $fromCurrency, string $toCurrency): float;
}
