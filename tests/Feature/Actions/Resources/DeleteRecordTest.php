<?php

declare(strict_types=1);

use App\Actions\Resources\DeleteRecord;
use App\Models\Category;
use App\Models\Post;

it('deletes the record', function (): void {
    $post = Post::factory()->create();

    app(DeleteRecord::class)->handle($post);

    expect(Post::whereKey($post->getKey())->exists())->toBeFalse();
});

it('leaves other records alone', function (): void {
    $doomed = Post::factory()->create();
    $survivor = Post::factory()->create();

    app(DeleteRecord::class)->handle($doomed);

    expect(Post::whereKey($survivor->getKey())->exists())->toBeTrue();
});

/**
 * Known gap, pinned rather than asserted as desirable: nothing detaches the
 * categoriables pivot when a post is deleted, so its rows outlive the record.
 * The pivot table has no cascade and no model hook does it. Extracting this
 * action did not introduce the behaviour and does not change it.
 */
it('leaves the category pivot rows behind, which is the current behaviour', function (): void {
    $post = Post::factory()->create();
    $post->categories()->sync(Category::factory()->count(2)->create()->modelKeys());

    app(DeleteRecord::class)->handle($post);

    expect(DB::table('categoriables')->where('categoriable_id', $post->getKey())->count())->toBe(2);
});
