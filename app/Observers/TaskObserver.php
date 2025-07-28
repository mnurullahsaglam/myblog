<?php

namespace App\Observers;

use App\Models\Task;
use App\Services\GitHubService;
use Illuminate\Support\Facades\Log;

class TaskObserver
{
    private GitHubService $githubService;

    public function __construct(GitHubService $githubService)
    {
        $this->githubService = $githubService;
    }

    /**
     * Handle the Task "updated" event.
     */
    public function updated(Task $task): void
    {
        // Only sync if this is a GitHub issue
        if (!$task->is_github_issue) {
            return;
        }

        // Check if relevant fields were changed
        $relevantFields = ['title', 'description', 'status'];
        $hasRelevantChanges = collect($relevantFields)
            ->some(fn($field) => $task->wasChanged($field));

        if (!$hasRelevantChanges) {
            return;
        }

        // Sync to GitHub in the background
        try {
            $this->githubService->updateIssue($task);
        } catch (\Exception $e) {
            Log::error("Failed to sync task to GitHub: " . $e->getMessage(), [
                'task_id' => $task->id,
                'github_issue_number' => $task->github_issue_number,
            ]);
        }
    }

    /**
     * Handle the Task "creating" event.
     */
    public function creating(Task $task): void
    {
        // Set default sort order if not provided
        if (is_null($task->sort_order)) {
            $maxOrder = Task::where('status', $task->status)
                ->when($task->project_id, fn($q) => $q->where('project_id', $task->project_id))
                ->when($task->repository_id, fn($q) => $q->where('repository_id', $task->repository_id))
                ->max('sort_order') ?? 0;

            $task->sort_order = $maxOrder + 1;
        }
    }

    /**
     * Handle the Task "created" event.
     */
    public function created(Task $task): void
    {
        // Create GitHub issue if task has repository but no GitHub issue
        if ($task->repository && !$task->github_issue_number) {
            try {
                $this->githubService->createIssue($task);
            } catch (\Exception $e) {
                Log::error("Failed to create GitHub issue: " . $e->getMessage(), [
                    'task_id' => $task->id,
                ]);
            }
        }
    }
} 