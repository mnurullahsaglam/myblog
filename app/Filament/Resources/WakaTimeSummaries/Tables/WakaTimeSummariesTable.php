<?php

declare(strict_types=1);

namespace App\Filament\Resources\WakaTimeSummaries\Tables;

use App\Models\WakaTimeSummary;
use App\Models\WakaTimeSummaryEntry;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WakaTimeSummariesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('entries'))
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')
                    ->date('M j, Y')
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('total_human')
                    ->label('Total')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('total_seconds', $direction))
                    ->badge()
                    ->color('success'),
                TextColumn::make('top_project')
                    ->label('Top project')
                    ->getStateUsing(fn (WakaTimeSummary $record): ?string => self::topName($record, WakaTimeSummaryEntry::TYPE_PROJECT))
                    ->placeholder('—'),
                TextColumn::make('top_language')
                    ->label('Top language')
                    ->getStateUsing(fn (WakaTimeSummary $record): ?string => self::topName($record, WakaTimeSummaryEntry::TYPE_LANGUAGE))
                    ->placeholder('—'),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function topName(WakaTimeSummary $record, string $type): ?string
    {
        $entry = $record->entries
            ->where('type', $type)
            ->sortByDesc('seconds')
            ->first();

        return $entry?->name;
    }
}
