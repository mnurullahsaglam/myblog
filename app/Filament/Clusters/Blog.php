<?php

declare(strict_types=1);

namespace App\Filament\Clusters;

use BackedEnum;
use Filament\Clusters\Cluster;

class Blog extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-s-newspaper';

    protected static ?string $navigationLabel = 'Blog';

    protected static ?string $clusterBreadcrumb = 'Blog';
}
