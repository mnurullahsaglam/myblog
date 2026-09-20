<?php

declare(strict_types=1);

namespace App\Tables\Definitions;

use App\Models\UtilityBill;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\ResourceTable;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Override;

final class UtilityBillTable extends ResourceTable
{
    #[Override]
    protected string $model = UtilityBill::class;

    // Every row reads account.type and account.label. Model::shouldBeStrict()
    // turns a lazy load into an exception outside production, so this is not an
    // optimisation, it is what stops the page throwing.
    #[Override]
    protected array $with = ['account'];

    #[Override]
    protected string $defaultSort = 'due_date';

    /**
     * @return array<int, Column>
     */
    protected function columns(): array
    {
        return [
            Column::badge('account.type')->label('Type')
                ->state(fn (UtilityBill $record): string => $record->account?->type->getLabel() ?? '—')
                ->color(fn (UtilityBill $record): string => $record->account?->type->getColor() ?? 'gray'),
            Column::text('account.label')->label('Account'),
            Column::date('period_start')->label('From')->default('—'),
            Column::date('period_end')->label('To')->default('—'),
            Column::date('due_date')->label('Due')->sortable(),
            Column::badge('paid_at')->label('Status')
                ->state(fn (UtilityBill $record): string => match (true) {
                    $record->isPaid() => 'Paid',
                    $record->due_date->isPast() => 'Overdue',
                    default => 'Outstanding',
                })
                ->color(fn (UtilityBill $record): string => match (true) {
                    $record->isPaid() => 'success',
                    $record->due_date->isPast() => 'danger',
                    default => 'warning',
                }),
            Column::money('total_amount', currencyFrom: 'currency')->label('Total')->sortable(),
            Column::datetime('created_at')->label('Created')->sortable()->toggleable(hiddenByDefault: true),
        ];
    }

    /**
     * @return array<int, Filter>
     */
    protected function filters(): array
    {
        return [
            Filter::relationship('utility_account_id', 'account', 'label')->label('Account')->multiple(),
            Filter::custom('unpaid', 'Unpaid', fn (Builder $query): Builder => $query->whereNull('paid_at')),
            Filter::custom('overdue', 'Overdue', fn (Builder $query): Builder => $query
                ->whereNull('paid_at')
                ->whereDate('due_date', '<', now())),
            Filter::custom('due_soon', 'Due within 7 days', fn (Builder $query): Builder => $query
                ->whereNull('paid_at')
                ->whereBetween('due_date', [now()->toDateString(), now()->addDays(7)->toDateString()])),
            Filter::dateRange('due_date')->label('Due'),
        ];
    }

    /**
     * @return array<int, array{label: string, value: string, caption: string|null, icon: string|null}>
     */
    public function tiles(Request $request): array
    {
        $query = $this->filteredQuery($request);

        $outstanding = (float) (clone $query)->whereNull('paid_at')->sum('total_amount');
        $overdue = (clone $query)->whereNull('paid_at')->whereDate('due_date', '<', now())->count();
        $thisMonth = (float) (clone $query)
            ->whereNotNull('paid_at')
            ->whereBetween('due_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->sum('total_amount');

        return [
            [
                'label' => 'Outstanding',
                'value' => number_format($outstanding, 2),
                'caption' => 'unpaid bills',
                'icon' => 'pi pi-wallet',
            ],
            [
                'label' => 'Overdue',
                'value' => number_format($overdue),
                'caption' => $overdue === 0 ? 'nothing late' : 'past the due date',
                'icon' => 'pi pi-exclamation-triangle',
            ],
            [
                'label' => 'Paid this month',
                'value' => number_format($thisMonth, 2),
                'caption' => now()->format('F'),
                'icon' => 'pi pi-check-circle',
            ],
        ];
    }
}
