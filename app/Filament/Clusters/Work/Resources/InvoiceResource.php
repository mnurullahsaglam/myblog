<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Work\Resources;

use App\Enums\Currencies;
use App\Filament\Clusters\Work;
use App\Filament\Clusters\Work\Resources\InvoiceResource\Pages;
use App\Models\Invoice;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $cluster = Work::class;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Select::make('client_id')
                    ->relationship('client', 'title')
                    ->searchable()
                    ->preload()
                    ->required(),

                TextInput::make('invoice_number')
                    ->string()
                    ->maxLength(255)
                    ->unique('invoices', 'invoice_number', ignoreRecord: true)
                    ->required(),

                DateTimePicker::make('issued_at')
                    ->default(now())
                    ->seconds(false)
                    ->required(),

                Select::make('currency')
                    ->options(Currencies::class)
                    ->default(Currencies::TRY)
                    ->required()
                    ->live(),

                TextInput::make('amount')
                    ->numeric()
                    ->default(0)
                    ->minValue(0)
                    ->required()
                    ->prefix(fn (Get $get): string => self::resolveCurrency($get('currency'))->getSymbol())
                    ->live(true)
                    ->afterStateUpdated(fn (Set $set, Get $get, ?int $state) => $set('total_amount', ($state ?? 0) + self::toInt($get('tax_amount')))),

                TextInput::make('tax_rate')
                    ->numeric()
                    ->default(0)
                    ->minValue(0)
                    ->maxValue(100)
                    ->required()
                    ->live(true)
                    ->afterStateUpdated(function (Set $set, Get $get, ?int $state): void {
                        $amount = self::toInt($get('amount'));
                        $tax_amount = ($amount * ($state ?? 0)) / 100;
                        $set('tax_amount', $tax_amount);
                        $set('total_amount', $amount + $tax_amount);
                    }),

                TextInput::make('tax_amount')
                    ->numeric()
                    ->default(0)
                    ->minValue(0)
                    ->required()
                    ->prefix(fn (Get $get): string => self::resolveCurrency($get('currency'))->getSymbol())
                    ->live(true)
                    ->afterStateUpdated(fn (Set $set, Get $get, ?int $state) => $set('total_amount', self::toInt($get('amount')) + ($state ?? 0))),

                TextInput::make('total_amount')
                    ->numeric()
                    ->default(0)
                    ->minValue(0)
                    ->required()
                    ->prefix(fn (Get $get): string => self::resolveCurrency($get('currency'))->getSymbol()),

                FileUpload::make('invoice')
                    ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed'])
                    ->disk('local')
                    ->visibility('private')
                    ->directory('invoices')
                    ->required(),
            ]);
    }

    private static function resolveCurrency(mixed $currency): Currencies
    {
        if ($currency instanceof Currencies) {
            return $currency;
        }

        if (is_int($currency) || is_string($currency)) {
            return Currencies::tryFrom($currency) ?? Currencies::TRY;
        }

        return Currencies::TRY;
    }

    private static function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_number'),

                TextColumn::make('client.title'),

                TextColumn::make('issued_at')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('amount_with_currency_symbol'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }
}
