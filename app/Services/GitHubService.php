<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\SyncsGitHubIssues;
use App\Models\Repository;
use App\Models\Task;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class GitHubService implements SyncsGitHubIssues
{
    private string $baseUrl = 'https://api.github.com';

    private function token(): string
    {
        $token = config('services.github.token', config('services.github.personal_access_token'));

        throw_if(! is_string($token) || $token === '', Exception::class, 'GitHub token is required. Set GITHUB_TOKEN environment variable.');

        return $token;
    }

    public function updateIssue(Task $task): bool
    {
        if (! $task->repository || ! $task->github_issue_number) {
            return false;
        }

        try {
            /** @var Repository $repository */
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
                Log::info('GitHub issue updated successfully', [
                    'task_id' => $task->id,
                    'issue_number' => $task->github_issue_number,
                    'repository' => $repository->full_name,
                ]);

                return true;
            }

            Log::error('Failed to update GitHub issue', [
                'task_id' => $task->id,
                'response' => $response->body(),
                'status' => $response->status(),
            ]);

            return false;

        } catch (Exception $exception) {
            Log::error('GitHub API error: '.$exception->getMessage(), [
                'task_id' => $task->id,
            ]);

            return false;
        }
    }

    private function mapStatusToGitHubState(string $status): string
    {
        return match ($status) {
            'completed' => 'closed',
            'todo', 'in_progress' => 'open',
            default => 'open',
        };
    }

    /**
     * @return array<string, string>
     */
    private function getHeaders(): array
    {
        $appName = config('app.name', 'Laravel-App');

        return [
            'Authorization' => 'token '.$this->token(),
            'Accept' => 'application/vnd.github.v3+json',
            'User-Agent' => is_string($appName) ? $appName : 'Laravel-App',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function createIssue(Task $task): ?array
    {
        if (! $task->repository) {
            return null;
        }

        try {
            /** @var Repository $repository */
            $repository = $task->repository;
            $url = "{$this->baseUrl}/repos/{$repository->full_name}/issues";

            $data = [
                'title' => $task->title,
                'body' => $task->description,
            ];

            $response = Http::withHeaders($this->getHeaders())
                ->post($url, $data);

            if ($response->successful()) {
                /** @var array<string, mixed> $issueData */
                $issueData = $response->json();

                $task->update([
                    'github_issue_number' => $issueData['number'],
                    'github_issue_url' => $issueData['html_url'],
                    'github_issue_state' => $issueData['state'],
                    'github_created_at' => $issueData['created_at'],
                    'github_updated_at' => $issueData['updated_at'],
                ]);

                Log::info('GitHub issue created successfully', [
                    'task_id' => $task->id,
                    'issue_number' => $issueData['number'],
                    'repository' => $repository->full_name,
                ]);

                return $issueData;
            }

            Log::error('Failed to create GitHub issue', [
                'task_id' => $task->id,
                'response' => $response->body(),
                'status' => $response->status(),
            ]);

            return null;

        } catch (Exception $exception) {
            Log::error('GitHub API error: '.$exception->getMessage(), [
                'task_id' => $task->id,
            ]);

            return null;
        }
    }

    public function closeIssue(Task $task): bool
    {
        if (! $task->repository || ! $task->github_issue_number) {
            return false;
        }

        try {
            /** @var Repository $repository */
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

        } catch (Exception $exception) {
            Log::error('GitHub API error: '.$exception->getMessage());

            return false;
        }
    }

    public function reopenIssue(Task $task): bool
    {
        if (! $task->repository || ! $task->github_issue_number) {
            return false;
        }

        try {
            /** @var Repository $repository */
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

        } catch (Exception $exception) {
            Log::error('GitHub API error: '.$exception->getMessage());

            return false;
        }
    }

    public function addComment(Task $task, string $comment): bool
    {
        if (! $task->repository || ! $task->github_issue_number) {
            return false;
        }

        try {
            /** @var Repository $repository */
            $repository = $task->repository;
            $url = "{$this->baseUrl}/repos/{$repository->full_name}/issues/{$task->github_issue_number}/comments";

            $data = ['body' => $comment];

            $response = Http::withHeaders($this->getHeaders())
                ->post($url, $data);

            return $response->successful();

        } catch (Exception $exception) {
            Log::error('GitHub API error: '.$exception->getMessage());

            return false;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getRepository(string $fullName): ?array
    {
        try {
            $url = "{$this->baseUrl}/repos/{$fullName}";

            $response = Http::withHeaders($this->getHeaders())
                ->get($url);

            if ($response->successful()) {
                /** @var array<string, mixed> $data */
                $data = $response->json();

                return $data;
            }

            return null;

        } catch (Exception $exception) {
            Log::error('GitHub API error: '.$exception->getMessage());

            return null;
        }
    }
}
