<?php

declare(strict_types=1);

namespace App\Tables\Definitions;

use App\Enums\Currencies;
use App\Models\Expense;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\ResourceTable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Override;

final class ExpenseTable extends ResourceTable
{
    #[Override]
    protected string $model = Expense::class;

    #[Override]
    protected array $with = ['expenseCategory', 'debt'];

    #[Override]
    protected string $defaultSort = '-date';

    protected function columns(): array
    {
        return [
            Column::image('receipt_path')->label('')->circular()->size(26),
            Column::date('date')->sortable(),
            Column::text('description')->limit(50)->tooltip(),
            Column::badge('expenseCategory.name')->label('Category')
                ->color(fn (Expense $record): string => $record->expenseCategory?->color ? 'primary' : 'gray')
                ->default('—'),
            Column::badge('debt.creditor_name')->label('Debt to')->color('warning')->default('—'),
            Column::boolean('is_recurring')->label('Recurring'),
            Column::boolean('is_tax_deductible')->label('Deductible'),
            Column::badge('currency')->color('danger')
                ->state(fn (Expense $record): string => $record->currency->value),
            Column::money('amount', currencyFrom: 'currency')->sortable(),
            Column::datetime('created_at')->label('Created')->sortable()->toggleable(hiddenByDefault: true),
        ];
    }

    /**
     * @return array<int, array{label: string, value: string, caption?: string|null, icon?: string|null}>
     */
    public function tiles(Request $request): array
    {
        $query = $this->filteredQuery($request);

        /** @var Collection<string, float> $byCurrency */
        $byCurrency = (clone $query)
            ->selectRaw('currency, SUM(amount) as total')
            ->groupBy('currency')
            ->pluck('total', 'currency');

        $count = (clone $query)->count();
        $earliest = (clone $query)->min('date');
        $latest = (clone $query)->max('date');

        $days = is_scalar($earliest) && is_scalar($latest)
            ? max(1, (int) Date::parse((string) $earliest)->diffInDays(Date::parse((string) $latest)) + 1)
            : 1;

        $dominant = $byCurrency->sortDesc()->keys()->first();
        $dominantTotal = $dominant === null ? 0.0 : (float) $byCurrency[$dominant];
        $symbol = $dominant === null ? '' : (Currencies::tryFrom($dominant)?->getSymbol() ?? $dominant.' ');

        return [
            [
                'label' => 'Net outflow',
                'value' => $symbol.number_format($dominantTotal, 2),
                'caption' => $byCurrency->count() > 1
                    ? 'plus '.($byCurrency->count() - 1).' other '.($byCurrency->count() === 2 ? 'currency' : 'currencies')
                    : $dominant,
                'icon' => 'pi pi-arrow-down-right',
            ],
            [
                'label' => 'Daily mean',
                'value' => $symbol.number_format($dominantTotal / $days, 2),
                'caption' => 'over '.$days.' '.($days === 1 ? 'day' : 'days'),
                'icon' => 'pi pi-chart-line',
            ],
            [
                'label' => 'Transactions',
                'value' => number_format($count),
                'caption' => $count === 0 ? 'nothing matches' : null,
                'icon' => 'pi pi-list',
            ],
            [
                'label' => 'Missing receipts',
                'value' => number_format((clone $query)->whereNull('receipt_path')->count()),
                'caption' => 'of '.number_format($count),
                'icon' => 'pi pi-exclamation-circle',
            ],
        ];
    }

    protected function filters(): array
    {
        return [
            Filter::relationship('expense_category_id', 'expenseCategory', 'name')->label('Category')->multiple(),
            Filter::enum('currency', Currencies::class)->multiple(),
            Filter::relationship('debt_id', 'debt', 'creditor_name')->label('Debt payment')->multiple(),
            Filter::dateRange('date'),
            Filter::boolean('receipt_path')->label('Receipt')->trueLabel('Has receipt')->falseLabel('No receipt'),
            Filter::select('is_recurring', ['1' => 'Recurring', '0' => 'One-off'])->label('Frequency'),
            Filter::select('is_tax_deductible', ['1' => 'Deductible', '0' => 'Not deductible'])->label('Tax'),
        ];
    }

    protected function searchable(): array
    {
        return ['description', 'expenseCategory.name'];
    }

    protected function titleColumn(): string
    {
        return 'description';
    }
}
