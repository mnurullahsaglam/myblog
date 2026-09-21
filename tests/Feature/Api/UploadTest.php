<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\Blog\PostController as PanelPostController;
use App\Http\Controllers\Admin\Budget\ExpenseController as PanelExpenseController;
use App\Http\Controllers\Admin\Library\BookController as PanelBookController;
use App\Http\Controllers\Admin\Library\WriterController as PanelWriterController;
use App\Http\Controllers\Admin\Utilities\UtilityBillController as PanelUtilityBillController;
use App\Http\Controllers\Api\V1\Blog\PostController as ApiPostController;
use App\Http\Controllers\Api\V1\Budget\ExpenseController as ApiExpenseController;
use App\Http\Controllers\Api\V1\Library\BookController as ApiBookController;
use App\Http\Controllers\Api\V1\Library\WriterController as ApiWriterController;
use App\Http\Controllers\Api\V1\Utilities\UtilityBillController as ApiUtilityBillController;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use ReflectionMethod;

beforeEach(function (): void {
    config(['app.admin_email' => 'owner@example.test']);
    $this->owner = User::factory()->admin()->create(['email' => 'owner@example.test']);
    Storage::fake('public');
});

function uploadAs(User $user, string $uri, array $data, string $method = 'POST', array $headers = []): TestResponse
{
    resolve('auth')->forgetGuards();

    return test()
        ->withToken($user->createToken('upload')->plainTextToken)
        ->withHeaders(['Accept' => 'application/json'] + $headers)
        ->post($uri, $data + ($method === 'POST' ? [] : ['_method' => $method]));
}

function expensePayloadWith(array $extra = []): array
{
    return [
        'amount' => '42.50',
        'currency' => 'TRY',
        'date' => now()->toDateString(),
        'description' => 'Coffee at the till',
    ] + $extra;
}

it('stores a receipt sent when the expense is created', function (): void {
    $response = uploadAs($this->owner, route('api.v1.expenses.store'), expensePayloadWith([
        'receipt_path' => UploadedFile::fake()->image('receipt.jpg'),
    ]))->assertCreated();

    $stored = Expense::query()->sole()->receipt_path;

    expect($stored)->toStartWith('receipts/');
    Storage::disk('public')->assertExists($stored);

    expect($response->json('data.receipt_path'))->toStartWith('http')
        ->and($response->json('data.receipt_path'))->toContain('receipts/');
});

/**
 * PHP does not parse a multipart body on a real PUT, which is why Laravel reads
 * _method. Without it the fields arrive empty and the upload silently does
 * nothing.
 */
it('stores a receipt sent when the expense is updated', function (): void {
    $expense = Expense::factory()->create(['receipt_path' => null]);

    uploadAs($this->owner, route('api.v1.expenses.update', $expense), expensePayloadWith([
        'receipt_path' => UploadedFile::fake()->create('bill.pdf', 12, 'application/pdf'),
    ]), 'PUT')->assertOk();

    $stored = $expense->refresh()->receipt_path;

    expect($stored)->toStartWith('receipts/')
        ->and($stored)->toEndWith('.pdf');
    Storage::disk('public')->assertExists($stored);
});

/**
 * An update that says nothing about the file must not blank it, which is what
 * would happen if the validated key were left in place as null.
 */
it('leaves an existing receipt alone when the update carries no file', function (): void {
    $expense = Expense::factory()->create(['receipt_path' => 'receipts/original.jpg']);

    apiAs($this->owner)->put(route('api.v1.expenses.update', $expense), expensePayloadWith())
        ->assertOk();

    expect($expense->refresh()->receipt_path)->toBe('receipts/original.jpg');
});

it('refuses a file the form request does not allow', function (): void {
    uploadAs($this->owner, route('api.v1.expenses.store'), expensePayloadWith([
        'receipt_path' => UploadedFile::fake()->create('notes.txt', 4, 'text/plain'),
    ]))->assertStatus(422)->assertJsonValidationErrors('receipt_path');

    expect(Expense::query()->count())->toBe(0);
});

it('refuses a file over five megabytes', function (): void {
    uploadAs($this->owner, route('api.v1.expenses.store'), expensePayloadWith([
        'receipt_path' => UploadedFile::fake()->create('huge.pdf', 6000, 'application/pdf'),
    ]))->assertStatus(422)->assertJsonValidationErrors('receipt_path');
});

it('turns a stored path into a URL the phone can fetch', function (): void {
    $expense = Expense::factory()->create(['receipt_path' => 'receipts/kept.jpg']);

    $value = apiAs($this->owner)->get(route('api.v1.expenses.show', $expense))->json('data.receipt_path');

    expect($value)->toStartWith('http')
        ->and($value)->toEndWith('receipts/kept.jpg');
});

it('leaves a null file field null rather than linking to nothing', function (): void {
    $expense = Expense::factory()->create(['receipt_path' => null]);

    expect(apiAs($this->owner)->get(route('api.v1.expenses.show', $expense))->json('data.receipt_path'))
        ->toBeNull();
});

/**
 * An uploaded file encodes to {} , so hashing the raw input gave two different
 * photographs the same fingerprint and the second upload was answered with the
 * first one's response.
 */
it('does not mistake one photograph for another under the same idempotency key', function (): void {
    $key = ['Idempotency-Key' => 'scan-1'];

    $first = uploadAs($this->owner, route('api.v1.expenses.store'), expensePayloadWith([
        'receipt_path' => UploadedFile::fake()->image('first.jpg'),
    ]), 'POST', $key)->assertCreated();

    $second = uploadAs($this->owner, route('api.v1.expenses.store'), expensePayloadWith([
        'receipt_path' => UploadedFile::fake()->image('second.jpg'),
    ]), 'POST', $key);

    expect($second->status())->toBe(422)
        ->and($first->json('data.id'))->not->toBeNull();
});

it('replays the same upload rather than storing it twice', function (): void {
    $key = ['Idempotency-Key' => 'scan-2'];
    $file = UploadedFile::fake()->image('same.jpg');

    $first = uploadAs($this->owner, route('api.v1.expenses.store'), expensePayloadWith([
        'receipt_path' => $file,
    ]), 'POST', $key)->assertCreated();

    $second = uploadAs($this->owner, route('api.v1.expenses.store'), expensePayloadWith([
        'receipt_path' => UploadedFile::fake()->image('same.jpg'),
    ]), 'POST', $key)->assertCreated();

    expect($second->json('data.id'))->toBe($first->json('data.id'))
        ->and(Expense::query()->count())->toBe(1);
});

/**
 * The panel and the API each name the fields that accept a file. They are two
 * lists of the same fact, so they are compared rather than trusted.
 */
it('accepts exactly the files the panel accepts', function (string $api, string $panel): void {
    $read = function (string $class): array {
        $method = new ReflectionMethod($class, 'uploads');

        return $method->invoke(resolve($class));
    };

    expect($read($api))->toBe($read($panel))
        ->and($read($api))->not->toBeEmpty();
})->with([
    [ApiBookController::class, PanelBookController::class],
    [ApiWriterController::class, PanelWriterController::class],
    [ApiPostController::class, PanelPostController::class],
    [ApiUtilityBillController::class, PanelUtilityBillController::class],
    [ApiExpenseController::class, PanelExpenseController::class],
]);
