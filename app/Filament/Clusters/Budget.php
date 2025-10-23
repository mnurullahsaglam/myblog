<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class Budget extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Budget Management';

    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        $pendingDebts = \App\Models\Debt::where('status', 'pending')->count();
        return $pendingDebts > 0 ? (string)$pendingDebts : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $overdueDebts = \App\Models\Debt::where('status', 'pending')
            ->where('due_date', '<', now())
            ->whereNotNull('due_date')
            ->count();

        return $overdueDebts > 0 ? 'danger' : 'warning';
    }
}
