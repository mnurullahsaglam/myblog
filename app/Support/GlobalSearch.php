<?php

declare(strict_types=1);

namespace App\Support;

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
     * Group label => [table factory, route name for a single record].
     *
     * @return array<string, array{table: callable(): ResourceTable, route: string}>
     */
    private static function registry(): array
    {
        return [
            'Posts' => ['table' => fn (): ResourceTable => new PostTable, 'route' => 'admin.posts.edit'],
            'Categories' => ['table' => fn (): ResourceTable => new CategoryTable, 'route' => 'admin.categories.edit'],
            'Books' => ['table' => fn (): ResourceTable => new BookTable, 'route' => 'admin.books.edit'],
            'Writers' => ['table' => fn (): ResourceTable => new WriterTable, 'route' => 'admin.writers.edit'],
            'Publishers' => ['table' => fn (): ResourceTable => new PublisherTable, 'route' => 'admin.publishers.edit'],
            'Clients' => ['table' => fn (): ResourceTable => new ClientTable, 'route' => 'admin.clients.edit'],
            'Projects' => ['table' => fn (): ResourceTable => new ProjectTable, 'route' => 'admin.projects.edit'],
            'Repositories' => ['table' => fn (): ResourceTable => new RepositoryTable, 'route' => 'admin.repositories.show'],
            'Invoices' => ['table' => fn (): ResourceTable => new InvoiceTable, 'route' => 'admin.invoices.edit'],
            'Incomes' => ['table' => fn (): ResourceTable => new IncomeTable, 'route' => 'admin.incomes.show'],
            'Expenses' => ['table' => fn (): ResourceTable => new ExpenseTable, 'route' => 'admin.expenses.edit'],
            'Debts' => ['table' => fn (): ResourceTable => new DebtTable, 'route' => 'admin.debts.edit'],
            'Daily summaries' => ['table' => fn (): ResourceTable => new WakaTimeSummaryTable, 'route' => 'admin.waka-time-summaries.show'],
        ];
    }

    /**
     * @return array<int, array{label: string, group: string, url: string}>
     */
    public static function query(string $term, int $perResource = self::PER_RESOURCE): array
    {
        $term = trim($term);

        if (mb_strlen($term) < self::MINIMUM_TERM_LENGTH) {
            return [];
        }

        $results = [];

        foreach (self::registry() as $group => $entry) {
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
                // One broken resource must not take the whole palette down.
                continue;
            }
        }

        return $results;
    }
}
