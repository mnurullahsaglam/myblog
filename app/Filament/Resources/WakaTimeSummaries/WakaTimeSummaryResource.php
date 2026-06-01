<?php

declare(strict_types=1);

namespace App\Filament\Resources\WakaTimeSummaries;

use App\Filament\Clusters\Work;
use App\Filament\Resources\WakaTimeSummaries\Pages\ListWakaTimeSummaries;
use App\Filament\Resources\WakaTimeSummaries\Pages\ViewWakaTimeSummary;
use App\Filament\Resources\WakaTimeSummaries\RelationManagers\EntriesRelationManager;
use App\Filament\Resources\WakaTimeSummaries\Schemas\WakaTimeSummaryInfolist;
use App\Filament\Resources\WakaTimeSummaries\Tables\WakaTimeSummariesTable;
use App\Models\WakaTimeSummary;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WakaTimeSummaryResource extends Resource
{
    protected static ?string $model = WakaTimeSummary::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $cluster = Work::class;

    protected static ?string $navigationLabel = 'Daily Summaries';

    protected static ?string $modelLabel = 'WakaTime day';

    protected static ?string $pluralModelLabel = 'WakaTime days';

    public static function infolist(Schema $schema): Schema
    {
        return WakaTimeSummaryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WakaTimeSummariesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            EntriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWakaTimeSummaries::route('/'),
            'view' => ViewWakaTimeSummary::route('/{record}'),
        ];
    }

    // Data is synced from WakaTime, never authored by hand.
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(mixed $record): bool
    {
        return false;
    }
}
