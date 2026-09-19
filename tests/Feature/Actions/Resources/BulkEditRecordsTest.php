<?php

declare(strict_types=1);

use App\Actions\Resources\BulkEditRecords;
use App\Models\Category;
use App\Models\Post;
use App\Models\Task;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('applies the attribute to every listed record and reports the count', function (): void {
    $posts = Post::factory()->count(3)->create(['content' => 'Before']);
    $untouched = Post::factory()->create(['content' => 'Before']);

    $updated = resolve(BulkEditRecords::class)->handle(Post::class, $posts->modelKeys(), [
        'attributes' => ['content' => 'After'],
        'relations' => [],
    ]);

    expect($updated)->toBe(3)
        ->and(Post::whereKey($posts->modelKeys())->pluck('content')->all())->each->toBe('After')
        ->and($untouched->fresh()->content)->toBe('Before');
});

it('changes nothing when given an empty list', function (): void {
    Post::factory()->count(2)->create(['content' => 'Before']);

    $updated = resolve(BulkEditRecords::class)->handle(Post::class, [], [
        'attributes' => ['content' => 'After'],
        'relations' => [],
    ]);

    expect($updated)->toBe(0)
        ->and(Post::pluck('content')->all())->each->toBe('Before');
});

it('reports rows actually updated, not ids submitted', function (): void {
    $post = Post::factory()->create();

    $updated = resolve(BulkEditRecords::class)->handle(Post::class, [$post->getKey(), 999_999], [
        'attributes' => ['content' => 'After'],
        'relations' => [],
    ]);

    expect($updated)->toBe(1);
});

it('syncs a many-to-many relation across the selection', function (): void {
    $posts = Post::factory()->count(2)->create();
    $categories = Category::factory()->count(2)->create();

    resolve(BulkEditRecords::class)->handle(Post::class, $posts->modelKeys(), [
        'attributes' => [],
        'relations' => ['categories' => $categories->modelKeys()],
    ]);

    foreach ($posts as $post) {
        expect($post->categories()->pluck('categories.id')->all())
            ->toEqualCanonicalizing($categories->modelKeys());
    }
});

it('replaces the relation set rather than appending to it', function (): void {
    $post = Post::factory()->create();
    $post->categories()->sync(Category::factory()->count(3)->create()->modelKeys());
    $replacement = Category::factory()->count(1)->create();

    resolve(BulkEditRecords::class)->handle(Post::class, [$post->getKey()], [
        'attributes' => [],
        'relations' => ['categories' => $replacement->modelKeys()],
    ]);

    expect($post->categories()->pluck('categories.id')->all())
        ->toEqualCanonicalizing($replacement->modelKeys());
});

it('clears a relation when given an empty list', function (): void {
    $post = Post::factory()->create();
    $post->categories()->sync(Category::factory()->count(2)->create()->modelKeys());

    resolve(BulkEditRecords::class)->handle(Post::class, [$post->getKey()], [
        'attributes' => [],
        'relations' => ['categories' => []],
    ]);

    expect($post->categories()->count())->toBe(0);
});

it('writes through the models so observers see the change', function (): void {
    $seen = [];

    Post::updated(function (Post $post) use (&$seen): void {
        $seen[] = $post->getKey();
    });

    $posts = Post::factory()->count(2)->create();

    resolve(BulkEditRecords::class)->handle(Post::class, $posts->modelKeys(), [
        'attributes' => ['content' => 'After'],
        'relations' => [],
    ]);

    expect($seen)->toEqualCanonicalizing($posts->modelKeys());
});

it('lets the task observer reindex when the status changes in bulk', function (): void {
    $tasks = Task::factory()->count(2)->create(['status' => 'todo']);

    resolve(BulkEditRecords::class)->handle(Task::class, $tasks->modelKeys(), [
        'attributes' => ['status' => 'completed'],
        'relations' => [],
    ]);

    expect(Task::whereKey($tasks->modelKeys())->pluck('status')->all())->each->toBe('completed');
});

it('applies nothing when one record in the selection is rejected', function (): void {
    $posts = Post::factory()->count(3)->create(['content' => 'Before']);

    expect(fn () => resolve(BulkEditRecords::class)->handle(Post::class, $posts->modelKeys(), [
        'attributes' => ['content' => null],
        'relations' => [],
    ]))->toThrow(QueryException::class)
        ->and(Post::pluck('content')->all())->each->toBe('Before');
});

it('leaves pivot rows of records outside the selection alone', function (): void {
    $target = Post::factory()->create();
    $bystander = Post::factory()->create();
    $original = Category::factory()->count(2)->create();

    $bystander->categories()->sync($original->modelKeys());

    resolve(BulkEditRecords::class)->handle(Post::class, [$target->getKey()], [
        'attributes' => [],
        'relations' => ['categories' => Category::factory()->count(1)->create()->modelKeys()],
    ]);

    expect($bystander->categories()->count())->toBe(2)
        ->and(DB::table('categoriables')->where('categoriable_id', $bystander->getKey())->count())->toBe(2);
});
