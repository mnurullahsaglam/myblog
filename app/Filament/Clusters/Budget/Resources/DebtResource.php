<?php

namespace App\Filament\Clusters\Budget\Resources;

use App\Enums\Currencies;
use App\Filament\Clusters\Budget;
use App\Filament\Clusters\Budget\Resources\DebtResource\Pages;
use App\Models\Debt;
use App\Models\Expense;
use App\Services\ExchangeRateService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
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

class DebtResource extends Resource
{
    protected static ?string $model = Debt::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $cluster = Budget::class;

    protected static ?string $navigationLabel = 'Debts';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('creditor_name')
                    ->label('Creditor')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('creditor_type')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'person' => 'info',
                        'institute' => 'warning',
                    }),

                TextColumn::make('amount')
                    ->label('Original Amount')
                    ->money(fn(Debt $record) => $record->currency->value)
                    ->sortable(),

                TextColumn::make('converted_amount')
                    ->label('Converted Amount')
                    ->getStateUsing(function (Debt $record, $livewire): string {
                        $targetCurrency = $livewire->tableFilters['conversion_currency']['value'] ?? 'TRY';

                        if ($record->currency->value === $targetCurrency) {
                            return $record->formatted_amount;
                        }

                        $exchangeService = app(ExchangeRateService::class);
                        $convertedAmount = $exchangeService->convert(
                            $record->amount,
                            $record->currency->value,
                            $targetCurrency
                        );

                        $targetCurrencyEnum = Currencies::tryFrom($targetCurrency) ?? Currencies::TRY;
                        return $targetCurrencyEnum->getSymbol() . ' ' . number_format($convertedAmount, 2);
                    })
                    ->sortable(false),

                TextColumn::make('currency')
                    ->badge(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn(Debt $record) => $record->status_color),

                TextColumn::make('due_date_status')
                    ->label('Due Status')
                    ->getStateUsing(fn(Debt $record): string => $record->due_date_status)
                    ->color(function (Debt $record): string {
                        if (!$record->due_date || $record->status === 'paid') {
                            return 'gray';
                        }

                        $days = $record->days_until_due;
                        if ($days === null) return 'gray';

                        if ($days < 0) return 'danger';
                        if ($days <= 7) return 'warning';
                        return 'success';
                    }),

                TextColumn::make('due_date')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('date')
                    ->label('Debt Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                    ])
                    ->multiple(),

                SelectFilter::make('creditor_type')
                    ->options([
                        'person' => 'Person',
                        'institute' => 'Institute',
                    ])
                    ->multiple(),

                SelectFilter::make('currency')
                    ->options(collect(Currencies::cases())
                        ->mapWithKeys(fn($currency) => [$currency->value => $currency->getLabel()]))
                    ->multiple(),

                SelectFilter::make('conversion_currency')
                    ->label('Convert To Currency')
                    ->options(collect(Currencies::cases())
                        ->mapWithKeys(fn($currency) => [$currency->value => $currency->getLabel() . ' (' . $currency->getSymbol() . ')']))
                    ->default('TRY')
                    ->selectablePlaceholder(false)
                    ->query(function (Builder $query, array $data) {
                        // This filter doesn't affect the query, it's just used for UI state
                        return $query;
                    }),

                Filter::make('overdue')
                    ->label('Overdue Debts')
                    ->query(fn(Builder $query): Builder => $query
                        ->where('status', 'pending')
                        ->whereNotNull('due_date')
                        ->where('due_date', '<', now())),

                Filter::make('due_soon')
                    ->label('Due Within 7 Days')
                    ->query(fn(Builder $query): Builder => $query
                        ->where('status', 'pending')
                        ->whereNotNull('due_date')
                        ->whereBetween('due_date', [now(), now()->addDays(7)])),

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

                Filter::make('due_date_range')
                    ->label('Due Date Range')
                    ->form([
                        DatePicker::make('due_from_date')
                            ->label('Due From'),
                        DatePicker::make('due_to_date')
                            ->label('Due Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['due_from_date'],
                                fn(Builder $query, $date): Builder => $query->whereDate('due_date', '>=', $date),
                            )
                            ->when(
                                $data['due_to_date'],
                                fn(Builder $query, $date): Builder => $query->whereDate('due_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['due_from_date'] ?? null) {
                            $indicators[] = 'Due from ' . Carbon::parse($data['due_from_date'])->toFormattedDateString();
                        }
                        if ($data['due_to_date'] ?? null) {
                            $indicators[] = 'Due until ' . Carbon::parse($data['due_to_date'])->toFormattedDateString();
                        }
                        return $indicators;
                    }),
            ])
            ->actions([
                Action::make('payDebt')
                    ->label('Pay Debt')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn(Debt $record) => $record->status === 'pending')
                    ->form([
                        TextInput::make('payment_amount')
                            ->label('Payment Amount')
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->maxValue(fn(Debt $record) => $record->amount)
                            ->default(fn(Debt $record) => $record->amount)
                            ->prefix(fn(Debt $record) => $record->currency->getSymbol())
                            ->helperText(fn(Debt $record) => 'Maximum amount: ' . $record->formatted_amount),

                        Textarea::make('payment_description')
                            ->label('Payment Description')
                            ->default(fn(Debt $record) => "Payment for debt to {$record->creditor_name}")
                            ->rows(2),

                        FileUpload::make('receipt')
                            ->label('Payment Receipt')
                            ->directory('receipts/debt-payments')
                            ->image()
                            ->imageEditor()
                            ->maxSize(5120) // 5MB
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                            ->helperText('Upload receipt for this payment (optional)'),
                    ])
                    ->action(function (Debt $record, array $data) {
                        $paymentAmount = $data['payment_amount'];
                        $remainingAmount = $record->amount - $paymentAmount;

                        // Create expense record for the payment
                        Expense::create([
                            'debt_id' => $record->id,
                            'amount' => $paymentAmount,
                            'currency' => $record->currency->value,
                            'description' => $data['payment_description'],
                            'receipt_path' => $data['receipt'] ?? null,
                            'date' => now()->toDateString(),
                        ]);

                        if ($remainingAmount <= 0) {
                            // Fully paid
                            $record->update([
                                'amount' => 0,
                                'status' => 'paid'
                            ]);

                            Notification::make()
                                ->title('Debt fully paid')
                                ->body("Debt to {$record->creditor_name} has been fully paid.")
                                ->success()
                                ->send();
                        } else {
                            // Partially paid
                            $record->update(['amount' => $remainingAmount]);

                            Notification::make()
                                ->title('Partial payment recorded')
                                ->body("Paid {$record->currency->getSymbol()}" . number_format($paymentAmount, 2) . ". Remaining: {$record->currency->getSymbol()}" . number_format($remainingAmount, 2))
                                ->success()
                                ->send();
                        }
                    }),

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
                TextInput::make('creditor_name')
                    ->required()
                    ->label('Creditor Name')
                    ->maxLength(255),

                Select::make('creditor_type')
                    ->options([
                        'person' => 'Person',
                        'institute' => 'Institute',
                    ])
                    ->default('person')
                    ->required(),

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
                    ->default(now())
                    ->label('Debt Date'),

                DatePicker::make('due_date')
                    ->label('Due Date')
                    ->helperText('Optional: When should this debt be paid?'),

                Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                    ])
                    ->default('pending')
                    ->required(),

                Textarea::make('description')
                    ->rows(3)
                    ->helperText('Optional: Add details about this debt'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDebts::route('/'),
            'create' => Pages\CreateDebt::route('/create'),
            'edit' => Pages\EditDebt::route('/{record}/edit'),
        ];
    }
} 