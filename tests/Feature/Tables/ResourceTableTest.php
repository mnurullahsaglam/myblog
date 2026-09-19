<?php

declare(strict_types=1);

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\ResourceTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * A fixture so the base class is tested independently of any real definition.
 */
final class FixtureExpenseTable extends ResourceTable
{
    #[Override]
    protected string $model = Expense::class;

    #[Override]
    protected array $with = ['expenseCategory'];

    #[Override]
    protected string $defaultSort = '-date';

    protected function columns(): array
    {
        return [
            Column::money('amount', currencyFrom: 'currency')->sortable(),
            Column::badge('expenseCategory.name')->label('Category'),
            Column::text('description')->limit(20)->tooltip(),
            Column::date('date')->sortable(),
            Column::text('receipt_path')->label('Receipt'),
        ];
    }

    protected function filters(): array
    {
        return [
            Filter::relationship('expense_category_id', 'expenseCategory', 'name')->multiple(),
            Filter::dateRange('date'),
            Filter::boolean('receipt_path')->label('Receipt'),
        ];
    }

    protected function searchable(): array
    {
        return ['description', 'expenseCategory.name'];
    }

    protected function titleColumn(): string
    {
        return 'description';
    }
}

function tableRequest(array $query = []): Request
{
    return Request::create('/admin/expenses', 'GET', $query);
}

it('emits a schema describing columns and filters', function (): void {
    $schema = (new FixtureExpenseTable)->schema();

    expect($schema['columns'])->toHaveCount(5)
        ->and($schema['columns'][0]['key'])->toBe('amount')
        ->and($schema['columns'][0]['sortable'])->toBeTrue()
        ->and($schema['filters'])->toHaveCount(3)
        ->and($schema['defaultSort'])->toBe('-date')
        ->and($schema['searchable'])->toBeTrue()
        ->and($schema['perPage'])->toBe(25);
});

it('returns rows keyed by id with resolved cells', function (): void {
    $category = ExpenseCategory::factory()->create(['name' => 'Food']);
    $expense = Expense::factory()->create([
        'expense_category_id' => $category->id,
        'amount' => 250.00,
        'currency' => 'TRY',
        'description' => 'Lunch',
    ]);

    $row = (new FixtureExpenseTable)->rows(tableRequest())->items()[0];

    expect($row['id'])->toBe($expense->id)
        ->and($row['cells']['amount']['display'])->toBe('₺250.00')
        ->and($row['cells']['expenseCategory.name']['display'])->toBe('Food')
        ->and($row['cells']['description']['display'])->toBe('Lunch');
});

it('applies the default sort', function (): void {
    Expense::factory()->create(['date' => '2026-01-01']);
    $newest = Expense::factory()->create(['date' => '2026-06-01']);

    expect((new FixtureExpenseTable)->rows(tableRequest())->items()[0]['id'])->toBe($newest->id);
});

it('applies an ascending sort from the request', function (): void {
    $oldest = Expense::factory()->create(['date' => '2026-01-01']);
    Expense::factory()->create(['date' => '2026-06-01']);

    expect((new FixtureExpenseTable)->rows(tableRequest(['sort' => 'date']))->items()[0]['id'])->toBe($oldest->id);
});

it('applies a descending sort from the request', function (): void {
    Expense::factory()->create(['amount' => 10]);
    $biggest = Expense::factory()->create(['amount' => 9999]);

    expect((new FixtureExpenseTable)->rows(tableRequest(['sort' => '-amount']))->items()[0]['id'])->toBe($biggest->id);
});

it('ignores a sort on a column that is not sortable', function (): void {
    Expense::factory()->count(2)->create();

    expect((new FixtureExpenseTable)->rows(tableRequest(['sort' => 'receipt_path']))->total())->toBe(2);
});

it('ignores a sort on an unknown column', function (): void {
    Expense::factory()->count(2)->create();

    expect((new FixtureExpenseTable)->rows(tableRequest(['sort' => 'unicorns']))->total())->toBe(2);
});

it('searches the declared columns', function (): void {
    Expense::factory()->create(['description' => 'Coffee beans']);
    Expense::factory()->create(['description' => 'Train ticket']);

    expect((new FixtureExpenseTable)->rows(tableRequest(['search' => 'coffee']))->total())->toBe(1);
});

it('searches across a relationship', function (): void {
    $food = ExpenseCategory::factory()->create(['name' => 'Groceries']);
    $rent = ExpenseCategory::factory()->create(['name' => 'Housing']);
    Expense::factory()->create(['expense_category_id' => $food->id, 'description' => 'x']);
    Expense::factory()->create(['expense_category_id' => $rent->id, 'description' => 'y']);

    expect((new FixtureExpenseTable)->rows(tableRequest(['search' => 'grocer']))->total())->toBe(1);
});

it('ignores a blank search', function (): void {
    Expense::factory()->count(3)->create();

    expect((new FixtureExpenseTable)->rows(tableRequest(['search' => '   ']))->total())->toBe(3);
});

it('applies filters from the request', function (): void {
    $food = ExpenseCategory::factory()->create();
    $rent = ExpenseCategory::factory()->create();
    Expense::factory()->create(['expense_category_id' => $food->id]);
    Expense::factory()->create(['expense_category_id' => $rent->id]);

    $rows = (new FixtureExpenseTable)->rows(tableRequest([
        'filter' => ['expense_category_id' => [$food->id]],
    ]));

    expect($rows->total())->toBe(1);
});

it('applies a date range filter from the request', function (): void {
    Expense::factory()->create(['date' => '2026-01-10']);
    Expense::factory()->create(['date' => '2026-02-15']);

    $rows = (new FixtureExpenseTable)->rows(tableRequest([
        'filter' => ['date' => ['from' => '2026-02-01', 'to' => '2026-02-28']],
    ]));

    expect($rows->total())->toBe(1);
});

it('combines a search with a filter', function (): void {
    $food = ExpenseCategory::factory()->create();
    Expense::factory()->create(['expense_category_id' => $food->id, 'description' => 'Coffee beans']);
    Expense::factory()->create(['expense_category_id' => $food->id, 'description' => 'Train ticket']);
    Expense::factory()->create(['description' => 'Coffee filter']);

    $rows = (new FixtureExpenseTable)->rows(tableRequest([
        'search' => 'coffee',
        'filter' => ['expense_category_id' => [$food->id]],
    ]));

    expect($rows->total())->toBe(1);
});

it('paginates and honours a per-page override', function (): void {
    Expense::factory()->count(30)->create();

    $rows = (new FixtureExpenseTable)->rows(tableRequest(['perPage' => 10, 'page' => 2]));

    expect($rows->perPage())->toBe(10)
        ->and($rows->currentPage())->toBe(2)
        ->and($rows->total())->toBe(30)
        ->and($rows->items())->toHaveCount(10);
});

it('clamps an absurd per-page value', function (): void {
    Expense::factory()->count(3)->create();

    expect((new FixtureExpenseTable)->rows(tableRequest(['perPage' => 5000]))->perPage())->toBe(100)
        ->and((new FixtureExpenseTable)->rows(tableRequest(['perPage' => 0]))->perPage())->toBe(25)
        ->and((new FixtureExpenseTable)->rows(tableRequest(['perPage' => -5]))->perPage())->toBe(25);
});

it('eager loads the declared relations rather than querying per row', function (): void {
    ExpenseCategory::factory()->count(3)->create();
    Expense::factory()->count(10)->create();

    DB::enableQueryLog();
    (new FixtureExpenseTable)->rows(tableRequest());
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBeLessThanOrEqual(4);
});

it('returns lightweight search results for the palette', function (): void {
    Expense::factory()->create(['description' => 'Coffee beans']);
    Expense::factory()->count(10)->create(['description' => 'Coffee filter']);

    $results = (new FixtureExpenseTable)->search('coffee', 5);

    expect($results)->toHaveCount(5)
        ->and($results->first())->toHaveKeys(['id', 'label'])
        ->and($results->first()['label'])->toContain('Coffee');
});

it('returns nothing from search for a blank term', function (): void {
    Expense::factory()->count(3)->create(['description' => 'Coffee']);

    expect((new FixtureExpenseTable)->search('  ', 5))->toBeEmpty();
});

it('carries the query string into pagination links', function (): void {
    Expense::factory()->count(30)->create();

    $rows = (new FixtureExpenseTable)->rows(tableRequest(['perPage' => 10, 'search' => 'x', 'sort' => 'date']));

    expect($rows->url(2))->toContain('sort=date');
});
