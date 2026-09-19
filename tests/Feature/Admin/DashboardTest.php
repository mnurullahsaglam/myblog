<?php

declare(strict_types=1);

use App\Models\Book;
use App\Models\Client;
use App\Models\Debt;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Post;
use App\Models\Publisher;
use App\Models\Repository;
use App\Models\Task;
use App\Models\User;
use App\Models\Writer;
use App\Services\ExchangeRateService;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));

    $exchange = Mockery::mock(ExchangeRateService::class);
    $exchange->shouldReceive('convert')->andReturnUsing(fn (float $amount): float => $amount * 2);
    $exchange->shouldIgnoreMissing();
    app()->instance(ExchangeRateService::class, $exchange);
});

it('renders all three overview groups', function (): void {
    $this->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Dashboard')
            ->has('budget', 4)
            ->has('work', 4)
            ->has('library', 4)
        );
});

it('totals this month income and spending', function (): void {
    Income::factory()->create(['amount' => 1000, 'currency' => 'TRY', 'date' => now()->toDateString()]);
    Expense::factory()->create(['amount' => 400, 'currency' => 'TRY', 'date' => now()->toDateString()]);
    // Last month, so excluded.
    Income::factory()->create(['amount' => 9999, 'currency' => 'TRY', 'date' => now()->subMonth()->toDateString()]);

    $this->get(route('admin.dashboard'))
        ->assertInertia(function (AssertableInertia $page): void {
            $tiles = collect($page->toArray()['props']['budget'])->keyBy('label');

            expect($tiles['Income this month']['value'])->toBe('₺1,000.00')
                ->and($tiles['Spent this month']['value'])->toBe('₺400.00')
                ->and($tiles['Net']['value'])->toBe('₺600.00')
                ->and($tiles['Net']['caption'])->toBe('in the black');
        });
});

it('converts foreign currency into the base currency', function (): void {
    Income::factory()->create(['amount' => 100, 'currency' => 'USD', 'date' => now()->toDateString()]);

    $this->get(route('admin.dashboard'))
        ->assertInertia(function (AssertableInertia $page): void {
            $tiles = collect($page->toArray()['props']['budget'])->keyBy('label');

            expect($tiles['Income this month']['value'])->toBe('₺200.00');
        });
});

it('says so when spending exceeds income', function (): void {
    Expense::factory()->create(['amount' => 500, 'currency' => 'TRY', 'date' => now()->toDateString()]);

    $this->get(route('admin.dashboard'))
        ->assertInertia(function (AssertableInertia $page): void {
            $tiles = collect($page->toArray()['props']['budget'])->keyBy('label');

            expect($tiles['Net']['caption'])->toBe('in the red');
        });
});

it('counts outstanding and overdue debts', function (): void {
    Debt::factory()->overdue()->create();
    Debt::factory()->create(['status' => 'pending', 'due_date' => now()->addYear()->toDateString()]);
    Debt::factory()->paid()->create();

    $this->get(route('admin.dashboard'))
        ->assertInertia(function (AssertableInertia $page): void {
            $tiles = collect($page->toArray()['props']['budget'])->keyBy('label');

            expect($tiles['Outstanding debts']['value'])->toBe('2')
                ->and($tiles['Outstanding debts']['caption'])->toBe('1 overdue');
        });
});

it('survives an exchange rate failure', function (): void {
    $failing = Mockery::mock(ExchangeRateService::class);
    $failing->shouldReceive('convert')->andThrow(new Exception('rate API down'));
    $failing->shouldIgnoreMissing();
    app()->instance(ExchangeRateService::class, $failing);

    Income::factory()->create(['amount' => 100, 'currency' => 'USD', 'date' => now()->toDateString()]);

    $this->get(route('admin.dashboard'))->assertOk();
});

it('summarises work', function (): void {
    Task::factory()->create(['status' => 'todo']);
    Task::factory()->create(['status' => 'in_progress']);
    Task::factory()->create(['status' => 'completed']);
    Client::factory()->count(2)->create();
    Repository::factory()->create(['is_active' => true]);
    Repository::factory()->create(['is_active' => false]);

    $this->get(route('admin.dashboard'))
        ->assertInertia(function (AssertableInertia $page): void {
            $tiles = collect($page->toArray()['props']['work'])->keyBy('label');

            expect($tiles['Open tasks']['value'])->toBe('2')
                ->and($tiles['Open tasks']['caption'])->toBe('1 in progress');
            // Task -> Project -> Client, so factories create more than the two here.
            expect($tiles['Clients']['value'])->toBe(number_format(Client::count()));
            expect($tiles['Repositories']['value'])->toBe('1');
        });
});

it('summarises the library', function (): void {
    $writer = Writer::factory()->create();
    Publisher::factory()->create();
    Book::factory()->count(2)->create(['writer_id' => $writer->id, 'page_count' => 300]);
    Writer::factory()->create();

    $this->get(route('admin.dashboard'))
        ->assertInertia(function (AssertableInertia $page): void {
            $tiles = collect($page->toArray()['props']['library'])->keyBy('label');

            expect($tiles['Books']['value'])->toBe('2')
                ->and($tiles['Writers']['value'])->toBe('2')
                ->and($tiles['Writers']['caption'])->toBe('1 with a book')
                ->and($tiles['Pages']['value'])->toBe('600')
                ->and($tiles['Pages']['caption'])->toBe('300 per book');
        });
});

it('lists recent posts and open tasks', function (): void {
    Post::factory()->count(7)->create();
    Task::factory()->count(3)->create(['status' => 'todo']);
    Task::factory()->create(['status' => 'completed']);

    $this->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('recentPosts', 5)
            ->has('openTasks', 3)
            ->has('recentPosts.0.title')
            ->has('recentPosts.0.updatedAt')
        );
});

it('renders cleanly with no data at all', function (): void {
    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('recentPosts', 0)
            ->has('openTasks', 0)
        );
});

it('shows the library summary above the books table', function (): void {
    Book::factory()->create();

    $this->get(route('admin.books.index'))
        ->assertInertia(function (AssertableInertia $page): void {
            $labels = collect($page->toArray()['props']['tiles'])->pluck('label');

            expect($labels)->toContain('Books', 'Writers', 'Publishers', 'Pages');
        });
});
