<?php

namespace App\Filament\Resources\WakaTimeSummaries\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'entries';

    protected static ?string $title = 'Breakdowns';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultGroup('type')
            ->defaultSort('seconds', 'desc')
            ->columns([
                TextColumn::make('type')
                    ->badge()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('seconds')
                    ->label('Time')
                    ->formatStateUsing(fn (int $state): string => sprintf('%dh %dm', intdiv($state, 3600), intdiv($state % 3600, 60)))
                    ->sortable(),
                TextColumn::make('percent')
                    ->label('Share')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 1) . '%')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'project' => 'Project',
                        'language' => 'Language',
                        'editor' => 'Editor',
                        'os' => 'OS',
                        'category' => 'Category',
                    ]),
            ], layout: FiltersLayout::AboveContent)
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
