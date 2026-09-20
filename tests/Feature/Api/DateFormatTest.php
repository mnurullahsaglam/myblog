<?php

declare(strict_types=1);

use App\Models\Expense;
use App\Models\Income;
use App\Models\User;
use App\Models\UtilityBill;

beforeEach(function (): void {
    config(['app.admin_email' => 'owner@example.test']);
    $this->owner = User::factory()->admin()->create(['email' => 'owner@example.test']);
});

/**
 * A client decodes JSON with one date strategy. Three formats across one API
 * means three special cases in every client forever, so every resource emits
 * dates as Y-m-d and datetimes as ISO 8601 with an offset.
 */
it('emits every date-only column as Y-m-d', function (string $routeName, string $field): void {
    Income::factory()->create();
    Expense::factory()->create();
    UtilityBill::factory()->create();

    $value = apiAs($this->owner)->get(route($routeName))->json("data.0.{$field}");

    expect($value)->toMatch('/^\d{4}-\d{2}-\d{2}$/', "{$routeName} returned {$field} as {$value}");
})->with([
    'income date' => ['api.v1.incomes.index', 'date'],
    'expense date' => ['api.v1.expenses.index', 'date'],
    'bill due date' => ['api.v1.utility-bills.index', 'due_date'],
]);

it('emits every timestamp as ISO 8601 with an offset', function (string $routeName): void {
    Income::factory()->create();
    Expense::factory()->create();

    $value = apiAs($this->owner)->get(route($routeName))->json('data.0.created_at');

    expect($value)->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', "{$routeName} returned {$value}");
})->with(['api.v1.incomes.index', 'api.v1.expenses.index']);

/**
 * The hand-written resource and the generated ones must agree, because the
 * inconsistency this guards against came from exactly that split.
 */
it('formats the same column identically whoever wrote the resource', function (): void {
    Income::factory()->create();
    Expense::factory()->create();

    $income = apiAs($this->owner)->get(route('api.v1.incomes.index'))->json('data.0');
    $expense = apiAs($this->owner)->get(route('api.v1.expenses.index'))->json('data.0');

    $shape = fn (string $value): string => preg_replace('/\d/', 'N', $value) ?? '';

    expect($shape($income['date']))->toBe($shape($expense['date']))
        ->and($shape($income['created_at']))->toBe($shape($expense['created_at']));
});
