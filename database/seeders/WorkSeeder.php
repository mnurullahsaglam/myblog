<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Repository;
use App\Models\Task;
use App\Models\WakaTimeSummary;
use Illuminate\Database\Seeder;

class WorkSeeder extends Seeder
{
    public function run(): void
    {
        $clients = $this->createClients();

        $this->createInvoices($clients);

        $projects = $this->createProjects();
        $repositories = $this->createRepositories($projects);

        $this->createTasks($repositories);
        $this->createWakaTimeSummaries();
    }

    /**
     * @return array<int, Client>
     */
    private function createClients(): array
    {
        return collect([
            [
                'title' => 'Tech Solutions Ltd',
                'email' => 'billing@techsolutions.test',
                'country' => 'Türkiye',
                'address' => 'Levent, İstanbul',
                'tax_no' => '1234567890',
            ],
            [
                'title' => 'Northwind Studio',
                'email' => 'accounts@northwind.test',
                'country' => 'United Kingdom',
                'address' => 'Shoreditch, London',
                'tax_no' => 'GB998877665',
            ],
            [
                'title' => 'Blauhaus GmbH',
                'email' => 'rechnung@blauhaus.test',
                'country' => 'Germany',
                'address' => 'Kreuzberg, Berlin',
                'tax_no' => 'DE441122334',
            ],
        ])->map(fn (array $client): Client => Client::create($client))->all();
    }

    /**
     * @param  array<int, Client>  $clients
     */
    private function createInvoices(array $clients): void
    {
        $invoices = [
            ['amount' => 15000, 'tax_rate' => 20, 'currency' => 'TRY', 'issued_at' => now()->subDays(40)],
            ['amount' => 8400, 'tax_rate' => 20, 'currency' => 'TRY', 'issued_at' => now()->subDays(26)],
            ['amount' => 2500, 'tax_rate' => 0, 'currency' => 'USD', 'issued_at' => now()->subDays(18)],
            ['amount' => 3200, 'tax_rate' => 19, 'currency' => 'EUR', 'issued_at' => now()->subDays(9)],
            ['amount' => 1800, 'tax_rate' => 20, 'currency' => 'GBP', 'issued_at' => now()->subDays(2)],
        ];

        foreach ($invoices as $index => $invoiceData) {
            $client = $clients[$index % count($clients)];
            $taxAmount = (int) round($invoiceData['amount'] * $invoiceData['tax_rate'] / 100);
            $number = sprintf('FTR-%d-%03d', now()->year, $index + 1);

            Invoice::create([
                'client_id' => $client->id,
                'invoice_number' => $number,
                'issued_at' => $invoiceData['issued_at'],
                'tax_rate' => $invoiceData['tax_rate'],
                'tax_amount' => $taxAmount,
                'amount' => $invoiceData['amount'],
                'total_amount' => $invoiceData['amount'] + $taxAmount,
                'currency' => $invoiceData['currency'],
                'invoice' => 'invoices/'.mb_strtolower($number).'.pdf',
            ]);
        }
    }

    /**
     * @return array<int, Project>
     */
    private function createProjects(): array
    {
        return collect([
            ['name' => 'Blog Development', 'due_date' => now()->addMonth()],
            ['name' => 'Laravel Enhancement Project', 'due_date' => now()->addMonths(2)],
            ['name' => 'iPhone Client', 'due_date' => now()->addMonths(4)],
        ])->map(fn (array $project): Project => Project::create($project))->all();
    }

    /**
     * @param  array<int, Project>  $projects
     * @return array<int, Repository>
     */
    private function createRepositories(array $projects): array
    {
        $repositories = [
            [
                'project_id' => $projects[0]->id,
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
                'project_id' => $projects[1]->id,
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
            [
                'project_id' => $projects[2]->id,
                'name' => 'myblog-mobile',
                'full_name' => 'nurullah/myblog-mobile',
                'owner' => 'nurullah',
                'description' => 'The native Swift client for the panel',
                'visibility' => 'private',
                'github_url' => 'https://github.com/nurullah/myblog-mobile',
                'github_id' => '12347',
                'default_branch' => 'main',
                'language' => 'Swift',
                'stars_count' => 0,
                'forks_count' => 0,
                'issues_count' => 2,
                'is_active' => true,
                'github_created_at' => now()->subWeeks(2),
                'github_updated_at' => now()->subHours(6),
            ],
        ];

        return collect($repositories)->map(fn (array $repository): Repository => Repository::create($repository))->all();
    }

    /**
     * @param  array<int, Repository>  $repositories
     */
    private function createTasks(array $repositories): void
    {
        $first = $repositories[0];
        $mobile = $repositories[2];

        $tasks = [
            [
                'repository' => $first,
                'title' => 'Add GitHub integration for issue tracking',
                'description' => 'Implement GitHub API integration to sync issues with our Kanban board',
                'status' => 'in_progress',
                'sort_order' => 1,
                'github_issue_number' => '1',
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
                'repository' => $first,
                'title' => 'Fix responsive design on mobile devices',
                'description' => 'The blog layout breaks on mobile devices. Need to fix CSS media queries.',
                'status' => 'todo',
                'sort_order' => 2,
                'github_issue_number' => '2',
                'github_issue_state' => 'open',
                'github_issue_labels' => [
                    ['name' => 'bug', 'color' => 'd73a49'],
                    ['name' => 'css', 'color' => 'ffc0cb'],
                ],
                'github_created_at' => now()->subDays(3),
                'github_updated_at' => now()->subDays(3),
            ],
            [
                'repository' => $first,
                'title' => 'Add SEO meta tags to all pages',
                'description' => 'Implement proper SEO meta tags for better search engine optimization',
                'status' => 'completed',
                'sort_order' => 3,
                'github_issue_number' => '3',
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
            [
                'repository' => $mobile,
                'title' => 'Hand-build the expense and bill forms',
                'description' => 'The generated layout puts amount and currency on separate rows.',
                'status' => 'completed',
                'sort_order' => 1,
                'github_issue_number' => '1',
                'github_issue_state' => 'closed',
                'github_issue_labels' => [['name' => 'ios', 'color' => '1d76db']],
                'github_assignee' => 'nurullah',
                'github_created_at' => now()->subWeek(),
                'github_updated_at' => now()->subDays(2),
                'github_closed_at' => now()->subDays(2),
            ],
            [
                'repository' => $mobile,
                'title' => 'Add a runnable Xcode app target',
                'description' => 'Six manual steps in docs/limitations.md still have to be done by hand.',
                'status' => 'todo',
                'sort_order' => 2,
                'github_issue_number' => '2',
                'github_issue_state' => 'open',
                'github_issue_labels' => [['name' => 'ios', 'color' => '1d76db']],
                'github_created_at' => now()->subDays(4),
                'github_updated_at' => now()->subDays(4),
            ],
        ];

        foreach ($tasks as $taskData) {
            $repository = $taskData['repository'];

            unset($taskData['repository']);

            Task::create($taskData + [
                'project_id' => $repository->project_id,
                'repository_id' => $repository->id,
                'github_issue_url' => $repository->github_url.'/issues/'.$taskData['github_issue_number'],
            ]);
        }
    }

    private function createWakaTimeSummaries(): void
    {
        $languages = [
            ['PHP', 0.46],
            ['Vue', 0.24],
            ['Swift', 0.16],
            ['Blade', 0.09],
            ['JSON', 0.05],
        ];

        $editors = [['PhpStorm', 0.72], ['VS Code', 0.28]];
        $projects = [['myblog', 0.68], ['myblog-mobile', 0.32]];

        foreach (range(13, 0) as $offset) {
            $date = now()->subDays($offset);
            $totalSeconds = random_int(4800, 27000);

            $summary = WakaTimeSummary::create([
                'date' => $date->toDateString(),
                'total_seconds' => $totalSeconds,
                'raw' => ['grand_total' => ['total_seconds' => $totalSeconds]],
            ]);

            foreach ([['language', $languages], ['editor', $editors], ['project', $projects]] as [$type, $breakdown]) {
                foreach ($breakdown as [$name, $share]) {
                    $summary->entries()->create([
                        'type' => $type,
                        'name' => $name,
                        'seconds' => (int) round($totalSeconds * $share),
                        'percent' => round($share * 100, 2),
                    ]);
                }
            }
        }
    }
}
