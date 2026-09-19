<?php

declare(strict_types=1);

namespace App\Support\Contracts;

interface HasLabel
{
    public function getLabel(): string;
}
