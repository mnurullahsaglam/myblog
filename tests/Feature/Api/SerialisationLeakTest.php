<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\Income;
use App\Models\User;

beforeEach(function (): void {
    config(['app.admin_email' => 'owner@example.test']);
    $this->owner = User::factory()->admin()->create(['email' => 'owner@example.test']);
    $this->member = User::factory()->member()->create(['email' => 'her@example.test']);
    $this->client = Client::factory()->create(['title' => 'Zzsecret Client']);
    $this->income = Income::factory()->create(['client_id' => $this->client->id, 'amount' => 4321]);
});

/**
 * API Resources are a third serialisation path. Field hiding lives in
 * ResourceTable::columns() and ResourceForm::fields(), and neither is involved
 * here, so a resource that returns client_id re-opens the leak in a response
 * nothing else inspects.
 *
 * The whole payload is searched rather than named fields, because a
 * field-by-field assertion only covers what somebody remembered to assert.
 */
it('never names a client anywhere in a member payload', function (string $routeName): void {
    $url = $routeName === 'api.v1.incomes.show'
        ? route($routeName, $this->income)
        : route($routeName);

    $body = apiAs($this->member)->get($url)->getContent();

    expect($body)->not->toContain('Zzsecret Client')
        ->and($body)->not->toContain('"client_id"');
})->with(['api.v1.incomes.index', 'api.v1.incomes.show']);

it('does name the client for an admin, so the test cannot pass by returning nothing', function (): void {
    $body = apiAs($this->owner)->get(route('api.v1.incomes.index'))->getContent();

    expect($body)->toContain('Zzsecret Client');
});

it('still gives her the amount, because her totals must match his', function (): void {
    $response = apiAs($this->member)->get(route('api.v1.incomes.index'))->assertOk();

    expect((float) $response->json('data.0.amount'))->toBe(4321.0);
});

it('degrades the derived source label over the api too', function (): void {
    $hers = apiAs($this->member)->get(route('api.v1.incomes.index'));
    $his = apiAs($this->owner)->get(route('api.v1.incomes.index'));

    expect($hers->json('data.0.source'))->toBe('Client work')
        ->and($his->json('data.0.source'))->toBe('Zzsecret Client');
});

it('marks a protected row as not editable for her', function (): void {
    $response = apiAs($this->member)->get(route('api.v1.incomes.index'));

    expect($response->json('data.0.editable'))->toBeFalse();
});

it('refuses to let her update an income that names a client', function (): void {
    apiAs($this->member)
        ->put(route('api.v1.incomes.update', $this->income), [
            'amount' => 999,
            'currency' => 'TRY',
            'date' => now()->toDateString(),
            'description' => 'Changed',
        ])
        ->assertNotFound();

    expect($this->income->fresh()->description)->not->toBe('Changed');
});

it('lets her update an income with no client', function (): void {
    $free = Income::factory()->create(['client_id' => null, 'description' => 'Before']);

    apiAs($this->member)
        ->put(route('api.v1.incomes.update', $free), [
            'amount' => 100,
            'currency' => 'TRY',
            'date' => now()->toDateString(),
            'description' => 'After',
        ])
        ->assertOk();

    expect($free->fresh()->description)->toBe('After');
});

it('ignores a client id she posts', function (): void {
    apiAs($this->member)
        ->post(route('api.v1.incomes.store'), [
            'amount' => 100,
            'currency' => 'TRY',
            'date' => now()->toDateString(),
            'description' => 'Mine',
            'client_id' => $this->client->id,
        ])
        ->assertCreated();

    expect(Income::query()->where('description', 'Mine')->sole()->client_id)->toBeNull();
});

it('lets the admin set a client', function (): void {
    apiAs($this->owner)
        ->post(route('api.v1.incomes.store'), [
            'amount' => 100,
            'currency' => 'TRY',
            'date' => now()->toDateString(),
            'description' => 'His',
            'client_id' => $this->client->id,
        ])
        ->assertCreated();

    expect(Income::query()->where('description', 'His')->sole()->client_id)->toBe($this->client->id);
});
