<?php

namespace App\Filament\Clusters\Work\Pages;

use App\Filament\Clusters\Work;
use App\Filament\Widgets\WakaTimeAiOverview;
use App\Filament\Widgets\WakaTimeCategoriesChart;
use App\Filament\Widgets\WakaTimeEditorsChart;
use App\Filament\Widgets\WakaTimeLanguagesChart;
use App\Filament\Widgets\WakaTimeOperatingSystemsChart;
use App\Filament\Widgets\WakaTimeOverview;
use App\Filament\Widgets\WakaTimeProjectsChart;
use App\Filament\Widgets\WakaTimeTrendChart;
use App\Filament\Widgets\WakaTimeWeekdayChart;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class CodingDashboard extends Page
{
    use HasFiltersForm;

    protected string $view = 'filament.clusters.work.pages.coding-dashboard';

    protected static ?string $cluster = Work::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Coding Dashboard';

    protected static ?string $title = 'Coding Dashboard';

    protected static ?int $navigationSort = -1;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('range')
                ->label('Date range')
                ->options([
                    '7' => 'Last 7 days',
                    '14' => 'Last 14 days',
                    '30' => 'Last 30 days',
                    '90' => 'Last 90 days',
                    'all' => 'All time',
                ])
                ->default('7')
                ->selectablePlaceholder(false),
        ]);
    }

    public function getFooterWidgets(): array
    {
        return [
            WakaTimeOverview::class,
            WakaTimeAiOverview::class,
            WakaTimeTrendChart::class,
            WakaTimeProjectsChart::class,
            WakaTimeLanguagesChart::class,
            WakaTimeEditorsChart::class,
            WakaTimeOperatingSystemsChart::class,
            WakaTimeCategoriesChart::class,
            WakaTimeWeekdayChart::class,
        ];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 2;
    }
}
