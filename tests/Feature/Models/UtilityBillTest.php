<?php

declare(strict_types=1);

use App\Enums\UtilityType;
use App\Models\UtilityAccount;
use App\Models\UtilityBill;
use App\Models\UtilityBillLine;

it('casts the account type to the enum', function (): void {
    $account = UtilityAccount::factory()->create(['type' => 'electricity']);

    expect($account->type)->toBe(UtilityType::Electricity);
});

it('computes consumption from the two readings', function (): void {
    $bill = UtilityBill::factory()->create(['meter_start' => 1000.5, 'meter_end' => 1250.25]);

    expect($bill->consumption)->toBe(249.75);
});

it('has no consumption when either reading is missing', function (?float $start, ?float $end): void {
    $bill = UtilityBill::factory()->create(['meter_start' => $start, 'meter_end' => $end]);

    expect($bill->consumption)->toBeNull();
})->with([
    'no start' => [null, 1250.0],
    'no end' => [1000.0, null],
    'neither' => [null, null],
]);

it('reports a negative consumption rather than throwing when a meter was replaced', function (): void {
    $bill = UtilityBill::factory()->create(['meter_start' => 9000.0, 'meter_end' => 12.0]);

    expect($bill->consumption)->toBeLessThan(0.0);
});

it('knows whether it is paid', function (): void {
    expect(UtilityBill::factory()->create(['paid_at' => null])->isPaid())->toBeFalse()
        ->and(UtilityBill::factory()->create(['paid_at' => now()])->isPaid())->toBeTrue();
});

it('sums its lines', function (): void {
    $bill = UtilityBill::factory()->create();
    UtilityBillLine::factory()->for($bill, 'bill')->create(['amount' => 10.00]);
    UtilityBillLine::factory()->for($bill, 'bill')->create(['amount' => 2.50]);
    UtilityBillLine::factory()->for($bill, 'bill')->create(['amount' => -1.00]);

    expect($bill->refresh()->lineTotal())->toBe(11.50);
});

it('sums to zero when it has no lines', function (): void {
    expect(UtilityBill::factory()->create()->lineTotal())->toBe(0.0);
});

it('belongs to an account which has many bills', function (): void {
    $account = UtilityAccount::factory()->create();
    UtilityBill::factory()->count(3)->for($account, 'account')->create();

    // Loaded explicitly: Model::shouldBeStrict() turns a lazy load into an
    // exception, which is exactly why UtilityBillTable eager loads the account.
    $bill = UtilityBill::with('account')->firstOrFail();

    expect($account->bills)->toHaveCount(3)
        ->and($bill->account->id)->toBe($account->id);
});

it('deletes its lines when the bill goes', function (): void {
    $bill = UtilityBill::factory()->create();
    UtilityBillLine::factory()->count(2)->for($bill, 'bill')->create();

    $bill->delete();

    expect(UtilityBillLine::count())->toBe(0);
});

it('deletes its bills when the account goes', function (): void {
    $account = UtilityAccount::factory()->create();
    UtilityBill::factory()->count(2)->for($account, 'account')->create();

    $account->delete();

    expect(UtilityBill::count())->toBe(0);
});
