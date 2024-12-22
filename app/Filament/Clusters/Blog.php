<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class Blog extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'Blog';

    protected static ?string $clusterBreadcrumb = 'Blog';
}
