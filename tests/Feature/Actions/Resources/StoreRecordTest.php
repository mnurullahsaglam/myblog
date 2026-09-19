<?php

declare(strict_types=1);

use App\Actions\Resources\StoreRecord;
use App\Models\Category;
use App\Models\Post;
use Illuminate\Database\QueryException;

it('creates a record from the partitioned attributes', function (): void {
    $post = resolve(StoreRecord::class)->handle(Post::class, [
        'attributes' => ['title' => 'A New Post', 'content' => 'Body copy.', 'image' => 'posts/a.png'],
        'relations' => [],
    ]);

    expect($post)->toBeInstanceOf(Post::class)
        ->and($post->exists)->toBeTrue()
        ->and($post->title)->toBe('A New Post');
});

it('syncs many-to-many relations declared in the partition', function (): void {
    $categories = Category::factory()->count(2)->create();

    $post = resolve(StoreRecord::class)->handle(Post::class, [
        'attributes' => ['title' => 'Tagged', 'content' => 'Body copy.', 'image' => 'posts/a.png'],
        'relations' => ['categories' => $categories->modelKeys()],
    ]);

    expect($post->categories()->pluck('categories.id')->all())
        ->toEqualCanonicalizing($categories->modelKeys());
});

it('attaches nothing when the relation list is empty', function (): void {
    $post = resolve(StoreRecord::class)->handle(Post::class, [
        'attributes' => ['title' => 'Untagged', 'content' => 'Body copy.', 'image' => 'posts/a.png'],
        'relations' => ['categories' => []],
    ]);

    expect($post->categories()->count())->toBe(0);
});

it('ignores a relation key the model does not define', function (): void {
    $post = resolve(StoreRecord::class)->handle(Post::class, [
        'attributes' => ['title' => 'Unknown Relation', 'content' => 'Body copy.', 'image' => 'posts/a.png'],
        'relations' => ['nonsense' => [1, 2, 3]],
    ]);

    expect($post->exists)->toBeTrue();
});

it('lets the model generate its slug', function (): void {
    $post = resolve(StoreRecord::class)->handle(Post::class, [
        'attributes' => ['title' => 'Slug Me Please', 'content' => 'Body copy.', 'image' => 'posts/a.png'],
        'relations' => [],
    ]);

    expect($post->slug)->toBe('slug-me-please');
});

it('creates nothing when the attributes are rejected by the database', function (): void {
    expect(fn () => resolve(StoreRecord::class)->handle(Post::class, [
        'attributes' => ['title' => 'Missing Required Columns'],
        'relations' => [],
    ]))->toThrow(QueryException::class)
        ->and(Post::where('title', 'Missing Required Columns')->exists())->toBeFalse();
});
