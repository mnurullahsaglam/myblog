<?php

declare(strict_types=1);

use App\Actions\Resources\BulkDeleteRecords;
use App\Models\Post;

it('deletes every listed record and reports the count', function (): void {
    $posts = Post::factory()->count(3)->create();
    $survivor = Post::factory()->create();

    $deleted = app(BulkDeleteRecords::class)->handle(Post::class, $posts->modelKeys());

    expect($deleted)->toBe(3)
        ->and(Post::whereKey($survivor->getKey())->exists())->toBeTrue();
});

it('deletes nothing when given an empty list', function (): void {
    Post::factory()->count(2)->create();

    expect(app(BulkDeleteRecords::class)->handle(Post::class, []))->toBe(0)
        ->and(Post::count())->toBe(2);
});

it('reports rows actually deleted, not ids submitted', function (): void {
    $post = Post::factory()->create();

    $deleted = app(BulkDeleteRecords::class)->handle(Post::class, [$post->getKey(), 999_999]);

    expect($deleted)->toBe(1);
});

it('is idempotent when the same id appears twice', function (): void {
    $post = Post::factory()->create();

    $deleted = app(BulkDeleteRecords::class)->handle(Post::class, [$post->getKey(), $post->getKey()]);

    expect($deleted)->toBe(1)
        ->and(Post::count())->toBe(0);
});
