<?php

declare(strict_types=1);

namespace App\Observers;

use App\Contracts\SyncsGitHubIssues;
use App\Models\Task;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

final readonly class TaskObserver
{
    public function __construct(private SyncsGitHubIssues $githubService) {}

    /**
     * Handle the Task "updated" event.
     */
    public function updated(Task $task): void
    {
        if (! $task->is_github_issue) {
            return;
        }

        $relevantFields = ['title', 'description', 'status'];
        $hasRelevantChanges = collect($relevantFields)
            ->contains(fn (string $field): bool => $task->wasChanged($field));

        if (! $hasRelevantChanges) {
            return;
        }

        try {
            $this->githubService->updateIssue($task);
        } catch (Exception $exception) {
            Log::error('Failed to sync task to GitHub: '.$exception->getMessage(), [
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
        if (is_null($task->sort_order)) {
            $maxOrder = Task::where('status', $task->status)
                ->when($task->project_id, fn (Builder $q) => $q->where('project_id', $task->project_id))
                ->when($task->repository_id, fn (Builder $q) => $q->where('repository_id', $task->repository_id))
                ->max('sort_order');

            $task->sort_order = (is_numeric($maxOrder) ? (int) $maxOrder : 0) + 1;
        }
    }

    /**
     * Handle the Task "created" event.
     */
    public function created(Task $task): void
    {
        if ($task->repository && ! $task->github_issue_number) {
            try {
                $this->githubService->createIssue($task);
            } catch (Exception $e) {
                Log::error('Failed to create GitHub issue: '.$e->getMessage(), [
                    'task_id' => $task->id,
                ]);
            }
        }
    }
}
