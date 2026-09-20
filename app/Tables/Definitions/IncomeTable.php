<?php

declare(strict_types=1);

namespace App\Tables\Definitions;

use App\Enums\Ability;
use App\Enums\Currencies;
use App\Models\Income;
use App\Support\Access\AccessProfile;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\ResourceTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Override;

final class IncomeTable extends ResourceTable
{
    #[Override]
    protected string $model = Income::class;

    #[Override]
    protected array $with = ['client', 'invoice', 'debt', 'incomeCategory'];

    #[Override]
    protected string $defaultSort = '-date';

    protected function columns(): array
    {
        return [
            Column::date('date')->sortable(),
            Column::money('amount', currencyFrom: 'currency')->sortable(),
            Column::badge('currency')->color('success')
                ->state(fn (Income $record): string => $record->currency->value),
            Column::badge('incomeCategory.name')->label('Category')
                ->color(fn (Income $record): string => $record->incomeCategory?->color ? 'primary' : 'gray')
                ->default('—'),
            Column::text('source')->default('—'),
            Column::text('client.title')->label('Client')->default('—')->toggleable(hiddenByDefault: true)
                ->hiddenWithout(Ability::SeeClientIdentity),
            Column::text('description')->limit(50)->tooltip(),
            Column::datetime('created_at')->label('Created')->sortable()->toggleable(hiddenByDefault: true),
        ];
    }

    protected function filters(): array
    {
        return [
            Filter::relationship('income_category_id', 'incomeCategory', 'name')->label('Category')->multiple(),
            Filter::enum('currency', Currencies::class)->multiple(),
            Filter::relationship('client_id', 'client', 'title')->label('Client')->multiple()
                ->hiddenWithout(Ability::SeeClientIdentity),
            Filter::dateRange('date'),
            Filter::custom('source_invoice', 'From an invoice', fn (Builder $query): Builder => $query->whereNotNull('invoice_id')),
            Filter::custom('source_debt', 'From debt repayment', fn (Builder $query): Builder => $query->whereNotNull('debt_id')),
            Filter::custom('source_client', 'From a client', fn (Builder $query): Builder => $query
                ->whereNotNull('client_id')
                ->whereNull('invoice_id')
                ->whereNull('debt_id')),
        ];
    }

    protected function searchable(): array
    {
        return ['source', 'description', 'incomeCategory.name'];
    }

    protected function titleColumn(): string
    {
        return 'description';
    }

    #[Override]
    protected function isRowEditable(Model $record): bool
    {
        return app(AccessProfile::class)->allows(Ability::SeeClientIdentity)
            || $record->getAttribute('client_id') === null;
    }
}
