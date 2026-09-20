<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Support\BookMetadata;
use App\Support\Isbn;

interface ResolvesIsbn
{
    public function resolve(Isbn $isbn): ?BookMetadata;
}
