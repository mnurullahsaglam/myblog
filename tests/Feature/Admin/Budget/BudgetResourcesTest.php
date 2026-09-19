<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\Debt;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Income;
use App\Models\IncomeCategory;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));
    Storage::fake('public');
});

// ------------------------------------------------------------------- Incomes

it('lists incomes newest first', function (): void {
    Income::factory()->create(['date' => '2026-01-01']);
    $newest = Income::factory()->create(['date' => '2026-06-01']);

    $this->get(route('admin.incomes.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Budget/Incomes/Index')
            ->where('rows.data.0.id', $newest->id)
        );
});

it('formats income with the record currency symbol', function (): void {
    Income::factory()->create(['amount' => 1500.00, 'currency' => 'TRY']);

    $this->get(route('admin.incomes.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rows.data.0.cells.amount.display', '₺1,500.00')
            ->where('rows.data.0.cells.currency.display', 'TRY')
        );
});

it('filters incomes by currency and category', function (): void {
    $salary = IncomeCategory::factory()->create();
    Income::factory()->create(['currency' => 'TRY', 'income_category_id' => $salary->id]);
    Income::factory()->create(['currency' => 'USD']);

    $this->get(route('admin.incomes.index', ['filter' => ['currency' => ['USD']]]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('rows.data', 1));

    $this->get(route('admin.incomes.index', ['filter' => ['income_category_id' => [$salary->id]]]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('rows.data', 1));
});

it('filters incomes by source flags', function (): void {
    $invoice = Invoice::factory()->create();
    $debt = Debt::factory()->create();
    $client = Client::factory()->create();

    Income::factory()->create(['invoice_id' => $invoice->id, 'debt_id' => null, 'client_id' => null]);
    Income::factory()->create(['debt_id' => $debt->id, 'invoice_id' => null, 'client_id' => null]);
    Income::factory()->create(['client_id' => $client->id, 'invoice_id' => null, 'debt_id' => null]);

    $this->get(route('admin.incomes.index', ['filter' => ['source_invoice' => 'yes']]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('rows.data', 1));

    $this->get(route('admin.incomes.index', ['filter' => ['source_debt' => 'yes']]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('rows.data', 1));

    $this->get(route('admin.incomes.index', ['filter' => ['source_client' => 'yes']]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('rows.data', 1));
});

it('creates an income', function (): void {
    $category = IncomeCategory::factory()->create();

    $this->post(route('admin.incomes.store'), [
        'amount' => 2500.50,
        'currency' => 'TRY',
        'date' => '2026-05-01',
        'income_category_id' => $category->id,
        'description' => 'Consulting work',
    ])->assertRedirect(route('admin.incomes.index'));

    $this->assertDatabaseHas('incomes', ['amount' => 2500.50, 'currency' => 'TRY']);
});

it('rejects an income with an unknown currency or a negative amount', function (): void {
    $this->from(route('admin.incomes.create'))
        ->post(route('admin.incomes.store'), [
            'amount' => -5,
            'currency' => 'ZZZ',
            'date' => '2026-05-01',
            'description' => 'x',
        ])
        ->assertSessionHasErrors(['amount', 'currency']);
});

it('renders the income show page', function (): void {
    $income = Income::factory()->create();

    $this->get(route('admin.incomes.show', $income))
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Budget/Incomes/Show'));
});

// ------------------------------------------------------------------ Expenses

it('lists expenses newest first with tiles', function (): void {
    Expense::factory()->create(['date' => '2026-01-01', 'amount' => 100, 'currency' => 'TRY']);
    $newest = Expense::factory()->create(['date' => '2026-06-01', 'amount' => 200, 'currency' => 'TRY']);

    $this->get(route('admin.expenses.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Budget/Expenses/Index')
            ->where('rows.data.0.id', $newest->id)
            ->has('tiles', 4)
            ->where('tiles.0.label', 'Net outflow')
            ->where('tiles.0.value', '₺300.00')
        );
});

it('computes tiles over the filtered set, not everything', function (): void {
    $category = ExpenseCategory::factory()->create();
    Expense::factory()->create(['expense_category_id' => $category->id, 'amount' => 100, 'currency' => 'TRY']);
    Expense::factory()->create(['amount' => 9999, 'currency' => 'TRY']);

    $this->get(route('admin.expenses.index', ['filter' => ['expense_category_id' => [$category->id]]]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('tiles.0.value', '₺100.00')
            ->where('tiles.2.value', '1')
        );
});

it('counts missing receipts in a tile', function (): void {
    Expense::factory()->withReceipt()->create(['currency' => 'TRY']);
    Expense::factory()->count(2)->create(['receipt_path' => null, 'currency' => 'TRY']);

    $this->get(route('admin.expenses.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('tiles.3.label', 'Missing receipts')
            ->where('tiles.3.value', '2')
        );
});

it('reports tiles safely when nothing matches', function (): void {
    $this->get(route('admin.expenses.index', ['search' => 'nothing-matches-this']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('rows.data', 0)
            ->where('tiles.2.value', '0')
        );
});

it('notes when more than one currency is in play', function (): void {
    Expense::factory()->create(['amount' => 100, 'currency' => 'TRY']);
    Expense::factory()->create(['amount' => 50, 'currency' => 'USD']);

    $this->get(route('admin.expenses.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('tiles.0.caption', 'plus 1 other currency')
        );
});

it('renders recurring and deductible flags', function (): void {
    Expense::factory()->recurring()->taxDeductible()->create();

    $this->get(route('admin.expenses.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rows.data.0.cells.is_recurring.raw', true)
            ->where('rows.data.0.cells.is_tax_deductible.raw', true)
        );
});

it('filters expenses by recurring and deductible', function (): void {
    Expense::factory()->recurring()->create();
    Expense::factory()->taxDeductible()->create();
    Expense::factory()->create();

    $this->get(route('admin.expenses.index', ['filter' => ['is_recurring' => '1']]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('rows.data', 1));

    $this->get(route('admin.expenses.index', ['filter' => ['is_tax_deductible' => '1']]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('rows.data', 1));

    $this->get(route('admin.expenses.index', ['filter' => ['is_recurring' => '0']]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('rows.data', 2));
});

it('filters expenses by receipt', function (): void {
    Expense::factory()->withReceipt()->create();
    Expense::factory()->create(['receipt_path' => null]);

    $this->get(route('admin.expenses.index', ['filter' => ['receipt_path' => 'yes']]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('rows.data', 1));
});

it('shows a dash when an expense is not tied to a debt', function (): void {
    Expense::factory()->create(['debt_id' => null]);

    $this->get(route('admin.expenses.index'))
        ->assertInertia(function (AssertableInertia $page): void {
            $cells = $page->toArray()['props']['rows']['data'][0]['cells'];

            expect($cells['debt.creditor_name']['display'])->toBe('—');
        });
});

it('creates an expense with a receipt and flags', function (): void {
    $category = ExpenseCategory::factory()->create();

    $this->post(route('admin.expenses.store'), [
        'expense_category_id' => $category->id,
        'amount' => 320.00,
        'currency' => 'TRY',
        'description' => 'Office chair',
        'date' => '2026-04-02',
        'is_recurring' => false,
        'is_tax_deductible' => true,
        'receipt_path' => UploadedFile::fake()->image('receipt.jpg'),
    ])->assertRedirect(route('admin.expenses.index'));

    $expense = Expense::where('description', 'Office chair')->firstOrFail();

    expect($expense->is_tax_deductible)->toBeTrue();
    expect($expense->receipt_path)->toStartWith('receipts/');
    Storage::disk('public')->assertExists($expense->receipt_path);
});

it('requires the core expense fields', function (): void {
    $this->from(route('admin.expenses.create'))
        ->post(route('admin.expenses.store'), [])
        ->assertSessionHasErrors(['amount', 'currency', 'description', 'date']);
});

it('lists the budget cluster in navigation', function (): void {
    $budget = collect(App\Support\Navigation::clusters())->firstWhere('label', 'Budget');

    expect(collect($budget['items'])->pluck('route')->all())
        ->toContain('admin.incomes.index', 'admin.expenses.index');
});
