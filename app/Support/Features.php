<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Flags hide a screen that is not finished yet.
 *
 * A flag is always on for an admin, so it can only ever hide something from a
 * member. That is what makes it safe to leave one in place while a screen is
 * being built. Remove a name once the screen is ready rather than leaving a
 * permanently-on flag behind.
 */
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
