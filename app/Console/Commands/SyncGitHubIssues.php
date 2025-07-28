<?php

namespace App\Console\Commands;

use App\Models\Repository;
use App\Models\Task;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncGitHubIssues extends Command
{
    protected $signature = 'github:sync-issues 
                            {--repository= : Specific repository ID to sync}
                            {--all : Sync all active repositories}
                            {--token= : GitHub personal access token}';

    protected $description = 'Sync GitHub issues from repositories to tasks';

    private string $githubToken;

    public function handle(): int
    {
        $this->githubToken = $this->option('token') ?? config('services.github.personal_access_token');

        if (!$this->githubToken) {
            $this->error('GitHub token is required. Set GITHUB_TOKEN environment variable or use --token option.');
            return self::FAILURE;
        }

        if ($this->option('all')) {
            $repositories = Repository::where('is_active', true)->get();
        } elseif ($repositoryId = $this->option('repository')) {
            $repositories = Repository::where('id', $repositoryId)->get();
        } else {
            $repositories = $this->selectRepositories();
        }

        if ($repositories->isEmpty()) {
            $this->info('No repositories found to sync.');
            return self::SUCCESS;
        }

        $this->info("Syncing issues from {$repositories->count()} repository(ies)...");

        foreach ($repositories as $repository) {
            $this->syncRepositoryIssues($repository);
        }

        $this->info('✅ Issue synchronization completed successfully!');
        return self::SUCCESS;
    }

    private function selectRepositories()
    {
        $repositories = Repository::where('is_active', true)->get();

        if ($repositories->isEmpty()) {
            $this->error('No active repositories found.');
            return collect();
        }

        $choices = $repositories->mapWithKeys(function ($repo) {
            return [$repo->id => "$repo->name ($repo->owner)"];
        })->toArray();

        $selectedIds = $this->choice(
            'Select repositories to sync (multiple allowed)',
            $choices,
            null,
            null,
            true
        );

        return $repositories->whereIn('id', array_keys(array_flip($selectedIds)));
    }

    private function syncRepositoryIssues(Repository $repository): void
    {
        $this->info("📁 Syncing issues for $repository->full_name...");

        try {
            $issues = $this->fetchGitHubIssues($repository);
            $syncedCount = 0;
            $updatedCount = 0;

            foreach ($issues as $issueData) {
                $wasCreated = $this->createOrUpdateTask($repository, $issueData);
                if ($wasCreated) {
                    $syncedCount++;
                } else {
                    $updatedCount++;
                }
            }

            // Update repository sync timestamp
            $repository->update([
                'last_synced_at' => now(),
                'issues_count' => count($issues),
            ]);

            $this->info("   ✅ Synced {$syncedCount} new issues, updated {$updatedCount} existing issues");

        } catch (\Exception $e) {
            $this->error("   ❌ Failed to sync {$repository->full_name}: {$e->getMessage()}");
        }
    }

    private function fetchGitHubIssues(Repository $repository): array
    {
        $url = "https://api.github.com/repos/{$repository->full_name}/issues";
        $allIssues = [];
        $page = 1;

        do {
            $response = Http::withHeaders([
                'Authorization' => "token {$this->githubToken}",
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => config('app.name', 'Laravel-App'),
            ])->get($url, [
                'state' => 'all',
                'per_page' => 100,
                'page' => $page,
                'sort' => 'updated',
                'direction' => 'desc',
            ]);

            if (!$response->successful()) {
                throw new \Exception("GitHub API error: {$response->status()} - {$response->body()}");
            }

            $issues = $response->json();

            // Filter out pull requests (GitHub API includes PRs in issues endpoint)
            $issuesOnly = array_filter($issues, function ($issue) {
                return !isset($issue['pull_request']);
            });

            $allIssues = array_merge($allIssues, $issuesOnly);
            $page++;

        } while (count($issues) === 100); // Continue if full page returned

        return $allIssues;
    }

    private function createOrUpdateTask(Repository $repository, array $issueData): bool
    {
        $existingTask = Task::where('github_issue_number', $issueData['number'])
            ->where('repository_id', $repository->id)
            ->first();

        $taskData = [
            'repository_id' => $repository->id,
            'title' => $issueData['title'],
            'description' => $issueData['body'] ?? '',
            'status' => $this->mapGitHubStateToStatus($issueData['state']),
            'github_issue_number' => $issueData['number'],
            'github_issue_url' => $issueData['html_url'],
            'github_issue_state' => $issueData['state'],
            'github_issue_labels' => $issueData['labels'] ?? [],
            'github_assignee' => $issueData['assignee']['login'] ?? null,
            'github_created_at' => $issueData['created_at'],
            'github_updated_at' => $issueData['updated_at'],
            'github_closed_at' => $issueData['closed_at'],
        ];

        if ($existingTask) {
            $existingTask->update($taskData);
            return false; // Updated existing
        } else {
            Task::create($taskData);
            return true; // Created new
        }
    }

    private function mapGitHubStateToStatus(string $githubState): string
    {
        return match ($githubState) {
            'open' => 'todo',
            'closed' => 'completed',
            default => 'todo',
        };
    }
} 