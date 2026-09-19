<?php

declare(strict_types=1);

namespace App\Support\Widgets;

use App\Models\Debt;
use App\Models\Expense;
use App\Models\Income;
use App\Services\ExchangeRateService;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Throwable;

final class BudgetOverview
{
    private const string BASE_CURRENCY = 'TRY';

    /**
     * @return array<int, array{label: string, value: string, caption: string|null, icon: string|null}>
     */
    public static function stats(): array
    {
        $income = self::monthlyTotal(Income::query());
        $expenses = self::monthlyTotal(Expense::query());
        $net = $income - $expenses;

        $pending = Debt::query()->where('status', 'pending')->count();
        $overdue = Debt::query()
            ->where('status', 'pending')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now())
            ->count();

        return [
            [
                'label' => 'Income this month',
                'value' => self::money($income),
                'caption' => now()->format('F Y'),
                'icon' => 'pi pi-plus-circle',
            ],
            [
                'label' => 'Spent this month',
                'value' => self::money($expenses),
                'caption' => now()->format('F Y'),
                'icon' => 'pi pi-minus-circle',
            ],
            [
                'label' => 'Net',
                'value' => self::money($net),
                'caption' => $net >= 0 ? 'in the black' : 'in the red',
                'icon' => $net >= 0 ? 'pi pi-arrow-up-right' : 'pi pi-arrow-down-right',
            ],
            [
                'label' => 'Outstanding debts',
                'value' => number_format($pending),
                'caption' => $overdue > 0
                    ? $overdue.' overdue'
                    : ($pending === 0 ? 'nothing owed' : 'none overdue'),
                'icon' => 'pi pi-exclamation-triangle',
            ],
        ];
    }

    /**
     * Sums this month's rows, converting each into the base currency.
     *
     * @param  Builder<covariant Model>  $query
     */
    private static function monthlyTotal(Builder $query): float
    {
        $rows = $query
            ->whereBetween('date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->get(['amount', 'currency']);

        $total = 0.0;

        foreach ($rows as $row) {
            $amount = is_numeric($row->getAttribute('amount')) ? (float) $row->getAttribute('amount') : 0.0;
            $currency = $row->getAttribute('currency');
            $code = match (true) {
                $currency instanceof BackedEnum => (string) $currency->value,
                is_scalar($currency) => (string) $currency,
                default => self::BASE_CURRENCY,
            };

            $total += $code === self::BASE_CURRENCY ? $amount : self::convert($amount, $code);
        }

        return $total;
    }

    /**
     * A rate lookup failure must not take the dashboard down.
     */
    private static function convert(float $amount, string $from): float
    {
        try {
            return resolve(ExchangeRateService::class)->convert($amount, $from, self::BASE_CURRENCY);
        } catch (Throwable) {
            return 0.0;
        }
    }

    private static function money(float $amount): string
    {
        return '₺'.number_format($amount, 2);
    }
}
