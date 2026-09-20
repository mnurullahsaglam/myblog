<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    config(['app.admin_email' => 'owner@example.test']);
    $this->owner = User::factory()->admin()->create(['email' => 'owner@example.test']);
    $this->member = User::factory()->member()->create(['email' => 'her@example.test']);
});

it('returns a table and a form schema', function (): void {
    apiAs($this->owner)
        ->get(route('api.v1.expenses.schema'))
        ->assertOk()
        ->assertJsonStructure([
            'table' => ['columns', 'filters', 'defaultSort', 'searchable', 'perPage'],
            'form' => ['fields', 'columns'],
        ]);
});

it('omits a field the member may not see', function (): void {
    $hers = apiAs($this->member)->get(route('api.v1.incomes.schema'))->json();

    expect(array_column($hers['form']['fields'], 'key'))->not->toContain('client_id')
        ->and(array_column($hers['table']['columns'], 'key'))->not->toContain('client.title')
        ->and(array_column($hers['table']['filters'], 'key'))->not->toContain('client_id');
});

it('includes it for the admin, so the test cannot pass by returning nothing', function (): void {
    $his = apiAs($this->owner)->get(route('api.v1.incomes.schema'))->json();

    expect(array_column($his['form']['fields'], 'key'))->toContain('client_id')
        ->and(array_column($his['table']['columns'], 'key'))->toContain('client.title');
});

it('hides a schema outside her areas entirely', function (string $routeName): void {
    apiAs($this->member)->get(route($routeName))->assertNotFound();
})->with([
    'api.v1.posts.schema',
    'api.v1.clients.schema',
    'api.v1.categories.schema',
]);

it('gives her the schemas inside her areas', function (string $routeName): void {
    apiAs($this->member)->get(route($routeName))->assertOk();
})->with([
    'api.v1.expenses.schema',
    'api.v1.utility-bills.schema',
    'api.v1.books.schema',
]);

it('turns away a request with no token', function (): void {
    $this->getJson(route('api.v1.expenses.schema'))->assertUnauthorized();
});

it('returns a table schema and no form for waka time summaries', function (): void {
    $response = apiAs($this->owner)->get(route('api.v1.waka-time-summaries.schema'))->assertOk();

    expect($response->json('table.columns'))->not->toBeEmpty()
        ->and($response->json('form'))->toBeNull();
});

it('is not captured by the show route', function (string $resource): void {
    $body = apiAs($this->owner)->get("/api/v1/{$resource}/schema")->assertOk()->json();

    expect($body)->toHaveKey('table');
})->with(['expenses', 'incomes', 'books', 'utility-bills', 'waka-time-summaries']);

it('gives every resource a schema', function (): void {
    $resources = [
        'posts', 'categories', 'books', 'writers', 'publishers',
        'utility-accounts', 'utility-bills', 'clients', 'projects',
        'repositories', 'invoices', 'incomes', 'expenses', 'debts',
        'waka-time-summaries',
    ];

    foreach ($resources as $resource) {
        expect(Route::has("api.v1.{$resource}.schema"))
            ->toBeTrue("{$resource} has no schema route");
    }
});
