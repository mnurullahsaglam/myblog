<?php

declare(strict_types=1);

namespace App\Support\Contracts;

interface HasColor
{
    public function getColor(): string;
}
