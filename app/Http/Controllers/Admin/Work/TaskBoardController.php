<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Work;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MoveTaskRequest;
use App\Http\Requests\Admin\TaskRequest;
use App\Models\Project;
use App\Models\Repository;
use App\Models\Task;
use App\Services\GitHubService;
use App\Support\AdminNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

class TaskBoardController extends Controller
{
    /**
     * @var array<int, array{key: string, label: string, color: string}>
     */
    private const COLUMNS = [
        ['key' => 'todo', 'label' => 'To Do', 'color' => 'info'],
        ['key' => 'in_progress', 'label' => 'In Progress', 'color' => 'warn'],
        ['key' => 'completed', 'label' => 'Completed', 'color' => 'success'],
    ];

    public function __construct(private readonly AdminNotifier $notifier) {}

    public function index(Request $request): Response
    {
        $tasks = Task::query()
            ->with(['repository', 'project'])
            ->when($request->integer('project'), fn ($query, int $id) => $query->where('project_id', $id))
            ->when(
                $request->string('search')->trim()->toString(),
                fn ($query, string $term) => $query->where(function ($builder) use ($term): void {
                    $builder->where('title', 'like', '%'.$term.'%')
                        ->orWhere('description', 'like', '%'.$term.'%');
                })
            )
            ->orderBy('sort_order')
            ->get()
            ->groupBy('status');

        $columns = [];

        foreach (self::COLUMNS as $column) {
            /** @var Collection<int, Task> $inColumn */
            $inColumn = $tasks->get($column['key']) ?? new Collection;

            $presented = [];

            foreach ($inColumn as $task) {
                $presented[] = $this->present($task);
            }

            $columns[] = [...$column, 'tasks' => $presented];
        }

        return Inertia::render('Work/TasksBoard', [
            'columns' => $columns,
            'statuses' => array_map(
                fn (array $column): array => ['value' => $column['key'], 'label' => $column['label']],
                self::COLUMNS,
            ),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Project $project): array => ['value' => $project->id, 'label' => $project->name])->all(),
            'repositories' => Repository::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Repository $repository): array => ['value' => $repository->id, 'label' => $repository->name])->all(),
            'filters' => [
                'project' => $request->integer('project') ?: null,
                'search' => $request->string('search')->toString(),
            ],
        ]);
    }

    /**
     * @return array{id: int, title: string, description: string|null, status: string, repository: string|null, repositoryId: int|null, project: string|null, projectId: int|null, sortOrder: int, isGithubIssue: bool, githubNumber: string|null, githubUrl: string|null, labels: array<int, array{name: string, color: string}>}
     */
    private function present(Task $task): array
    {
        /** @var array<int, array<string, mixed>> $rawLabels */
        $rawLabels = $task->github_issue_labels ?? [];

        $labels = [];

        foreach ($rawLabels as $label) {
            $name = $label['name'] ?? null;

            if (is_string($name) && $name !== '') {
                $color = $label['color'] ?? null;

                $labels[] = [
                    'name' => $name,
                    'color' => is_string($color) && $color !== '' ? $color : '9096A2',
                ];
            }
        }

        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status,
            'repository' => $task->repository?->name,
            'repositoryId' => $task->repository_id,
            'project' => $task->project?->name,
            'projectId' => $task->project_id,
            'sortOrder' => (int) $task->sort_order,
            'isGithubIssue' => $task->is_github_issue,
            'githubNumber' => $task->github_issue_number,
            'githubUrl' => $task->github_issue_url,
            'labels' => $labels,
        ];
    }

    /**
     * Move a task to a column and a position within it.
     */
    public function move(MoveTaskRequest $request, Task $task): RedirectResponse
    {
        /** @var array{status: string, position: int} $data */
        $data = $request->validated();

        DB::transaction(function () use ($task, $data): void {
            // Take the task out of the ordering first so reindexing is simple.
            $task->updateQuietly(['sort_order' => null]);

            /** @var Collection<int, Task> $siblings */
            $siblings = Task::query()
                ->where('status', $data['status'])
                ->whereKeyNot($task->getKey())
                ->orderBy('sort_order')
                ->get();

            $ordered = $siblings->values();
            $ordered->splice(min($data['position'], $ordered->count()), 0, [$task]);

            foreach ($ordered as $index => $sibling) {
                if ($sibling->is($task)) {
                    // Through the model, so TaskObserver sees the status change
                    // and syncs it to GitHub.
                    $task->update(['status' => $data['status'], 'sort_order' => $index + 1]);

                    continue;
                }

                // Pure reordering: no observer, no GitHub call.
                $sibling->updateQuietly(['sort_order' => $index + 1]);
            }
        });

        return back();
    }

    public function update(TaskRequest $request, Task $task): RedirectResponse
    {
        $task->update($request->validated());

        $this->notifier->success('Task updated');

        return to_route('admin.tasks.board');
    }

    public function store(TaskRequest $request): RedirectResponse
    {
        Task::create($request->validated());

        $this->notifier->success('Task created');

        return to_route('admin.tasks.board');
    }

    public function destroy(Task $task): RedirectResponse
    {
        $task->delete();

        $this->notifier->success('Task deleted');

        return to_route('admin.tasks.board');
    }

    public function syncToGitHub(Task $task): RedirectResponse
    {
        if (! $task->is_github_issue) {
            throw new AccessDeniedHttpException('This task is not linked to a GitHub issue.');
        }

        try {
            app(GitHubService::class)->updateIssue($task)
                ? $this->notifier->success('Synced to GitHub')
                : $this->notifier->danger('GitHub rejected the update');
        } catch (Throwable $exception) {
            $this->notifier->danger('Could not sync to GitHub', $exception->getMessage());
        }

        return to_route('admin.tasks.board');
    }
}
