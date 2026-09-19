<?php

declare(strict_types=1);

use App\Models\Book;
use App\Models\Category;
use App\Models\Post;
use App\Models\Publisher;
use App\Models\Writer;

it('generates a post slug from the title', function (): void {
    $post = Post::factory()->create(['title' => 'Learning Rust', 'slug' => null]);

    expect($post->slug)->toBe('learning-rust');
});

it('keeps post slugs unique', function (): void {
    Post::factory()->create(['title' => 'Learning Rust', 'slug' => null]);
    $second = Post::factory()->create(['title' => 'Learning Rust', 'slug' => null]);

    expect($second->slug)->not->toBe('learning-rust')
        ->toStartWith('learning-rust');
});

it('generates slugs from the name for models that have one', function (string $model): void {
    $record = $model::factory()->create(['name' => 'Systems Programming', 'slug' => null]);

    expect($record->slug)->toBe('systems-programming');
})->with([Category::class, Publisher::class, Writer::class, Book::class]);

it('resolves models by slug as the route key', function (string $model): void {
    expect((new $model)->getRouteKeyName())->toBe('slug');
})->with([Post::class, Category::class, Publisher::class, Writer::class, Book::class]);

it('regenerates the slug when the title changes', function (): void {
    $post = Post::factory()->create(['title' => 'Learning Rust', 'slug' => null]);

    $post->update(['title' => 'Learning Go']);

    expect($post->fresh()->slug)->toBe('learning-go');
});

it('honours a slug supplied explicitly on create', function (): void {
    $post = Post::factory()->create(['title' => 'Learning Rust', 'slug' => 'hand-written']);

    expect($post->slug)->toBe('hand-written');
});

it('honours a slug supplied explicitly alongside a title change', function (): void {
    $post = Post::factory()->create(['title' => 'Learning Rust', 'slug' => null]);

    $post->update(['title' => 'Learning Go', 'slug' => 'kept-by-hand']);

    expect($post->fresh()->slug)->toBe('kept-by-hand');
});
