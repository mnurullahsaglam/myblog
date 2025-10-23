<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Repository;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()
            ->create([
                'name' => config('app.admin_name'),
                'email' => config('app.admin_email'),
            ]);

        // Create sample projects
        $projects = [
            [
                'name' => 'Blog Development',
                'due_date' => now()->addMonth(),
            ],
            [
                'name' => 'Laravel Enhancement Project',
                'due_date' => now()->addMonths(2),
            ],
        ];

        foreach ($projects as $projectData) {
            Project::create($projectData);
        }

        // Create sample repositories
        $firstProject = Project::first();
        $secondProject = Project::skip(1)->first();

        $repositories = [
            [
                'project_id' => $firstProject->id,
                'name' => 'myblog',
                'full_name' => 'nurullah/myblog',
                'owner' => 'nurullah',
                'description' => 'My personal blog built with Laravel',
                'visibility' => 'public',
                'github_url' => 'https://github.com/nurullah/myblog',
                'github_id' => '12345',
                'default_branch' => 'main',
                'language' => 'PHP',
                'stars_count' => 5,
                'forks_count' => 2,
                'issues_count' => 0,
                'is_active' => true,
                'github_created_at' => now()->subMonths(6),
                'github_updated_at' => now()->subDays(7),
            ],
            [
                'project_id' => $secondProject->id,
                'name' => 'awesome-laravel-project',
                'full_name' => 'nurullah/awesome-laravel-project',
                'owner' => 'nurullah',
                'description' => 'An awesome Laravel project with Filament admin',
                'visibility' => 'private',
                'github_url' => 'https://github.com/nurullah/awesome-laravel-project',
                'github_id' => '12346',
                'default_branch' => 'main',
                'language' => 'PHP',
                'stars_count' => 12,
                'forks_count' => 3,
                'issues_count' => 0,
                'is_active' => true,
                'github_created_at' => now()->subMonths(3),
                'github_updated_at' => now()->subDays(2),
            ],
        ];

        foreach ($repositories as $repoData) {
            Repository::create($repoData);
        }

        // Create sample tasks for the first repository
        $firstRepo = Repository::first();
        if ($firstRepo) {
            $tasks = [
                [
                    'project_id' => $firstRepo->project_id,
                    'repository_id' => $firstRepo->id,
                    'title' => 'Add GitHub integration for issue tracking',
                    'description' => 'Implement GitHub API integration to sync issues with our Kanban board',
                    'status' => 'in_progress',
                    'sort_order' => 1,
                    'github_issue_number' => '1',
                    'github_issue_url' => 'https://github.com/nurullah/myblog/issues/1',
                    'github_issue_state' => 'open',
                    'github_issue_labels' => [
                        ['name' => 'enhancement', 'color' => 'a2eeef'],
                        ['name' => 'good first issue', 'color' => '7057ff'],
                    ],
                    'github_assignee' => 'nurullah',
                    'github_created_at' => now()->subDays(5),
                    'github_updated_at' => now()->subDays(1),
                ],
                [
                    'project_id' => $firstRepo->project_id,
                    'repository_id' => $firstRepo->id,
                    'title' => 'Fix responsive design on mobile devices',
                    'description' => 'The blog layout breaks on mobile devices. Need to fix CSS media queries.',
                    'status' => 'todo',
                    'sort_order' => 2,
                    'github_issue_number' => '2',
                    'github_issue_url' => 'https://github.com/nurullah/myblog/issues/2',
                    'github_issue_state' => 'open',
                    'github_issue_labels' => [
                        ['name' => 'bug', 'color' => 'd73a49'],
                        ['name' => 'css', 'color' => 'ffc0cb'],
                    ],
                    'github_created_at' => now()->subDays(3),
                    'github_updated_at' => now()->subDays(3),
                ],
                [
                    'project_id' => $firstRepo->project_id,
                    'repository_id' => $firstRepo->id,
                    'title' => 'Add SEO meta tags to all pages',
                    'description' => 'Implement proper SEO meta tags for better search engine optimization',
                    'status' => 'completed',
                    'sort_order' => 3,
                    'github_issue_number' => '3',
                    'github_issue_url' => 'https://github.com/nurullah/myblog/issues/3',
                    'github_issue_state' => 'closed',
                    'github_issue_labels' => [
                        ['name' => 'enhancement', 'color' => 'a2eeef'],
                        ['name' => 'seo', 'color' => '0052cc'],
                    ],
                    'github_assignee' => 'nurullah',
                    'github_created_at' => now()->subDays(10),
                    'github_updated_at' => now()->subDays(7),
                    'github_closed_at' => now()->subDays(7),
                ],
            ];

            foreach ($tasks as $taskData) {
                Task::create($taskData);
            }
        }

        // Create sample budget data
        $this->createBudgetData();
    }

    private function createBudgetData(): void
    {
        // Create income categories
        $incomeCategories = [
            ['name' => 'Freelance Work', 'description' => 'Income from freelance projects', 'color' => '#10B981'],
            ['name' => 'Client Payments', 'description' => 'Payments from regular clients', 'color' => '#3B82F6'],
            ['name' => 'Investment Returns', 'description' => 'Returns from investments', 'color' => '#8B5CF6'],
            ['name' => 'Other Income', 'description' => 'Miscellaneous income sources', 'color' => '#6B7280'],
        ];

        foreach ($incomeCategories as $categoryData) {
            \App\Models\IncomeCategory::create($categoryData);
        }

        // Create expense categories
        $expenseCategories = [
            ['name' => 'Office Supplies', 'description' => 'Office equipment and supplies', 'color' => '#EF4444'],
            ['name' => 'Software & Tools', 'description' => 'Software subscriptions and tools', 'color' => '#F59E0B'],
            ['name' => 'Marketing', 'description' => 'Marketing and advertising expenses', 'color' => '#EC4899'],
            ['name' => 'Travel', 'description' => 'Business travel expenses', 'color' => '#06B6D4'],
            ['name' => 'Utilities', 'description' => 'Internet, phone, electricity', 'color' => '#84CC16'],
            ['name' => 'Food & Dining', 'description' => 'Meals and dining expenses', 'color' => '#F97316'],
        ];

        foreach ($expenseCategories as $categoryData) {
            \App\Models\ExpenseCategory::create($categoryData);
        }

        // Create sample debts
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
                'due_date' => now()->subDays(5), // Overdue
                'status' => 'pending',
                'description' => 'Logo design and branding work',
                'date' => now()->subDays(45),
            ],
        ];

        foreach ($debts as $debtData) {
            \App\Models\Debt::create($debtData);
        }

        // Create sample incomes
        $firstClient = \App\Models\Client::first();
        $incomeCategory = \App\Models\IncomeCategory::first();

        $incomes = [
            [
                'client_id' => $firstClient?->id,
                'income_category_id' => $incomeCategory?->id,
                'amount' => 15000.00,
                'currency' => 'TRY',
                'description' => 'Web development project payment',
                'date' => now()->subDays(5),
            ],
            [
                'income_category_id' => $incomeCategory?->id,
                'amount' => 2500.00,
                'currency' => 'USD',
                'description' => 'Consulting work for international client',
                'date' => now()->subDays(10),
            ],
            [
                'income_category_id' => \App\Models\IncomeCategory::skip(2)->first()?->id,
                'amount' => 750.00,
                'currency' => 'EUR',
                'description' => 'Investment dividend payment',
                'date' => now()->subDays(15),
            ],
            [
                'income_category_id' => \App\Models\IncomeCategory::skip(2)->first()?->id,
                'amount' => 10.50,
                'currency' => 'XAU',
                'description' => 'Gold sale from investment portfolio',
                'date' => now()->subDays(20),
            ],
            [
                'income_category_id' => \App\Models\IncomeCategory::skip(2)->first()?->id,
                'amount' => 250.75,
                'currency' => 'XAG',
                'description' => 'Silver sale from precious metals collection',
                'date' => now()->subDays(25),
            ],
        ];

        foreach ($incomes as $incomeData) {
            \App\Models\Income::create($incomeData);
        }

        // Create sample expenses
        $expenseCategory = \App\Models\ExpenseCategory::first();

        $expenses = [
            [
                'expense_category_id' => $expenseCategory?->id,
                'amount' => 1200.00,
                'currency' => 'TRY',
                'description' => 'New laptop for development work',
                'date' => now()->subDays(3),
            ],
            [
                'expense_category_id' => \App\Models\ExpenseCategory::skip(1)->first()?->id,
                'amount' => 99.99,
                'currency' => 'USD',
                'description' => 'Adobe Creative Suite subscription',
                'date' => now()->subDays(7),
            ],
            [
                'expense_category_id' => \App\Models\ExpenseCategory::skip(4)->first()?->id,
                'amount' => 450.00,
                'currency' => 'TRY',
                'description' => 'Internet and phone bills',
                'date' => now()->subDays(12),
            ],
            [
                'expense_category_id' => \App\Models\ExpenseCategory::skip(5)->first()?->id,
                'amount' => 250.00,
                'currency' => 'TRY',
                'description' => 'Client dinner meeting',
                'date' => now()->subDays(8),
            ],
            [
                'expense_category_id' => \App\Models\ExpenseCategory::skip(2)->first()?->id,
                'amount' => 5.25,
                'currency' => 'XAU',
                'description' => 'Gold purchase for investment (5.25 grams)',
                'date' => now()->subDays(18),
            ],
            [
                'expense_category_id' => \App\Models\ExpenseCategory::skip(2)->first()?->id,
                'amount' => 100.00,
                'currency' => 'XAG',
                'description' => 'Silver purchase for portfolio (100 grams)',
                'date' => now()->subDays(22),
            ],
        ];

        foreach ($expenses as $expenseData) {
            \App\Models\Expense::create($expenseData);
        }
    }
}
