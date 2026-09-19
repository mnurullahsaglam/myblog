<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Expense;
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
