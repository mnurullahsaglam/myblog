<?php

namespace App\Filament\Clusters\Work\Resources;

use App\Enums\Currencies;
use App\Filament\Clusters\Work;
use App\Filament\Clusters\Work\Resources\InvoiceResource\Pages;
use App\Models\Invoice;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $cluster = Work::class;

    public static function form(Form $form): Form
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
                    ->prefix(function (Get $get) {
                        $currency = $get('currency');
                        $currencyInstance = $currency instanceof Currencies ? $currency : Currencies::tryFrom($currency) ?? Currencies::TRY;
                        return $currencyInstance->getSymbol();
                    })
                    ->live(true)
                    ->afterStateUpdated(fn(Set $set, Get $get, ?int $state) => $set('total_amount', $state + $get('tax_amount'))),

                TextInput::make('tax_rate')
                    ->numeric()
                    ->default(0)
                    ->minValue(0)
                    ->maxValue(100)
                    ->required()
                    ->live(true)
                    ->afterStateUpdated(function (Set $set, Get $get, ?int $state) {
                        $amount = $get('amount');
                        $tax_amount = ($amount * $state) / 100;
                        $set('tax_amount', $tax_amount);
                        $set('total_amount', $amount + $tax_amount);
                    }),

                TextInput::make('tax_amount')
                    ->numeric()
                    ->default(0)
                    ->minValue(0)
                    ->required()
                    ->prefix(function (Get $get) {
                        $currency = $get('currency');
                        $currencyInstance = $currency instanceof Currencies ? $currency : Currencies::tryFrom($currency) ?? Currencies::TRY;
                        return $currencyInstance->getSymbol();
                    })
                    ->live(true)
                    ->afterStateUpdated(fn(Set $set, Get $get, ?int $state) => $set('total_amount', $get('amount') + $state)),

                TextInput::make('total_amount')
                    ->numeric()
                    ->default(0)
                    ->minValue(0)
                    ->required()
                    ->prefix(function (Get $get) {
                        $currency = $get('currency');
                        $currencyInstance = $currency instanceof Currencies ? $currency : Currencies::tryFrom($currency) ?? Currencies::TRY;
                        return $currencyInstance->getSymbol();
                    }),

                FileUpload::make('invoice')
                    ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed'])
                    ->disk('local')
                    ->visibility('private')
                    ->directory('invoices')
                    ->required(),
            ]);
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
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
