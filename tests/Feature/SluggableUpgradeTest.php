<?php

declare(strict_types=1);

use App\Models\Post;
use App\Models\Writer;

it('keeps generating the slug format the site already publishes', function (string $title, string $expected): void {
    $post = Post::factory()->create(['title' => $title, 'slug' => null]);

    expect($post->slug)->toBe($expected);
})->with([
    'plain words' => ['Hello World', 'hello-world'],
    'punctuation is dropped' => ['Hello, World!', 'hello-world'],
    'accents are folded' => ['Über Café', 'uber-cafe'],
    'numbers survive' => ['Rust in 2026', 'rust-in-2026'],
    'runs of spaces collapse' => ['Too   Many    Spaces', 'too-many-spaces'],
]);

it('appends a suffix when a slug already exists', function (): void {
    Post::factory()->create(['title' => 'Duplicate Title', 'slug' => null]);
    $second = Post::factory()->create(['title' => 'Duplicate Title', 'slug' => null]);

    expect($second->slug)->not->toBe('duplicate-title')
        ->and($second->slug)->toStartWith('duplicate-title');
});

it('slugs models that use the default name field', function (): void {
    $writer = Writer::factory()->create(['name' => 'Ursula K. Le Guin', 'slug' => null]);

    expect($writer->slug)->toBe('ursula-k-le-guin');
});

it('keeps an explicitly supplied slug instead of regenerating it', function (): void {
    $post = Post::factory()->create(['title' => 'Generated Would Differ', 'slug' => 'hand-picked']);

    expect($post->slug)->toBe('hand-picked');
});
