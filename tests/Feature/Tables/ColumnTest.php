<?php

declare(strict_types=1);

use App\Enums\Currencies;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Post;
use App\Models\Publisher;
use App\Models\Repository;
use App\Models\Writer;
use App\Tables\Column;

it('describes itself in the schema', function (): void {
    expect(Column::text('title')->sortable()->schema())->toBe([
        'key' => 'title',
        'label' => 'Title',
        'type' => 'text',
        'sortable' => true,
        'toggleable' => false,
        'hiddenByDefault' => false,
        'align' => 'left',
    ]);
});

it('humanises keys into labels', function (string $key, string $expected): void {
    expect(Column::text($key)->schema()['label'])->toBe($expected);
})->with([
    ['title', 'Title'],
    ['created_at', 'Created at'],
    ['tax_no', 'Tax no'],
    ['writer.name', 'Writer'],
    ['expenseCategory.name', 'Expense category'],
    ['books_count', 'Books'],
]);

it('honours an explicit label', function (): void {
    expect(Column::text('tax_no')->label('Tax number')->schema()['label'])->toBe('Tax number');
});

it('right-aligns money and counts by default', function (): void {
    expect(Column::money('amount', currency: 'TRY')->schema()['align'])->toBe('right')
        ->and(Column::count('books_count')->schema()['align'])->toBe('right')
        ->and(Column::text('title')->schema()['align'])->toBe('left')
        ->and(Column::text('title')->numeric()->schema()['align'])->toBe('right');
});

it('marks a toggleable column hidden by default', function (): void {
    $schema = Column::datetime('created_at')->toggleable(hiddenByDefault: true)->schema();

    expect($schema['toggleable'])->toBeTrue()
        ->and($schema['hiddenByDefault'])->toBeTrue();
});

it('resolves a plain text value', function (): void {
    $post = Post::factory()->create(['title' => 'Learning Rust']);

    expect(Column::text('title')->resolve($post))
        ->toMatchArray(['display' => 'Learning Rust', 'raw' => 'Learning Rust']);
});

it('resolves a dotted relationship value', function (): void {
    $category = ExpenseCategory::factory()->create(['name' => 'Groceries']);
    $expense = Expense::factory()->create(['expense_category_id' => $category->id]);

    expect(Column::text('expenseCategory.name')->resolve($expense)['display'])->toBe('Groceries');
});

it('falls back to the default when the value is missing', function (): void {
    $expense = Expense::factory()->create(['debt_id' => null]);

    expect(Column::text('debt.creditor_name')->default('N/A')->resolve($expense)['display'])->toBe('N/A');
});

it('formats money from a currency held on another column', function (): void {
    $expense = Expense::factory()->create(['amount' => 1250.5, 'currency' => Currencies::TRY->value]);

    $resolved = Column::money('amount', currencyFrom: 'currency')->resolve($expense);
    expect($resolved)->toMatchArray(['display' => '₺1,250.50', 'raw' => 1250.5]);
});

it('formats money with a fixed currency', function (): void {
    $expense = Expense::factory()->create(['amount' => 99.0]);

    expect(Column::money('amount', currency: 'USD')->resolve($expense)['display'])->toBe('$99.00');
});

it('truncates a long value and keeps the whole of it as a tooltip', function (): void {
    $long = str_repeat('a', 80);
    $post = Post::factory()->create(['content' => $long]);

    $resolved = Column::text('content')->limit(50)->tooltip()->resolve($post);

    expect($resolved['display'])->toEndWith('...')
        ->and(mb_strlen($resolved['display']))->toBeLessThanOrEqual(53)
        ->and($resolved['tooltip'])->toBe($long);
});

it('does not tooltip a short value', function (): void {
    $post = Post::factory()->create(['content' => 'short']);

    expect(Column::text('content')->limit(50)->tooltip()->resolve($post)['tooltip'])->toBeNull();
});

it('resolves a badge variant from a closure', function (): void {
    $repository = Repository::factory()->create(['visibility' => 'public']);

    $resolved = Column::badge('visibility')
        ->color(fn (Repository $record): string => $record->visibility === 'public' ? 'success' : 'warning')
        ->resolve($repository);
    expect($resolved)->toMatchArray(['variant' => 'success', 'display' => 'public']);
});

it('resolves a badge variant from a static string', function (): void {
    $expense = Expense::factory()->create();

    expect(Column::badge('currency')->color('warning')->resolve($expense)['variant'])->toBe('warning');
});

it('falls back to secondary when no colour is configured', function (): void {
    $repository = Repository::factory()->create();

    expect(Column::badge('visibility')->resolve($repository)['variant'])->toBe('secondary');
});

it('labels a HasLabel enum in a badge and keeps the raw value', function (): void {
    $expense = Expense::factory()->create(['currency' => 'USD']);

    $resolved = Column::badge('currency')->resolve($expense);
    expect($resolved)->toMatchArray(['display' => 'US Dollar', 'raw' => 'USD']);
});

it('can show the short enum value instead, via state', function (): void {
    // Badges are 11px and compact, so currency columns want the code.
    $expense = Expense::factory()->create(['currency' => 'USD']);

    $resolved = Column::badge('currency')
        ->state(fn (Expense $record): string => $record->currency->value)
        ->resolve($expense);

    expect($resolved['display'])->toBe('USD');
});

it('formats a date and keeps the iso value', function (): void {
    $expense = Expense::factory()->create(['date' => '2026-03-14']);

    $resolved = Column::date('date')->resolve($expense);
    expect($resolved)->toMatchArray(['display' => '14 Mar 2026', 'raw' => '2026-03-14']);
});

it('formats a datetime', function (): void {
    $expense = Expense::factory()->create();

    expect(Column::datetime('created_at')->resolve($expense)['display'])
        ->toMatch('/^\d{2} \w{3} \d{4}, \d{2}:\d{2}$/');
});

it('resolves an image to a public url with its meta', function (): void {
    $expense = Expense::factory()->create(['receipt_path' => 'receipts/one.png']);

    $resolved = Column::image('receipt_path')->circular()->size(40)->resolve($expense);

    expect($resolved['display'])->toContain('receipts/one.png')
        ->and($resolved['meta'])->toBe(['circular' => true, 'size' => 40]);
});

it('resolves an empty image to an empty string', function (): void {
    $expense = Expense::factory()->create(['receipt_path' => null]);

    expect(Column::image('receipt_path')->resolve($expense)['display'])->toBeEmpty();
});

it('resolves a boolean', function (): void {
    $active = Repository::factory()->create(['is_active' => true]);
    $inactive = Repository::factory()->create(['is_active' => false]);

    expect(Column::boolean('is_active')->resolve($active))
        ->toMatchArray(['display' => 'Yes', 'raw' => true, 'variant' => 'success'])
        ->and(Column::boolean('is_active')->resolve($inactive))->toMatchArray(['display' => 'No', 'raw' => false, 'variant' => 'gray']);
});

it('formats a count with thousands separators', function (): void {
    $publisher = Publisher::factory()->create();
    $publisher->setAttribute('books_count', 1234);

    expect(Column::count('books_count')->resolve($publisher))
        ->toMatchArray(['display' => '1,234', 'raw' => 1234]);
});

it('treats a missing count as zero', function (): void {
    $publisher = Publisher::factory()->create();
    $publisher->setAttribute('books_count', null);

    expect(Column::count('books_count')->resolve($publisher)['raw'])->toBe(0);
});

it('resolves a computed state closure', function (): void {
    $post = Post::factory()->create(['title' => 'Hello']);

    $resolved = Column::text('headline')
        ->state(fn (Post $record): string => mb_strtoupper($record->title))
        ->resolve($post);

    expect($resolved['display'])->toBe('HELLO');
});

it('renders a bare number without separators', function (): void {
    $writer = Writer::factory()->create(['birth_year' => 1929]);

    expect(Column::number('birth_year')->resolve($writer))
        ->toMatchArray(['display' => '1929', 'raw' => 1929]);
});

it('right-aligns a bare number', function (): void {
    expect(Column::number('birth_year')->schema()['align'])->toBe('right');
});

it('falls back to the default for a missing number', function (): void {
    $writer = Writer::factory()->create(['death_year' => null]);

    expect(Column::number('death_year')->default('—')->resolve($writer)['display'])->toBe('—');
});
