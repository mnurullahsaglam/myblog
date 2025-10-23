<?php

namespace App\Filament\Clusters\Budget\Resources;

use App\Enums\Currencies;
use App\Filament\Clusters\Budget;
use App\Filament\Clusters\Budget\Resources\IncomeResource\Pages;
use App\Models\Income;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class IncomeResource extends Resource
{
    protected static ?string $model = Income::class;

    protected static ?string $navigationIcon = 'heroicon-o-plus-circle';

    protected static ?string $cluster = Budget::class;

    protected static ?string $navigationLabel = 'Incomes';

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query) => $query->with(['client', 'invoice', 'debt', 'incomeCategory']))
            ->columns([
                TextColumn::make('amount')
                    ->money(fn(Income $record) => $record->currency->value)
                    ->sortable(),

                TextColumn::make('currency')
                    ->badge()
                    ->color('success'),

                TextColumn::make('source')
                    ->label('Source')
                    ->searchable(),

                TextColumn::make('incomeCategory.name')
                    ->label('Category')
                    ->badge()
                    ->color(fn(Income $record) => $record->incomeCategory?->color ? 'primary' : 'gray')
                    ->sortable(),

                TextColumn::make('description')
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) <= 50 ? null : $state;
                    }),

                TextColumn::make('date')
                    ->date()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('income_category_id')
                    ->relationship('incomeCategory', 'name')
                    ->label('Category')
                    ->multiple()
                    ->preload(),

                SelectFilter::make('currency')
                    ->options(collect(Currencies::cases())
                        ->mapWithKeys(fn($currency) => [$currency->value => $currency->getLabel()]))
                    ->multiple(),

                SelectFilter::make('client_id')
                    ->relationship('client', 'title')
                    ->label('Client')
                    ->multiple()
                    ->preload(),

                Filter::make('date_range')
                    ->form([
                        DatePicker::make('from_date')
                            ->label('From Date'),
                        DatePicker::make('to_date')
                            ->label('To Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from_date'],
                                fn(Builder $query, $date): Builder => $query->whereDate('date', '>=', $date),
                            )
                            ->when(
                                $data['to_date'],
                                fn(Builder $query, $date): Builder => $query->whereDate('date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from_date'] ?? null) {
                            $indicators[] = 'From ' . Carbon::parse($data['from_date'])->toFormattedDateString();
                        }
                        if ($data['to_date'] ?? null) {
                            $indicators[] = 'Until ' . Carbon::parse($data['to_date'])->toFormattedDateString();
                        }
                        return $indicators;
                    }),

                SelectFilter::make('source_type')
                    ->label('Source Type')
                    ->options([
                        'client' => 'Client',
                        'invoice' => 'Invoice',
                        'debt' => 'Debt Payment',
                        'other' => 'Other',
                    ])
                    ->query(function (Builder $query, array $data) {
                        $value = $data['value'] ?? null;
                        if (!$value) return $query;

                        return match ($value) {
                            'client' => $query->whereNotNull('client_id')->whereNull('invoice_id')->whereNull('debt_id'),
                            'invoice' => $query->whereNotNull('invoice_id'),
                            'debt' => $query->whereNotNull('debt_id'),
                            'other' => $query->whereNull('client_id')->whereNull('invoice_id')->whereNull('debt_id'),
                            default => $query,
                        };
                    }),
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
            ])
            ->defaultSort('date', 'desc');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('amount')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01)
                    ->prefix(function (Get $get) {
                        $currency = $get('currency');
                        $currencyInstance = $currency instanceof Currencies ? $currency : Currencies::tryFrom($currency) ?? Currencies::TRY;
                        return $currencyInstance->getSymbol();
                    }),

                Select::make('currency')
                    ->options(collect(Currencies::cases())
                        ->mapWithKeys(fn($currency) => [$currency->value => $currency->getLabel() . ' (' . $currency->getSymbol() . ')']))
                    ->default('TRY')
                    ->required()
                    ->live(),

                DatePicker::make('date')
                    ->required()
                    ->default(now()),

                Select::make('income_category_id')
                    ->relationship('incomeCategory', 'name')
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        TextInput::make('name')->required(),
                        Textarea::make('description')->rows(2),
                        TextInput::make('color')->helperText('Hex color code (e.g., #FF0000)'),
                    ]),

                Select::make('client_id')
                    ->relationship('client', 'title')
                    ->searchable()
                    ->preload(),

                Select::make('invoice_id')
                    ->relationship('invoice', 'id')
                    ->searchable()
                    ->preload()
                    ->helperText('Select invoice if this income is from an invoice payment'),

                Select::make('debt_id')
                    ->relationship('debt', 'creditor_name')
                    ->searchable()
                    ->preload()
                    ->helperText('Select debt if this income is from debt repayment'),

                Textarea::make('description')
                    ->required()
                    ->rows(3),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIncomes::route('/'),
            'create' => Pages\CreateIncome::route('/create'),
            'view' => Pages\ViewIncome::route('/{record}'),
            'edit' => Pages\EditIncome::route('/{record}/edit'),
        ];
    }
} 