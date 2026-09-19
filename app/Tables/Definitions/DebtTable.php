<?php

declare(strict_types=1);

namespace App\Tables\Definitions;

use App\Enums\Currencies;
use App\Models\Debt;
use App\Services\ExchangeRateService;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\ResourceTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Override;
use Throwable;

final class DebtTable extends ResourceTable
{
    #[Override]
    protected string $model = Debt::class;

    #[Override]
    protected string $defaultSort = '-date';

    /**
     * The conversion currency comes from a filter, but it changes how a column
     * renders rather than which rows come back, so it is passed in and the
     * filter itself is display-only.
     */
    public function __construct(private readonly string $conversionCurrency = 'TRY') {}

    protected function columns(): array
    {
        $target = Currencies::tryFrom($this->conversionCurrency) ?? Currencies::TRY;

        return [
            Column::text('creditor_name')->label('Creditor')->sortable(),
            Column::badge('creditor_type')->label('Type')
                ->color(fn (Debt $record): string => $record->creditor_type === 'person' ? 'info' : 'warning'),
            Column::money('amount', currencyFrom: 'currency')->label('Amount')->sortable(),
            Column::text('converted_amount')->label('In '.$target->value)
                ->state(fn (Debt $record): string => $this->convert($record, $target))
                ->align('right'),
            Column::badge('currency')
                ->state(fn (Debt $record): string => $record->currency->value),
            Column::badge('status')
                ->color(fn (Debt $record): string => $record->status_color),
            Column::badge('due_date_status')->label('Due status')
                ->state(fn (Debt $record): string => $record->due_date_status)
                ->color(fn (Debt $record): string => $this->dueStatusColor($record)),
            Column::date('date')->label('Incurred')->sortable(),
            Column::date('due_date')->label('Due')->sortable()->default('—')->toggleable(hiddenByDefault: true),
            Column::datetime('created_at')->label('Created')->sortable()->toggleable(hiddenByDefault: true),
        ];
    }

    private function convert(Debt $record, Currencies $target): string
    {
        if ($record->currency->value === $target->value) {
            return $target->getSymbol().number_format((float) $record->amount, 2);
        }

        try {
            $converted = resolve(ExchangeRateService::class)->convert(
                (float) $record->amount,
                $record->currency->value,
                $target->value,
            );
        } catch (Throwable) {
            return '—';
        }

        return $target->getSymbol().number_format($converted, 2);
    }

    private function dueStatusColor(Debt $record): string
    {
        if ($record->due_date === null || $record->status === 'paid') {
            return 'gray';
        }

        $days = $record->days_until_due;

        if ($days === null) {
            return 'gray';
        }

        if ($days < 0) {
            return 'danger';
        }

        return $days <= 7 ? 'warning' : 'success';
    }

    /**
     * @return array<int, array{label: string, value: string, caption?: string|null, icon?: string|null}>
     */
    public function tiles(Request $request): array
    {
        $query = $this->filteredQuery($request);
        $target = Currencies::tryFrom($this->conversionCurrency) ?? Currencies::TRY;

        $outstanding = 0.0;
        $overdue = 0;
        $dueSoon = 0;

        /** @var Collection<int, Debt> $pending */
        $pending = (clone $query)->where('status', 'pending')->get();

        foreach ($pending as $debt) {
            $outstanding += (float) str_replace(
                [$target->getSymbol(), ',', '—'],
                '',
                $this->convert($debt, $target)
            );

            $days = $debt->days_until_due;

            if ($days === null) {
                continue;
            }

            if ($days < 0) {
                $overdue++;
            } elseif ($days <= 7) {
                $dueSoon++;
            }
        }

        return [
            [
                'label' => 'Outstanding',
                'value' => $target->getSymbol().number_format($outstanding, 2),
                'caption' => 'converted to '.$target->value,
                'icon' => 'pi pi-wallet',
            ],
            [
                'label' => 'Pending',
                'value' => number_format((clone $query)->where('status', 'pending')->count()),
                'caption' => 'of '.number_format((clone $query)->count()).' total',
                'icon' => 'pi pi-clock',
            ],
            [
                'label' => 'Overdue',
                'value' => number_format($overdue),
                'caption' => $overdue > 0 ? 'past the due date' : 'nothing late',
                'icon' => 'pi pi-exclamation-triangle',
            ],
            [
                'label' => 'Due within 7 days',
                'value' => number_format($dueSoon),
                'caption' => $dueSoon > 0 ? 'act soon' : 'nothing imminent',
                'icon' => 'pi pi-calendar',
            ],
        ];
    }

    protected function filters(): array
    {
        return [
            Filter::select('status', ['pending' => 'Pending', 'paid' => 'Paid'])->multiple(),
            Filter::select('creditor_type', ['person' => 'Person', 'institute' => 'Institute'])->label('Type')->multiple(),
            Filter::enum('currency', Currencies::class)->multiple(),
            Filter::enum('conversion_currency', Currencies::class)
                ->label('Convert to')
                ->default(Currencies::TRY->value)
                ->displayOnly(),
            Filter::custom('overdue', 'Overdue', fn (Builder $query): Builder => $query
                ->where('status', 'pending')
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', now())),
            Filter::custom('due_soon', 'Due within 7 days', fn (Builder $query): Builder => $query
                ->where('status', 'pending')
                ->whereNotNull('due_date')
                ->whereBetween('due_date', [now()->toDateString(), now()->addDays(7)->toDateString()])),
            Filter::dateRange('date')->label('Incurred'),
            Filter::dateRange('due_date')->label('Due'),
        ];
    }

    protected function searchable(): array
    {
        return ['creditor_name', 'description'];
    }

    protected function titleColumn(): string
    {
        return 'creditor_name';
    }
}
