<?php

declare(strict_types=1);

namespace App\Support\Contracts;

interface HasColor
{
    /**
     * A semantic color token: primary, secondary, success, warning, danger, info or gray.
     */
    public function getColor(): string;
}
