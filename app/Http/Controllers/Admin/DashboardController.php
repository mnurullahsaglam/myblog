<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Task;
use App\Support\Widgets\BudgetOverview;
use App\Support\Widgets\LibraryOverview;
use App\Support\Widgets\WorkOverview;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Dashboard', [
            'budget' => fn (): array => BudgetOverview::stats(),
            'work' => fn (): array => WorkOverview::stats(),
            'library' => fn (): array => LibraryOverview::stats(),

            'recentPosts' => fn (): array => Post::query()
                ->latest()
                ->limit(5)
                ->get(['id', 'title', 'slug', 'updated_at'])
                ->map(fn (Post $post): array => [
                    'id' => $post->id,
                    'title' => $post->title,
                    'slug' => $post->slug,
                    'updatedAt' => $post->updated_at?->diffForHumans(),
                ])
                ->all(),

            'openTasks' => fn (): array => Task::query()
                ->with('repository')
                ->whereIn('status', ['todo', 'in_progress'])
                ->orderBy('sort_order')
                ->limit(5)
                ->get()
                ->map(fn (Task $task): array => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'status' => $task->status,
                    'repository' => $task->repository?->name,
                ])
                ->all(),
        ]);
    }
}
