<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Repository;
use App\Models\Task;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
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
        $token = $this->option('token') ?? config('services.github.personal_access_token');
        $this->githubToken = is_string($token) ? $token : '';

        if ($this->githubToken === '') {
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

    /**
     * @return Collection<int, Repository>
     */
    private function selectRepositories(): Collection
    {
        /** @var Collection<int, Repository> $repositories */
        $repositories = Repository::where('is_active', true)->get();

        if ($repositories->isEmpty()) {
            $this->error('No active repositories found.');

            return $repositories;
        }

        /** @var array<int, string> $choices */
        $choices = $repositories->mapWithKeys(function (Repository $repo): array {
            return [$repo->id => "$repo->name ($repo->owner)"];
        })->toArray();

        $selectedIds = $this->choice(
            'Select repositories to sync (multiple allowed)',
            $choices,
            null,
            null,
            true
        );

        $selectedIds = is_array($selectedIds) ? $selectedIds : [$selectedIds];

        $selectedIds = array_values(array_filter($selectedIds, 'is_string'));

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

        } catch (Exception $e) {
            $this->error("   ❌ Failed to sync {$repository->full_name}: {$e->getMessage()}");
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchGitHubIssues(Repository $repository): array
    {
        $url = "https://api.github.com/repos/{$repository->full_name}/issues";
        $appName = config('app.name', 'Laravel-App');
        $allIssues = [];
        $page = 1;

        do {
            $response = Http::withHeaders([
                'Authorization' => "token {$this->githubToken}",
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => is_string($appName) ? $appName : 'Laravel-App',
            ])->get($url, [
                'state' => 'all',
                'per_page' => 100,
                'page' => $page,
                'sort' => 'updated',
                'direction' => 'desc',
            ]);

            if (! $response->successful()) {
                throw new Exception("GitHub API error: {$response->status()} - {$response->body()}");
            }

            /** @var array<int, array<string, mixed>> $issues */
            $issues = $response->json();

            // Filter out pull requests (GitHub API includes PRs in issues endpoint)
            $issuesOnly = array_filter($issues, function (array $issue): bool {
                return ! isset($issue['pull_request']);
            });

            $allIssues = array_merge($allIssues, $issuesOnly);
            $page++;

        } while (count($issues) === 100); // Continue if full page returned

        return $allIssues;
    }

    /**
     * @param  array<string, mixed>  $issueData
     */
    private function createOrUpdateTask(Repository $repository, array $issueData): bool
    {
        $existingTask = Task::where('github_issue_number', $issueData['number'])
            ->where('repository_id', $repository->id)
            ->first();

        $state = is_string($issueData['state']) ? $issueData['state'] : '';
        $assignee = $issueData['assignee'] ?? null;
        $assigneeLogin = is_array($assignee) && isset($assignee['login']) ? $assignee['login'] : null;

        $taskData = [
            'repository_id' => $repository->id,
            'title' => $issueData['title'],
            'description' => $issueData['body'] ?? '',
            'status' => $this->mapGitHubStateToStatus($state),
            'github_issue_number' => $issueData['number'],
            'github_issue_url' => $issueData['html_url'],
            'github_issue_state' => $state,
            'github_issue_labels' => $issueData['labels'] ?? [],
            'github_assignee' => $assigneeLogin,
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
