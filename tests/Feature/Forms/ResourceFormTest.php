<?php

declare(strict_types=1);

use App\Enums\Currencies;
use App\Forms\Field;
use App\Forms\ResourceForm;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Post;

final class FixturePostForm extends ResourceForm
{
    #[Override]
    protected int $columns = 1;

    protected function fields(): array
    {
        return [
            Field::text('title')->required(),
            Field::readonlyCode('slug')->slugFrom('title'),
            Field::markdown('content')->required(),
            Field::multiRelationship('categories', Category::class, 'name'),
            Field::placeholder('created_at')->label('Created'),
        ];
    }
}

final class FixtureDefaultsForm extends ResourceForm
{
    protected function fields(): array
    {
        return [
            Field::select('status', ['pending' => 'Pending', 'paid' => 'Paid'])->default('pending'),
            Field::text('note'),
        ];
    }
}

it('emits a schema with every field', function (): void {
    $schema = (new FixturePostForm)->schema();

    expect($schema['columns'])->toBe(1)
        ->and(array_column($schema['fields'], 'key'))->toBe(['title', 'slug', 'content', 'categories', 'created_at']);
});

it('returns empty values for a new record', function (): void {
    expect((new FixturePostForm)->values(null))->toBe([
        'title' => null,
        'slug' => null,
        'content' => null,
        'categories' => null,
    ]);
});

it('applies field defaults for a new record', function (): void {
    expect((new FixtureDefaultsForm)->values(null))->toBe(['status' => 'pending', 'note' => null]);
});

it('fills values from an existing record', function (): void {
    $post = Post::factory()->create(['title' => 'Learning Rust', 'content' => '# Hi']);

    expect((new FixturePostForm)->values($post))->toMatchArray([
        'title' => 'Learning Rust',
        'slug' => $post->slug,
        'content' => '# Hi',
    ]);
});

it('fills a many-to-many field with the related ids', function (): void {
    $post = Post::factory()->create();
    $categories = Category::factory()->count(2)->create();
    $post->categories()->attach($categories);

    expect((new FixturePostForm)->values($post->fresh())['categories'])
        ->toEqualCanonicalizing($categories->pluck('id')->all());
});

it('gives an empty array for a record with no related rows', function (): void {
    $post = Post::factory()->create();

    expect((new FixturePostForm)->values($post)['categories'])->toBe([]);
});

it('excludes placeholder fields from the value map', function (): void {
    expect((new FixturePostForm)->values(Post::factory()->create()))->not->toHaveKey('created_at');
});

it('renders placeholder values separately', function (): void {
    $post = Post::factory()->create();

    expect((new FixturePostForm)->placeholders($post))->toHaveKey('created_at');
    expect((new FixturePostForm)->placeholders($post)['created_at'])->toBeString()->not->toBeEmpty();
});

it('returns no placeholders for a new record', function (): void {
    expect((new FixturePostForm)->placeholders(null))->toBeEmpty();
});

it('lists the relation keys that need syncing', function (): void {
    expect((new FixturePostForm)->relationKeys())->toBe(['categories'])
        ->and((new FixtureDefaultsForm)->relationKeys())->toBeEmpty();
});

it('partitions input into attributes and relations', function (): void {
    $result = (new FixturePostForm)->partition([
        'title' => 'Learning Rust',
        'content' => '# Hi',
        'categories' => [1, 2],
    ]);
    expect($result)->toMatchArray(['attributes' => ['title' => 'Learning Rust', 'content' => '# Hi'], 'relations' => ['categories' => [1, 2]]]);
});

it('treats an absent relation as untouched', function (): void {
    $result = (new FixturePostForm)->partition(['title' => 'Learning Rust']);
    expect($result)->toMatchArray(['attributes' => ['title' => 'Learning Rust'], 'relations' => []]);
});

it('treats a null relation as an empty sync', function (): void {
    $result = (new FixturePostForm)->partition(['title' => 'x', 'categories' => null]);

    expect($result['relations'])->toBe(['categories' => []]);
});

it('unwraps a backed enum into its value', function (): void {
    $form = new class extends ResourceForm
    {
        protected function fields(): array
        {
            return [Field::enum('currency', Currencies::class)];
        }
    };

    $expense = Expense::factory()->create(['currency' => 'USD']);

    expect($form->values($expense)['currency'])->toBe('USD');
});
