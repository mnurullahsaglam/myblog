<?php

declare(strict_types=1);

use App\Models\Debt;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Income;
use App\Models\IncomeCategory;
use App\Models\Invoice;
use App\Models\Repository;
use App\Models\Setting;
use App\Models\Task;
use App\Models\WakaTimeSummary;
use App\Models\WakaTimeSummaryEntry;

it('can create a record from every factory', function (string $model): void {
    expect($model::factory()->create()->exists)->toBeTrue();
})->with([
    Debt::class,
    Expense::class,
    ExpenseCategory::class,
    Income::class,
    IncomeCategory::class,
    Invoice::class,
    Repository::class,
    Setting::class,
    Task::class,
    WakaTimeSummary::class,
    WakaTimeSummaryEntry::class,
]);

it('can create several records from every factory', function (string $model): void {
    expect($model::factory()->count(3)->create())->toHaveCount(3);
})->with([
    Debt::class,
    Expense::class,
    ExpenseCategory::class,
    Income::class,
    IncomeCategory::class,
    Invoice::class,
    Repository::class,
    Setting::class,
    Task::class,
    WakaTimeSummary::class,
    WakaTimeSummaryEntry::class,
]);

it('creates a github-linked task via the state', function (): void {
    $task = Task::factory()->githubIssue()->create();

    expect($task->is_github_issue)->toBeTrue()
        ->and($task->github_issue_number)->not->toBeNull()
        ->and($task->github_issue_labels)->toBeArray();
});

it('leaves sort order to the observer by default', function (): void {
    expect(Task::factory()->create()->sort_order)->toBeInt();
});

it('creates paid and overdue debts via states', function (): void {
    expect(Debt::factory()->paid()->create()->status)->toBe('paid');

    $overdue = Debt::factory()->overdue()->create();

    expect($overdue->status)->toBe('pending')
        ->and($overdue->due_date->isPast())->toBeTrue();
});

it('creates an expense with a receipt via the state', function (): void {
    expect(Expense::factory()->withReceipt()->create()->receipt_path)->toStartWith('receipts/');
});

it('creates a category without a colour via the state', function (): void {
    expect(ExpenseCategory::factory()->withoutColor()->create()->color)->toBeNull()
        ->and(IncomeCategory::factory()->withoutColor()->create()->color)->toBeNull();
});

it('keeps invoice totals internally consistent', function (): void {
    $invoice = Invoice::factory()->create();

    expect($invoice->total_amount)->toBe($invoice->amount + $invoice->tax_amount);
});

it('gives repositories a unique github id', function (): void {
    $repositories = Repository::factory()->count(5)->create();

    expect($repositories->pluck('github_id')->unique())->toHaveCount(5);
});
