<?php

declare(strict_types=1);

use App\Models\Debt;
use App\Models\Expense;
use App\Support\AdminNotifier;

it('creates an expense when a debt is marked paid', function (): void {
    $debt = Debt::factory()->create(['status' => 'pending', 'amount' => 1500.00]);

    $debt->update(['status' => 'paid']);

    $expense = Expense::where('debt_id', $debt->id)->first();

    expect($expense)->not->toBeNull();
    expect((float) $expense->amount)->toBe(1500.00);
    expect($expense->currency->value)->toBe($debt->currency->value);
    expect($expense->description)->toContain($debt->creditor_name);
});

it('does not create a second expense for an already-paid debt', function (): void {
    $debt = Debt::factory()->create(['status' => 'pending']);
    $debt->update(['status' => 'paid']);

    $debt->update(['status' => 'paid', 'description' => 'touched again']);

    expect(Expense::where('debt_id', $debt->id)->count())->toBe(1);
});

it('does not create an expense for other status changes', function (): void {
    $debt = Debt::factory()->create(['status' => 'pending']);

    $debt->update(['description' => 'just a note']);

    expect(Expense::where('debt_id', $debt->id)->exists())->toBeFalse();
});

it('does not create an expense when a debt is created already paid', function (): void {
    $debt = Debt::factory()->paid()->create();

    expect(Expense::where('debt_id', $debt->id)->exists())->toBeFalse();
});

it('notifies through the admin notifier when the expense is created', function (): void {
    $notifier = Mockery::mock(AdminNotifier::class);
    $notifier->shouldReceive('success')
        ->once()
        ->withArgs(fn (string $title, ?string $body): bool => $title === 'Expense Created');
    app()->instance(AdminNotifier::class, $notifier);

    Debt::factory()->create(['status' => 'pending'])->update(['status' => 'paid']);
});
