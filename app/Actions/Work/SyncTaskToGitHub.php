<?php

declare(strict_types=1);

namespace App\Actions\Work;

use App\Models\Task;
use App\Services\GitHubService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Push a task's current state to the GitHub issue it mirrors.
 *
 * Returns whether GitHub accepted the change. A task with no linked issue is
 * refused outright rather than reported as a failure, because the two mean
 * different things to the caller.
 */
final readonly class SyncTaskToGitHub
{
    public function __construct(private GitHubService $github) {}

    public function handle(Task $task): bool
    {
        throw_unless($task->is_github_issue, AccessDeniedHttpException::class, 'This task is not linked to a GitHub issue.');

        return $this->github->updateIssue($task);
    }
}
