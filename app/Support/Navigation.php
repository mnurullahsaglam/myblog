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
            ['label' => 'Budget', 'icon' => 'pi pi-wallet', 'items' => [
                ['label' => 'Incomes', 'route' => 'admin.incomes.index', 'icon' => 'pi pi-plus-circle'],
                ['label' => 'Expenses', 'route' => 'admin.expenses.index', 'icon' => 'pi pi-minus-circle'],
                ['label' => 'Debts', 'route' => 'admin.debts.index', 'icon' => 'pi pi-exclamation-triangle'],
            ]],
            ['label' => 'Work', 'icon' => 'pi pi-briefcase', 'items' => [
                ['label' => 'Clients', 'route' => 'admin.clients.index', 'icon' => 'pi pi-users'],
                ['label' => 'Projects', 'route' => 'admin.projects.index', 'icon' => 'pi pi-folder-open'],
                ['label' => 'Repositories', 'route' => 'admin.repositories.index', 'icon' => 'pi pi-code'],
                ['label' => 'Invoices', 'route' => 'admin.invoices.index', 'icon' => 'pi pi-receipt'],
                ['label' => 'Daily summaries', 'route' => 'admin.waka-time-summaries.index', 'icon' => 'pi pi-clock'],
            ]],
            ['label' => 'Library', 'icon' => 'pi pi-book', 'items' => [
                ['label' => 'Books', 'route' => 'admin.books.index', 'icon' => 'pi pi-book'],
                ['label' => 'Writers', 'route' => 'admin.writers.index', 'icon' => 'pi pi-user'],
                ['label' => 'Publishers', 'route' => 'admin.publishers.index', 'icon' => 'pi pi-building'],
            ]],
            ['label' => 'General', 'icon' => 'pi pi-cog', 'items' => [
                ['label' => 'Categories', 'route' => 'admin.categories.index', 'icon' => 'pi pi-tags'],
            ]],
        ];
    }
}
