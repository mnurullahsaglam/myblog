<?php

declare(strict_types=1);

namespace App\Support;

final class Features
{
    public const string BudgetLimits = 'budget-limits';

    /**
     * @var array<int, string>
     */
    public const array ALL = [
        self::BudgetLimits,
    ];
}
