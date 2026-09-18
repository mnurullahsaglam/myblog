<?php

declare(strict_types=1);

namespace App\Tables\Definitions;

use App\Enums\Currencies;
use App\Models\Invoice;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\ResourceTable;
use Illuminate\Http\Request;

final class InvoiceTable extends ResourceTable
{
    protected string $model = Invoice::class;

    protected array $with = ['client'];

    protected string $defaultSort = '-issued_at';

    protected function columns(): array
    {
        return [
            Column::text('invoice_number')->label('Number')->sortable(),
            Column::text('client.title')->label('Client')->default('—'),
            Column::date('issued_at')->label('Issued')->sortable(),
            Column::money('amount', currencyFrom: 'currency')->label('Net')->sortable(),
            Column::number('tax_rate')->label('Tax %'),
            Column::money('tax_amount', currencyFrom: 'currency')->label('Tax'),
            Column::money('total_amount', currencyFrom: 'currency')->label('Total')->sortable(),
            Column::badge('currency')
                ->state(fn (Invoice $record): string => $record->currency->value),
        ];
    }

    /**
     * @return array<int, array{label: string, value: string, caption?: string|null, icon?: string|null}>
     */
    public function tiles(Request $request): array
    {
        $query = $this->filteredQuery($request);

        /** @var \Illuminate\Support\Collection<string, float> $byCurrency */
        $byCurrency = (clone $query)
            ->selectRaw('currency, SUM(total_amount) as total')
            ->groupBy('currency')
            ->pluck('total', 'currency');

        $dominant = $byCurrency->sortDesc()->keys()->first();
        $dominantTotal = $dominant === null ? 0.0 : (float) $byCurrency[$dominant];
        $symbol = $dominant === null ? '' : (Currencies::tryFrom($dominant)?->getSymbol() ?? $dominant.' ');

        $count = (clone $query)->count();
        $taxTotal = (float) (clone $query)->when($dominant, fn ($builder) => $builder->where('currency', $dominant))->sum('tax_amount');

        return [
            [
                'label' => 'Invoiced',
                'value' => $symbol.number_format($dominantTotal, 2),
                'caption' => $byCurrency->count() > 1
                    ? 'plus '.($byCurrency->count() - 1).' other '.($byCurrency->count() === 2 ? 'currency' : 'currencies')
                    : $dominant,
                'icon' => 'pi pi-receipt',
            ],
            [
                'label' => 'Tax charged',
                'value' => $symbol.number_format($taxTotal, 2),
                'caption' => $dominant,
                'icon' => 'pi pi-percentage',
            ],
            [
                'label' => 'Invoices',
                'value' => number_format($count),
                'caption' => $count === 0 ? 'nothing matches' : null,
                'icon' => 'pi pi-list',
            ],
            [
                'label' => 'Average',
                'value' => $symbol.number_format($count > 0 ? $dominantTotal / $count : 0, 2),
                'caption' => 'per invoice',
                'icon' => 'pi pi-chart-bar',
            ],
        ];
    }

    protected function filters(): array
    {
        return [
            Filter::relationship('client_id', 'client', 'title')->label('Client')->multiple(),
            Filter::enum('currency', Currencies::class)->multiple(),
            Filter::dateRange('issued_at')->label('Issued'),
        ];
    }

    protected function searchable(): array
    {
        return ['invoice_number', 'client.title'];
    }

    protected function titleColumn(): string
    {
        return 'invoice_number';
    }
}
