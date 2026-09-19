<?php

declare(strict_types=1);

use App\Enums\Currencies;
use App\Models\Debt;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Tables\Filter;
use Illuminate\Database\Eloquent\Builder;

it('describes a relationship filter with its options', function (): void {
    $food = ExpenseCategory::factory()->create(['name' => 'Food']);
    $rent = ExpenseCategory::factory()->create(['name' => 'Rent']);

    $schema = Filter::relationship('expense_category_id', 'expenseCategory', 'name')
        ->label('Category')
        ->multiple()
        ->schema();
    expect($schema)->toMatchArray(['key' => 'expense_category_id', 'type' => 'select', 'label' => 'Category'])
        ->and($schema['multiple'])->toBeTrue()
        ->and($schema['options'])->toContain(['value' => $food->id, 'label' => 'Food'], ['value' => $rent->id, 'label' => 'Rent']);
});

it('humanises a foreign key into a label', function (): void {
    expect(Filter::relationship('expense_category_id', 'expenseCategory', 'name')->schema()['label'])
        ->toBe('Expense category');
});

it('describes an enum filter using HasLabel', function (): void {
    $schema = Filter::enum('currency', Currencies::class)->multiple()->schema();

    expect($schema['options'])->toContain(['value' => 'TRY', 'label' => 'Turkish Lira'])
        ->toHaveSameSize(Currencies::cases());
});

it('describes a select filter from a plain map', function (): void {
    expect(Filter::select('status', ['pending' => 'Pending', 'paid' => 'Paid'])->schema()['options'])
        ->toBe([
            ['value' => 'pending', 'label' => 'Pending'],
            ['value' => 'paid', 'label' => 'Paid'],
        ]);
});

it('describes a date range filter', function (): void {
    $schema = Filter::dateRange('date')->schema();
    expect($schema)->toMatchArray(['type' => 'dateRange', 'options' => []]);
});

it('describes a boolean filter with custom labels', function (): void {
    $schema = Filter::boolean('receipt_path')
        ->label('Receipt')
        ->trueLabel('Has receipt')
        ->falseLabel('No receipt')
        ->schema();
    expect($schema)->toMatchArray(['type' => 'boolean', 'options' => [
        ['value' => 'yes', 'label' => 'Has receipt'],
        ['value' => 'no', 'label' => 'No receipt'],
    ]]);
});

it('exposes a default value', function (): void {
    expect(Filter::enum('currency', Currencies::class)->default('TRY')->schema()['default'])->toBe('TRY');
});

it('applies a single-value relationship filter', function (): void {
    $food = ExpenseCategory::factory()->create();
    $rent = ExpenseCategory::factory()->create();
    Expense::factory()->create(['expense_category_id' => $food->id]);
    Expense::factory()->create(['expense_category_id' => $rent->id]);

    $query = Expense::query();
    Filter::relationship('expense_category_id', 'expenseCategory', 'name')->apply($query, $food->id);

    expect($query->count())->toBe(1);
});

it('applies a multi-value relationship filter', function (): void {
    $a = ExpenseCategory::factory()->create();
    $b = ExpenseCategory::factory()->create();
    $c = ExpenseCategory::factory()->create();
    Expense::factory()->create(['expense_category_id' => $a->id]);
    Expense::factory()->create(['expense_category_id' => $b->id]);
    Expense::factory()->create(['expense_category_id' => $c->id]);

    $query = Expense::query();
    Filter::relationship('expense_category_id', 'expenseCategory', 'name')
        ->multiple()
        ->apply($query, [$a->id, $b->id]);

    expect($query->count())->toBe(2);
});

it('ignores an empty filter value', function (mixed $value): void {
    Expense::factory()->count(3)->create();

    $query = Expense::query();
    Filter::relationship('expense_category_id', 'expenseCategory', 'name')->apply($query, $value);

    expect($query->count())->toBe(3);
})->with([
    'null' => [null],
    'empty string' => [''],
    'empty array' => [[]],
    'array of null' => [[null]],
    'array of empty string' => [['']],
]);

it('applies a closed date range', function (): void {
    Expense::factory()->create(['date' => '2026-01-10']);
    Expense::factory()->create(['date' => '2026-02-20']);
    Expense::factory()->create(['date' => '2026-03-30']);

    $query = Expense::query();
    Filter::dateRange('date')->apply($query, ['from' => '2026-02-01', 'to' => '2026-03-01']);

    expect($query->count())->toBe(1);
});

it('applies an open-ended date range', function (): void {
    Expense::factory()->create(['date' => '2026-01-10']);
    Expense::factory()->create(['date' => '2026-05-10']);

    $fromOnly = Expense::query();
    Filter::dateRange('date')->apply($fromOnly, ['from' => '2026-02-01', 'to' => null]);
    expect($fromOnly->count())->toBe(1);

    $toOnly = Expense::query();
    Filter::dateRange('date')->apply($toOnly, ['from' => null, 'to' => '2026-02-01']);
    expect($toOnly->count())->toBe(1);
});

it('includes the boundary dates', function (): void {
    Expense::factory()->create(['date' => '2026-02-01']);
    Expense::factory()->create(['date' => '2026-02-28']);

    $query = Expense::query();
    Filter::dateRange('date')->apply($query, ['from' => '2026-02-01', 'to' => '2026-02-28']);

    expect($query->count())->toBe(2);
});

it('applies a boolean filter both ways', function (): void {
    Expense::factory()->withReceipt()->create();
    Expense::factory()->create(['receipt_path' => null]);

    $with = Expense::query();
    Filter::boolean('receipt_path')->apply($with, 'yes');
    expect($with->count())->toBe(1);

    $without = Expense::query();
    Filter::boolean('receipt_path')->apply($without, 'no');
    expect($without->count())->toBe(1);
});

it('applies a custom query closure', function (): void {
    Debt::factory()->overdue()->create();
    Debt::factory()->create(['status' => 'pending', 'due_date' => now()->addYear()->toDateString()]);

    $query = Debt::query();
    Filter::custom('overdue', 'Overdue debts', fn (Builder $builder): Builder => $builder
        ->where('status', 'pending')
        ->whereNotNull('due_date')
        ->where('due_date', '<', now()))
        ->apply($query, 'yes');

    expect($query->count())->toBe(1);
});

it('does not apply a custom filter when unset', function (): void {
    Debt::factory()->count(3)->create();

    $query = Debt::query();
    Filter::custom('overdue', 'Overdue debts', fn (Builder $builder): Builder => $builder->where('status', 'paid'))
        ->apply($query, null);

    expect($query->count())->toBe(3);
});

it('never constrains the query when display-only', function (): void {
    Expense::factory()->count(3)->create();

    $query = Expense::query();
    Filter::enum('conversion_currency', Currencies::class)
        ->displayOnly()
        ->apply($query, 'TRY');

    expect($query->count())->toBe(3);
});

it('still describes a display-only filter to the browser', function (): void {
    $schema = Filter::enum('conversion_currency', Currencies::class)
        ->label('Convert to')
        ->default('TRY')
        ->displayOnly()
        ->schema();

    expect($schema['displayOnly'])->toBeTrue()
        ->and($schema['default'])->toBe('TRY')
        ->and($schema['options'])->not->toBeEmpty();
});

it('returns no options for an unknown relationship', function (): void {
    expect(Filter::relationship('unicorn_id', 'unicorn', 'name')->schema()['options'])->toBeEmpty();
});
