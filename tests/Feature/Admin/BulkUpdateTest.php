<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Post;
use App\Models\User;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));
});

it('sets a relation across the selection', function (): void {
    $posts = Post::factory()->count(3)->create();
    $categories = Category::factory()->count(2)->create();

    $this->patch(route('admin.posts.bulk-update'), [
        'ids' => $posts->modelKeys(),
        'field' => 'categories',
        'value' => $categories->modelKeys(),
    ])->assertRedirect(route('admin.posts.index'));

    foreach ($posts as $post) {
        expect($post->categories()->pluck('categories.id')->all())
            ->toEqualCanonicalizing($categories->modelKeys());
    }
});

it('reports how many rows it touched', function (): void {
    $posts = Post::factory()->count(2)->create();

    $this->patch(route('admin.posts.bulk-update'), [
        'ids' => $posts->modelKeys(),
        'field' => 'categories',
        'value' => Category::factory()->count(1)->create()->modelKeys(),
    ]);

    expect(session('flash.notification'))->toHaveKey('title', '2 posts updated');
});

it('refuses a field the form does not declare bulk editable', function (string $field): void {
    $posts = Post::factory()->count(2)->create(['content' => 'Before']);

    $this->from(route('admin.posts.index'))
        ->patch(route('admin.posts.bulk-update'), [
            'ids' => $posts->modelKeys(),
            'field' => $field,
            'value' => 'Injected',
        ])
        ->assertSessionHasErrors('field');

    expect(Post::pluck('content')->all())->each->toBe('Before');
})->with(['title', 'slug', 'content', 'image', 'id', 'created_at']);

it('refuses a value outside the options the field offers', function (): void {
    $posts = Post::factory()->count(2)->create();

    $this->from(route('admin.posts.index'))
        ->patch(route('admin.posts.bulk-update'), [
            'ids' => $posts->modelKeys(),
            'field' => 'categories',
            'value' => [999_999],
        ])
        ->assertSessionHasErrors('value.0');
});

it('refuses an id that does not exist', function (): void {
    $this->from(route('admin.posts.index'))
        ->patch(route('admin.posts.bulk-update'), [
            'ids' => [999_999],
            'field' => 'categories',
            'value' => [],
        ])
        ->assertSessionHasErrors('ids.0');
});

it('requires a selection', function (): void {
    $this->from(route('admin.posts.index'))
        ->patch(route('admin.posts.bulk-update'), [
            'ids' => [],
            'field' => 'categories',
            'value' => [],
        ])
        ->assertSessionHasErrors('ids');
});

it('sets a scalar field across the selection', function (): void {
    $expenses = Expense::factory()->count(2)->create(['is_tax_deductible' => false]);

    $this->patch(route('admin.expenses.bulk-update'), [
        'ids' => $expenses->modelKeys(),
        'field' => 'is_tax_deductible',
        'value' => true,
    ])->assertRedirect(route('admin.expenses.index'));

    expect(Expense::whereKey($expenses->modelKeys())->pluck('is_tax_deductible')->all())
        ->each->toBeTruthy();
});

it('turns a guest away', function (): void {
    auth()->logout();

    $this->patch(route('admin.posts.bulk-update'), [
        'ids' => [1],
        'field' => 'categories',
        'value' => [],
    ])->assertRedirect(route('login'));
});

it('sets a money field across the selection', function (): void {
    $expenses = Expense::factory()->count(3)->create(['amount' => 10]);

    $this->patch(route('admin.expenses.bulk-update'), [
        'ids' => $expenses->modelKeys(),
        'field' => 'amount',
        'value' => 250.5,
    ])->assertRedirect(route('admin.expenses.index'));

    expect(Expense::whereKey($expenses->modelKeys())->pluck('amount')->all())
        ->each->toEqual(250.5);
});

it('rejects a non-numeric value for a money field', function (mixed $value): void {
    $expenses = Expense::factory()->count(2)->create(['amount' => 10]);

    $this->from(route('admin.expenses.index'))
        ->patch(route('admin.expenses.bulk-update'), [
            'ids' => $expenses->modelKeys(),
            'field' => 'amount',
            'value' => $value,
        ])
        ->assertSessionHasErrors('value');

    expect(Expense::pluck('amount')->all())->each->toEqual(10);
})->with(['not a number', '', null, [[1, 2]]]);

it('honours the minimum declared on the field', function (): void {
    $expenses = Expense::factory()->count(2)->create(['amount' => 10]);

    $this->from(route('admin.expenses.index'))
        ->patch(route('admin.expenses.bulk-update'), [
            'ids' => $expenses->modelKeys(),
            'field' => 'amount',
            'value' => -5,
        ])
        ->assertSessionHasErrors('value');
});

it('honours the maximum declared on the field', function (): void {
    $invoices = Invoice::factory()->count(2)->create(['tax_rate' => 10]);

    $this->from(route('admin.invoices.index'))
        ->patch(route('admin.invoices.bulk-update'), [
            'ids' => $invoices->modelKeys(),
            'field' => 'tax_rate',
            'value' => 250,
        ])
        ->assertSessionHasErrors('value');
});

it('accepts a value at the declared boundary', function (): void {
    $invoices = Invoice::factory()->count(2)->create(['tax_rate' => 10]);

    $this->patch(route('admin.invoices.bulk-update'), [
        'ids' => $invoices->modelKeys(),
        'field' => 'tax_rate',
        'value' => 100,
    ])->assertRedirect(route('admin.invoices.index'));

    expect(Invoice::whereKey($invoices->modelKeys())->pluck('tax_rate')->all())->each->toEqual(100);
});

it('refuses a field the form marks disabled', function (string $field): void {
    $invoices = Invoice::factory()->count(2)->create();

    $this->from(route('admin.invoices.index'))
        ->patch(route('admin.invoices.bulk-update'), [
            'ids' => $invoices->modelKeys(),
            'field' => $field,
            'value' => 999,
        ])
        ->assertSessionHasErrors('field');
})->with(['tax_amount', 'total_amount']);
