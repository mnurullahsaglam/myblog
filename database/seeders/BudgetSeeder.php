<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Debt;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Income;
use App\Models\IncomeCategory;
use Illuminate\Database\Seeder;

class BudgetSeeder extends Seeder
{
    public function run(): void
    {
        $incomeCategories = $this->createIncomeCategories();
        $expenseCategories = $this->createExpenseCategories();

        $this->createDebts();
        $this->createIncomes($incomeCategories);
        $this->createExpenses($expenseCategories);
    }

    /**
     * @return array<int, IncomeCategory>
     */
    private function createIncomeCategories(): array
    {
        return collect([
            ['name' => 'Freelance Work', 'description' => 'Income from freelance projects', 'color' => '#10B981'],
            ['name' => 'Client Payments', 'description' => 'Payments from regular clients', 'color' => '#3B82F6'],
            ['name' => 'Investment Returns', 'description' => 'Returns from investments', 'color' => '#8B5CF6'],
            ['name' => 'Other Income', 'description' => 'Miscellaneous income sources', 'color' => '#6B7280'],
        ])->map(fn (array $category): IncomeCategory => IncomeCategory::create($category))->all();
    }

    /**
     * @return array<int, ExpenseCategory>
     */
    private function createExpenseCategories(): array
    {
        return collect([
            ['name' => 'Office Supplies', 'description' => 'Office equipment and supplies', 'color' => '#EF4444'],
            ['name' => 'Software & Tools', 'description' => 'Software subscriptions and tools', 'color' => '#F59E0B'],
            ['name' => 'Marketing', 'description' => 'Marketing and advertising expenses', 'color' => '#EC4899'],
            ['name' => 'Travel', 'description' => 'Business travel expenses', 'color' => '#06B6D4'],
            ['name' => 'Utilities', 'description' => 'Internet, phone, electricity', 'color' => '#84CC16'],
            ['name' => 'Food & Dining', 'description' => 'Meals and dining expenses', 'color' => '#F97316'],
        ])->map(fn (array $category): ExpenseCategory => ExpenseCategory::create($category))->all();
    }

    private function createDebts(): void
    {
        $debts = [
            [
                'creditor_name' => 'Tech Solutions Ltd',
                'creditor_type' => 'institute',
                'amount' => 5000.00,
                'currency' => 'TRY',
                'due_date' => now()->addDays(15),
                'status' => 'pending',
                'description' => 'Payment for web development services',
                'date' => now()->subDays(20),
            ],
            [
                'creditor_name' => 'John Smith',
                'creditor_type' => 'person',
                'amount' => 500.00,
                'currency' => 'USD',
                'due_date' => now()->addDays(30),
                'status' => 'pending',
                'description' => 'Borrowed money for equipment',
                'date' => now()->subDays(10),
            ],
            [
                'creditor_name' => 'Design Agency',
                'creditor_type' => 'institute',
                'amount' => 2000.00,
                'currency' => 'TRY',
                'due_date' => now()->subDays(5),
                'status' => 'pending',
                'description' => 'Logo design and branding work',
                'date' => now()->subDays(45),
            ],
            [
                'creditor_name' => 'Northwind Studio',
                'creditor_type' => 'institute',
                'amount' => 1250.00,
                'currency' => 'GBP',
                'due_date' => now()->addDays(60),
                'status' => 'paid',
                'description' => 'Retainer settled in full',
                'date' => now()->subMonths(2),
            ],
        ];

        foreach ($debts as $debtData) {
            Debt::create($debtData);
        }
    }

    /**
     * @param  array<int, IncomeCategory>  $categories
     */
    private function createIncomes(array $categories): void
    {
        $clients = Client::query()->orderBy('id')->get()->all();

        $incomes = [
            ['category' => 0, 'client' => 0, 'amount' => 15000.00, 'currency' => 'TRY', 'description' => 'Web development project payment', 'date' => now()->subDays(5)],
            ['category' => 1, 'client' => 1, 'amount' => 2500.00, 'currency' => 'USD', 'description' => 'Consulting work for international client', 'date' => now()->subDays(10)],
            ['category' => 1, 'client' => 2, 'amount' => 3200.00, 'currency' => 'EUR', 'description' => 'Retainer for the Berlin engagement', 'date' => now()->subDays(12)],
            ['category' => 2, 'client' => null, 'amount' => 750.00, 'currency' => 'EUR', 'description' => 'Investment dividend payment', 'date' => now()->subDays(15)],
            ['category' => 2, 'client' => null, 'amount' => 10.50, 'currency' => 'XAU', 'description' => 'Gold sale from investment portfolio', 'date' => now()->subDays(20)],
            ['category' => 2, 'client' => null, 'amount' => 250.75, 'currency' => 'XAG', 'description' => 'Silver sale from precious metals collection', 'date' => now()->subDays(25)],
            ['category' => 3, 'client' => null, 'amount' => 1400.00, 'currency' => 'TRY', 'description' => 'Refund from a cancelled subscription', 'date' => now()->subDays(33)],
        ];

        foreach ($incomes as $incomeData) {
            Income::create([
                'client_id' => $incomeData['client'] === null ? null : $clients[$incomeData['client']]->id,
                'income_category_id' => $categories[$incomeData['category']]->id,
                'amount' => $incomeData['amount'],
                'currency' => $incomeData['currency'],
                'description' => $incomeData['description'],
                'date' => $incomeData['date'],
            ]);
        }
    }

    /**
     * @param  array<int, ExpenseCategory>  $categories
     */
    private function createExpenses(array $categories): void
    {
        $expenses = [
            ['category' => 0, 'amount' => 1200.00, 'currency' => 'TRY', 'description' => 'New laptop for development work', 'date' => now()->subDays(3)],
            ['category' => 1, 'amount' => 99.99, 'currency' => 'USD', 'description' => 'Adobe Creative Suite subscription', 'date' => now()->subDays(7)],
            ['category' => 1, 'amount' => 229.00, 'currency' => 'USD', 'description' => 'PhpStorm licence renewal', 'date' => now()->subDays(15)],
            ['category' => 4, 'amount' => 450.00, 'currency' => 'TRY', 'description' => 'Internet and phone bills', 'date' => now()->subDays(12)],
            ['category' => 5, 'amount' => 250.00, 'currency' => 'TRY', 'description' => 'Client dinner meeting', 'date' => now()->subDays(8)],
            ['category' => 3, 'amount' => 680.00, 'currency' => 'EUR', 'description' => 'Flights for the Berlin kickoff', 'date' => now()->subDays(29)],
            ['category' => 2, 'amount' => 5.25, 'currency' => 'XAU', 'description' => 'Gold purchase for investment (5.25 grams)', 'date' => now()->subDays(18)],
            ['category' => 2, 'amount' => 100.00, 'currency' => 'XAG', 'description' => 'Silver purchase for portfolio (100 grams)', 'date' => now()->subDays(22)],
        ];

        foreach ($expenses as $expenseData) {
            Expense::create([
                'expense_category_id' => $categories[$expenseData['category']]->id,
                'amount' => $expenseData['amount'],
                'currency' => $expenseData['currency'],
                'description' => $expenseData['description'],
                'date' => $expenseData['date'],
            ]);
        }
    }
}
