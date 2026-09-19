<?php

declare(strict_types=1);

use App\Actions\Budget\PayDebt;
use App\Models\Debt;
use App\Models\Expense;

it('records a partial payment and reduces the balance', function (): void {
    $debt = Debt::factory()->create(['amount' => 1000, 'status' => 'pending']);

    $remaining = resolve(PayDebt::class)->handle($debt, 250.0, 'First instalment');

    expect($remaining)->toBe(750.0)
        ->and((float) $debt->refresh()->amount)->toBe(750.0)
        ->and($debt->status)->toBe('pending');
});

it('settles the debt when the payment clears the balance exactly', function (): void {
    $debt = Debt::factory()->create(['amount' => 500, 'status' => 'pending']);

    $remaining = resolve(PayDebt::class)->handle($debt, 500.0, 'Final payment');

    expect($remaining)->toBe(0.0)
        ->and((float) $debt->refresh()->amount)->toBe(0.0)
        ->and($debt->status)->toBe('paid');
});

it('settles rather than going negative when the payment overshoots', function (): void {
    $debt = Debt::factory()->create(['amount' => 100, 'status' => 'pending']);

    $remaining = resolve(PayDebt::class)->handle($debt, 150.0, 'Overpaid');

    expect($remaining)->toBeLessThanOrEqual(0.0)
        ->and((float) $debt->refresh()->amount)->toBe(0.0)
        ->and($debt->status)->toBe('paid');
});

it('creates one expense carrying the debt currency and today as the date', function (): void {
    $debt = Debt::factory()->create(['amount' => 300, 'currency' => 'USD']);

    resolve(PayDebt::class)->handle($debt, 100.0, 'Instalment');

    $expense = Expense::where('debt_id', $debt->id)->sole();

    expect((float) $expense->amount)->toBe(100.0)
        ->and($expense->currency->value)->toBe('USD')
        ->and($expense->description)->toBe('Instalment')
        ->and($expense->date->toDateString())->toBe(now()->toDateString());
});

it('stores the receipt path on the expense when one is given', function (): void {
    $debt = Debt::factory()->create(['amount' => 300]);

    resolve(PayDebt::class)->handle($debt, 100.0, 'With receipt', 'receipts/debt-payments/abc.pdf');

    expect(Expense::where('debt_id', $debt->id)->sole()->receipt_path)
        ->toBe('receipts/debt-payments/abc.pdf');
});

it('leaves the receipt path null when none is given', function (): void {
    $debt = Debt::factory()->create(['amount' => 300]);

    resolve(PayDebt::class)->handle($debt, 100.0, 'No receipt');

    expect(Expense::where('debt_id', $debt->id)->sole()->receipt_path)->toBeNull();
});

it('does not let the observer create a second expense for the settling payment', function (): void {
    $debt = Debt::factory()->create(['amount' => 200, 'status' => 'pending']);

    resolve(PayDebt::class)->handle($debt, 200.0, 'Settles it');

    expect(Expense::where('debt_id', $debt->id)->count())->toBe(1)
        ->and(Expense::where('debt_id', $debt->id)->sole()->description)->toBe('Settles it');
});

it('records the payment amount, not the outstanding balance, on the expense', function (): void {
    $debt = Debt::factory()->create(['amount' => 900, 'status' => 'pending']);

    resolve(PayDebt::class)->handle($debt, 300.0, 'Third of it');

    expect((float) Expense::where('debt_id', $debt->id)->sole()->amount)->toBe(300.0);
});

it('rounds the remaining balance to two decimal places', function (float $amount, float $payment, float $expected): void {
    $debt = Debt::factory()->create(['amount' => $amount]);

    expect(resolve(PayDebt::class)->handle($debt, $payment, 'Rounding'))->toBe($expected);
})->with([
    'thirds of a sum' => [100.00, 33.333, 66.67],
    'floating point drift' => [0.30, 0.10, 0.20],
    'repeating remainder' => [10.00, 3.33, 6.67],
]);

it('accepts a zero payment without settling the debt', function (): void {
    $debt = Debt::factory()->create(['amount' => 400, 'status' => 'pending']);

    $remaining = resolve(PayDebt::class)->handle($debt, 0.0, 'Nothing yet');

    expect($remaining)->toBe(400.0)
        ->and($debt->refresh()->status)->toBe('pending');
});

it('can be called repeatedly until the debt is settled', function (): void {
    $debt = Debt::factory()->create(['amount' => 300, 'status' => 'pending']);

    resolve(PayDebt::class)->handle($debt, 100.0, 'One');
    resolve(PayDebt::class)->handle($debt->refresh(), 100.0, 'Two');
    $remaining = resolve(PayDebt::class)->handle($debt->refresh(), 100.0, 'Three');

    expect($remaining)->toBe(0.0)
        ->and($debt->refresh()->status)->toBe('paid')
        ->and(Expense::where('debt_id', $debt->id)->count())->toBe(3);
});
