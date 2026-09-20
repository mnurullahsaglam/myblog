<?php

declare(strict_types=1);

use App\Actions\Utilities\SaveBillLines;
use App\Models\UtilityBill;
use App\Models\UtilityBillLine;
use Illuminate\Database\QueryException;

it('stores the lines in the order given', function (): void {
    $bill = UtilityBill::factory()->create();

    $stored = resolve(SaveBillLines::class)->handle($bill, [
        ['label' => 'Enerji bedeli', 'amount' => 180.40],
        ['label' => 'Dağıtım bedeli', 'amount' => 42.10],
        ['label' => 'KDV', 'amount' => 44.50],
    ]);

    expect($stored)->toBe(3)
        ->and($bill->refresh()->lines->pluck('label')->all())
        ->toBe(['Enerji bedeli', 'Dağıtım bedeli', 'KDV']);
});

it('numbers the rows so the order survives a reload', function (): void {
    $bill = UtilityBill::factory()->create();

    resolve(SaveBillLines::class)->handle($bill, [
        ['label' => 'First', 'amount' => 1],
        ['label' => 'Second', 'amount' => 2],
    ]);

    expect($bill->refresh()->lines->pluck('sort_order')->all())->toBe([0, 1]);
});

it('replaces the previous lines rather than appending to them', function (): void {
    $bill = UtilityBill::factory()->create();
    UtilityBillLine::factory()->count(3)->for($bill, 'bill')->create(['label' => 'Old']);

    resolve(SaveBillLines::class)->handle($bill, [['label' => 'New', 'amount' => 5]]);

    expect($bill->refresh()->lines->pluck('label')->all())->toBe(['New'])
        ->and(UtilityBillLine::count())->toBe(1);
});

it('clears the lines when given none', function (): void {
    $bill = UtilityBill::factory()->create();
    UtilityBillLine::factory()->count(2)->for($bill, 'bill')->create();

    expect(resolve(SaveBillLines::class)->handle($bill, []))->toBe(0)
        ->and($bill->refresh()->lines)->toBeEmpty();
});

it('keeps negative amounts, because bills carry discounts', function (): void {
    $bill = UtilityBill::factory()->create();

    resolve(SaveBillLines::class)->handle($bill, [
        ['label' => 'Hizmet bedeli', 'amount' => 100],
        ['label' => 'İndirim', 'amount' => -15.50],
    ]);

    expect($bill->refresh()->lineTotal())->toBe(84.50);
});

it('leaves another bill untouched', function (): void {
    $mine = UtilityBill::factory()->create();
    $theirs = UtilityBill::factory()->create();
    UtilityBillLine::factory()->count(2)->for($theirs, 'bill')->create();

    resolve(SaveBillLines::class)->handle($mine, [['label' => 'Only mine', 'amount' => 1]]);

    expect($theirs->refresh()->lines)->toHaveCount(2);
});

it('keeps the previous lines when one new row is rejected', function (): void {
    $bill = UtilityBill::factory()->create();
    UtilityBillLine::factory()->for($bill, 'bill')->create(['label' => 'Original']);

    expect(fn () => resolve(SaveBillLines::class)->handle($bill, [
        ['label' => 'Fine', 'amount' => 1],
        ['label' => null, 'amount' => 2],
    ]))->toThrow(QueryException::class)
        ->and($bill->refresh()->lines->pluck('label')->all())->toBe(['Original']);
});

it('accepts amounts arriving as strings from the form', function (): void {
    $bill = UtilityBill::factory()->create();

    resolve(SaveBillLines::class)->handle($bill, [['label' => 'KDV', 'amount' => '44.50']]);

    expect($bill->refresh()->lineTotal())->toBe(44.50);
});
