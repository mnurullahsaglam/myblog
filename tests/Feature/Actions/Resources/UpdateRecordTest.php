<?php

declare(strict_types=1);

use App\Actions\Resources\UpdateRecord;
use App\Models\Category;
use App\Models\Post;
use Illuminate\Database\QueryException;

it('applies the partitioned attributes to an existing record', function (): void {
    $post = Post::factory()->create(['title' => 'Before']);

    $updated = app(UpdateRecord::class)->handle($post, [
        'attributes' => ['title' => 'After'],
        'relations' => [],
    ]);

    expect($updated->refresh()->title)->toBe('After');
});

it('replaces the relation set rather than appending to it', function (): void {
    $post = Post::factory()->create();
    $original = Category::factory()->count(2)->create();
    $post->categories()->sync($original->modelKeys());

    $replacement = Category::factory()->count(3)->create();

    app(UpdateRecord::class)->handle($post, [
        'attributes' => [],
        'relations' => ['categories' => $replacement->modelKeys()],
    ]);

    expect($post->categories()->pluck('categories.id')->all())
        ->toEqualCanonicalizing($replacement->modelKeys());
});

it('detaches every relation when given an empty list', function (): void {
    $post = Post::factory()->create();
    $post->categories()->sync(Category::factory()->count(2)->create()->modelKeys());

    app(UpdateRecord::class)->handle($post, [
        'attributes' => [],
        'relations' => ['categories' => []],
    ]);

    expect($post->categories()->count())->toBe(0);
});

it('leaves relations alone when the partition names none', function (): void {
    $post = Post::factory()->create();
    $categories = Category::factory()->count(2)->create();
    $post->categories()->sync($categories->modelKeys());

    app(UpdateRecord::class)->handle($post, [
        'attributes' => ['title' => 'Renamed'],
        'relations' => [],
    ]);

    expect($post->categories()->count())->toBe(2);
});

it('leaves the record untouched when the update is rejected', function (): void {
    $post = Post::factory()->create(['title' => 'Original']);

    expect(fn () => app(UpdateRecord::class)->handle($post, [
        'attributes' => ['title' => 'Renamed', 'content' => null],
        'relations' => [],
    ]))->toThrow(QueryException::class);

    expect($post->fresh()->title)->toBe('Original');
});
