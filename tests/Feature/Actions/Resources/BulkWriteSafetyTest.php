<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Post;
use Illuminate\Support\Facades\DB;

it('fires no model events for a query-builder delete, which is why bulk delete loops', function (): void {
    $post = Post::factory()->create();
    $post->categories()->sync(Category::factory()->count(2)->create()->modelKeys());

    Post::query()->whereKey($post->getKey())->delete();

    expect(DB::table('categoriables')->where('categoriable_id', $post->getKey())->count())
        ->toBe(2, 'Laravel now fires events for mass deletes; the defensive loop may be revisited.');
});

it('fires no model events for a query-builder update either', function (): void {
    $seen = [];

    Post::updated(function (Post $post) use (&$seen): void {
        $seen[] = $post->getKey();
    });

    $post = Post::factory()->create(['title' => 'Before']);

    Post::query()->whereKey($post->getKey())->update(['title' => 'After']);

    expect($post->fresh()->title)->toBe('After')
        ->and($seen)->toBe([], 'Laravel now fires events for mass updates; a bulk edit action could use one.');
});

it('fires model events when the same write goes through the model', function (): void {
    $seen = [];

    Post::updated(function (Post $post) use (&$seen): void {
        $seen[] = $post->getKey();
    });

    $post = Post::factory()->create(['title' => 'Before']);
    $post->update(['title' => 'After']);

    expect($seen)->toBe([$post->getKey()]);
});

it('has no bulk write in app that bypasses the models', function (): void {
    $offenders = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path()));

    foreach ($files as $file) {
        if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        foreach (file($file->getPathname()) ?: [] as $number => $line) {
            $bypassesModels = preg_match(
                '/(::query\(\)|->newQuery\(\)|->whereIn\(|->whereKey\(|::where\()[^;]*->(update|delete)\(/',
                $line,
            );

            if ($bypassesModels === 1) {
                $offenders[] = str_replace(base_path().'/', '', $file->getPathname()).':'.($number + 1);
            }
        }
    }

    expect($offenders)->toBe([], implode("\n", [
        'A bulk write is going through the query builder, which fires no model events.',
        'Post and Book detach their category pivot on deleting; this would orphan those rows.',
        'Load the records and write through them, as App\Actions\Resources\BulkDeleteRecords does.',
        ...$offenders,
    ]));
});
