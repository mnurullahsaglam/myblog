<?php

namespace App\Filament\Clusters\Work\Resources;

use App\Filament\Clusters\Work;
use App\Filament\Clusters\Work\Resources\RepositoryResource\Pages;
use App\Models\Repository;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RepositoryResource extends Resource
{
    protected static ?string $model = Repository::class;

    protected static ?string $slug = 'repositories';

    protected static ?string $navigationIcon = 'heroicon-o-code-bracket';

    protected static ?string $cluster = Work::class;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('project_id')
                    ->relationship('project', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),

                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('full_name')
                    ->required()
                    ->maxLength(255)
                    ->label('Full Name (owner/repo)'),

                TextInput::make('owner')
                    ->required()
                    ->maxLength(255),

                Textarea::make('description')
                    ->maxLength(1000)
                    ->rows(3),

                Select::make('visibility')
                    ->options([
                        'public' => 'Public',
                        'private' => 'Private',
                    ])
                    ->required(),

                TextInput::make('github_url')
                    ->url()
                    ->required()
                    ->label('GitHub URL'),

                TextInput::make('github_id')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->label('GitHub ID'),

                TextInput::make('default_branch')
                    ->required()
                    ->default('main')
                    ->maxLength(255),

                TextInput::make('language')
                    ->maxLength(255),

                TextInput::make('stars_count')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),

                TextInput::make('forks_count')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),

                TextInput::make('issues_count')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),

                Toggle::make('is_active')
                    ->default(true),

                DateTimePicker::make('github_created_at')
                    ->label('GitHub Created At'),

                DateTimePicker::make('github_updated_at')
                    ->label('GitHub Updated At'),

                DateTimePicker::make('last_synced_at')
                    ->label('Last Synced At'),

                Placeholder::make('created_at')
                    ->label('Created Date')
                    ->content(fn(?Repository $record): string => $record?->created_at?->diffForHumans() ?? '-'),

                Placeholder::make('updated_at')
                    ->label('Last Modified Date')
                    ->content(fn(?Repository $record): string => $record?->updated_at?->diffForHumans() ?? '-'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('owner')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('visibility')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'public' => 'success',
                        'private' => 'warning',
                    }),

                TextColumn::make('language')
                    ->sortable(),

                TextColumn::make('stars_count')
                    ->label('Stars')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('issues_count')
                    ->label('Issues')
                    ->numeric()
                    ->sortable(),

                IconColumn::make('is_active')
                    ->boolean(),

                TextColumn::make('last_synced_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRepositories::route('/'),
            'create' => Pages\CreateRepository::route('/create'),
            'view' => Pages\ViewRepository::route('/{record}'),
            'edit' => Pages\EditRepository::route('/{record}/edit'),
        ];
    }
} 