<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Client;
use App\Models\Debt;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Income;
use App\Models\IncomeCategory;
use App\Models\Post;
use App\Models\Project;
use App\Models\Repository;
use App\Models\Setting;
use App\Models\Task;
use App\Models\User;
use App\Models\UtilityAccount;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()
            ->admin()
            ->create([
                'name' => config('app.admin_name'),
                'email' => config('app.admin_email'),
            ]);

        $this->createMember();

        $this->createSettings();
        $this->createContent();
        $this->createUtilities();

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

        $firstProject = Project::query()->firstOrFail();
        $secondProject = Project::query()->skip(1)->firstOrFail();

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
                'description' => 'An awesome Laravel project with an Inertia admin panel',
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

        $this->createBudgetData();
    }

    private function createBudgetData(): void
    {
        $incomeCategories = [
            ['name' => 'Freelance Work', 'description' => 'Income from freelance projects', 'color' => '#10B981'],
            ['name' => 'Client Payments', 'description' => 'Payments from regular clients', 'color' => '#3B82F6'],
            ['name' => 'Investment Returns', 'description' => 'Returns from investments', 'color' => '#8B5CF6'],
            ['name' => 'Other Income', 'description' => 'Miscellaneous income sources', 'color' => '#6B7280'],
        ];

        foreach ($incomeCategories as $categoryData) {
            IncomeCategory::create($categoryData);
        }

        $expenseCategories = [
            ['name' => 'Office Supplies', 'description' => 'Office equipment and supplies', 'color' => '#EF4444'],
            ['name' => 'Software & Tools', 'description' => 'Software subscriptions and tools', 'color' => '#F59E0B'],
            ['name' => 'Marketing', 'description' => 'Marketing and advertising expenses', 'color' => '#EC4899'],
            ['name' => 'Travel', 'description' => 'Business travel expenses', 'color' => '#06B6D4'],
            ['name' => 'Utilities', 'description' => 'Internet, phone, electricity', 'color' => '#84CC16'],
            ['name' => 'Food & Dining', 'description' => 'Meals and dining expenses', 'color' => '#F97316'],
        ];

        foreach ($expenseCategories as $categoryData) {
            ExpenseCategory::create($categoryData);
        }

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
        ];

        foreach ($debts as $debtData) {
            Debt::create($debtData);
        }

        $firstClient = Client::first();
        $incomeCategory = IncomeCategory::first();

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
                'income_category_id' => IncomeCategory::skip(2)->first()?->id,
                'amount' => 750.00,
                'currency' => 'EUR',
                'description' => 'Investment dividend payment',
                'date' => now()->subDays(15),
            ],
            [
                'income_category_id' => IncomeCategory::skip(2)->first()?->id,
                'amount' => 10.50,
                'currency' => 'XAU',
                'description' => 'Gold sale from investment portfolio',
                'date' => now()->subDays(20),
            ],
            [
                'income_category_id' => IncomeCategory::skip(2)->first()?->id,
                'amount' => 250.75,
                'currency' => 'XAG',
                'description' => 'Silver sale from precious metals collection',
                'date' => now()->subDays(25),
            ],
        ];

        foreach ($incomes as $incomeData) {
            Income::create($incomeData);
        }

        $expenseCategory = ExpenseCategory::first();

        $expenses = [
            [
                'expense_category_id' => $expenseCategory?->id,
                'amount' => 1200.00,
                'currency' => 'TRY',
                'description' => 'New laptop for development work',
                'date' => now()->subDays(3),
            ],
            [
                'expense_category_id' => ExpenseCategory::skip(1)->first()?->id,
                'amount' => 99.99,
                'currency' => 'USD',
                'description' => 'Adobe Creative Suite subscription',
                'date' => now()->subDays(7),
            ],
            [
                'expense_category_id' => ExpenseCategory::skip(4)->first()?->id,
                'amount' => 450.00,
                'currency' => 'TRY',
                'description' => 'Internet and phone bills',
                'date' => now()->subDays(12),
            ],
            [
                'expense_category_id' => ExpenseCategory::skip(5)->first()?->id,
                'amount' => 250.00,
                'currency' => 'TRY',
                'description' => 'Client dinner meeting',
                'date' => now()->subDays(8),
            ],
            [
                'expense_category_id' => ExpenseCategory::skip(2)->first()?->id,
                'amount' => 5.25,
                'currency' => 'XAU',
                'description' => 'Gold purchase for investment (5.25 grams)',
                'date' => now()->subDays(18),
            ],
            [
                'expense_category_id' => ExpenseCategory::skip(2)->first()?->id,
                'amount' => 100.00,
                'currency' => 'XAG',
                'description' => 'Silver purchase for portfolio (100 grams)',
                'date' => now()->subDays(22),
            ],
        ];

        foreach ($expenses as $expenseData) {
            Expense::create($expenseData);
        }
    }

    private function createMember(): void
    {
        $email = config('app.member_email');

        if (! is_string($email) || $email === '') {
            return;
        }

        User::factory()
            ->member()
            ->create([
                'name' => config('app.member_name'),
                'email' => $email,
            ]);
    }

    private function createSettings(): void
    {
        Setting::set('appearance', 'accent', 'khaki');
        Setting::set('appearance', 'color_scheme', 'dark');
        Setting::set('site_info', 'title', 'OP//SHELL');
        Setting::set('site_info', 'description', 'A developer blog and personal admin panel.');
        Setting::set('meta', 'meta_keywords', json_encode(['laravel', 'php', 'rust']), 'json');
    }

    private function createContent(): void
    {
        $categories = collect(['Laravel', 'PHP', 'Rust'])
            ->map(fn (string $name): Category => Category::create(['name' => $name]));

        $posts = [
            ['title' => 'Learning Rust', 'content' => 'Notes from starting out with Rust.'],
            ['title' => 'Rewriting the admin panel', 'content' => 'Why the panel was rewritten in Inertia and Vue.'],
            ['title' => 'Static analysis at level max', 'content' => 'What PHPStan found.'],
        ];

        foreach ($posts as $index => $postData) {
            Post::create($postData)->categories()->attach($categories[$index % $categories->count()]);
        }
    }

    private function createUtilities(): void
    {
        $accounts = [
            ['type' => 'electricity', 'provider' => 'Enerjisa', 'label' => 'Ev elektrik', 'subscriber_no' => '4001234567'],
            ['type' => 'natural_gas', 'provider' => 'İGDAŞ', 'label' => 'Ev doğalgaz', 'subscriber_no' => '9007654321'],
            ['type' => 'water', 'provider' => 'İSKİ', 'label' => 'Ev su', 'subscriber_no' => '5551112222'],
            ['type' => 'internet', 'provider' => 'Türk Telekom', 'label' => 'Ev internet', 'subscriber_no' => null],
            ['type' => 'phone', 'provider' => 'Turkcell', 'label' => 'İş telefonu', 'subscriber_no' => null],
        ];

        foreach ($accounts as $accountData) {
            $account = UtilityAccount::create($accountData + ['is_active' => true]);

            $bill = $account->bills()->create([
                'bill_number' => 'FTR-'.random_int(1000000, 9999999),
                'period_start' => now()->startOfMonth()->toDateString(),
                'period_end' => now()->endOfMonth()->toDateString(),
                'issued_at' => now()->toDateString(),
                'due_date' => now()->addDays(12)->toDateString(),
                'meter_start' => $account->type->hasMeter() ? 1000 : null,
                'meter_end' => $account->type->hasMeter() ? 1240 : null,
                'total_amount' => 266.00,
                'currency' => 'TRY',
            ]);

            $bill->lines()->createMany([
                ['label' => 'Hizmet bedeli', 'amount' => 221.50, 'sort_order' => 0],
                ['label' => 'KDV', 'amount' => 44.50, 'sort_order' => 1],
            ]);
        }
    }
}
