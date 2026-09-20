<?php

declare(strict_types=1);

use App\Models\Expense;
use App\Models\User;
use App\Models\UtilityAccount;
use App\Models\UtilityBill;
use App\Models\UtilityBillLine;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));
});

it('creates a bill with its breakdown', function (): void {
    $account = UtilityAccount::factory()->metered()->create();

    $this->post(route('admin.utility-bills.store'), [
        'utility_account_id' => $account->id,
        'due_date' => now()->addDays(10)->toDateString(),
        'total_amount' => 266.00,
        'currency' => 'TRY',
        'lines' => [
            ['label' => 'Enerji bedeli', 'amount' => 180.40],
            ['label' => 'Dağıtım bedeli', 'amount' => 41.10],
            ['label' => 'KDV', 'amount' => 44.50],
        ],
    ])->assertRedirect(route('admin.utility-bills.index'));

    $bill = UtilityBill::with('lines')->sole();

    expect($bill->lines)->toHaveCount(3)
        ->and($bill->lines->pluck('label')->all())->toBe(['Enerji bedeli', 'Dağıtım bedeli', 'KDV'])
        ->and($bill->lineTotal())->toBe(266.00);
});

it('creates a bill with no breakdown at all', function (): void {
    $account = UtilityAccount::factory()->create();

    $this->post(route('admin.utility-bills.store'), [
        'utility_account_id' => $account->id,
        'due_date' => now()->addDays(10)->toDateString(),
        'total_amount' => 99.00,
        'currency' => 'TRY',
    ])->assertRedirect(route('admin.utility-bills.index'));

    expect(UtilityBill::with('lines')->sole()->lines)->toBeEmpty();
});

it('replaces the breakdown on update rather than appending', function (): void {
    $bill = UtilityBill::factory()->create();
    UtilityBillLine::factory()->count(2)->for($bill, 'bill')->create(['label' => 'Old']);

    $this->put(route('admin.utility-bills.update', $bill), [
        'utility_account_id' => $bill->utility_account_id,
        'due_date' => $bill->due_date->toDateString(),
        'total_amount' => $bill->total_amount,
        'currency' => $bill->currency->value,
        'lines' => [['label' => 'New', 'amount' => 5]],
    ])->assertRedirect(route('admin.utility-bills.index'));

    expect($bill->refresh()->lines->pluck('label')->all())->toBe(['New']);
});

it('rejects a line with no label', function (): void {
    $account = UtilityAccount::factory()->create();

    $this->from(route('admin.utility-bills.index'))
        ->post(route('admin.utility-bills.store'), [
            'utility_account_id' => $account->id,
            'due_date' => now()->toDateString(),
            'total_amount' => 10,
            'currency' => 'TRY',
            'lines' => [['label' => '', 'amount' => 5]],
        ])
        ->assertSessionHasErrors('lines.0.label');
});

it('refuses meter readings on a utility with no meter', function (string $field): void {
    $account = UtilityAccount::factory()->unmetered()->create();

    $this->from(route('admin.utility-bills.index'))
        ->post(route('admin.utility-bills.store'), [
            'utility_account_id' => $account->id,
            'due_date' => now()->toDateString(),
            'total_amount' => 10,
            'currency' => 'TRY',
            $field => 1234,
        ])
        ->assertSessionHasErrors($field);
})->with(['meter_start', 'meter_end']);

it('accepts meter readings on a metered utility', function (): void {
    $account = UtilityAccount::factory()->metered()->create();

    $this->post(route('admin.utility-bills.store'), [
        'utility_account_id' => $account->id,
        'due_date' => now()->toDateString(),
        'total_amount' => 10,
        'currency' => 'TRY',
        'meter_start' => 1000,
        'meter_end' => 1250,
    ])->assertRedirect(route('admin.utility-bills.index'));

    expect(UtilityBill::sole()->consumption)->toBe(250.0);
});

it('refuses a period that ends before it starts', function (): void {
    $account = UtilityAccount::factory()->create();

    $this->from(route('admin.utility-bills.index'))
        ->post(route('admin.utility-bills.store'), [
            'utility_account_id' => $account->id,
            'period_start' => '2026-08-31',
            'period_end' => '2026-08-01',
            'due_date' => now()->toDateString(),
            'total_amount' => 10,
            'currency' => 'TRY',
        ])
        ->assertSessionHasErrors('period_end');
});

it('pays a bill and records the expense', function (): void {
    $bill = UtilityBill::factory()->create(['total_amount' => 300.00]);

    $this->post(route('admin.utility-bills.pay', $bill))
        ->assertRedirect(route('admin.utility-bills.index'));

    expect($bill->refresh()->isPaid())->toBeTrue()
        ->and(Expense::count())->toBe(1)
        ->and((float) Expense::sole()->amount)->toBe(300.00);
});

it('reports rather than throws when the bill is already paid', function (): void {
    $bill = UtilityBill::factory()->paid()->create();

    $this->post(route('admin.utility-bills.pay', $bill))->assertRedirect();

    expect(Expense::count())->toBe(0)
        ->and(session('flash.notification'))->toHaveKey('variant', 'danger');
});

it('lists bills', function (): void {
    UtilityBill::factory()->count(3)->create();

    $this->get(route('admin.utility-bills.index'))->assertOk();
});

it('turns a guest away', function (): void {
    auth()->logout();

    $this->get(route('admin.utility-bills.index'))->assertRedirect(route('login'));
});
