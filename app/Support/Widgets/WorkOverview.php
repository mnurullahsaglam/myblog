<?php

declare(strict_types=1);

namespace App\Support\Widgets;

use App\Models\Client;
use App\Models\Project;
use App\Models\Repository;
use App\Models\Task;

final class WorkOverview
{
    /**
     * @return array<int, array{label: string, value: string, caption: string|null, icon: string|null}>
     */
    public static function stats(): array
    {
        $open = Task::query()->whereIn('status', ['todo', 'in_progress'])->count();
        $inProgress = Task::query()->where('status', 'in_progress')->count();

        $dueSoon = Project::query()
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [now()->toDateString(), now()->addDays(14)->toDateString()])
            ->count();

        return [
            [
                'label' => 'Open tasks',
                'value' => number_format($open),
                'caption' => $inProgress > 0 ? $inProgress.' in progress' : 'nothing started',
                'icon' => 'pi pi-check-square',
            ],
            [
                'label' => 'Projects',
                'value' => number_format(Project::query()->count()),
                'caption' => $dueSoon > 0 ? $dueSoon.' due within a fortnight' : null,
                'icon' => 'pi pi-folder-open',
            ],
            [
                'label' => 'Clients',
                'value' => number_format(Client::query()->count()),
                'caption' => null,
                'icon' => 'pi pi-users',
            ],
            [
                'label' => 'Repositories',
                'value' => number_format(Repository::query()->where('is_active', true)->count()),
                'caption' => 'active',
                'icon' => 'pi pi-code',
            ],
        ];
    }
}
