<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\Utilities\UtilityBillController as PanelBillController;
use App\Models\User;
use App\Models\UtilityAccount;
use App\Models\UtilityBill;

beforeEach(function (): void {
    config(['app.admin_email' => 'owner@example.test']);
    $this->owner = User::factory()->admin()->create(['email' => 'owner@example.test']);
    $this->account = UtilityAccount::factory()->create();
});

function billPayload(array $lines, array $extra = []): array
{
    return [
        'utility_account_id' => test()->account->id,
        'due_date' => '2026-10-01',
        'total_amount' => '100.00',
        'currency' => 'TRY',
        'lines' => $lines,
    ] + $extra;
}

/**
 * The repeater's key is a field on the form and not a column on the table.
 * Models are unguarded, so nothing refused it: the insert reached PostgreSQL
 * with an array bound to "lines" and died on the value.
 */
it('does not fall over when a bill arrives with its lines', function (): void {
    apiAs($this->owner)
        ->post(route('api.v1.utility-bills.store'), billPayload([
            ['label' => 'Hizmet bedeli', 'amount' => '80.00'],
        ]))
        ->assertCreated();
});

it('stores the lines a bill was created with', function (): void {
    apiAs($this->owner)->post(route('api.v1.utility-bills.store'), billPayload([
        ['label' => 'Hizmet bedeli', 'amount' => '80.00'],
        ['label' => 'KDV', 'amount' => '20.00'],
    ]))->assertCreated();

    $lines = UtilityBill::query()->sole()->lines()->orderBy('sort_order')->get();

    expect($lines)->toHaveCount(2)
        ->and($lines[0]->label)->toBe('Hizmet bedeli')
        ->and($lines[1]->label)->toBe('KDV');
});

/**
 * Order is the edit. The server takes sort_order from the position in the
 * array, so moving a row on the phone has to arrive as a different order here.
 */
it('keeps the order the lines arrived in', function (): void {
    apiAs($this->owner)->post(route('api.v1.utility-bills.store'), billPayload([
        ['label' => 'KDV', 'amount' => '20.00'],
        ['label' => 'Hizmet bedeli', 'amount' => '80.00'],
    ]))->assertCreated();

    $lines = UtilityBill::query()->sole()->lines()->orderBy('sort_order')->get();

    expect($lines[0]->label)->toBe('KDV')
        ->and($lines[0]->sort_order)->toBe(0)
        ->and($lines[1]->sort_order)->toBe(1);
});

it('replaces the lines on an update rather than adding to them', function (): void {
    $bill = UtilityBill::factory()->create(['utility_account_id' => $this->account->id]);
    $bill->lines()->createMany([
        ['label' => 'Old one', 'amount' => 10, 'sort_order' => 0],
        ['label' => 'Old two', 'amount' => 20, 'sort_order' => 1],
    ]);

    apiAs($this->owner)->put(route('api.v1.utility-bills.update', $bill), billPayload([
        ['label' => 'Only this', 'amount' => '100.00'],
    ]))->assertOk();

    $lines = $bill->lines()->get();

    expect($lines)->toHaveCount(1)
        ->and($lines[0]->label)->toBe('Only this');
});

it('clears the lines when an update sends none', function (): void {
    $bill = UtilityBill::factory()->create(['utility_account_id' => $this->account->id]);
    $bill->lines()->create(['label' => 'Old', 'amount' => 10, 'sort_order' => 0]);

    apiAs($this->owner)->put(route('api.v1.utility-bills.update', $bill), billPayload([]))->assertOk();

    expect($bill->lines()->count())->toBe(0);
});

it('accepts a bill that mentions no lines at all', function (): void {
    $payload = billPayload([]);

    unset($payload['lines']);

    apiAs($this->owner)->post(route('api.v1.utility-bills.store'), $payload)->assertCreated();

    expect(UtilityBill::query()->sole()->lines()->count())->toBe(0);
});

it('refuses a line missing the fields the server asked for', function (): void {
    apiAs($this->owner)
        ->post(route('api.v1.utility-bills.store'), billPayload([['amount' => '80.00']]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('lines.0.label');

    expect(UtilityBill::query()->count())->toBe(0);
});

it('never writes the repeater key as a column', function (): void {
    apiAs($this->owner)->post(route('api.v1.utility-bills.store'), billPayload([
        ['label' => 'Hizmet bedeli', 'amount' => '80.00'],
    ]))->assertCreated();

    expect(UtilityBill::query()->sole()->getAttributes())->not->toHaveKey('lines');
});

/**
 * The panel and the API each turn a payload into rows. They are the same fact
 * twice, so they are compared rather than trusted.
 */
it('stores what the panel would store from the same payload', function (): void {
    $lines = [
        ['label' => 'Hizmet bedeli', 'amount' => '80.00'],
        ['label' => 'KDV', 'amount' => '20.00'],
    ];

    apiAs($this->owner)->post(route('api.v1.utility-bills.store'), billPayload($lines))->assertCreated();

    $throughApi = UtilityBill::query()->sole()->lines()->orderBy('sort_order')
        ->get(['label', 'amount', 'sort_order'])->toArray();

    UtilityBill::query()->delete();

    $this->actingAs($this->owner)
        ->post(route('admin.utility-bills.store'), billPayload($lines))
        ->assertRedirect();

    $throughPanel = UtilityBill::query()->sole()->lines()->orderBy('sort_order')
        ->get(['label', 'amount', 'sort_order'])->toArray();

    expect($throughApi)->toBe($throughPanel)
        ->and(class_exists(PanelBillController::class))->toBeTrue();
});
