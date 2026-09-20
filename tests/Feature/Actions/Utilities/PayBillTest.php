<?php

declare(strict_types=1);

use App\Actions\Utilities\PayBill;
use App\Models\Expense;
use App\Models\UtilityAccount;
use App\Models\UtilityBill;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

it('creates one expense for the bill total', function (): void {
    $bill = UtilityBill::factory()->create(['total_amount' => 412.75, 'currency' => 'TRY']);

    $expense = resolve(PayBill::class)->handle($bill);

    expect(Expense::count())->toBe(1)
        ->and((float) $expense->amount)->toBe(412.75)
        ->and($expense->currency->value)->toBe('TRY');
});

it('marks the bill paid and links the expense', function (): void {
    $bill = UtilityBill::factory()->create();

    $expense = resolve(PayBill::class)->handle($bill);

    expect($bill->refresh()->isPaid())->toBeTrue()
        ->and($bill->expense_id)->toBe($expense->id);
});

it('describes the expense with the account label and the period', function (): void {
    $account = UtilityAccount::factory()->create(['label' => 'Ev elektrik']);
    $bill = UtilityBill::factory()->for($account, 'account')->create([
        'period_start' => '2026-08-01',
        'period_end' => '2026-08-31',
    ]);

    $expense = resolve(PayBill::class)->handle($bill);

    expect($expense->description)->toContain('Ev elektrik')
        ->and($expense->description)->toContain('2026-08-01')
        ->and($expense->description)->toContain('2026-08-31');
});

it('still describes a bill that carries no period', function (): void {
    $account = UtilityAccount::factory()->create(['label' => 'İş telefonu']);
    $bill = UtilityBill::factory()->for($account, 'account')->create([
        'period_start' => null,
        'period_end' => null,
    ]);

    expect(resolve(PayBill::class)->handle($bill)->description)->toContain('İş telefonu');
});

it('marks the expense recurring, because a utility bill always is', function (): void {
    $expense = resolve(PayBill::class)->handle(UtilityBill::factory()->create());

    expect($expense->is_recurring)->toBeTrue();
});

it('dates the expense today', function (): void {
    $this->freezeTime();

    $expense = resolve(PayBill::class)->handle(UtilityBill::factory()->create());

    expect($expense->date->toDateString())->toBe(now()->toDateString());
});

it('refuses a bill that is already paid', function (): void {
    $bill = UtilityBill::factory()->paid()->create();

    expect(fn () => resolve(PayBill::class)->handle($bill))
        ->toThrow(RuntimeException::class, 'already paid');

    expect(Expense::count())->toBe(0);
});

it('does not double count when called twice', function (): void {
    $bill = UtilityBill::factory()->create();

    resolve(PayBill::class)->handle($bill);

    expect(fn () => resolve(PayBill::class)->handle($bill->refresh()))->toThrow(RuntimeException::class);

    expect(Expense::count())->toBe(1);
});

it('leaves nothing behind when the transaction fails', function (): void {
    $bill = UtilityBill::factory()->create(['total_amount' => 100.0]);

    // Force a failure inside the transaction by hiding the table it writes to.
    Schema::rename('expenses', 'expenses_hidden');

    try {
        expect(fn () => resolve(PayBill::class)->handle($bill))->toThrow(QueryException::class);
    } finally {
        Schema::rename('expenses_hidden', 'expenses');
    }

    expect($bill->refresh()->isPaid())->toBeFalse()
        ->and($bill->expense_id)->toBeNull();
});
