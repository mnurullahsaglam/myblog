<?php

namespace App\Filament\Resources\WakaTimeSummaries\Pages;

use App\Filament\Resources\WakaTimeSummaries\WakaTimeSummaryResource;
use App\Filament\Widgets\WakaTimeAiOverview;
use App\Filament\Widgets\WakaTimeCategoriesChart;
use App\Filament\Widgets\WakaTimeEditorsChart;
use App\Filament\Widgets\WakaTimeLanguagesChart;
use App\Filament\Widgets\WakaTimeOperatingSystemsChart;
use App\Filament\Widgets\WakaTimeOverview;
use App\Filament\Widgets\WakaTimeProjectsChart;
use Filament\Resources\Pages\ViewRecord;

class ViewWakaTimeSummary extends ViewRecord
{
    protected static string $resource = WakaTimeSummaryResource::class;

    public function getTitle(): string
    {
        return $this->record->date->format('l, F j, Y');
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            WakaTimeOverview::class,
            WakaTimeAiOverview::class,
            WakaTimeProjectsChart::class,
            WakaTimeLanguagesChart::class,
            WakaTimeEditorsChart::class,
            WakaTimeOperatingSystemsChart::class,
            WakaTimeCategoriesChart::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 2;
    }
}
