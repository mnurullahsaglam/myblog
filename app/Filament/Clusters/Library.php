<?php

declare(strict_types=1);

namespace App\Filament\Clusters;

use BackedEnum;
use Filament\Clusters\Cluster;

class Library extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'Kütüphane';

    protected static ?string $clusterBreadcrumb = 'Kütüphane';
}
