<?php

namespace App\Filament\Clusters\Budget\Resources;

use App\Enums\Currencies;
use App\Filament\Clusters\Budget;
use App\Filament\Clusters\Budget\Resources\ExpenseResource\Pages;
use App\Models\Expense;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
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
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static ?string $navigationIcon = 'heroicon-o-minus-circle';

    protected static ?string $cluster = Budget::class;

    protected static ?string $navigationLabel = 'Expenses';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('amount')
                    ->money(fn(Expense $record) => $record->currency->value)
                    ->sortable(),

                TextColumn::make('currency')
                    ->badge()
                    ->color('danger'),

                TextColumn::make('expenseCategory.name')
                    ->label('Category')
                    ->badge()
                    ->color(fn(Expense $record) => $record->expenseCategory?->color ? 'primary' : 'gray')
                    ->sortable(),

                TextColumn::make('description')
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) <= 50 ? null : $state;
                    }),

                ImageColumn::make('receipt_path')
                    ->label('Receipt')
                    ->circular()
                    ->size(40),

                TextColumn::make('debt.creditor_name')
                    ->label('Debt To')
                    ->badge()
                    ->color('warning')
                    ->default('N/A'),

                TextColumn::make('date')
                    ->date()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('expense_category_id')
                    ->relationship('expenseCategory', 'name')
                    ->label('Category')
                    ->multiple()
                    ->preload(),

                SelectFilter::make('currency')
                    ->options(collect(Currencies::cases())
                        ->mapWithKeys(fn($currency) => [$currency->value => $currency->getLabel()]))
                    ->multiple(),

                SelectFilter::make('debt_id')
                    ->relationship('debt', 'creditor_name')
                    ->label('Debt Payment')
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

                Filter::make('has_receipt')
                    ->label('Has Receipt')
                    ->query(fn(Builder $query): Builder => $query->whereNotNull('receipt_path')),

                Filter::make('no_receipt')
                    ->label('No Receipt')
                    ->query(fn(Builder $query): Builder => $query->whereNull('receipt_path')),
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

                Select::make('expense_category_id')
                    ->relationship('expenseCategory', 'name')
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        TextInput::make('name')->required(),
                        Textarea::make('description')->rows(2),
                        TextInput::make('color')->helperText('Hex color code (e.g., #FF0000)'),
                    ]),

                Select::make('debt_id')
                    ->relationship('debt', 'creditor_name')
                    ->searchable()
                    ->preload()
                    ->helperText('Select debt if this expense is a debt payment'),

                Textarea::make('description')
                    ->required()
                    ->rows(3),

                FileUpload::make('receipt_path')
                    ->label('Receipt')
                    ->image()
                    ->imageEditor()
                    ->directory('receipts')
                    ->helperText('Upload receipt image (optional)'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpenses::route('/'),
            'create' => Pages\CreateExpense::route('/create'),
            'edit' => Pages\EditExpense::route('/{record}/edit'),
        ];
    }
} 