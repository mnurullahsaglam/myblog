<?php

namespace App\Filament\Clusters\Budget\Resources\DebtResource\Widgets;

use App\Services\ExchangeRateService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class ExchangeRateWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $exchangeService = app(ExchangeRateService::class);
        $lastUpdated = Cache::get('exchange_rates_last_updated', 'Never');

        if ($lastUpdated !== 'Never') {
            $lastUpdated = \Carbon\Carbon::parse($lastUpdated)->diffForHumans();
        }

        return [
            Stat::make('Exchange Rates', 'Live Currency Conversion')
                ->description('Last updated: ' . $lastUpdated)
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success'),

            Stat::make('Base Currency', 'Turkish Lira (TRY)')
                ->description('All conversions calculated from TRY base')
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color('info'),
        ];
    }
} 