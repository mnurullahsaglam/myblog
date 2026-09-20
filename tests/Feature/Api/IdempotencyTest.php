<?php

declare(strict_types=1);

use App\Models\Expense;
use App\Models\IdempotencyKey;
use App\Models\User;

beforeEach(function (): void {
    config(['app.admin_email' => 'owner@example.test']);
    $this->owner = User::factory()->admin()->create(['email' => 'owner@example.test']);
    $this->member = User::factory()->member()->create(['email' => 'her@example.test']);
});

/**
 * @return array<string, mixed>
 */
function expensePayload(string $description = 'Coffee'): array
{
    return [
        'amount' => 100,
        'currency' => 'TRY',
        'date' => now()->toDateString(),
        'description' => $description,
    ];
}

/**
 * The case this exists for: a phone queues a write, the connection drops after
 * the server handled it, and the phone retries.
 */
it('creates one record for a repeated key and returns the first response', function (): void {
    $token = $this->owner->createToken('phone')->plainTextToken;

    $first = $this->withToken($token)
        ->withHeader('Idempotency-Key', 'abc-123')
        ->postJson(route('api.v1.expenses.store'), expensePayload());

    $second = $this->withToken($token)
        ->withHeader('Idempotency-Key', 'abc-123')
        ->postJson(route('api.v1.expenses.store'), expensePayload());

    expect(Expense::query()->where('description', 'Coffee')->count())->toBe(1)
        ->and($second->status())->toBe($first->status())
        ->and($second->json())->toBe($first->json());
});

/**
 * Silently answering a different question with a stored answer is worse than
 * refusing.
 */
it('refuses a key replayed against a different payload', function (): void {
    $token = $this->owner->createToken('phone')->plainTextToken;

    $this->withToken($token)
        ->withHeader('Idempotency-Key', 'abc-123')
        ->postJson(route('api.v1.expenses.store'), expensePayload('Coffee'));

    $this->withToken($token)
        ->withHeader('Idempotency-Key', 'abc-123')
        ->postJson(route('api.v1.expenses.store'), expensePayload('Something else'))
        ->assertStatus(422);

    expect(Expense::query()->count())->toBe(1);
});

it('lets two different keys create two records', function (): void {
    $token = $this->owner->createToken('phone')->plainTextToken;

    foreach (['key-one', 'key-two'] as $key) {
        $this->withToken($token)
            ->withHeader('Idempotency-Key', $key)
            ->postJson(route('api.v1.expenses.store'), expensePayload())
            ->assertCreated();
    }

    expect(Expense::query()->where('description', 'Coffee')->count())->toBe(2);
});

it('stops replaying after twenty-four hours', function (): void {
    $token = $this->owner->createToken('phone')->plainTextToken;

    $this->withToken($token)
        ->withHeader('Idempotency-Key', 'abc-123')
        ->postJson(route('api.v1.expenses.store'), expensePayload());

    $this->travel(25)->hours();

    $this->withToken($token)
        ->withHeader('Idempotency-Key', 'abc-123')
        ->postJson(route('api.v1.expenses.store'), expensePayload())
        ->assertCreated();

    expect(Expense::query()->where('description', 'Coffee')->count())->toBe(2);
});

/**
 * Keys are scoped per account, so one person cannot use another's key to read
 * back a response that was never theirs.
 */
it('scopes keys per user', function (): void {
    apiAs($this->owner)
        ->json('POST', route('api.v1.expenses.store'), expensePayload('His'));

    $hers = apiAs($this->member)->json('POST', route('api.v1.expenses.store'), expensePayload('Hers'));

    $hers->assertCreated();

    expect(Expense::query()->count())->toBe(2);
});

it('ignores the header on a read', function (): void {
    $token = $this->owner->createToken('phone')->plainTextToken;

    $one = $this->withToken($token)
        ->withHeader('Idempotency-Key', 'abc-123')
        ->getJson(route('api.v1.expenses.index'));

    Expense::factory()->create();

    $two = $this->withToken($token)
        ->withHeader('Idempotency-Key', 'abc-123')
        ->getJson(route('api.v1.expenses.index'));

    expect($one->json('meta.total'))->not->toBe($two->json('meta.total'));
});

it('works normally with no header at all', function (): void {
    $token = $this->owner->createToken('phone')->plainTextToken;

    foreach (range(1, 2) as $ignored) {
        $this->withToken($token)
            ->postJson(route('api.v1.expenses.store'), expensePayload())
            ->assertCreated();
    }

    expect(Expense::query()->where('description', 'Coffee')->count())->toBe(2);
});

it('prunes expired keys', function (): void {
    $token = $this->owner->createToken('phone')->plainTextToken;

    $this->withToken($token)
        ->withHeader('Idempotency-Key', 'abc-123')
        ->postJson(route('api.v1.expenses.store'), expensePayload());

    expect(IdempotencyKey::query()->count())->toBe(1);

    $this->travel(25)->hours();

    $this->artisan('idempotency:prune')->assertSuccessful();

    expect(IdempotencyKey::query()->count())->toBe(0);
});
