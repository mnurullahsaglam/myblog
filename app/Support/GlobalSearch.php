<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Area;
use App\Models\User;
use App\Tables\Definitions\BookTable;
use App\Tables\Definitions\CategoryTable;
use App\Tables\Definitions\ClientTable;
use App\Tables\Definitions\DebtTable;
use App\Tables\Definitions\ExpenseTable;
use App\Tables\Definitions\IncomeTable;
use App\Tables\Definitions\InvoiceTable;
use App\Tables\Definitions\PostTable;
use App\Tables\Definitions\ProjectTable;
use App\Tables\Definitions\PublisherTable;
use App\Tables\Definitions\RepositoryTable;
use App\Tables\Definitions\WakaTimeSummaryTable;
use App\Tables\Definitions\WriterTable;
use App\Tables\ResourceTable;
use Throwable;

/**
 * Searches every resource at once, reusing the searchable columns each table
 * already declares so the two can never drift apart.
 */
final class GlobalSearch
{
    private const int MINIMUM_TERM_LENGTH = 2;

    private const int PER_RESOURCE = 5;

    /**
     * Group label => [table factory, route name for a single record, area].
     *
     * @return array<string, array{table: callable(): ResourceTable, route: string, area: Area}>
     */
    private static function registry(): array
    {
        return [
            'Posts' => ['table' => fn (): ResourceTable => new PostTable, 'route' => 'admin.posts.edit', 'area' => Area::Blog],
            'Categories' => ['table' => fn (): ResourceTable => new CategoryTable, 'route' => 'admin.categories.edit', 'area' => Area::General],
            'Books' => ['table' => fn (): ResourceTable => new BookTable, 'route' => 'admin.books.edit', 'area' => Area::Library],
            'Writers' => ['table' => fn (): ResourceTable => new WriterTable, 'route' => 'admin.writers.edit', 'area' => Area::Library],
            'Publishers' => ['table' => fn (): ResourceTable => new PublisherTable, 'route' => 'admin.publishers.edit', 'area' => Area::Library],
            'Clients' => ['table' => fn (): ResourceTable => new ClientTable, 'route' => 'admin.clients.edit', 'area' => Area::Work],
            'Projects' => ['table' => fn (): ResourceTable => new ProjectTable, 'route' => 'admin.projects.edit', 'area' => Area::Work],
            'Repositories' => ['table' => fn (): ResourceTable => new RepositoryTable, 'route' => 'admin.repositories.show', 'area' => Area::Work],
            'Invoices' => ['table' => fn (): ResourceTable => new InvoiceTable, 'route' => 'admin.invoices.edit', 'area' => Area::Work],
            'Incomes' => ['table' => fn (): ResourceTable => new IncomeTable, 'route' => 'admin.incomes.show', 'area' => Area::Budget],
            'Expenses' => ['table' => fn (): ResourceTable => new ExpenseTable, 'route' => 'admin.expenses.edit', 'area' => Area::Budget],
            'Debts' => ['table' => fn (): ResourceTable => new DebtTable, 'route' => 'admin.debts.edit', 'area' => Area::Budget],
            'Daily summaries' => ['table' => fn (): ResourceTable => new WakaTimeSummaryTable, 'route' => 'admin.waka-time-summaries.show', 'area' => Area::Work],
        ];
    }

    /**
     * The user is required rather than nullable: a call site that forgets it
     * will not compile, instead of quietly searching everything.
     *
     * @return array<int, array{label: string, group: string, url: string}>
     */
    public static function query(string $term, User $user, int $perResource = self::PER_RESOURCE): array
    {
        $term = trim($term);

        if (mb_strlen($term) < self::MINIMUM_TERM_LENGTH) {
            return [];
        }

        $results = [];

        foreach (self::registry() as $group => $entry) {
            // The palette reaches every resource at once, so it is the surface
            // most likely to name something the user cannot open.
            if (! $user->canAccess($entry['area'])) {
                continue;
            }

            try {
                $table = ($entry['table'])();

                foreach ($table->search($term, $perResource) as $row) {
                    $results[] = [
                        'label' => $row['label'],
                        'group' => $group,
                        'url' => route($entry['route'], $row['id']),
                    ];
                }
            } catch (Throwable) {
                continue;
            }
        }

        return $results;
    }
}
