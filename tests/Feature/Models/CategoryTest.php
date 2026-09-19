<?php

declare(strict_types=1);

use App\Models\Book;
use App\Models\Category;
use App\Models\Post;

it('attaches one category to many posts', function (): void {
    $category = Category::factory()->create(['name' => 'Rust']);
    $posts = Post::factory()->count(3)->create();

    $category->posts()->attach($posts);

    expect($category->posts)->toHaveCount(3)
        ->and($posts->first()->categories->pluck('name')->all())->toBe(['Rust']);
});

it('shares one category across posts and books', function (): void {
    $category = Category::factory()->create(['name' => 'Systems']);
    $post = Post::factory()->create();
    $book = Book::factory()->create();

    $post->categories()->attach($category);
    $book->categories()->attach($category);

    expect($category->posts)->toHaveCount(1)
        ->and($category->books)->toHaveCount(1)
        ->and($post->categories->first()->is($category))->toBeTrue()
        ->and($book->categories->first()->is($category))->toBeTrue();
});

it('gives a post many categories', function (): void {
    $post = Post::factory()->create();

    $post->categories()->attach(Category::factory()->count(4)->create());

    expect($post->categories)->toHaveCount(4);
});

it('syncs categories without duplicating rows', function (): void {
    $post = Post::factory()->create();
    $categories = Category::factory()->count(2)->create();

    $post->categories()->sync($categories);
    $post->categories()->sync($categories);

    expect($post->fresh()->categories)->toHaveCount(2);
});

it('detaches a category from a post without deleting it', function (): void {
    $post = Post::factory()->create();
    $category = Category::factory()->create();
    $post->categories()->attach($category);

    $post->categories()->detach($category);

    expect($post->fresh()->categories)->toBeEmpty()
        ->and(Category::find($category->id))->not->toBeNull();
});

it('drops pivot rows when the category is deleted', function (): void {
    $post = Post::factory()->create();
    $category = Category::factory()->create();
    $post->categories()->attach($category);

    $category->delete();

    expect($post->fresh()->categories)->toBeEmpty();
});

it('generates a distinct slug for a duplicate name', function (): void {
    $first = Category::factory()->create(['name' => 'Rust']);
    $second = Category::factory()->create(['name' => 'Rust']);

    expect($second->slug)->not->toBe($first->slug);
});

it('exists standalone with no attachments', function (): void {
    $category = Category::factory()->create(['name' => 'Orphan']);

    expect($category->exists)->toBeTrue()
        ->and($category->posts)->toBeEmpty()
        ->and($category->books)->toBeEmpty();
});

it('does not delete the records it was attached to', function (): void {
    $category = Category::factory()->create();
    $post = Post::factory()->create();

    $category->posts()->attach($post);
    $category->delete();

    expect(Post::whereKey($post->getKey())->exists())->toBeTrue();
});

it('detaches a book when the book is deleted', function (): void {
    $book = Book::factory()->create();
    $book->categories()->sync(Category::factory()->count(2)->create()->modelKeys());

    $book->delete();

    expect(DB::table('categoriables')->where('categoriable_id', $book->getKey())
        ->where('categoriable_type', Book::class)->count())->toBe(0);
});
