<?php

declare(strict_types=1);

use App\Contracts\ConvertsCurrency;
use App\Models\Debt;
use App\Models\Expense;
use App\Models\User;
use App\Support\Navigation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));
    Storage::fake('public');

    $exchange = Mockery::mock(ConvertsCurrency::class);
    $exchange->shouldReceive('convert')->andReturnUsing(
        fn (float $amount, string $from, string $to): float => $amount * 2,
    );
    $exchange->shouldIgnoreMissing();
    app()->instance(ConvertsCurrency::class, $exchange);
});

it('lists debts newest first', function (): void {
    Debt::factory()->create(['date' => '2026-01-01']);
    $newest = Debt::factory()->create(['date' => '2026-06-01']);

    $this->get(route('admin.debts.index'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Budget/Debts/Index')
            ->where('rows.data.0.id', $newest->id)
        );
});

it('converts the amount into the selected currency', function (): void {
    Debt::factory()->create(['amount' => 100.00, 'currency' => 'USD']);

    $this->get(route('admin.debts.index', ['filter' => ['conversion_currency' => 'TRY']]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('rows.data.0.cells.converted_amount.display', '₺200.00')
        );
});

it('shows the original amount when the currencies match', function (): void {
    Debt::factory()->create(['amount' => 100.00, 'currency' => 'TRY']);

    $this->get(route('admin.debts.index', ['filter' => ['conversion_currency' => 'TRY']]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('rows.data.0.cells.converted_amount.display', '₺100.00')
        );
});

it('labels the converted column with the target currency', function (): void {
    $this->get(route('admin.debts.index', ['filter' => ['conversion_currency' => 'EUR']]))
        ->assertInertia(function (AssertableInertia $page): void {
            $columns = collect($page->toArray()['props']['schema']['columns'])->keyBy('key');

            expect($columns['converted_amount']['label'])->toBe('In EUR');
        });
});

it('does not filter rows out when only the conversion currency changes', function (): void {
    Debt::factory()->create(['currency' => 'USD']);
    Debt::factory()->create(['currency' => 'TRY']);

    $this->get(route('admin.debts.index', ['filter' => ['conversion_currency' => 'EUR']]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->has('rows.data', 2));
});

it('survives an exchange rate failure', function (): void {
    $failing = Mockery::mock(ConvertsCurrency::class);
    $failing->shouldReceive('convert')->andThrow(new Exception('rate API down'));
    $failing->shouldIgnoreMissing();
    app()->instance(ConvertsCurrency::class, $failing);

    Debt::factory()->create(['amount' => 100.00, 'currency' => 'USD']);

    $this->get(route('admin.debts.index', ['filter' => ['conversion_currency' => 'TRY']]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('rows.data.0.cells.converted_amount.display', '—')
        );
});

it('colours the due status', function (): void {
    Debt::factory()->overdue()->create();

    $this->get(route('admin.debts.index'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('rows.data.0.cells.due_date_status.variant', 'danger')
        );
});

it('greys the due status for a settled debt', function (): void {
    Debt::factory()->paid()->create(['due_date' => now()->subMonth()->toDateString()]);

    $this->get(route('admin.debts.index'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('rows.data.0.cells.due_date_status.variant', 'gray')
        );
});

it('filters to overdue debts', function (): void {
    Debt::factory()->overdue()->create();
    Debt::factory()->create(['status' => 'pending', 'due_date' => now()->addYear()->toDateString()]);

    $this->get(route('admin.debts.index', ['filter' => ['overdue' => 'yes']]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->has('rows.data', 1));
});

it('filters to debts due within seven days', function (): void {
    Debt::factory()->create(['status' => 'pending', 'due_date' => now()->addDays(3)->toDateString()]);
    Debt::factory()->create(['status' => 'pending', 'due_date' => now()->addDays(60)->toDateString()]);

    $this->get(route('admin.debts.index', ['filter' => ['due_soon' => 'yes']]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->has('rows.data', 1));
});

it('summarises outstanding debt in tiles', function (): void {
    Debt::factory()->create(['amount' => 100.00, 'currency' => 'TRY', 'status' => 'pending']);
    Debt::factory()->paid()->create(['amount' => 9999.00, 'currency' => 'TRY']);

    $this->get(route('admin.debts.index'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('tiles', 4)
            ->where('tiles.0.label', 'Outstanding')
            ->where('tiles.0.value', '₺100.00')
            ->where('tiles.1.value', '1')
        );
});

it('counts overdue and imminent debts in tiles', function (): void {
    Debt::factory()->overdue()->create(['currency' => 'TRY']);
    Debt::factory()->create(['status' => 'pending', 'currency' => 'TRY', 'due_date' => now()->addDays(2)->toDateString()]);

    $this->get(route('admin.debts.index'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('tiles.2.value', '1')
            ->where('tiles.3.value', '1')
        );
});

it('creates a debt', function (): void {
    $this->post(route('admin.debts.store'), [
        'creditor_name' => 'Bank',
        'creditor_type' => 'institute',
        'amount' => 5000.00,
        'currency' => 'TRY',
        'date' => '2026-02-01',
        'due_date' => '2026-08-01',
        'status' => 'pending',
        'description' => 'Loan',
    ])->assertRedirect(route('admin.debts.index'));

    $this->assertDatabaseHas('debts', ['creditor_name' => 'Bank']);
});

it('records a full payment and settles the debt', function (): void {
    $debt = Debt::factory()->create(['amount' => 1000.00, 'currency' => 'TRY', 'status' => 'pending']);

    $this->post(route('admin.debts.pay', $debt), [
        'payment_amount' => 1000.00,
        'payment_description' => 'Final payment',
    ])->assertRedirect(route('admin.debts.index'));

    $debt->refresh();

    expect((float) $debt->amount)->toBe(0.0)
        ->and($debt->status)->toBe('paid');
});

it('records a partial payment and reduces the balance', function (): void {
    $debt = Debt::factory()->create(['amount' => 1000.00, 'currency' => 'TRY', 'status' => 'pending']);

    $this->post(route('admin.debts.pay', $debt), [
        'payment_amount' => 400.00,
        'payment_description' => 'First instalment',
    ]);

    $debt->refresh();

    expect((float) $debt->amount)->toBe(600.0)
        ->and($debt->status)->toBe('pending');
});

it('creates one expense for the payment, not two', function (): void {
    $debt = Debt::factory()->create(['amount' => 200.00, 'currency' => 'USD', 'status' => 'pending']);

    $this->post(route('admin.debts.pay', $debt), [
        'payment_amount' => 200.00,
        'payment_description' => 'Settled',
    ]);

    $expenses = Expense::where('debt_id', $debt->id)->get();

    expect($expenses)->toHaveCount(1)
        ->and((float) $expenses->first()->amount)->toBe(200.0)
        ->and($expenses->first()->currency->value)->toBe('USD');
});

it('stores a payment receipt', function (): void {
    $debt = Debt::factory()->create(['amount' => 500.00, 'status' => 'pending']);

    $this->post(route('admin.debts.pay', $debt), [
        'payment_amount' => 500.00,
        'payment_description' => 'Paid',
        'receipt' => UploadedFile::fake()->image('receipt.png'),
    ]);

    $expense = Expense::where('debt_id', $debt->id)->firstOrFail();

    expect($expense->receipt_path)->toStartWith('receipts/debt-payments/');
    Storage::disk('public')->assertExists($expense->receipt_path);
});

it('rejects a payment larger than the debt', function (): void {
    $debt = Debt::factory()->create(['amount' => 100.00, 'status' => 'pending']);

    $this->from(route('admin.debts.index'))
        ->post(route('admin.debts.pay', $debt), ['payment_amount' => 500.00, 'payment_description' => 'Too much'])
        ->assertSessionHasErrors('payment_amount');

    expect((float) $debt->fresh()->amount)->toBe(100.0)
        ->and(Expense::where('debt_id', $debt->id)->exists())->toBeFalse();
});

it('rejects a zero payment', function (): void {
    $debt = Debt::factory()->create(['amount' => 100.00, 'status' => 'pending']);

    $this->from(route('admin.debts.index'))
        ->post(route('admin.debts.pay', $debt), ['payment_amount' => 0, 'payment_description' => 'Nothing'])
        ->assertSessionHasErrors('payment_amount');
});

it('refuses to pay a debt that is already settled', function (): void {
    $debt = Debt::factory()->paid()->create(['amount' => 0]);

    $this->from(route('admin.debts.index'))
        ->post(route('admin.debts.pay', $debt), ['payment_amount' => 10, 'payment_description' => 'x'])
        ->assertSessionHasErrors('payment_amount');
});

it('reports the remaining balance after a partial payment', function (): void {
    $debt = Debt::factory()->create(['amount' => 1000.00, 'currency' => 'TRY', 'status' => 'pending']);

    $this->post(route('admin.debts.pay', $debt), [
        'payment_amount' => 400.00,
        'payment_description' => 'Instalment',
    ]);

    expect(session('flash.notification'))
        ->toHaveKey('title', 'Partial payment recorded')
        ->toHaveKey('body', 'Paid ₺400.00. Remaining ₺600.00.');
});

it('lists debts in the budget cluster', function (): void {
    $budget = collect(Navigation::clusters())->firstWhere('label', 'Budget');

    expect(collect($budget['items'])->pluck('route'))->toContain('admin.debts.index');
});
