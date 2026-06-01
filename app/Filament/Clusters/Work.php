<?php

declare(strict_types=1);

namespace App\Filament\Clusters;

use BackedEnum;
use Filament\Clusters\Cluster;

class Work extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-s-briefcase';
}
