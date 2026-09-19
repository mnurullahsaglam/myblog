<?php

declare(strict_types=1);

use App\Models\Book;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use App\Support\Navigation;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));
});

it('lists categories sorted by name', function (): void {
    Category::factory()->create(['name' => 'Zebra']);
    Category::factory()->create(['name' => 'Apple']);

    $this->get(route('admin.categories.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('General/Categories/Index')
            ->where('rows.data.0.cells.name.display', 'Apple')
        );
});

it('counts attached posts and books separately', function (): void {
    $category = Category::factory()->create();
    $category->posts()->attach(Post::factory()->count(2)->create());
    $category->books()->attach(Book::factory()->count(3)->create());

    $this->get(route('admin.categories.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rows.data.0.cells.posts_count.display', '2')
            ->where('rows.data.0.cells.books_count.display', '3')
        );
});

it('searches categories by name', function (): void {
    Category::factory()->create(['name' => 'Rust']);
    Category::factory()->create(['name' => 'Elixir']);

    $this->get(route('admin.categories.index', ['search' => 'rust']))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('rows.data', 1));
});

it('creates a standalone category with no parent', function (): void {
    $this->post(route('admin.categories.store'), ['name' => 'Rust', 'slug' => 'rust'])
        ->assertRedirect(route('admin.categories.index'));

    $category = Category::where('slug', 'rust')->firstOrFail();

    expect($category->name)->toBe('Rust')
        ->and($category->posts)->toBeEmpty()
        ->and($category->books)->toBeEmpty();
});

it('requires a name and a unique slug', function (): void {
    Category::factory()->create(['slug' => 'taken']);

    $this->from(route('admin.categories.create'))
        ->post(route('admin.categories.store'), ['name' => '', 'slug' => 'taken'])
        ->assertSessionHasErrors(['name', 'slug']);
});

it('lets a category keep its own slug on update', function (): void {
    $category = Category::factory()->create(['name' => 'Old']);

    $this->put(route('admin.categories.update', $category), ['name' => 'New', 'slug' => $category->slug])
        ->assertSessionHasNoErrors();

    expect($category->fresh()->name)->toBe('New');
});

it('deletes a category and detaches it from posts', function (): void {
    $category = Category::factory()->create();
    $post = Post::factory()->create();
    $post->categories()->attach($category);

    $this->delete(route('admin.categories.destroy', $category));

    expect(Category::find($category->id))->toBeNull()
        ->and($post->fresh()->categories)->toBeEmpty();
});

it('bulk deletes categories', function (): void {
    $categories = Category::factory()->count(3)->create();

    $this->delete(route('admin.categories.bulk-destroy'), ['ids' => $categories->pluck('id')->all()]);

    expect(Category::count())->toBe(0);
});

it('appears in the General cluster', function (): void {
    $general = collect(Navigation::clusters())->firstWhere('label', 'General');

    expect(collect($general['items'])->pluck('route'))->toContain('admin.categories.index');
});
