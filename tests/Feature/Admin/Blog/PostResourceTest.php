<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));
    Storage::fake('public');
});

it('lists posts with a schema and rows', function (): void {
    Post::factory()->count(3)->create();

    $this->get(route('admin.posts.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Blog/Posts/Index')
            ->has('schema.columns', 6)
            ->has('schema.filters', 2)
            ->has('rows.data', 3)
        );
});

it('renders cells rather than raw attributes', function (): void {
    Post::factory()->create(['title' => 'Learning Rust']);

    $this->get(route('admin.posts.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rows.data.0.cells.title.display', 'Learning Rust')
            ->where('rows.data.0.cells.created_at.display', fn (string $value): bool => $value !== '')
        );
});

it('counts attached categories', function (): void {
    $post = Post::factory()->create();
    $post->categories()->attach(Category::factory()->count(2)->create());

    $this->get(route('admin.posts.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rows.data.0.cells.categories_count.display', '2')
        );
});

it('searches posts by title and content', function (): void {
    Post::factory()->create(['title' => 'Learning Rust', 'content' => 'ownership']);
    Post::factory()->create(['title' => 'Something else', 'content' => 'nothing']);

    $this->get(route('admin.posts.index', ['search' => 'rust']))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('rows.data', 1));

    $this->get(route('admin.posts.index', ['search' => 'ownership']))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('rows.data', 1));
});

it('sorts posts by title', function (): void {
    Post::factory()->create(['title' => 'Zebra']);
    Post::factory()->create(['title' => 'Apple']);

    $this->get(route('admin.posts.index', ['sort' => 'title']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rows.data.0.cells.title.display', 'Apple')
        );
});

it('filters posts by whether they have an image', function (): void {
    Post::factory()->create(['image' => 'posts/one.png']);
    Post::factory()->create(['image' => null]);

    $this->get(route('admin.posts.index', ['filter' => ['image' => 'yes']]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('rows.data', 1));

    $this->get(route('admin.posts.index', ['filter' => ['image' => 'no']]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('rows.data', 1));
});

it('paginates', function (): void {
    Post::factory()->count(30)->create();

    $this->get(route('admin.posts.index', ['perPage' => 10, 'page' => 2]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('rows.data', 10)
            ->where('rows.current_page', 2)
            ->where('rows.total', 30)
        );
});

it('renders the create form with category options', function (): void {
    Category::factory()->create(['name' => 'Rust']);

    $this->get(route('admin.posts.create'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Blog/Posts/Create')
            ->has('schema.fields', 7)
            ->where('values.title', null)
            ->where('values.slug', null)
        );
});

it('creates a post', function (): void {
    $this->post(route('admin.posts.store'), [
        'title' => 'Learning Rust',
        'slug' => 'learning-rust',
        'content' => '# Hello',
    ])->assertRedirect(route('admin.posts.index'));

    $this->assertDatabaseHas('posts', ['title' => 'Learning Rust', 'slug' => 'learning-rust']);
});

it('syncs categories rather than mass assigning them', function (): void {
    $categories = Category::factory()->count(2)->create();

    $this->post(route('admin.posts.store'), [
        'title' => 'Learning Rust',
        'slug' => 'learning-rust',
        'content' => '# Hello',
        'categories' => $categories->pluck('id')->all(),
    ])->assertSessionHasNoErrors();

    $post = Post::where('slug', 'learning-rust')->firstOrFail();

    expect($post->categories)->toHaveCount(2);
});

it('stores an uploaded image', function (): void {
    $this->post(route('admin.posts.store'), [
        'title' => 'With Picture',
        'slug' => 'with-picture',
        'content' => 'x',
        'image' => UploadedFile::fake()->image('cover.jpg'),
    ]);

    $post = Post::where('slug', 'with-picture')->firstOrFail();

    expect($post->image)->toStartWith('posts/');
    Storage::disk('public')->assertExists($post->image);
});

it('rejects a post without a title or content', function (): void {
    $this->from(route('admin.posts.create'))
        ->post(route('admin.posts.store'), ['title' => '', 'slug' => '', 'content' => ''])
        ->assertSessionHasErrors(['title', 'slug', 'content']);

    expect(Post::count())->toBe(0);
});

it('rejects a duplicate slug', function (): void {
    Post::factory()->create(['slug' => 'taken']);

    $this->from(route('admin.posts.create'))
        ->post(route('admin.posts.store'), ['title' => 'New', 'slug' => 'taken', 'content' => 'x'])
        ->assertSessionHasErrors('slug');
});

it('rejects a category that does not exist', function (): void {
    $this->from(route('admin.posts.create'))
        ->post(route('admin.posts.store'), [
            'title' => 'New',
            'slug' => 'new',
            'content' => 'x',
            'categories' => [999999],
        ])
        ->assertSessionHasErrors('categories.0');
});

it('renders the edit form filled with the record', function (): void {
    $post = Post::factory()->create(['title' => 'Learning Rust']);
    $post->categories()->attach(Category::factory()->create());

    $this->get(route('admin.posts.edit', $post))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Blog/Posts/Edit')
            ->where('values.title', 'Learning Rust')
            ->has('values.categories', 1)
            ->has('placeholders.created_at')
            ->has('placeholders.updated_at')
        );
});

it('updates a post', function (): void {
    $post = Post::factory()->create(['title' => 'Old']);

    $this->put(route('admin.posts.update', $post), [
        'title' => 'New',
        'slug' => $post->slug,
        'content' => 'updated',
    ])->assertRedirect(route('admin.posts.index'));

    expect($post->fresh()->title)->toBe('New');
});

it('keeps the stored image when no new file is uploaded', function (): void {
    $post = Post::factory()->create(['image' => 'posts/existing.jpg']);

    $this->put(route('admin.posts.update', $post), [
        'title' => 'Renamed',
        'slug' => $post->slug,
        'content' => 'x',
    ]);

    expect($post->fresh()->image)->toBe('posts/existing.jpg');
});

it('replaces categories on update', function (): void {
    $post = Post::factory()->create();
    $post->categories()->attach(Category::factory()->count(3)->create());
    $replacement = Category::factory()->create();

    $this->put(route('admin.posts.update', $post), [
        'title' => $post->title,
        'slug' => $post->slug,
        'content' => 'x',
        'categories' => [$replacement->id],
    ]);

    expect($post->fresh()->categories->pluck('id')->all())->toBe([$replacement->id]);
});

it('deletes a post', function (): void {
    $post = Post::factory()->create();

    $this->delete(route('admin.posts.destroy', $post))->assertRedirect(route('admin.posts.index'));

    expect(Post::find($post->id))->toBeNull();
});

it('bulk deletes posts', function (): void {
    $posts = Post::factory()->count(3)->create();
    $keep = Post::factory()->create();

    $this->delete(route('admin.posts.bulk-destroy'), ['ids' => $posts->pluck('id')->all()])
        ->assertRedirect(route('admin.posts.index'));

    expect(Post::count())->toBe(1);
    expect(Post::first()->id)->toBe($keep->id);
});

it('rejects a bulk delete of unknown ids', function (): void {
    $this->from(route('admin.posts.index'))
        ->delete(route('admin.posts.bulk-destroy'), ['ids' => [999999]])
        ->assertSessionHasErrors('ids.0');
});

it('flashes a notification after creating', function (): void {
    $this->post(route('admin.posts.store'), [
        'title' => 'Learning Rust',
        'slug' => 'learning-rust',
        'content' => '# Hello',
    ]);

    expect(session('flash.notification'))
        ->toHaveKey('variant', 'success')
        ->toHaveKey('title', 'Post created');
});

it('appears in the navigation', function (): void {
    $blog = collect(App\Support\Navigation::clusters())->firstWhere('label', 'Blog');

    expect($blog['items'])->toContain([
        'label' => 'Posts',
        'route' => 'admin.posts.index',
        'icon' => 'pi pi-file-edit',
    ]);
});

it('forbids a non-admin', function (): void {
    $this->actingAs(User::factory()->create(['email' => 'nobody@example.test']));

    $this->get(route('admin.posts.index'))->assertForbidden();
});
