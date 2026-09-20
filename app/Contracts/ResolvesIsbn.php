<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Support\BookMetadata;
use App\Support\Isbn;

/**
 * Looks up one edition by ISBN.
 *
 * Returns null when no record exists or the source cannot be reached: a
 * lookup that finds nothing is an ordinary outcome, not an exception.
 */
interface ResolvesIsbn
{
    public function resolve(Isbn $isbn): ?BookMetadata;
}
