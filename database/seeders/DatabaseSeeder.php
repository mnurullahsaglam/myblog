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
    }
}
