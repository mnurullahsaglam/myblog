<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Area;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Task;
use App\Support\Access\AccessProfile;
use App\Support\Widgets\BudgetOverview;
use App\Support\Widgets\LibraryOverview;
use App\Support\Widgets\WorkOverview;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    /**
     * Panels are omitted rather than emptied.
     *
     * An empty panel still names the area and links into it, which is the thing
     * a user without that area should not learn exists.
     */
    public function __invoke(): Response
    {
        $profile = app(AccessProfile::class);

        $props = [];

        if ($profile->canAccess(Area::Budget)) {
            $props['budget'] = BudgetOverview::stats(...);
        }

        if ($profile->canAccess(Area::Work)) {
            $props['work'] = WorkOverview::stats(...);
            $props['openTasks'] = fn (): array => $this->openTasks();
        }

        if ($profile->canAccess(Area::Library)) {
            $props['library'] = LibraryOverview::stats(...);
        }

        if ($profile->canAccess(Area::Blog)) {
            $props['recentPosts'] = fn (): array => $this->recentPosts();
        }

        return Inertia::render('Dashboard', $props);
    }

    /**
     * @return array<int, array{id: int, title: string, slug: string, updatedAt: string|null}>
     */
    private function recentPosts(): array
    {
        return Post::query()
            ->latest()
            ->limit(5)
            ->get(['id', 'title', 'slug', 'updated_at'])
            ->map(fn (Post $post): array => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'updatedAt' => $post->updated_at?->diffForHumans(),
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, title: string, status: string, repository: string|null}>
     */
    private function openTasks(): array
    {
        return Task::query()
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
            ->all();
    }
}
