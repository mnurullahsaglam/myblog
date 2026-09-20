<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Task;

interface SyncsGitHubIssues
{
    public function updateIssue(Task $task): bool;

    /**
     * @return array<string, mixed>|null the created issue, or null when GitHub refused
     */
    public function createIssue(Task $task): ?array;
}
