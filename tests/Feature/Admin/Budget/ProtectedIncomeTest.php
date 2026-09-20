<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\Income;
use App\Models\User;

beforeEach(function (): void {
    config(['app.admin_email' => 'owner@example.test']);
    $this->owner = User::factory()->admin()->create(['email' => 'owner@example.test']);
    $this->member = User::factory()->member()->create(['email' => 'her@example.test']);
    $this->client = Client::factory()->create();
});

it('lets her edit an income with no client', function (): void {
    $income = Income::factory()->create(['client_id' => null, 'description' => 'Before']);

    $this->actingAs($this->member)->get(route('admin.incomes.edit', $income))->assertOk();

    $this->actingAs($this->member)
        ->put(route('admin.incomes.update', $income), [
            'amount' => 100,
            'currency' => 'TRY',
            'date' => now()->toDateString(),
            'description' => 'After',
        ])
        ->assertRedirect();

    expect($income->fresh()->description)->toBe('After');
});

it('refuses to let her edit an income with a client', function (string $verb): void {
    $income = Income::factory()->create(['client_id' => $this->client->id, 'description' => 'Before']);

    $response = match ($verb) {
        'edit' => $this->actingAs($this->member)->get(route('admin.incomes.edit', $income)),
        'update' => $this->actingAs($this->member)->put(route('admin.incomes.update', $income), [
            'amount' => 999, 'currency' => 'TRY', 'date' => now()->toDateString(), 'description' => 'After',
        ]),
        'destroy' => $this->actingAs($this->member)->delete(route('admin.incomes.destroy', $income)),
    };

    $response->assertNotFound();

    expect($income->fresh())->not->toBeNull()
        ->and($income->fresh()->description)->toBe('Before');
})->with(['edit', 'update', 'destroy']);

it('lets the owner edit it', function (): void {
    $income = Income::factory()->create(['client_id' => $this->client->id]);

    $this->actingAs($this->owner)->get(route('admin.incomes.edit', $income))->assertOk();
});

it('still shows her the row and its amount', function (): void {
    Income::factory()->create(['client_id' => $this->client->id, 'amount' => 4321]);

    $this->actingAs($this->member)
        ->get(route('admin.incomes.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('rows.data.0.cells.amount.raw', 4321));
});

it('marks the row as not editable for her and editable for him', function (): void {
    Income::factory()->create(['client_id' => $this->client->id]);

    $this->actingAs($this->member)
        ->get(route('admin.incomes.index'))
        ->assertInertia(fn ($page) => $page->where('rows.data.0.editable', false));

    $this->actingAs($this->owner)
        ->get(route('admin.incomes.index'))
        ->assertInertia(fn ($page) => $page->where('rows.data.0.editable', true));
});

it('skips protected rows in a bulk delete', function (): void {
    $protected = Income::factory()->count(2)->create(['client_id' => $this->client->id]);
    $free = Income::factory()->count(3)->create(['client_id' => null]);

    $this->actingAs($this->member)
        ->delete(route('admin.incomes.bulk-destroy'), [
            'ids' => array_merge($protected->modelKeys(), $free->modelKeys()),
        ])
        ->assertRedirect();

    expect(Income::whereKey($protected->modelKeys())->count())->toBe(2)
        ->and(Income::whereKey($free->modelKeys())->count())->toBe(0);
});

it('skips protected rows in a bulk edit', function (): void {
    $protected = Income::factory()->create(['client_id' => $this->client->id, 'amount' => 10]);
    $free = Income::factory()->create(['client_id' => null, 'amount' => 10]);

    $this->actingAs($this->member)
        ->patch(route('admin.incomes.bulk-update'), [
            'ids' => [$protected->id, $free->id],
            'field' => 'amount',
            'value' => 250,
        ]);

    expect((float) $protected->fresh()->amount)->toBe(10.0)
        ->and((float) $free->fresh()->amount)->toBe(250.0);
});

it('lets the owner bulk edit every row', function (): void {
    $protected = Income::factory()->create(['client_id' => $this->client->id, 'amount' => 10]);

    $this->actingAs($this->owner)
        ->patch(route('admin.incomes.bulk-update'), [
            'ids' => [$protected->id],
            'field' => 'amount',
            'value' => 250,
        ]);

    expect((float) $protected->fresh()->amount)->toBe(250.0);
});
