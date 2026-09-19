<?php

declare(strict_types=1);

use App\Actions\Resources\DeleteRecord;
use App\Models\Category;
use App\Models\Post;

it('deletes the record', function (): void {
    $post = Post::factory()->create();

    resolve(DeleteRecord::class)->handle($post);

    expect(Post::whereKey($post->getKey())->exists())->toBeFalse();
});

it('leaves other records alone', function (): void {
    $doomed = Post::factory()->create();
    $survivor = Post::factory()->create();

    resolve(DeleteRecord::class)->handle($doomed);

    expect(Post::whereKey($survivor->getKey())->exists())->toBeTrue();
});

it('detaches the category pivot rows along with the record', function (): void {
    $post = Post::factory()->create();
    $post->categories()->sync(Category::factory()->count(2)->create()->modelKeys());

    resolve(DeleteRecord::class)->handle($post);

    expect(DB::table('categoriables')->where('categoriable_id', $post->getKey())->count())->toBe(0);
});

it('leaves another record\'s pivot rows alone', function (): void {
    $doomed = Post::factory()->create();
    $survivor = Post::factory()->create();
    $categories = Category::factory()->count(2)->create();

    $doomed->categories()->sync($categories->modelKeys());
    $survivor->categories()->sync($categories->modelKeys());

    resolve(DeleteRecord::class)->handle($doomed);

    expect($survivor->categories()->count())->toBe(2);
});

it('does not delete the categories themselves', function (): void {
    $post = Post::factory()->create();
    $categories = Category::factory()->count(2)->create();
    $post->categories()->sync($categories->modelKeys());

    resolve(DeleteRecord::class)->handle($post);

    expect(Category::whereKey($categories->modelKeys())->count())->toBe(2);
});
