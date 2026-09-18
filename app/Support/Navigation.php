<?php

declare(strict_types=1);

namespace App\Support;

final class Navigation
{
    /**
     * Single source of truth for the top navigation and the command palette.
     *
     * Each resource task adds its entry in the same commit that defines the
     * route, so a cluster never points at a route that does not exist yet.
     *
     * @return array<int, array{label: string, icon: string, items: array<int, array{label: string, route: string, icon: string}>}>
     */
    public static function clusters(): array
    {
        return [
            ['label' => 'Blog', 'icon' => 'pi pi-pencil', 'items' => [
                ['label' => 'Posts', 'route' => 'admin.posts.index', 'icon' => 'pi pi-file-edit'],
            ]],
            ['label' => 'Budget', 'icon' => 'pi pi-wallet', 'items' => []],
            ['label' => 'Work', 'icon' => 'pi pi-briefcase', 'items' => []],
            ['label' => 'Library', 'icon' => 'pi pi-book', 'items' => []],
            ['label' => 'General', 'icon' => 'pi pi-cog', 'items' => []],
        ];
    }
}
