<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class Library extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'Kütüphane';

    protected static ?string $clusterBreadcrumb = 'Kütüphane';
}
