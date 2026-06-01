<?php

namespace App\Filament\Resources\WakaTimeSummaries\Pages;

use App\Filament\Resources\WakaTimeSummaries\WakaTimeSummaryResource;
use App\Services\WakaTimeService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;

class ListWakaTimeSummaries extends ListRecords
{
    protected static string $resource = WakaTimeSummaryResource::class;

    protected function getHeaderActions(): array
    {
        $connected = app(WakaTimeService::class)->isConnected();

        return [
            Action::make('connect')
                ->label($connected ? 'Reconnect WakaTime' : 'Connect WakaTime')
                ->icon(Heroicon::OutlinedLink)
                ->color($connected ? 'gray' : 'primary')
                ->url(route('wakatime.connect')),

            Action::make('sync')
                ->label('Sync now')
                ->icon(Heroicon::OutlinedArrowPath)
                ->visible($connected)
                ->requiresConfirmation()
                ->modalDescription('Fetch the last 7 days from WakaTime and upsert them.')
                ->action(function (): void {
                    $exitCode = Artisan::call('wakatime:sync');

                    if ($exitCode === 0) {
                        Notification::make()
                            ->title('WakaTime sync complete')
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('WakaTime sync failed')
                            ->body('Check the logs for details.')
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
