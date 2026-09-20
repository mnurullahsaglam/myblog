<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Area;
use App\Models\User;

final class Navigation
{
    /**
     * Single source of truth for the top navigation and the command palette.
     *
     * Each resource task adds its entry in the same commit that defines the
     * route, so a cluster never points at a route that does not exist yet.
     *
     * @return array<int, array{label: string, icon: string, area: Area, items: array<int, array{label: string, route: string, icon: string}>}>
     */
    public static function clusters(): array
    {
        return [
            ['label' => 'Blog', 'icon' => 'pi pi-pencil', 'area' => Area::Blog, 'items' => [
                ['label' => 'Posts', 'route' => 'admin.posts.index', 'icon' => 'pi pi-file-edit'],
            ]],
            ['label' => 'Budget', 'icon' => 'pi pi-wallet', 'area' => Area::Budget, 'items' => [
                ['label' => 'Incomes', 'route' => 'admin.incomes.index', 'icon' => 'pi pi-plus-circle'],
                ['label' => 'Expenses', 'route' => 'admin.expenses.index', 'icon' => 'pi pi-minus-circle'],
                ['label' => 'Debts', 'route' => 'admin.debts.index', 'icon' => 'pi pi-exclamation-triangle'],
            ]],
            ['label' => 'Work', 'icon' => 'pi pi-briefcase', 'area' => Area::Work, 'items' => [
                ['label' => 'Coding analytics', 'route' => 'admin.coding-dashboard', 'icon' => 'pi pi-chart-bar'],
                ['label' => 'Task board', 'route' => 'admin.tasks.board', 'icon' => 'pi pi-th-large'],
                ['label' => 'Clients', 'route' => 'admin.clients.index', 'icon' => 'pi pi-users'],
                ['label' => 'Projects', 'route' => 'admin.projects.index', 'icon' => 'pi pi-folder-open'],
                ['label' => 'Repositories', 'route' => 'admin.repositories.index', 'icon' => 'pi pi-code'],
                ['label' => 'Invoices', 'route' => 'admin.invoices.index', 'icon' => 'pi pi-receipt'],
                ['label' => 'Daily summaries', 'route' => 'admin.waka-time-summaries.index', 'icon' => 'pi pi-clock'],
            ]],
            ['label' => 'Library', 'icon' => 'pi pi-book', 'area' => Area::Library, 'items' => [
                ['label' => 'Books', 'route' => 'admin.books.index', 'icon' => 'pi pi-book'],
                ['label' => 'Writers', 'route' => 'admin.writers.index', 'icon' => 'pi pi-user'],
                ['label' => 'Publishers', 'route' => 'admin.publishers.index', 'icon' => 'pi pi-building'],
            ]],
            ['label' => 'Utilities', 'icon' => 'pi pi-bolt', 'area' => Area::Utilities, 'items' => [
                ['label' => 'Bills', 'route' => 'admin.utility-bills.index', 'icon' => 'pi pi-receipt'],
                ['label' => 'Accounts', 'route' => 'admin.utility-accounts.index', 'icon' => 'pi pi-id-card'],
            ]],
            ['label' => 'General', 'icon' => 'pi pi-cog', 'area' => Area::General, 'items' => [
                ['label' => 'Categories', 'route' => 'admin.categories.index', 'icon' => 'pi pi-tags'],
                ['label' => 'People', 'route' => 'admin.people.index', 'icon' => 'pi pi-users'],
                ['label' => 'Settings', 'route' => 'admin.settings', 'icon' => 'pi pi-cog'],
            ]],
        ];
    }

    /**
     * The clusters this user may actually open.
     *
     * The bar is the first thing that gives a resource away, so it is filtered
     * at the source rather than hidden in the component.
     *
     * @return array<int, array{label: string, icon: string, area: Area, items: array<int, array{label: string, route: string, icon: string}>}>
     */
    public static function forUser(User $user): array
    {
        return array_values(array_filter(
            self::clusters(),
            fn (array $cluster): bool => $user->canAccess($cluster['area']),
        ));
    }
}
