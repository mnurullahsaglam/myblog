<?php

declare(strict_types=1);

use App\Contracts\ConvertsCurrency;
use App\Contracts\SyncsGitHubIssues;
use App\Models\Book;
use App\Models\Category;
use App\Models\Client;
use App\Models\Debt;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Invoice;
use App\Models\Post;
use App\Models\Project;
use App\Models\Repository;
use App\Models\Task;
use App\Models\User;
use App\Models\WakaTimeSummary;
use App\Models\WakaTimeSummaryEntry;
use App\Models\Writer;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->admin = User::factory()->create(['email' => 'admin@example.test']);
    $this->actingAs($this->admin);

    $github = Mockery::mock(SyncsGitHubIssues::class);
    $github->shouldIgnoreMissing();

    app()->instance(SyncsGitHubIssues::class, $github);

    $exchange = Mockery::mock(ConvertsCurrency::class);
    $exchange->shouldReceive('convert')->andReturnUsing(fn (float $amount): float => $amount * 2);
    $exchange->shouldIgnoreMissing();
    app()->instance(ConvertsCurrency::class, $exchange);

    $writer = Writer::factory()->create();
    Book::factory()->count(3)->create(['writer_id' => $writer->id]);
    Post::factory()->count(3)->create();
    Category::factory()->count(2)->create();
    Client::factory()->create();
    Project::factory()->create();
    Repository::factory()->count(2)->create();
    Invoice::factory()->count(2)->create();
    Income::factory()->count(3)->create();
    Expense::factory()->count(4)->create();
    Debt::factory()->count(2)->create();
    Task::factory()->count(4)->create();

    $summary = WakaTimeSummary::factory()->create(['date' => now()->toDateString(), 'total_seconds' => 7200]);
    WakaTimeSummaryEntry::factory()->count(4)->create(['waka_time_summary_id' => $summary->id]);
});

it('loads every admin page without javascript errors', function (string $routeName): void {
    visit(route($routeName))->assertNoJavaScriptErrors();
})->with([
    'admin.dashboard',
    'admin.coding-dashboard',
    'admin.tasks.board',
    'admin.settings',
    'admin.profile',
    'admin.posts.index',
    'admin.categories.index',
    'admin.books.index',
    'admin.writers.index',
    'admin.publishers.index',
    'admin.clients.index',
    'admin.projects.index',
    'admin.repositories.index',
    'admin.invoices.index',
    'admin.incomes.index',
    'admin.expenses.index',
    'admin.debts.index',
    'admin.waka-time-summaries.index',
]);

it('loads every create form without javascript errors', function (string $routeName): void {
    visit(route($routeName))->assertNoJavaScriptErrors();
})->with([
    'admin.posts.create',
    'admin.books.create',
    'admin.writers.create',
    'admin.expenses.create',
    'admin.debts.create',
    'admin.invoices.create',
    'admin.repositories.create',
]);

it('loads the login page without javascript errors', function (): void {
    auth()->logout();

    visit('/login')->assertNoJavaScriptErrors()->assertSee('Sign in');
});

it('redirects a guest away from the panel', function (): void {
    auth()->logout();

    visit(route('admin.dashboard'))->assertNoJavaScriptErrors()->assertSee('Sign in');
});

/**
 * The server side of bulk editing is covered in tests/Feature/Admin/BulkUpdateTest.php.
 * This checks the part only a browser can: the dialog renders, and its options
 * arrive from the table schema rather than being hardcoded in the component.
 */
it('opens the bulk edit dialog with options drawn from the schema', function (): void {
    $category = Category::factory()->create(['name' => 'Bulk Target']);

    visit(route('admin.posts.index'))
        ->assertNoJavaScriptErrors()
        ->click('thead .p-checkbox')
        ->assertSee('Edit 3')
        ->click('button:has-text("Edit 3")')
        ->assertSee('Field')
        ->assertSee('Categories')
        ->click('.p-dialog .p-multiselect')
        ->assertSee($category->name)
        ->assertNoJavaScriptErrors();
});
