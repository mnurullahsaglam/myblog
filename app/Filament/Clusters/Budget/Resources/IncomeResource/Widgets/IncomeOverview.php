<?php

namespace App\Filament\Clusters\Budget\Resources\IncomeResource\Widgets;

use App\Models\Debt;
use App\Models\Expense;
use App\Models\Income;
use App\Services\ExchangeRateService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class IncomeOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $exchangeService = app(ExchangeRateService::class);
        $baseCurrency = 'TRY';

        // Calculate total income this month
        $monthlyIncome = $this->calculateMonthlyTotal(Income::class, $exchangeService, $baseCurrency);

        // Calculate total expenses this month
        $monthlyExpenses = $this->calculateMonthlyTotal(Expense::class, $exchangeService, $baseCurrency);

        // Calculate net income (income - expenses)
        $netIncome = $monthlyIncome - $monthlyExpenses;

        // Get pending debts
        $pendingDebts = Debt::where('status', 'pending')->count();
        $overdueDebts = Debt::where('status', 'pending')
            ->where('due_date', '<', now())
            ->whereNotNull('due_date')
            ->count();

        // Total pending debt amount
        $totalPendingDebt = $this->calculateTotalPendingDebts($exchangeService, $baseCurrency);

        return [
            Stat::make('Monthly Income', $exchangeService->formatMoney($monthlyIncome, $baseCurrency))
                ->description('Income this month')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Monthly Expenses', $exchangeService->formatMoney($monthlyExpenses, $baseCurrency))
                ->description('Expenses this month')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger'),

            Stat::make('Net Income', $exchangeService->formatMoney($netIncome, $baseCurrency))
                ->description($netIncome >= 0 ? 'Profit this month' : 'Loss this month')
                ->descriptionIcon($netIncome >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($netIncome >= 0 ? 'success' : 'danger'),

            Stat::make('Pending Debts', $pendingDebts)
                ->description($overdueDebts > 0 ? "{$overdueDebts} overdue" : 'All current')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($overdueDebts > 0 ? 'danger' : 'warning'),

            Stat::make('Total Debt', $exchangeService->formatMoney($totalPendingDebt, $baseCurrency))
                ->description('Outstanding debt amount')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning'),
        ];
    }

    private function calculateMonthlyTotal(string $model, ExchangeRateService $exchangeService, string $baseCurrency): float
    {
        $records = $model::whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->get();

        $total = 0;
        foreach ($records as $record) {
            $convertedAmount = $exchangeService->convert(
                $record->amount,
                $record->currency->value,
                $baseCurrency
            );
            $total += $convertedAmount;
        }

        return $total;
    }

    private function calculateTotalPendingDebts(ExchangeRateService $exchangeService, string $baseCurrency): float
    {
        $debts = Debt::where('status', 'pending')->get();

        $total = 0;
        foreach ($debts as $debt) {
            $convertedAmount = $exchangeService->convert(
                $debt->amount,
                $debt->currency->value,
                $baseCurrency
            );
            $total += $convertedAmount;
        }

        return $total;
    }
} 