<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Work;

use App\Actions\Work\CreateTask;
use App\Actions\Work\DeleteTask;
use App\Actions\Work\MoveTask;
use App\Actions\Work\SyncTaskToGitHub;
use App\Actions\Work\UpdateTask;
use App\Contracts\NotifiesAdmin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MoveTaskRequest;
use App\Http\Requests\Admin\TaskRequest;
use App\Models\Project;
use App\Models\Repository;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

final class TaskBoardController extends Controller
{
    /**
     * @var array<int, array{key: string, label: string, color: string}>
     */
    private const array COLUMNS = [
        ['key' => 'todo', 'label' => 'To Do', 'color' => 'info'],
        ['key' => 'in_progress', 'label' => 'In Progress', 'color' => 'warn'],
        ['key' => 'completed', 'label' => 'Completed', 'color' => 'success'],
    ];

    public function __construct(
        private readonly NotifiesAdmin $notifier,
        private readonly CreateTask $createTask,
        private readonly UpdateTask $updateTask,
        private readonly DeleteTask $deleteTask,
        private readonly MoveTask $moveTask,
        private readonly SyncTaskToGitHub $syncTaskToGitHub,
    ) {}

    public function index(Request $request): Response
    {
        $tasks = Task::query()
            ->with(['repository', 'project'])
            ->when($request->integer('project'), fn (Builder $query, int $id) => $query->where('project_id', $id))
            ->when(
                $request->string('search')->trim()->toString(),
                fn (Builder $query, string $term) => $query->where(function (Builder $builder) use ($term): void {
                    $builder->whereLike('title', '%'.$term.'%')
                        ->orWhereLike('description', '%'.$term.'%');
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

        $this->moveTask->handle($task, $data['status'], $data['position']);

        return back();
    }

    public function update(TaskRequest $request, Task $task): RedirectResponse
    {
        $this->updateTask->handle($task, $request->validated());

        $this->notifier->success('Task updated');

        return to_route('admin.tasks.board');
    }

    public function store(TaskRequest $request): RedirectResponse
    {
        $this->createTask->handle($request->validated());

        $this->notifier->success('Task created');

        return to_route('admin.tasks.board');
    }

    public function destroy(Task $task): RedirectResponse
    {
        $this->deleteTask->handle($task);

        $this->notifier->success('Task deleted');

        return to_route('admin.tasks.board');
    }

    public function syncToGitHub(Task $task): RedirectResponse
    {
        try {
            $this->syncTaskToGitHub->handle($task)
                ? $this->notifier->success('Synced to GitHub')
                : $this->notifier->danger('GitHub rejected the update');
        } catch (AccessDeniedHttpException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            $this->notifier->danger('Could not sync to GitHub', $throwable->getMessage());
        }

        return to_route('admin.tasks.board');
    }
}
