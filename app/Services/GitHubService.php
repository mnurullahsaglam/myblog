<?php

namespace App\Services;

use App\Models\Task;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GitHubService
{
    private string $token;
    private string $baseUrl = 'https://api.github.com';

    public function __construct()
    {
        $this->token = config('services.github.token') ?? env('GITHUB_TOKEN');

        if (!$this->token) {
            throw new \Exception('GitHub token is required. Set GITHUB_TOKEN environment variable.');
        }
    }

    /**
     * Update a GitHub issue with task data
     */
    public function updateIssue(Task $task): bool
    {
        if (!$task->repository || !$task->github_issue_number) {
            return false;
        }

        try {
            $repository = $task->repository;
            $url = "{$this->baseUrl}/repos/{$repository->full_name}/issues/{$task->github_issue_number}";

            $data = [
                'title' => $task->title,
                'body' => $task->description,
                'state' => $this->mapStatusToGitHubState($task->status),
            ];

            $response = Http::withHeaders($this->getHeaders())
                ->patch($url, $data);

            if ($response->successful()) {
                Log::info("GitHub issue updated successfully", [
                    'task_id' => $task->id,
                    'issue_number' => $task->github_issue_number,
                    'repository' => $repository->full_name
                ]);
                return true;
            }

            Log::error("Failed to update GitHub issue", [
                'task_id' => $task->id,
                'response' => $response->body(),
                'status' => $response->status()
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error("GitHub API error: " . $e->getMessage(), [
                'task_id' => $task->id,
            ]);
            return false;
        }
    }

    /**
     * Map task status to GitHub issue state
     */
    private function mapStatusToGitHubState(string $status): string
    {
        return match ($status) {
            'completed' => 'closed',
            'todo', 'in_progress' => 'open',
            default => 'open',
        };
    }

    /**
     * Get GitHub API headers
     */
    private function getHeaders(): array
    {
        return [
            'Authorization' => "token {$this->token}",
            'Accept' => 'application/vnd.github.v3+json',
            'User-Agent' => config('app.name', 'Laravel-App'),
        ];
    }

    /**
     * Create a new GitHub issue from task
     */
    public function createIssue(Task $task): ?array
    {
        if (!$task->repository) {
            return null;
        }

        try {
            $repository = $task->repository;
            $url = "{$this->baseUrl}/repos/{$repository->full_name}/issues";

            $data = [
                'title' => $task->title,
                'body' => $task->description,
            ];

            $response = Http::withHeaders($this->getHeaders())
                ->post($url, $data);

            if ($response->successful()) {
                $issueData = $response->json();

                // Update task with GitHub issue data
                $task->update([
                    'github_issue_number' => $issueData['number'],
                    'github_issue_url' => $issueData['html_url'],
                    'github_issue_state' => $issueData['state'],
                    'github_created_at' => $issueData['created_at'],
                    'github_updated_at' => $issueData['updated_at'],
                ]);

                Log::info("GitHub issue created successfully", [
                    'task_id' => $task->id,
                    'issue_number' => $issueData['number'],
                    'repository' => $repository->full_name
                ]);

                return $issueData;
            }

            Log::error("Failed to create GitHub issue", [
                'task_id' => $task->id,
                'response' => $response->body(),
                'status' => $response->status()
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error("GitHub API error: " . $e->getMessage(), [
                'task_id' => $task->id,
            ]);
            return null;
        }
    }

    /**
     * Close a GitHub issue
     */
    public function closeIssue(Task $task): bool
    {
        if (!$task->repository || !$task->github_issue_number) {
            return false;
        }

        try {
            $repository = $task->repository;
            $url = "{$this->baseUrl}/repos/{$repository->full_name}/issues/{$task->github_issue_number}";

            $data = ['state' => 'closed'];

            $response = Http::withHeaders($this->getHeaders())
                ->patch($url, $data);

            if ($response->successful()) {
                $task->update([
                    'github_issue_state' => 'closed',
                    'github_closed_at' => now(),
                ]);

                return true;
            }

            return false;

        } catch (\Exception $e) {
            Log::error("GitHub API error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Reopen a GitHub issue
     */
    public function reopenIssue(Task $task): bool
    {
        if (!$task->repository || !$task->github_issue_number) {
            return false;
        }

        try {
            $repository = $task->repository;
            $url = "{$this->baseUrl}/repos/{$repository->full_name}/issues/{$task->github_issue_number}";

            $data = ['state' => 'open'];

            $response = Http::withHeaders($this->getHeaders())
                ->patch($url, $data);

            if ($response->successful()) {
                $task->update([
                    'github_issue_state' => 'open',
                    'github_closed_at' => null,
                ]);

                return true;
            }

            return false;

        } catch (\Exception $e) {
            Log::error("GitHub API error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Add comment to GitHub issue
     */
    public function addComment(Task $task, string $comment): bool
    {
        if (!$task->repository || !$task->github_issue_number) {
            return false;
        }

        try {
            $repository = $task->repository;
            $url = "{$this->baseUrl}/repos/{$repository->full_name}/issues/{$task->github_issue_number}/comments";

            $data = ['body' => $comment];

            $response = Http::withHeaders($this->getHeaders())
                ->post($url, $data);

            return $response->successful();

        } catch (\Exception $e) {
            Log::error("GitHub API error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get repository information
     */
    public function getRepository(string $fullName): ?array
    {
        try {
            $url = "{$this->baseUrl}/repos/{$fullName}";

            $response = Http::withHeaders($this->getHeaders())
                ->get($url);

            if ($response->successful()) {
                return $response->json();
            }

            return null;

        } catch (\Exception $e) {
            Log::error("GitHub API error: " . $e->getMessage());
            return null;
        }
    }
} 