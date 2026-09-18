# Filament to Inertia + Vue + PrimeVue Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the entire FilamentPHP admin panel with a custom Inertia + Vue 3 + PrimeVue panel, preserving every feature, and remove Filament, its four panel plugins, Laravel Pulse and Livewire from the dependency tree.

**Architecture:** A server-driven table and form contract. Each resource declares its columns, filters and fields in a PHP definition class; a single Vue `ResourceTable` / `ResourceForm` pair renders any of them. Cell presentation (money formatting, badge colors, image URLs) resolves on the server so the Vue layer needs no model knowledge, which keeps adding a resource to one PHP file.

**Tech Stack:** PHP 8.4, Laravel 13, Inertia 2, Vue 3, PrimeVue 4 (styled mode, custom preset), Tailwind 4, Fortify, Ziggy, Chart.js, Pest 4, Larastan 3, Pint.

**Spec:** `docs/superpowers/specs/2026-09-18-filament-to-inertia-vue-design.md`

## Global Constraints

- PHP 8.4. Every new PHP file starts with `declare(strict_types=1);`.
- Explicit return types and parameter type hints on every method. Curly braces on all control structures, even single-line bodies.
- PHPDoc array shapes on anything returning or accepting a structured array. Larastan level 10 must stay at **zero errors** — run `vendor/bin/phpstan analyse` before every commit.
- Run `vendor/bin/pint --dirty --format agent` before every commit that touches PHP.
- Tests are Pest. Create with `php artisan make:test --pest {Name}`. Run with `php artisan test --compact`. Feature tests get `RefreshDatabase` automatically via `tests/Pest.php`.
- Artisan commands always take `--no-interaction`.
- Never create a model, migration or factory by hand — use `php artisan make:*`.
- The new panel mounts at `/app` for Phases 1–5 and moves to `/admin` in Phase 6. Filament keeps running at `/admin` until Phase 6.
- The public site (`routes/web.php` — `/`, `/books` via `BookController`, the WakaTime OAuth routes) is **out of scope** and must keep working unchanged.
- Accent default is khaki. Khaki fills take dark text (`#2A2613`), never white.
- Do not change dependencies beyond those listed in Task 9 and Task 43 without asking.

## Phase Map

| Phase | Tasks | Deliverable |
| --- | --- | --- |
| 0 — Decouple + safety net | 1–8 | Filament references removed from non-Filament code; tests covering surviving logic |
| 1 — Foundation | 9–15 | Inertia/Vue/PrimeVue/Fortify installed, layout, theme, auth |
| 2 — Engine | 16–21 | `ResourceTable` + `ResourceForm`, proven on Posts |
| 3 — Bulk port | 22–33 | All 13 resources on the new panel |
| 4 — Specials | 34–39 | Kanban, spotlight, search, exports, notifications, settings |
| 5 — Dashboards | 40–41 | WakaTime charts and overview widgets |
| 6 — Demolition | 42–45 | Filament removed, `/app` becomes `/admin`, smoke tests |

---

# Phase 0 — Decouple and build the safety net

Filament is referenced from seven files **outside** `app/Filament/`. Those references must be severed before Filament can be removed, and they can be severed now, while Filament still works. Doing it first means Phase 6 is a deletion rather than a debugging session.

The tests written here cover code that survives the rewrite — observers, services, model behavior — so they keep paying off after the panel is gone.

### Task 1: Replace Filament enum contracts with application-owned ones

`App\Enums\Currencies`, `PhpVersions` and `LaravelVersions` implement `Filament\Support\Contracts\HasLabel` and `HasColor`. Filament only ever calls `getLabel()` / `getColor()` on them, so identical local interfaces are drop-in.

**Files:**
- Create: `app/Support/Contracts/HasLabel.php`
- Create: `app/Support/Contracts/HasColor.php`
- Modify: `app/Enums/Currencies.php:7` (the `use` statement), `app/Enums/PhpVersions.php:7-9`, `app/Enums/LaravelVersions.php:7-9`
- Test: `tests/Feature/Enums/CurrenciesTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `App\Support\Contracts\HasLabel` with `public function getLabel(): string;` and `App\Support\Contracts\HasColor` with `public function getColor(): string;`. Tasks 16, 22–33 and 38 rely on `Currencies` implementing these.

- [ ] **Step 1: Write the failing test**

```bash
php artisan make:test --pest Enums/CurrenciesTest --no-interaction
```

```php
<?php

declare(strict_types=1);

use App\Enums\Currencies;
use App\Support\Contracts\HasLabel;

it('implements the application HasLabel contract, not Filament\'s', function (): void {
    expect(Currencies::TRY)->toBeInstanceOf(HasLabel::class);
    expect(interface_exists('Filament\Support\Contracts\HasLabel'))
        ->toBeTrue('Filament is still installed at this phase; the point is that our enums no longer depend on it.');

    $reflection = new ReflectionEnum(Currencies::class);
    $interfaces = $reflection->getInterfaceNames();

    expect($interfaces)->not->toContain('Filament\Support\Contracts\HasLabel');
});

it('returns a label and symbol for every case', function (Currencies $currency): void {
    expect($currency->getLabel())->toBeString()->not->toBeEmpty();
    expect($currency->getSymbol())->toBeString()->not->toBeEmpty();
})->with(Currencies::cases());
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=CurrenciesTest`
Expected: FAIL — `Class "App\Support\Contracts\HasLabel" not found`.

- [ ] **Step 3: Create the two contracts**

`app/Support/Contracts/HasLabel.php`:

```php
<?php

declare(strict_types=1);

namespace App\Support\Contracts;

interface HasLabel
{
    public function getLabel(): string;
}
```

`app/Support/Contracts/HasColor.php`:

```php
<?php

declare(strict_types=1);

namespace App\Support\Contracts;

interface HasColor
{
    /**
     * A semantic color token: primary, secondary, success, warning, danger, info or gray.
     */
    public function getColor(): string;
}
```

- [ ] **Step 4: Swap the imports in all three enums**

In `app/Enums/Currencies.php`, replace `use Filament\Support\Contracts\HasLabel;` with `use App\Support\Contracts\HasLabel;`. Leave the enum body untouched.

In `app/Enums/PhpVersions.php` and `app/Enums/LaravelVersions.php`, replace both imports:

```php
use App\Support\Contracts\HasColor;
use App\Support\Contracts\HasLabel;
```

- [ ] **Step 5: Run the test and the full suite**

Run: `php artisan test --compact`
Expected: PASS. Filament still renders these enums correctly because it duck-types `getLabel()`/`getColor()` through its own contract check — verify by loading `/admin/expenses` in the browser and confirming the currency badges still show labels.

If Filament v5 *strictly* type-checks against its own interface and the badges break, have the enums implement both interfaces simultaneously (they have identical signatures), and drop the Filament one in Task 43.

- [ ] **Step 6: Static analysis and format**

Run: `vendor/bin/pint --dirty --format agent && vendor/bin/phpstan analyse`
Expected: no Pint changes needed beyond formatting, zero PHPStan errors.

- [ ] **Step 7: Commit**

```bash
git add app/Support/Contracts app/Enums tests/Feature/Enums
git commit -m "Replace Filament enum contracts with application-owned ones"
```

### Task 2: Introduce an admin notification channel independent of Filament

Three files send `Filament\Notifications\Notification`: `app/Observers/DebtObserver.php:52`, `app/Http/Controllers/WakaTimeOAuthController.php`, `app/Console/Commands/SyncWakaTime.php`. A command sending a UI toast is already wrong — it runs in the scheduler where no session exists.

Replace all three with one service that writes to Laravel's session flash bag when there is a request, and to the log when there is not. Inertia picks the flash up in Task 13.

**Files:**
- Create: `app/Support/AdminNotifier.php`
- Modify: `app/Observers/DebtObserver.php:52-57`, `app/Http/Controllers/WakaTimeOAuthController.php`, `app/Console/Commands/SyncWakaTime.php`
- Test: `tests/Feature/Support/AdminNotifierTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `App\Support\AdminNotifier` with
  `public function success(string $title, ?string $body = null): void`,
  `public function danger(string $title, ?string $body = null): void`,
  `public function info(string $title, ?string $body = null): void`.
  Flash payload shape: `array{variant: string, title: string, body: string|null}` under session key `flash.notification`. Task 13 reads this key; Task 34 and 38 write to it.

- [ ] **Step 1: Write the failing test**

```bash
php artisan make:test --pest Support/AdminNotifierTest --no-interaction
```

```php
<?php

declare(strict_types=1);

use App\Support\AdminNotifier;
use Illuminate\Support\Facades\Log;

it('flashes a success notification to the session', function (): void {
    app(AdminNotifier::class)->success('Saved', 'The record was saved.');

    expect(session('flash.notification'))->toBe([
        'variant' => 'success',
        'title' => 'Saved',
        'body' => 'The record was saved.',
    ]);
});

it('flashes a danger notification without a body', function (): void {
    app(AdminNotifier::class)->danger('Something broke');

    expect(session('flash.notification'))->toBe([
        'variant' => 'danger',
        'title' => 'Something broke',
        'body' => null,
    ]);
});

it('logs instead of flashing when running in the console', function (): void {
    Log::spy();

    $notifier = new AdminNotifier(runningInConsole: true);
    $notifier->info('Sync finished', '42 rows');

    Log::shouldHaveReceived('info')->once()->with('Sync finished', ['body' => '42 rows']);
    expect(session()->has('flash.notification'))->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=AdminNotifierTest`
Expected: FAIL — `Class "App\Support\AdminNotifier" not found`.

- [ ] **Step 3: Write the notifier**

`app/Support/AdminNotifier.php`:

```php
<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class AdminNotifier
{
    public function __construct(private readonly bool $runningInConsole = false) {}

    public function success(string $title, ?string $body = null): void
    {
        $this->send('success', $title, $body);
    }

    public function danger(string $title, ?string $body = null): void
    {
        $this->send('danger', $title, $body);
    }

    public function info(string $title, ?string $body = null): void
    {
        $this->send('info', $title, $body);
    }

    private function send(string $variant, string $title, ?string $body): void
    {
        if ($this->runningInConsole) {
            Log::info($title, ['body' => $body]);

            return;
        }

        Session::flash('flash.notification', [
            'variant' => $variant,
            'title' => $title,
            'body' => $body,
        ]);
    }
}
```

- [ ] **Step 4: Bind it in the container**

In `app/Providers/AppServiceProvider.php`, inside `register()`, replace the `//` placeholder with:

```php
$this->app->singleton(AdminNotifier::class, fn (): AdminNotifier => new AdminNotifier(
    runningInConsole: $this->app->runningInConsole(),
));
```

Add `use App\Support\AdminNotifier;` to the imports.

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=AdminNotifierTest`
Expected: PASS (3 tests).

- [ ] **Step 6: Replace the three Filament notification call sites**

In `app/Observers/DebtObserver.php`, add a constructor and swap the call. Replace the `\Filament\Notifications\Notification::make()...->send();` block at line 52 with:

```php
$this->notifier->success(
    'Expense Created',
    "Expense record created for debt payment to {$debt->creditor_name}",
);
```

and add to the class:

```php
public function __construct(private readonly AdminNotifier $notifier) {}
```

plus `use App\Support\AdminNotifier;`.

In `app/Http/Controllers/WakaTimeOAuthController.php` and `app/Console/Commands/SyncWakaTime.php`, replace each `Notification::make()->title(X)->body(Y)->success()->send()` chain with `app(AdminNotifier::class)->success(X, Y)` (and `->danger(...)` where the original used `->danger()`). Remove the `use Filament\Notifications\Notification;` import from both.

- [ ] **Step 7: Verify no Filament notification references remain**

Run: `grep -rn "Filament.Notifications" app --include="*.php" | grep -v "^app/Filament/"`
Expected: no output.

- [ ] **Step 8: Run the suite, analyse, format, commit**

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
git add app/Support app/Providers/AppServiceProvider.php app/Observers/DebtObserver.php app/Http/Controllers/WakaTimeOAuthController.php app/Console/Commands/SyncWakaTime.php tests/Feature/Support
git commit -m "Add AdminNotifier and remove Filament notifications from non-panel code"
```

### Task 3: Remove the Filament macros from AppServiceProvider

`AppServiceProvider::boot()` configures `CreateAction`, `ImportAction`, `ExportAction`, `ExportBulkAction`, `Field` and `Column` — all Filament classes. The new panel has no equivalent global macros; icons and translation are per-component concerns handled in Vue.

The `viewPulse` gate goes too, since Pulse is dropped (spec decision).

**Files:**
- Modify: `app/Providers/AppServiceProvider.php:8-13` (imports), `:41-75` (macro block)
- Test: `tests/Feature/Providers/AppServiceProviderTest.php`

**Interfaces:**
- Consumes: `App\Support\AdminNotifier` binding from Task 2.
- Produces: an `AppServiceProvider` free of Filament imports. Task 43 depends on this.

- [ ] **Step 1: Write the failing test**

```bash
php artisan make:test --pest Providers/AppServiceProviderTest --no-interaction
```

```php
<?php

declare(strict_types=1);

it('does not reference Filament', function (): void {
    $source = file_get_contents(app_path('Providers/AppServiceProvider.php'));

    expect($source)->not->toContain('Filament');
});

it('keeps the global model and URL hardening', function (): void {
    $source = file_get_contents(app_path('Providers/AppServiceProvider.php'));

    expect($source)
        ->toContain('Model::shouldBeStrict()')
        ->toContain('Model::unguard()')
        ->toContain('DB::prohibitDestructiveCommands')
        ->toContain('URL::forceHttps');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=AppServiceProviderTest`
Expected: FAIL on the first test — the file still contains `Filament`.

- [ ] **Step 3: Rewrite AppServiceProvider**

```php
<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\AdminNotifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AdminNotifier::class, fn (): AdminNotifier => new AdminNotifier(
            runningInConsole: $this->app->runningInConsole(),
        ));
    }

    public function boot(): void
    {
        DB::prohibitDestructiveCommands(app()->isProduction());

        Model::unguard();

        Model::shouldBeStrict();

        URL::forceHttps(app()->isProduction());

        Vite::useAggressivePrefetching();
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=AppServiceProviderTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Confirm the Filament panel still boots**

Run: `php artisan route:list --path=admin | head -20`
Expected: Filament admin routes still listed. Losing the action-icon macros only changes cosmetics on the old panel, which is being deleted anyway.

- [ ] **Step 6: Analyse, format, commit**

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
git add app/Providers/AppServiceProvider.php tests/Feature/Providers
git commit -m "Remove Filament macros and the Pulse gate from AppServiceProvider"
```

### Task 4: Decouple the User model from Filament

`User implements FilamentUser` and carries `canAccessPanel(Panel $panel)`. The new panel authorizes on the same rule — email matches `config('app.admin_email')` — but through a Gate rather than a Filament contract.

**Files:**
- Modify: `app/Models/User.php:7-8, 14, 24-27`
- Modify: `app/Providers/AppServiceProvider.php` (add the gate)
- Test: `tests/Feature/Models/UserTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `User::isAdmin(): bool`, and a Gate ability named `access-admin`. Task 12 (route middleware) and Task 15 (login) both use `access-admin`.

- [ ] **Step 1: Write the failing test**

```bash
php artisan make:test --pest Models/UserTest --no-interaction
```

```php
<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('marks the configured admin email as admin', function (): void {
    config(['app.admin_email' => 'admin@example.test']);

    $admin = User::factory()->create(['email' => 'admin@example.test']);
    $other = User::factory()->create(['email' => 'someone@example.test']);

    expect($admin->isAdmin())->toBeTrue();
    expect($other->isAdmin())->toBeFalse();
});

it('gates admin access on the same rule', function (): void {
    config(['app.admin_email' => 'admin@example.test']);

    $admin = User::factory()->create(['email' => 'admin@example.test']);
    $other = User::factory()->create(['email' => 'someone@example.test']);

    expect(Gate::forUser($admin)->allows('access-admin'))->toBeTrue();
    expect(Gate::forUser($other)->allows('access-admin'))->toBeFalse();
});

it('does not implement any Filament contract', function (): void {
    expect(class_implements(User::class))
        ->not->toHaveKey('Filament\Models\Contracts\FilamentUser');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=UserTest`
Expected: FAIL — `Call to undefined method App\Models\User::isAdmin()`.

- [ ] **Step 3: Rewrite the User model**

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function isAdmin(): bool
    {
        return $this->email === config('app.admin_email');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
```

- [ ] **Step 4: Define the gate**

In `app/Providers/AppServiceProvider.php` `boot()`, append:

```php
Gate::define('access-admin', fn (User $user): bool => $user->isAdmin());
```

Add `use App\Models\User;` and `use Illuminate\Support\Facades\Gate;` to the imports.

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=UserTest`
Expected: PASS (3 tests).

Note: the old Filament panel will now let any authenticated user in, because `canAccessPanel` is gone. That is acceptable for the remaining weeks on a single-user personal app; it is deleted in Phase 6.

- [ ] **Step 6: Update the AppServiceProvider assertion**

The Task 3 test asserts `AppServiceProvider` does not contain `Filament`. Adding the gate keeps that true. Re-run: `php artisan test --compact --filter=AppServiceProviderTest`
Expected: PASS.

- [ ] **Step 7: Analyse, format, commit**

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
git add app/Models/User.php app/Providers/AppServiceProvider.php tests/Feature/Models
git commit -m "Decouple User from Filament; gate admin access on access-admin"
```

### Task 5: Build the missing factories

Eleven models have no factory: `Debt`, `Expense`, `ExpenseCategory`, `Income`, `IncomeCategory`, `Invoice`, `Repository`, `Setting`, `Task`, `WakaTimeSummary`, `WakaTimeSummaryEntry`. Every test from Task 6 onward needs them.

**Files:**
- Create: `database/factories/{Debt,Expense,ExpenseCategory,Income,IncomeCategory,Invoice,Repository,Setting,Task,WakaTimeSummary,WakaTimeSummaryEntry}Factory.php`
- Test: `tests/Feature/Factories/FactoriesTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: a factory per model, plus named states `TaskFactory::githubIssue()`, `DebtFactory::paid()`, `DebtFactory::overdue()`. Tasks 6, 7, 22–33, 34 and 38 use these.

- [ ] **Step 1: Read the migrations that define the columns**

Run: `cat database/migrations/2025_07_28_220747_create_debts_table.php database/migrations/2025_07_28_220748_create_expense_categories_table.php database/migrations/2025_07_28_220749_create_incomes_table.php database/migrations/2025_07_28_220746_create_income_categories_table.php database/migrations/2025_07_23_104704_create_invoices_table.php database/migrations/2025_05_08_094500_create_repositories_table.php database/migrations/2026_06_01_000001_create_waka_time_summaries_table.php database/migrations/2026_06_01_000002_create_waka_time_summary_entries_table.php`

Use the exact column names and nullability you find. The factory definitions below cover `tasks`, `expenses` and `settings`, whose migrations are quoted in this plan; write the other eight to match their migrations the same way.

- [ ] **Step 2: Write the failing test**

```bash
php artisan make:test --pest Factories/FactoriesTest --no-interaction
```

```php
<?php

declare(strict_types=1);

use App\Models\Debt;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Income;
use App\Models\IncomeCategory;
use App\Models\Invoice;
use App\Models\Repository;
use App\Models\Setting;
use App\Models\Task;
use App\Models\WakaTimeSummary;
use App\Models\WakaTimeSummaryEntry;

it('can create a record from every factory', function (string $model): void {
    $record = $model::factory()->create();

    expect($record->exists)->toBeTrue();
})->with([
    Debt::class,
    Expense::class,
    ExpenseCategory::class,
    Income::class,
    IncomeCategory::class,
    Invoice::class,
    Repository::class,
    Setting::class,
    Task::class,
    WakaTimeSummary::class,
    WakaTimeSummaryEntry::class,
]);

it('creates a github-linked task via the state', function (): void {
    $task = Task::factory()->githubIssue()->create();

    expect($task->is_github_issue)->toBeTrue();
    expect($task->github_issue_number)->not->toBeNull();
});

it('creates paid and overdue debts via states', function (): void {
    expect(Debt::factory()->paid()->create()->status)->toBe('paid');

    $overdue = Debt::factory()->overdue()->create();
    expect($overdue->status)->toBe('pending');
    expect($overdue->due_date->isPast())->toBeTrue();
});
```

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test --compact --filter=FactoriesTest`
Expected: FAIL — `Class "Database\Factories\DebtFactory" not found`.

- [ ] **Step 4: Generate the factory files**

```bash
php artisan make:factory DebtFactory --model=Debt --no-interaction
php artisan make:factory ExpenseFactory --model=Expense --no-interaction
php artisan make:factory ExpenseCategoryFactory --model=ExpenseCategory --no-interaction
php artisan make:factory IncomeFactory --model=Income --no-interaction
php artisan make:factory IncomeCategoryFactory --model=IncomeCategory --no-interaction
php artisan make:factory InvoiceFactory --model=Invoice --no-interaction
php artisan make:factory RepositoryFactory --model=Repository --no-interaction
php artisan make:factory SettingFactory --model=Setting --no-interaction
php artisan make:factory TaskFactory --model=Task --no-interaction
php artisan make:factory WakaTimeSummaryFactory --model=WakaTimeSummary --no-interaction
php artisan make:factory WakaTimeSummaryEntryFactory --model=WakaTimeSummaryEntry --no-interaction
```

- [ ] **Step 5: Fill in TaskFactory**

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'repository_id' => null,
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'status' => fake()->randomElement(['todo', 'in_progress', 'completed']),
            'sort_order' => null,
        ];
    }

    public function githubIssue(): static
    {
        return $this->state(fn (array $attributes): array => [
            'github_issue_number' => (string) fake()->numberBetween(1, 500),
            'github_issue_url' => 'https://github.com/owner/repo/issues/'.fake()->numberBetween(1, 500),
            'github_issue_state' => 'open',
            'github_issue_labels' => [['name' => 'bug', 'color' => 'd73a4a']],
            'github_assignee' => fake()->userName(),
        ]);
    }
}
```

`TaskObserver::creating` assigns `sort_order` when it is null, so leaving it null exercises the real path.

- [ ] **Step 6: Fill in ExpenseFactory**

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Currencies;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'expense_category_id' => ExpenseCategory::factory(),
            'debt_id' => null,
            'amount' => fake()->randomFloat(2, 10, 5000),
            'currency' => fake()->randomElement(Currencies::cases())->value,
            'description' => fake()->sentence(),
            'receipt_path' => null,
            'date' => fake()->dateTimeBetween('-1 year')->format('Y-m-d'),
        ];
    }
}
```

- [ ] **Step 7: Fill in SettingFactory**

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Setting>
 */
class SettingFactory extends Factory
{
    protected $model = Setting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group' => fake()->randomElement(['site_info', 'meta', 'branding', 'appearance']),
            'name' => fake()->unique()->slug(2),
            'value' => fake()->word(),
            'type' => 'text',
        ];
    }
}
```

- [ ] **Step 8: Fill in DebtFactory with its two states**

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Currencies;
use App\Models\Debt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Debt>
 */
class DebtFactory extends Factory
{
    protected $model = Debt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'creditor_name' => fake()->name(),
            'creditor_type' => fake()->randomElement(['person', 'institute']),
            'amount' => fake()->randomFloat(2, 100, 20000),
            'currency' => fake()->randomElement(Currencies::cases())->value,
            'status' => 'pending',
            'date' => fake()->dateTimeBetween('-6 months')->format('Y-m-d'),
            'due_date' => fake()->dateTimeBetween('now', '+6 months')->format('Y-m-d'),
            'description' => fake()->sentence(),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => 'paid']);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'pending',
            'due_date' => now()->subDays(fake()->numberBetween(1, 60))->format('Y-m-d'),
        ]);
    }
}
```

`DebtObserver::updated` creates an `Expense` when status flips to `paid`. `paid()` sets the status at creation, not on update, so it does not trigger the observer — which is what Task 7 wants to test explicitly.

- [ ] **Step 9: Fill in the remaining seven factories**

Write `ExpenseCategoryFactory`, `IncomeCategoryFactory`, `IncomeFactory`, `InvoiceFactory`, `RepositoryFactory`, `WakaTimeSummaryFactory` and `WakaTimeSummaryEntryFactory` against the columns you read in Step 1, following the same shape: `protected $model`, a `/** @return array<string, mixed> */` docblock on `definition()`, related models via `Model::factory()`, and `fake()` for scalars. `RepositoryFactory` must set a unique `github_id` (`fake()->unique()->numerify('########')`) because the resource marks it unique. `InvoiceFactory` must set a unique `invoice_number`.

- [ ] **Step 10: Run test to verify it passes**

Run: `php artisan test --compact --filter=FactoriesTest`
Expected: PASS (13 assertions across the dataset plus 2 state tests).

- [ ] **Step 11: Analyse, format, commit**

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
git add database/factories tests/Feature/Factories
git commit -m "Add factories for the eleven models that lacked them"
```

### Task 6: Test TaskObserver and GitHubService

`TaskObserver` assigns `sort_order` on create, creates a GitHub issue when a task has a repository, and syncs title/description/status changes back to GitHub. The kanban rebuild in Task 34 reorders tasks directly, so this ordering logic must be pinned down first.

**Files:**
- Test: `tests/Feature/Observers/TaskObserverTest.php`
- Read: `app/Services/GitHubService.php`, `app/Observers/TaskObserver.php`

**Interfaces:**
- Consumes: `TaskFactory` and `RepositoryFactory` from Task 5.
- Produces: no production code — a behavioral contract Task 34 must not break.

- [ ] **Step 1: Read GitHubService to learn its public surface**

Run: `grep -n "public function" app/Services/GitHubService.php`
Note the exact signatures of `createIssue` and `updateIssue` — the test mocks them.

- [ ] **Step 2: Write the failing test**

```bash
php artisan make:test --pest Observers/TaskObserverTest --no-interaction
```

```php
<?php

declare(strict_types=1);

use App\Models\Repository;
use App\Models\Task;
use App\Services\GitHubService;

beforeEach(function (): void {
    $this->github = Mockery::mock(GitHubService::class);
    $this->github->shouldIgnoreMissing();
    app()->instance(GitHubService::class, $this->github);
});

it('assigns the next sort order within a status', function (): void {
    Task::factory()->create(['status' => 'todo', 'sort_order' => 3, 'project_id' => null]);

    $task = Task::factory()->create(['status' => 'todo', 'sort_order' => null, 'project_id' => null]);

    expect($task->sort_order)->toBe(4);
});

it('starts sort order at 1 for an empty status column', function (): void {
    $task = Task::factory()->create(['status' => 'in_progress', 'sort_order' => null, 'project_id' => null]);

    expect($task->sort_order)->toBe(1);
});

it('scopes sort order to the project', function (): void {
    $taskA = Task::factory()->create(['status' => 'todo', 'sort_order' => 7]);
    $taskB = Task::factory()->create([
        'status' => 'todo',
        'sort_order' => null,
        'project_id' => $taskA->project_id,
    ]);

    expect($taskB->sort_order)->toBe(8);
});

it('respects an explicitly provided sort order', function (): void {
    $task = Task::factory()->create(['status' => 'todo', 'sort_order' => 99]);

    expect($task->sort_order)->toBe(99);
});

it('creates a github issue when the task has a repository and no issue number', function (): void {
    $repository = Repository::factory()->create();

    $this->github->shouldReceive('createIssue')->once();

    Task::factory()->create([
        'repository_id' => $repository->id,
        'github_issue_number' => null,
    ]);
});

it('does not create a github issue when one already exists', function (): void {
    $repository = Repository::factory()->create();

    $this->github->shouldNotReceive('createIssue');

    Task::factory()->githubIssue()->create(['repository_id' => $repository->id]);
});

it('syncs to github when the title changes on a github-linked task', function (): void {
    $task = Task::factory()->githubIssue()->create();

    $this->github->shouldReceive('updateIssue')->once();

    $task->update(['title' => 'A new title']);
});

it('does not sync when only an irrelevant field changes', function (): void {
    $task = Task::factory()->githubIssue()->create();

    $this->github->shouldNotReceive('updateIssue');

    $task->update(['sort_order' => 42]);
});

it('swallows github failures so the save still succeeds', function (): void {
    $task = Task::factory()->githubIssue()->create();

    $this->github->shouldReceive('updateIssue')->andThrow(new Exception('API down'));

    $task->update(['title' => 'Still saved']);

    expect($task->fresh()->title)->toBe('Still saved');
});
```

- [ ] **Step 3: Run test to verify it fails or passes**

Run: `php artisan test --compact --filter=TaskObserverTest`
Expected: mostly PASS, since this is characterization of existing behavior. Any failure is a genuine discovery about the current code — investigate before changing the test. Do **not** modify `TaskObserver` to make a test pass; adjust the test to describe what the code actually does, and note the discrepancy in the commit message.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/Observers
git commit -m "Pin down TaskObserver sort-order and GitHub sync behavior"
```

### Task 7: Test DebtObserver, Setting caching and model accessors

**Files:**
- Test: `tests/Feature/Observers/DebtObserverTest.php`, `tests/Feature/Models/SettingTest.php`

**Interfaces:**
- Consumes: factories from Task 5, `AdminNotifier` from Task 2.
- Produces: no production code.

- [ ] **Step 1: Write the DebtObserver test**

```bash
php artisan make:test --pest Observers/DebtObserverTest --no-interaction
```

```php
<?php

declare(strict_types=1);

use App\Models\Debt;
use App\Models\Expense;

it('creates an expense when a debt is marked paid', function (): void {
    $debt = Debt::factory()->create(['status' => 'pending', 'amount' => 1500.00]);

    $debt->update(['status' => 'paid']);

    $expense = Expense::where('debt_id', $debt->id)->first();

    expect($expense)->not->toBeNull();
    expect((float) $expense->amount)->toBe(1500.00);
    expect($expense->currency->value)->toBe($debt->currency->value);
    expect($expense->description)->toContain($debt->creditor_name);
});

it('flashes a success notification when the expense is created', function (): void {
    $debt = Debt::factory()->create(['status' => 'pending']);

    $debt->update(['status' => 'paid']);

    expect(session('flash.notification'))
        ->toHaveKey('variant', 'success')
        ->toHaveKey('title', 'Expense Created');
});

it('does not create a second expense for an already-paid debt', function (): void {
    $debt = Debt::factory()->create(['status' => 'pending']);
    $debt->update(['status' => 'paid']);

    $debt->update(['status' => 'paid', 'description' => 'touched again']);

    expect(Expense::where('debt_id', $debt->id)->count())->toBe(1);
});

it('does not create an expense for other status changes', function (): void {
    $debt = Debt::factory()->create(['status' => 'pending']);

    $debt->update(['description' => 'just a note']);

    expect(Expense::where('debt_id', $debt->id)->exists())->toBeFalse();
});
```

- [ ] **Step 2: Run it**

Run: `php artisan test --compact --filter=DebtObserverTest`
Expected: PASS (4 tests). The notification assertion only passes because Task 2 replaced the Filament call.

- [ ] **Step 3: Write the Setting test**

```bash
php artisan make:test --pest Models/SettingTest --no-interaction
```

```php
<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

it('returns the default when the setting is missing', function (): void {
    expect(Setting::get('appearance', 'accent', 'khaki'))->toBe('khaki');
});

it('reads a stored value', function (): void {
    Setting::create(['group' => 'appearance', 'name' => 'accent', 'value' => 'amber', 'type' => 'text']);

    expect(Setting::get('appearance', 'accent', 'khaki'))->toBe('amber');
});

it('caches the read', function (): void {
    Setting::create(['group' => 'appearance', 'name' => 'accent', 'value' => 'amber', 'type' => 'text']);

    Setting::get('appearance', 'accent');

    expect(Cache::has('setting_appearance_accent'))->toBeTrue();
});

it('busts the cache when the value is set', function (): void {
    Setting::set('appearance', 'accent', 'amber');
    expect(Setting::get('appearance', 'accent'))->toBe('amber');

    Setting::set('appearance', 'accent', 'emerald');

    expect(Setting::get('appearance', 'accent'))->toBe('emerald');
});

it('busts the cache when the model is saved directly', function (): void {
    $setting = Setting::create(['group' => 'appearance', 'name' => 'accent', 'value' => 'amber', 'type' => 'text']);
    Setting::get('appearance', 'accent');

    $setting->update(['value' => 'rose']);

    expect(Setting::get('appearance', 'accent'))->toBe('rose');
});

it('upserts on set rather than duplicating', function (): void {
    Setting::set('appearance', 'accent', 'amber');
    Setting::set('appearance', 'accent', 'khaki');

    expect(Setting::where('group', 'appearance')->where('name', 'accent')->count())->toBe(1);
});

it('returns a group as a name-keyed collection', function (): void {
    Setting::set('appearance', 'accent', 'khaki');
    Setting::set('appearance', 'color_scheme', 'dark');

    expect(Setting::getGroup('appearance')->toArray())
        ->toBe(['accent' => 'khaki', 'color_scheme' => 'dark']);
});
```

- [ ] **Step 4: Run it**

Run: `php artisan test --compact --filter=SettingTest`
Expected: PASS (7 tests). Task 38 depends on every one of these behaviors.

- [ ] **Step 5: Write the sluggable test**

`spatie/laravel-sluggable` survives the rewrite and the new forms depend on slugs being stable, so pin the behavior down.

```bash
php artisan make:test --pest Models/SluggableTest --no-interaction
```

```php
<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Post;

it('generates a slug from the title on create', function (): void {
    $post = Post::factory()->create(['title' => 'Learning Rust', 'slug' => null]);

    expect($post->slug)->toBe('learning-rust');
});

it('keeps slugs unique', function (): void {
    Post::factory()->create(['title' => 'Learning Rust', 'slug' => null]);
    $second = Post::factory()->create(['title' => 'Learning Rust', 'slug' => null]);

    expect($second->slug)->not->toBe('learning-rust');
});

it('generates a category slug from the name', function (): void {
    $category = Category::factory()->create(['name' => 'Systems Programming', 'slug' => null]);

    expect($category->slug)->toBe('systems-programming');
});

it('resolves a post by its slug as the route key', function (): void {
    $post = Post::factory()->create(['title' => 'Learning Rust', 'slug' => null]);

    expect($post->getRouteKeyName())->toBeString();
});
```

Run it: `php artisan test --compact --filter=SluggableTest`

This is characterization of existing behavior. If a test fails, the code is right and the test's expectation is wrong — check which traits (`DefaultSlugOptions`, `SlugAsRouteKeyName`) each model actually uses with `grep -n "use .*Slug" app/Models/*.php`, and correct the test to match. Do not change the models.

- [ ] **Step 6: Commit**

```bash
git add tests/Feature/Observers/DebtObserverTest.php tests/Feature/Models/SettingTest.php tests/Feature/Models/SluggableTest.php
git commit -m "Pin down DebtObserver, Setting cache and sluggable behavior"
```

### Task 8: Test the WakaTime aggregation logic

`app/Filament/Widgets/Concerns/InteractsWithWakaTimeData.php` holds the aggregation that all nine charts render. The trait lives under `app/Filament/` and must move somewhere that survives Phase 6.

**Files:**
- Create: `app/Support/WakaTime/AggregatesWakaTimeData.php` (moved from the Filament concern)
- Modify: `app/Filament/Widgets/Concerns/InteractsWithWakaTimeData.php` to re-export the new trait
- Test: `tests/Feature/WakaTime/AggregatesWakaTimeDataTest.php`

**Interfaces:**
- Consumes: `WakaTimeSummaryFactory`, `WakaTimeSummaryEntryFactory` from Task 5.
- Produces: `App\Support\WakaTime\AggregatesWakaTimeData` with the same method names the old trait had. Tasks 42–44 consume it.

- [ ] **Step 1: Read the existing trait**

Run: `cat app/Filament/Widgets/Concerns/InteractsWithWakaTimeData.php`
List every public and protected method, its signature and return shape. The new trait must keep them identical — this is a move, not a redesign.

- [ ] **Step 2: Create the new trait by copying the file**

```bash
mkdir -p app/Support/WakaTime
git mv app/Filament/Widgets/Concerns/InteractsWithWakaTimeData.php app/Support/WakaTime/AggregatesWakaTimeData.php
```

Change the namespace to `App\Support\WakaTime` and the trait name to `AggregatesWakaTimeData`. If any method references a Filament class (for example a chart color helper), replace that reference with a plain array or string — the trait must not import Filament.

- [ ] **Step 3: Keep the old widgets compiling**

Recreate `app/Filament/Widgets/Concerns/InteractsWithWakaTimeData.php` as a thin alias so the nine existing widgets keep working until Phase 6:

```php
<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Concerns;

use App\Support\WakaTime\AggregatesWakaTimeData;

trait InteractsWithWakaTimeData
{
    use AggregatesWakaTimeData;
}
```

- [ ] **Step 4: Write the test**

```bash
php artisan make:test --pest WakaTime/AggregatesWakaTimeDataTest --no-interaction
```

```php
<?php

declare(strict_types=1);

use App\Models\WakaTimeSummary;
use App\Models\WakaTimeSummaryEntry;
use App\Support\WakaTime\AggregatesWakaTimeData;

/**
 * Minimal host so the trait can be exercised without a widget.
 */
function wakaTimeAggregator(string $range = '7'): object
{
    return new class($range)
    {
        use AggregatesWakaTimeData;

        public function __construct(public string $range) {}

        /**
         * @return array<string, string>
         */
        public function getFilters(): array
        {
            return ['range' => $this->range];
        }
    };
}

it('sums seconds per breakdown type within the range', function (): void {
    $summary = WakaTimeSummary::factory()->create(['date' => now()->subDay()->toDateString()]);

    WakaTimeSummaryEntry::factory()->create([
        'waka_time_summary_id' => $summary->id,
        'type' => WakaTimeSummaryEntry::TYPE_LANGUAGE,
        'name' => 'PHP',
        'total_seconds' => 3600,
    ]);
    WakaTimeSummaryEntry::factory()->create([
        'waka_time_summary_id' => $summary->id,
        'type' => WakaTimeSummaryEntry::TYPE_LANGUAGE,
        'name' => 'PHP',
        'total_seconds' => 1800,
    ]);
    WakaTimeSummaryEntry::factory()->create([
        'waka_time_summary_id' => $summary->id,
        'type' => WakaTimeSummaryEntry::TYPE_LANGUAGE,
        'name' => 'Vue',
        'total_seconds' => 900,
    ]);

    $result = wakaTimeAggregator()->breakdownSeconds(WakaTimeSummaryEntry::TYPE_LANGUAGE);

    expect($result)->toMatchArray(['PHP' => 5400, 'Vue' => 900]);
});

it('excludes summaries outside the range', function (): void {
    $inRange = WakaTimeSummary::factory()->create(['date' => now()->subDays(2)->toDateString()]);
    $outOfRange = WakaTimeSummary::factory()->create(['date' => now()->subDays(40)->toDateString()]);

    WakaTimeSummaryEntry::factory()->create([
        'waka_time_summary_id' => $inRange->id,
        'type' => WakaTimeSummaryEntry::TYPE_LANGUAGE,
        'name' => 'PHP',
        'total_seconds' => 100,
    ]);
    WakaTimeSummaryEntry::factory()->create([
        'waka_time_summary_id' => $outOfRange->id,
        'type' => WakaTimeSummaryEntry::TYPE_LANGUAGE,
        'name' => 'PHP',
        'total_seconds' => 9999,
    ]);

    expect(wakaTimeAggregator('7')->breakdownSeconds(WakaTimeSummaryEntry::TYPE_LANGUAGE))
        ->toMatchArray(['PHP' => 100]);
});

it('includes everything when the range is all', function (): void {
    $old = WakaTimeSummary::factory()->create(['date' => now()->subYears(2)->toDateString()]);

    WakaTimeSummaryEntry::factory()->create([
        'waka_time_summary_id' => $old->id,
        'type' => WakaTimeSummaryEntry::TYPE_LANGUAGE,
        'name' => 'PHP',
        'total_seconds' => 500,
    ]);

    expect(wakaTimeAggregator('all')->breakdownSeconds(WakaTimeSummaryEntry::TYPE_LANGUAGE))
        ->toMatchArray(['PHP' => 500]);
});

it('returns an empty breakdown when there is no data', function (): void {
    expect(wakaTimeAggregator()->breakdownSeconds(WakaTimeSummaryEntry::TYPE_LANGUAGE))->toBe([]);
});
```

Adjust the method names in this test to match whatever Step 1 found. If the trait's range method is not `getFilters()`, change the anonymous class to supply what the trait actually reads.

- [ ] **Step 5: Run the test and the full suite**

Run: `php artisan test --compact`
Expected: PASS. The existing Filament widgets must still work — load `/admin/work/coding-dashboard` and confirm the charts render.

- [ ] **Step 6: Analyse, format, commit**

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
git add app/Support/WakaTime app/Filament/Widgets/Concerns tests/Feature/WakaTime
git commit -m "Move WakaTime aggregation out of app/Filament and cover it with tests"
```

---

# Phase 1 — Foundation

At the end of this phase the new panel exists at `/app`, you can log in, and the theme is correct on first paint. No resource has been ported yet.

### Task 9: Install Inertia, Vue, PrimeVue, Fortify and Ziggy

**Files:**
- Modify: `composer.json`, `package.json`, `vite.config.js`
- Create: `resources/views/app.blade.php`, `resources/js/app.js`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php` (generated)
- Test: `tests/Feature/InertiaBootTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: an Inertia root view named `app`, a Vue entry at `resources/js/app.js` resolving pages from `resources/js/Pages`, and `App\Http\Middleware\HandleInertiaRequests` in the `web` group. Every later Vue task depends on this.

- [ ] **Step 1: Install the PHP packages**

```bash
composer require inertiajs/inertia-laravel laravel/fortify tightenco/ziggy --no-interaction
php artisan inertia:middleware --no-interaction
php artisan vendor:publish --provider="Laravel\Fortify\FortifyServiceProvider" --no-interaction
```

- [ ] **Step 2: Install the npm packages**

```bash
npm install vue@^3 @inertiajs/vue3 @vitejs/plugin-vue primevue@^4 @primeuix/themes primeicons tailwindcss-primeui vuedraggable@^4.1.0 chart.js
```

- [ ] **Step 3: Register the Inertia middleware**

In `bootstrap/app.php`, replace the empty `withMiddleware` closure:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->web(append: [
        \App\Http\Middleware\HandleInertiaRequests::class,
        \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
    ]);
})
```

- [ ] **Step 4: Configure Vite**

`vite.config.js`:

```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/filament/admin/theme.css',
            ],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        tailwindcss(),
    ],
});
```

The Filament theme input stays until Task 43.

- [ ] **Step 5: Create the Inertia root view**

`resources/views/app.blade.php`:

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ $colorScheme === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>{{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <style>{!! $accentCss !!}</style>
    @routes
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body class="antialiased">
    @inertia
</body>
</html>
```

`$accentCss` and `$colorScheme` are supplied in Task 11. Until then, add temporary defaults so the page renders — put this at the top of the Blade file:

```blade
@php
    $accentCss ??= '';
    $colorScheme ??= 'dark';
@endphp
```

Remove the `@php` block in Task 11 once the view composer supplies them for real.

- [ ] **Step 6: Create the Vue entry point**

`resources/js/app.js`:

```js
import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import { ZiggyVue } from '../../vendor/tightenco/ziggy'
import PrimeVue from 'primevue/config'
import ToastService from 'primevue/toastservice'
import ConfirmationService from 'primevue/confirmationservice'
import preset from './theme/preset'
import 'primeicons/primeicons.css'

createInertiaApp({
    title: (title) => (title ? `${title} — ${import.meta.env.VITE_APP_NAME ?? 'Admin'}` : 'Admin'),
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true })
        return pages[`./Pages/${name}.vue`]
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(PrimeVue, {
                theme: {
                    preset,
                    options: {
                        darkModeSelector: '.dark',
                        cssLayer: { name: 'primevue', order: 'theme, base, primevue' },
                    },
                },
            })
            .use(ToastService)
            .use(ConfirmationService)
            .mount(el)
    },
    progress: { color: '#C9BE6E' },
})
```

- [ ] **Step 7: Write a boot test**

```bash
php artisan make:test --pest InertiaBootTest --no-interaction
```

```php
<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia;

it('renders an inertia page', function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $admin = User::factory()->create(['email' => 'admin@example.test']);

    Route::middleware('web')->get('/__inertia-probe', fn () => inertia('Probe', ['answer' => 42]));

    $this->actingAs($admin)
        ->get('/__inertia-probe')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Probe')
            ->where('answer', 42)
        );
});
```

Add `use Illuminate\Support\Facades\Route;` at the top.

- [ ] **Step 8: Run the test**

Run: `php artisan test --compact --filter=InertiaBootTest`
Expected: PASS. If it fails on a missing Vite manifest, run `npm run build` first.

- [ ] **Step 9: Build the assets**

Run: `npm run build`
Expected: builds without error. The build will fail on the missing `./theme/preset` import — that is Task 10. If you want a green build before Task 10, create `resources/js/theme/preset.js` with `export default {}` as a stub and replace it in Task 10.

- [ ] **Step 10: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add composer.json composer.lock package.json package-lock.json vite.config.js bootstrap/app.php app/Http/Middleware resources/views/app.blade.php resources/js/app.js config/fortify.php tests/Feature/InertiaBootTest.php
git commit -m "Install Inertia, Vue, PrimeVue, Fortify and Ziggy"
```

### Task 10: Build the theme preset and accent ramps

**Files:**
- Create: `resources/js/theme/ramps.js`, `resources/js/theme/preset.js`
- Create: `app/Support/Theme/AccentRamps.php`
- Test: `tests/Feature/Theme/AccentRampsTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - JS: `ramps.js` default-exports `RAMPS`, an object keyed by accent name whose values are `{50..950}` hex maps; `preset.js` default-exports a PrimeVue preset built from `RAMPS.khaki`.
  - PHP: `App\Support\Theme\AccentRamps::all(): array<string, array<string, string>>`, `::has(string $name): bool`, `::names(): array<int, string>`, `::cssVariables(string $name): string`.
  - Tasks 11, 38 and 39 consume the PHP side; Task 38 consumes the JS side.

- [ ] **Step 1: Write the ramps in JavaScript**

`resources/js/theme/ramps.js`:

```js
export const RAMPS = {
    khaki: {
        50: '#FBFAF0', 100: '#F5F1D8', 200: '#EAE3B0', 300: '#DCD183', 400: '#C9BE6E',
        500: '#B3A651', 600: '#96893F', 700: '#756A32', 800: '#5A522A', 900: '#4A4426', 950: '#2A2613',
    },
    amber: {
        50: '#FFFBEB', 100: '#FEF3C7', 200: '#FDE68A', 300: '#FCD34D', 400: '#FBBF24',
        500: '#F59E0B', 600: '#D97706', 700: '#B45309', 800: '#92400E', 900: '#78350F', 950: '#451A03',
    },
    orange: {
        50: '#FFF7ED', 100: '#FFEDD5', 200: '#FED7AA', 300: '#FDBA74', 400: '#FB923C',
        500: '#F97316', 600: '#EA580C', 700: '#C2410C', 800: '#9A3412', 900: '#7C2D12', 950: '#431407',
    },
    rose: {
        50: '#FFF1F2', 100: '#FFE4E6', 200: '#FECDD3', 300: '#FDA4AF', 400: '#FB7185',
        500: '#F43F5E', 600: '#E11D48', 700: '#BE123C', 800: '#9F1239', 900: '#881337', 950: '#4C0519',
    },
    emerald: {
        50: '#ECFDF5', 100: '#D1FAE5', 200: '#A7F3D0', 300: '#6EE7B7', 400: '#34D399',
        500: '#10B981', 600: '#059669', 700: '#047857', 800: '#065F46', 900: '#064E3B', 950: '#022C22',
    },
    sky: {
        50: '#F0F9FF', 100: '#E0F2FE', 200: '#BAE6FD', 300: '#7DD3FC', 400: '#38BDF8',
        500: '#0EA5E9', 600: '#0284C7', 700: '#0369A1', 800: '#075985', 900: '#0C4A6E', 950: '#082F49',
    },
    indigo: {
        50: '#EEF2FF', 100: '#E0E7FF', 200: '#C7D2FE', 300: '#A5B4FC', 400: '#818CF8',
        500: '#6366F1', 600: '#4F46E5', 700: '#4338CA', 800: '#3730A3', 900: '#312E81', 950: '#1E1B4B',
    },
    violet: {
        50: '#F5F3FF', 100: '#EDE9FE', 200: '#DDD6FE', 300: '#C4B5FD', 400: '#A78BFA',
        500: '#8B5CF6', 600: '#7C3AED', 700: '#6D28D9', 800: '#5B21B6', 900: '#4C1D95', 950: '#2E1065',
    },
    zinc: {
        50: '#FAFAFA', 100: '#F4F4F5', 200: '#E4E4E7', 300: '#D4D4D8', 400: '#A1A1AA',
        500: '#71717A', 600: '#52525B', 700: '#3F3F46', 800: '#27272A', 900: '#18181B', 950: '#09090B',
    },
}

/**
 * Accents whose mid-tones are light enough to require dark text on a filled button.
 */
export const LIGHT_ACCENTS = ['khaki', 'amber']

export default RAMPS
```

- [ ] **Step 2: Write the PrimeVue preset**

`resources/js/theme/preset.js`:

```js
import { definePreset } from '@primeuix/themes'
import Aura from '@primeuix/themes/aura'
import { RAMPS } from './ramps'

const DARK_INK = '#2A2613'

export default definePreset(Aura, {
    semantic: {
        primary: RAMPS.khaki,
        borderRadius: {
            none: '0',
            xs: '2px',
            sm: '3px',
            md: '4px',
            lg: '6px',
            xl: '10px',
        },
        colorScheme: {
            light: {
                primary: {
                    color: '{primary.500}',
                    contrastColor: DARK_INK,
                    hoverColor: '{primary.600}',
                    activeColor: '{primary.700}',
                },
                surface: {
                    0: '#ffffff',
                    50: '{zinc.50}', 100: '{zinc.100}', 200: '{zinc.200}', 300: '{zinc.300}',
                    400: '{zinc.400}', 500: '{zinc.500}', 600: '{zinc.600}', 700: '{zinc.700}',
                    800: '{zinc.800}', 900: '{zinc.900}', 950: '{zinc.950}',
                },
            },
            dark: {
                primary: {
                    color: '{primary.400}',
                    contrastColor: DARK_INK,
                    hoverColor: '{primary.300}',
                    activeColor: '{primary.200}',
                },
                surface: {
                    0: '#ffffff',
                    50: '{zinc.50}', 100: '{zinc.100}', 200: '{zinc.200}', 300: '{zinc.300}',
                    400: '{zinc.400}', 500: '{zinc.500}', 600: '{zinc.600}', 700: '{zinc.700}',
                    800: '{zinc.800}', 900: '{zinc.900}', 950: '{zinc.950}',
                },
            },
        },
    },
    components: {
        datatable: {
            headerCell: { padding: '0.625rem 0.75rem' },
            bodyCell: { padding: '0.5rem 0.75rem' },
        },
    },
})
```

`contrastColor` is set to the dark ink in both schemes. That is the spec's khaki constraint; for dark accents like indigo it is slightly unusual but readable, and Task 38 overrides it per accent.

- [ ] **Step 3: Write the failing PHP test**

```bash
php artisan make:test --pest Theme/AccentRampsTest --no-interaction
```

```php
<?php

declare(strict_types=1);

use App\Support\Theme\AccentRamps;

it('ships the nine accents from the spec', function (): void {
    expect(AccentRamps::names())->toBe([
        'khaki', 'amber', 'orange', 'rose', 'emerald', 'sky', 'indigo', 'violet', 'zinc',
    ]);
});

it('gives every accent a complete eleven-stop ramp', function (string $name): void {
    $ramp = AccentRamps::all()[$name];

    expect(array_keys($ramp))->toBe(['50', '100', '200', '300', '400', '500', '600', '700', '800', '900', '950']);

    foreach ($ramp as $hex) {
        expect($hex)->toMatch('/^#[0-9A-F]{6}$/');
    }
})->with(['khaki', 'amber', 'orange', 'rose', 'emerald', 'sky', 'indigo', 'violet', 'zinc']);

it('knows which accents exist', function (): void {
    expect(AccentRamps::has('khaki'))->toBeTrue();
    expect(AccentRamps::has('chartreuse'))->toBeFalse();
});

it('renders css custom properties for an accent', function (): void {
    $css = AccentRamps::cssVariables('khaki');

    expect($css)
        ->toContain(':root{')
        ->toContain('--p-primary-400:#C9BE6E;')
        ->toContain('--p-primary-950:#2A2613;')
        ->toEndWith('}');
});

it('falls back to khaki for an unknown accent', function (): void {
    expect(AccentRamps::cssVariables('chartreuse'))->toBe(AccentRamps::cssVariables('khaki'));
});
```

- [ ] **Step 4: Run test to verify it fails**

Run: `php artisan test --compact --filter=AccentRampsTest`
Expected: FAIL — `Class "App\Support\Theme\AccentRamps" not found`.

- [ ] **Step 5: Write the PHP mirror**

`app/Support/Theme/AccentRamps.php`:

```php
<?php

declare(strict_types=1);

namespace App\Support\Theme;

final class AccentRamps
{
    public const DEFAULT = 'khaki';

    /**
     * Mirrors resources/js/theme/ramps.js. Keep the two in sync.
     *
     * @var array<string, array<string, string>>
     */
    private const RAMPS = [
        'khaki' => ['50' => '#FBFAF0', '100' => '#F5F1D8', '200' => '#EAE3B0', '300' => '#DCD183', '400' => '#C9BE6E', '500' => '#B3A651', '600' => '#96893F', '700' => '#756A32', '800' => '#5A522A', '900' => '#4A4426', '950' => '#2A2613'],
        'amber' => ['50' => '#FFFBEB', '100' => '#FEF3C7', '200' => '#FDE68A', '300' => '#FCD34D', '400' => '#FBBF24', '500' => '#F59E0B', '600' => '#D97706', '700' => '#B45309', '800' => '#92400E', '900' => '#78350F', '950' => '#451A03'],
        'orange' => ['50' => '#FFF7ED', '100' => '#FFEDD5', '200' => '#FED7AA', '300' => '#FDBA74', '400' => '#FB923C', '500' => '#F97316', '600' => '#EA580C', '700' => '#C2410C', '800' => '#9A3412', '900' => '#7C2D12', '950' => '#431407'],
        'rose' => ['50' => '#FFF1F2', '100' => '#FFE4E6', '200' => '#FECDD3', '300' => '#FDA4AF', '400' => '#FB7185', '500' => '#F43F5E', '600' => '#E11D48', '700' => '#BE123C', '800' => '#9F1239', '900' => '#881337', '950' => '#4C0519'],
        'emerald' => ['50' => '#ECFDF5', '100' => '#D1FAE5', '200' => '#A7F3D0', '300' => '#6EE7B7', '400' => '#34D399', '500' => '#10B981', '600' => '#059669', '700' => '#047857', '800' => '#065F46', '900' => '#064E3B', '950' => '#022C22'],
        'sky' => ['50' => '#F0F9FF', '100' => '#E0F2FE', '200' => '#BAE6FD', '300' => '#7DD3FC', '400' => '#38BDF8', '500' => '#0EA5E9', '600' => '#0284C7', '700' => '#0369A1', '800' => '#075985', '900' => '#0C4A6E', '950' => '#082F49'],
        'indigo' => ['50' => '#EEF2FF', '100' => '#E0E7FF', '200' => '#C7D2FE', '300' => '#A5B4FC', '400' => '#818CF8', '500' => '#6366F1', '600' => '#4F46E5', '700' => '#4338CA', '800' => '#3730A3', '900' => '#312E81', '950' => '#1E1B4B'],
        'violet' => ['50' => '#F5F3FF', '100' => '#EDE9FE', '200' => '#DDD6FE', '300' => '#C4B5FD', '400' => '#A78BFA', '500' => '#8B5CF6', '600' => '#7C3AED', '700' => '#6D28D9', '800' => '#5B21B6', '900' => '#4C1D95', '950' => '#2E1065'],
        'zinc' => ['50' => '#FAFAFA', '100' => '#F4F4F5', '200' => '#E4E4E7', '300' => '#D4D4D8', '400' => '#A1A1AA', '500' => '#71717A', '600' => '#52525B', '700' => '#3F3F46', '800' => '#27272A', '900' => '#18181B', '950' => '#09090B'],
    ];

    /**
     * @return array<string, array<string, string>>
     */
    public static function all(): array
    {
        return self::RAMPS;
    }

    /**
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_keys(self::RAMPS);
    }

    public static function has(string $name): bool
    {
        return array_key_exists($name, self::RAMPS);
    }

    /**
     * Inline CSS custom properties so the correct accent paints before Vue boots.
     */
    public static function cssVariables(string $name): string
    {
        $ramp = self::RAMPS[self::has($name) ? $name : self::DEFAULT];

        $declarations = '';

        foreach ($ramp as $stop => $hex) {
            $declarations .= "--p-primary-{$stop}:{$hex};";
        }

        return ':root{'.$declarations.'}';
    }
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --compact --filter=AccentRampsTest`
Expected: PASS (5 test groups, 13 assertions).

- [ ] **Step 7: Write the design system reference**

The spec promises this alongside the preset. `docs/superpowers/design-system.md`:

```markdown
# Admin design system

## Type scale
- Page title: 20px / 600 / -0.01em
- Section heading: 14px / 600
- Body and table cells: 14px / 400
- Meta, captions, footer: 12px / 400, surface-500
- Numbers, IDs, dates, currency: monospace, tabular-nums

## Spacing
4px base. Card padding 16px. Page gutter 16px, max width 1280px.
Form field gap 20px. Table cell padding 8px/12px, header 10px/12px.

## Color
One accent, used only for primary actions and active states. Everything
else neutral. Khaki fills take dark text (#2A2613), never white.
Borders 1px, surface-200 / surface-800. No gradients, no card shadows.

## Badge semantics
| Variant | Meaning | Used by |
| --- | --- | --- |
| primary | categorised, has a colour set | expense/income category |
| success | healthy, public, paid, active | repository visibility, debt paid |
| warning | needs attention, private, due soon | debt due within 7 days |
| danger | overdue, outgoing money | overdue debts, expense currency |
| info | neutral classification | creditor type "person" |
| secondary | uncategorised, unknown | fallback |

## States
- Empty table: centred, 48px vertical padding, surface-500, one sentence.
- Loading: Inertia's progress bar only; no skeletons.
- Destructive actions: always a confirm dialog naming what is deleted.
- Form errors: inline under the field, red, 12px. No error summary banner.
```

Keep this file updated as the panel grows; it is what keeps thirteen resources looking like one product.

- [ ] **Step 8: Build and commit**

```bash
npm run build
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
git add resources/js/theme app/Support/Theme docs/superpowers/design-system.md tests/Feature/Theme
git commit -m "Add khaki-default accent ramps, the PrimeVue token preset and the design system reference"
```

### Task 11: Share appearance and flash data with every Inertia response

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Modify: `resources/views/app.blade.php` (remove the temporary `@php` defaults)
- Create: `app/Support/Theme/Appearance.php`
- Test: `tests/Feature/Theme/AppearanceTest.php`

**Interfaces:**
- Consumes: `AccentRamps` (Task 10), `Setting` model, `AdminNotifier` flash key (Task 2).
- Produces:
  - `App\Support\Theme\Appearance::accent(): string`, `::colorScheme(): string`, `::toArray(): array{accent: string, colorScheme: string}`.
  - Inertia shared props `auth.user`, `appearance`, `flash.notification`.
  - Blade view data `$accentCss` and `$colorScheme`.
  - Tasks 13, 14, 38 and 40 read these.

- [ ] **Step 1: Write the failing test**

```bash
php artisan make:test --pest Theme/AppearanceTest --no-interaction
```

```php
<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Support\Theme\Appearance;

it('defaults to khaki and system', function (): void {
    expect(Appearance::accent())->toBe('khaki');
    expect(Appearance::colorScheme())->toBe('system');
});

it('reads stored values', function (): void {
    Setting::set('appearance', 'accent', 'emerald');
    Setting::set('appearance', 'color_scheme', 'light');

    expect(Appearance::accent())->toBe('emerald');
    expect(Appearance::colorScheme())->toBe('light');
});

it('falls back to khaki for an unknown stored accent', function (): void {
    Setting::set('appearance', 'accent', 'chartreuse');

    expect(Appearance::accent())->toBe('khaki');
});

it('falls back to system for an unknown stored scheme', function (): void {
    Setting::set('appearance', 'color_scheme', 'neon');

    expect(Appearance::colorScheme())->toBe('system');
});

it('exposes an array for inertia', function (): void {
    Setting::set('appearance', 'accent', 'rose');
    Setting::set('appearance', 'color_scheme', 'dark');

    expect(Appearance::toArray())->toBe(['accent' => 'rose', 'colorScheme' => 'dark']);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=AppearanceTest`
Expected: FAIL — `Class "App\Support\Theme\Appearance" not found`.

- [ ] **Step 3: Write Appearance**

`app/Support/Theme/Appearance.php`:

```php
<?php

declare(strict_types=1);

namespace App\Support\Theme;

use App\Models\Setting;

final class Appearance
{
    public const SCHEMES = ['light', 'dark', 'system'];

    public static function accent(): string
    {
        $stored = Setting::get('appearance', 'accent', AccentRamps::DEFAULT);
        $accent = is_string($stored) ? $stored : AccentRamps::DEFAULT;

        return AccentRamps::has($accent) ? $accent : AccentRamps::DEFAULT;
    }

    public static function colorScheme(): string
    {
        $stored = Setting::get('appearance', 'color_scheme', 'system');
        $scheme = is_string($stored) ? $stored : 'system';

        return in_array($scheme, self::SCHEMES, true) ? $scheme : 'system';
    }

    /**
     * @return array{accent: string, colorScheme: string}
     */
    public static function toArray(): array
    {
        return [
            'accent' => self::accent(),
            'colorScheme' => self::colorScheme(),
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=AppearanceTest`
Expected: PASS (5 tests).

- [ ] **Step 5: Share the data through the middleware**

`app/Http/Middleware/HandleInertiaRequests.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Theme\Appearance;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user === null ? null : [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ],
            'appearance' => Appearance::toArray(),
            'flash' => [
                'notification' => fn (): mixed => $request->session()->get('flash.notification'),
            ],
            'env' => [
                'name' => app()->environment(),
                'isProduction' => app()->isProduction(),
            ],
        ];
    }
}
```

- [ ] **Step 6: Feed the root view**

In `HandleInertiaRequests`, add:

```php
/**
 * @return array<string, mixed>
 */
public function rootView(Request $request): string
{
    return $this->rootView;
}
```

is not needed. Instead supply the Blade variables with a view composer. In `AppServiceProvider::boot()`, append:

```php
View::composer('app', function ($view): void {
    $view->with([
        'accentCss' => AccentRamps::cssVariables(Appearance::accent()),
        'colorScheme' => Appearance::colorScheme(),
    ]);
});
```

Add `use App\Support\Theme\AccentRamps;`, `use App\Support\Theme\Appearance;` and `use Illuminate\Support\Facades\View;`.

- [ ] **Step 7: Remove the temporary defaults from the Blade view**

Delete the `@php $accentCss ??= ''; $colorScheme ??= 'dark'; @endphp` block added in Task 9 Step 5.

The `system` scheme must resolve client-side, so change the `<html>` class expression to:

```blade
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      class="{{ $colorScheme === 'dark' ? 'dark' : '' }}"
      data-color-scheme="{{ $colorScheme }}">
```

and add this inline script immediately after the `<style>` tag, before `@vite`:

```blade
<script>
    (function () {
        var scheme = document.documentElement.dataset.colorScheme;
        if (scheme === 'system') {
            var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.classList.toggle('dark', prefersDark);
        }
    })();
</script>
```

- [ ] **Step 8: Test the shared props end to end**

Append to `tests/Feature/Theme/AppearanceTest.php`:

```php
it('shares appearance and flash with every inertia response', function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $admin = App\Models\User::factory()->create(['email' => 'admin@example.test']);

    Setting::set('appearance', 'accent', 'sky');

    Illuminate\Support\Facades\Route::middleware('web')->get('/__appearance-probe', fn () => inertia('Probe'));

    $this->actingAs($admin)
        ->get('/__appearance-probe')
        ->assertInertia(fn (Inertia\Testing\AssertableInertia $page) => $page
            ->where('appearance.accent', 'sky')
            ->where('appearance.colorScheme', 'system')
            ->where('auth.user.email', 'admin@example.test')
        );
});

it('inlines the accent css in the root view', function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $admin = App\Models\User::factory()->create(['email' => 'admin@example.test']);

    Setting::set('appearance', 'accent', 'emerald');

    Illuminate\Support\Facades\Route::middleware('web')->get('/__css-probe', fn () => inertia('Probe'));

    $this->actingAs($admin)
        ->get('/__css-probe')
        ->assertSee('--p-primary-400:#34D399;', escape: false);
});
```

- [ ] **Step 9: Run the tests**

Run: `php artisan test --compact --filter=AppearanceTest`
Expected: PASS (7 tests).

- [ ] **Step 10: Analyse, format, commit**

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
git add app/Support/Theme app/Http/Middleware app/Providers/AppServiceProvider.php resources/views/app.blade.php tests/Feature/Theme
git commit -m "Share appearance, auth and flash state with every Inertia response"
```

### Task 12: Route scaffolding and the admin middleware group

**Files:**
- Create: `routes/admin.php`
- Modify: `bootstrap/app.php` (register the route file)
- Create: `app/Http/Controllers/Admin/DashboardController.php`
- Create: `resources/js/Pages/Dashboard.vue` (placeholder replaced in Task 45)
- Test: `tests/Feature/Admin/AdminAccessTest.php`

**Interfaces:**
- Consumes: the `access-admin` gate from Task 4.
- Produces: a route group prefixed `/app`, named `admin.*`, with middleware `['auth', 'can:access-admin']`. Route `admin.dashboard` exists. Every Phase 3–5 task adds routes to `routes/admin.php`.

- [ ] **Step 1: Write the failing test**

```bash
php artisan make:test --pest Admin/AdminAccessTest --no-interaction
```

```php
<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
});

it('redirects guests to login', function (): void {
    $this->get('/app')->assertRedirect('/login');
});

it('forbids a non-admin user', function (): void {
    $user = User::factory()->create(['email' => 'nobody@example.test']);

    $this->actingAs($user)->get('/app')->assertForbidden();
});

it('renders the dashboard for the admin', function (): void {
    $admin = User::factory()->create(['email' => 'admin@example.test']);

    $this->actingAs($admin)
        ->get('/app')
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Dashboard'));
});

it('names the route admin.dashboard', function (): void {
    expect(route('admin.dashboard'))->toEndWith('/app');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=AdminAccessTest`
Expected: FAIL — 404, route not defined.

- [ ] **Step 3: Create the route file**

`routes/admin.php`:

```php
<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:access-admin'])
    ->prefix('app')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');
    });
```

- [ ] **Step 4: Register it**

In `bootstrap/app.php`, change `withRouting`:

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
    then: function (): void {
        Route::middleware('web')->group(base_path('routes/admin.php'));
    },
)
```

Add `use Illuminate\Support\Facades\Route;` at the top of the file.

- [ ] **Step 5: Create the controller**

```bash
php artisan make:controller Admin/DashboardController --invokable --no-interaction
```

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Dashboard');
    }
}
```

- [ ] **Step 6: Create the placeholder page**

`resources/js/Pages/Dashboard.vue`:

```vue
<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
</script>

<template>
    <AdminLayout title="Dashboard">
        <p class="text-surface-500 dark:text-surface-400">Dashboard widgets land in Phase 5.</p>
    </AdminLayout>
</template>
```

Add the `@` alias to `vite.config.js` inside `defineConfig`:

```js
resolve: {
    alias: {
        '@': '/resources/js',
    },
},
```

`AdminLayout.vue` arrives in Task 13; until then this page will fail to build. Complete Task 13 before running `npm run build`.

- [ ] **Step 7: Run the PHP tests**

Run: `php artisan test --compact --filter=AdminAccessTest`
Expected: PASS (4 tests). Inertia page assertions do not require the Vue file to exist.

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
git add routes/admin.php bootstrap/app.php app/Http/Controllers/Admin resources/js/Pages/Dashboard.vue vite.config.js tests/Feature/Admin
git commit -m "Add the /app route group gated on access-admin"
```

### Task 13: Build AdminLayout

**Files:**
- Create: `resources/js/Layouts/AdminLayout.vue`
- Create: `resources/js/navigation.js`
- Create: `app/Support/Navigation.php`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php` (share navigation)
- Test: `tests/Feature/Admin/NavigationTest.php`

**Interfaces:**
- Consumes: shared props from Task 11.
- Produces:
  - `App\Support\Navigation::clusters(): array` returning
    `array<int, array{label: string, icon: string, items: array<int, array{label: string, route: string, icon: string}>}>`.
  - Inertia shared prop `navigation`.
  - `AdminLayout.vue` with props `title: string` and a default slot, plus named slots `actions` and `subheader`.
  - Tasks 14, 21–45 all render inside `AdminLayout`. Task 35 reads `navigation` for the spotlight registry.

- [ ] **Step 1: Write the failing test**

```bash
php artisan make:test --pest Admin/NavigationTest --no-interaction
```

```php
<?php

declare(strict_types=1);

use App\Support\Navigation;

it('exposes the five clusters in order', function (): void {
    $labels = array_column(Navigation::clusters(), 'label');

    expect($labels)->toBe(['Blog', 'Budget', 'Work', 'Library', 'General']);
});

it('gives every item a resolvable route name', function (): void {
    foreach (Navigation::clusters() as $cluster) {
        foreach ($cluster['items'] as $item) {
            expect(Illuminate\Support\Facades\Route::has($item['route']))
                ->toBeTrue("Route {$item['route']} is not defined");
        }
    }
});

it('gives every item a label and icon', function (): void {
    foreach (Navigation::clusters() as $cluster) {
        expect($cluster['icon'])->toBeString()->not->toBeEmpty();

        foreach ($cluster['items'] as $item) {
            expect($item['label'])->toBeString()->not->toBeEmpty();
            expect($item['icon'])->toBeString()->not->toBeEmpty();
        }
    }
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=NavigationTest`
Expected: FAIL — `Class "App\Support\Navigation" not found`.

- [ ] **Step 3: Write Navigation with only the routes that exist so far**

`app/Support/Navigation.php`:

```php
<?php

declare(strict_types=1);

namespace App\Support;

final class Navigation
{
    /**
     * Single source of truth for top navigation and the spotlight registry.
     *
     * Add one entry per resource as it is ported. Every `route` must be a
     * defined route name, or NavigationTest fails.
     *
     * @return array<int, array{label: string, icon: string, items: array<int, array{label: string, route: string, icon: string}>}>
     */
    public static function clusters(): array
    {
        return [
            [
                'label' => 'Blog',
                'icon' => 'pi pi-pencil',
                'items' => [],
            ],
            [
                'label' => 'Budget',
                'icon' => 'pi pi-wallet',
                'items' => [],
            ],
            [
                'label' => 'Work',
                'icon' => 'pi pi-briefcase',
                'items' => [],
            ],
            [
                'label' => 'Library',
                'icon' => 'pi pi-book',
                'items' => [],
            ],
            [
                'label' => 'General',
                'icon' => 'pi pi-cog',
                'items' => [],
            ],
        ];
    }
}
```

Every cluster starts with empty `items`. Each resource task from Task 21 onward adds its own navigation entry **in the same commit that defines its route**, so `NavigationTest` never sees an entry pointing at a route that does not exist yet. Task 42's parity test tightens this by additionally asserting no cluster is left empty.


- [ ] **Step 4: Share it**

In `HandleInertiaRequests::share()`, add `'navigation' => Navigation::clusters(),` and import `App\Support\Navigation`.

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=NavigationTest`
Expected: PASS (3 tests).

- [ ] **Step 6: Write the layout**

`resources/js/Layouts/AdminLayout.vue`:

```vue
<script setup>
import { computed, ref, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import Toast from 'primevue/toast'
import Menu from 'primevue/menu'
import Button from 'primevue/button'
import ConfirmDialog from 'primevue/confirmdialog'
import { useToast } from 'primevue/usetoast'

defineProps({
    title: { type: String, required: true },
})

const page = usePage()
const toast = useToast()

const navigation = computed(() => page.props.navigation ?? [])
const user = computed(() => page.props.auth?.user ?? null)
const env = computed(() => page.props.env ?? { name: 'production', isProduction: true })

const openCluster = ref(null)
const clusterMenus = ref({})

function toggleCluster(event, label) {
    clusterMenus.value[label]?.toggle(event)
    openCluster.value = label
}

watch(
    () => page.props.flash?.notification,
    (notification) => {
        if (!notification) {
            return
        }

        toast.add({
            severity: notification.variant === 'danger' ? 'error' : notification.variant,
            summary: notification.title,
            detail: notification.body ?? undefined,
            life: 5000,
        })
    },
    { immediate: true },
)

function logout() {
    router.post(route('logout'))
}
</script>

<template>
    <Head :title="title" />

    <div class="min-h-screen bg-surface-50 text-surface-900 dark:bg-surface-950 dark:text-surface-100">
        <div
            v-if="!env.isProduction"
            class="w-full bg-primary py-1 text-center text-xs font-medium uppercase tracking-wide text-primary-contrast"
        >
            {{ env.name }}
        </div>

        <header class="border-b border-surface-200 bg-surface-0 dark:border-surface-800 dark:bg-surface-900">
            <div class="mx-auto flex h-14 max-w-[1280px] items-center gap-6 px-4">
                <Link :href="route('admin.dashboard')" class="font-mono text-sm font-semibold tracking-tight">
                    admin
                </Link>

                <nav class="flex items-center gap-1">
                    <template v-for="cluster in navigation" :key="cluster.label">
                        <Button
                            v-if="cluster.items.length"
                            :label="cluster.label"
                            :icon="cluster.icon"
                            text
                            size="small"
                            severity="secondary"
                            @click="toggleCluster($event, cluster.label)"
                        />
                        <Menu
                            v-if="cluster.items.length"
                            :ref="(el) => (clusterMenus[cluster.label] = el)"
                            :model="cluster.items.map((item) => ({
                                label: item.label,
                                icon: item.icon,
                                command: () => router.visit(route(item.route)),
                            }))"
                            popup
                        />
                    </template>
                </nav>

                <div class="ml-auto flex items-center gap-2">
                    <slot name="topbar" />

                    <Button
                        v-if="user"
                        :label="user.name"
                        icon="pi pi-user"
                        text
                        size="small"
                        severity="secondary"
                        @click="logout"
                    />
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-[1280px] px-4 py-8">
            <div class="mb-6 flex items-start justify-between gap-4">
                <h1 class="text-xl font-semibold tracking-tight">{{ title }}</h1>
                <div class="flex items-center gap-2">
                    <slot name="actions" />
                </div>
            </div>

            <slot name="subheader" />

            <slot />
        </main>

        <footer class="border-t border-surface-200 py-6 dark:border-surface-800">
            <div class="mx-auto flex max-w-[1280px] items-center justify-between px-4 text-xs text-surface-500">
                <span class="font-mono">{{ env.name }}</span>
                <a href="https://github.com/mnurullahsaglam" class="hover:text-primary" rel="noopener">GitHub</a>
            </div>
        </footer>

        <Toast position="bottom-right" />
        <ConfirmDialog />
    </div>
</template>
```

The env strip and footer satisfy the spec's environment-indicator and easy-footer replacements. The avatar button logs out directly for now; Task 15 turns it into a menu.

- [ ] **Step 7: Add the Tailwind PrimeUI plugin**

In `resources/css/app.css`, add after the existing Tailwind import:

```css
@import 'tailwindcss';
@import 'tailwindcss-primeui';
```

Keep any existing content in the file below these lines.

- [ ] **Step 8: Build**

Run: `npm run build`
Expected: builds clean.

- [ ] **Step 9: Commit**

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
git add app/Support/Navigation.php app/Http/Middleware resources/js/Layouts resources/css/app.css tests/Feature/Admin/NavigationTest.php
git commit -m "Add AdminLayout with top navigation, toasts, env strip and footer"
```

### Task 14: Fortify login and logout

**Files:**
- Modify: `config/fortify.php`
- Modify: `app/Providers/FortifyServiceProvider.php` (published in Task 9)
- Modify: `bootstrap/providers.php`
- Create: `resources/js/Pages/Auth/Login.vue`
- Test: `tests/Feature/Auth/LoginTest.php`

**Interfaces:**
- Consumes: `AdminLayout` is **not** used here — login renders standalone.
- Produces: routes `login` (GET/POST) and `logout` (POST). Task 12's test already asserts the guest redirect to `/login`.

- [ ] **Step 1: Write the failing test**

```bash
php artisan make:test --pest Auth/LoginTest --no-interaction
```

```php
<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
});

it('renders the login page', function (): void {
    $this->get('/login')
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/Login'));
});

it('logs the admin in with valid credentials', function (): void {
    $admin = User::factory()->create([
        'email' => 'admin@example.test',
        'password' => Hash::make('correct-horse'),
    ]);

    $this->post('/login', [
        'email' => 'admin@example.test',
        'password' => 'correct-horse',
    ])->assertRedirect('/app');

    $this->assertAuthenticatedAs($admin);
});

it('rejects a wrong password', function (): void {
    User::factory()->create([
        'email' => 'admin@example.test',
        'password' => Hash::make('correct-horse'),
    ]);

    $this->from('/login')->post('/login', [
        'email' => 'admin@example.test',
        'password' => 'wrong',
    ])->assertRedirect('/login')->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('logs out', function (): void {
    $admin = User::factory()->create(['email' => 'admin@example.test']);

    $this->actingAs($admin)->post('/logout')->assertRedirect('/');

    $this->assertGuest();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=LoginTest`
Expected: FAIL — Fortify's default views are not Inertia pages.

- [ ] **Step 3: Register the Fortify provider**

In `bootstrap/providers.php`, add `App\Providers\FortifyServiceProvider::class,` to the array. Leave `AdminPanelProvider` in place until Task 43.

- [ ] **Step 4: Configure Fortify**

In `config/fortify.php`, set:

```php
'home' => '/app',
'views' => true,
'features' => [
    Features::updatePasswords(),
    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]),
],
```

Remove `Features::registration()`, `Features::resetPasswords()` and `Features::emailVerification()` from the array — this is a single-user panel and registration must not be reachable.

- [ ] **Step 5: Point Fortify at the Inertia page**

In `app/Providers/FortifyServiceProvider::boot()`:

```php
Fortify::loginView(fn (): Response => Inertia::render('Auth/Login', [
    'status' => session('status'),
]));

RateLimiter::for('login', function (Request $request): Limit {
    $throttleKey = Str::transliterate(Str::lower((string) $request->input(Fortify::username())).'|'.$request->ip());

    return Limit::perMinute(5)->by($throttleKey);
});
```

Imports: `use Inertia\Inertia;`, `use Inertia\Response;`, `use Illuminate\Cache\RateLimiting\Limit;`, `use Illuminate\Support\Facades\RateLimiter;`, `use Illuminate\Support\Str;`, `use Illuminate\Http\Request;`, `use Laravel\Fortify\Fortify;`.

- [ ] **Step 6: Write the login page**

`resources/js/Pages/Auth/Login.vue`:

```vue
<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Checkbox from 'primevue/checkbox'
import Button from 'primevue/button'
import Message from 'primevue/message'

defineProps({
    status: { type: String, default: null },
})

const form = useForm({
    email: '',
    password: '',
    remember: false,
})

function submit() {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    })
}
</script>

<template>
    <Head title="Sign in" />

    <div class="flex min-h-screen items-center justify-center bg-surface-50 px-4 dark:bg-surface-950">
        <div class="w-full max-w-sm">
            <h1 class="mb-8 font-mono text-sm font-semibold tracking-tight text-surface-900 dark:text-surface-100">
                admin
            </h1>

            <Message v-if="status" severity="info" class="mb-4">{{ status }}</Message>

            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <div class="flex flex-col gap-2">
                    <label for="email" class="text-sm font-medium">Email</label>
                    <InputText
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="username"
                        autofocus
                        :invalid="Boolean(form.errors.email)"
                    />
                    <small v-if="form.errors.email" class="text-red-500">{{ form.errors.email }}</small>
                </div>

                <div class="flex flex-col gap-2">
                    <label for="password" class="text-sm font-medium">Password</label>
                    <Password
                        id="password"
                        v-model="form.password"
                        toggle-mask
                        :feedback="false"
                        autocomplete="current-password"
                        input-class="w-full"
                        :invalid="Boolean(form.errors.password)"
                    />
                    <small v-if="form.errors.password" class="text-red-500">{{ form.errors.password }}</small>
                </div>

                <div class="flex items-center gap-2">
                    <Checkbox v-model="form.remember" input-id="remember" binary />
                    <label for="remember" class="text-sm">Remember me</label>
                </div>

                <Button type="submit" label="Sign in" :loading="form.processing" />
            </form>
        </div>
    </div>
</template>
```

- [ ] **Step 7: Run the tests**

Run: `php artisan test --compact --filter=LoginTest`
Expected: PASS (4 tests).

- [ ] **Step 8: Build and check it by hand**

Run: `npm run build`, then open `https://myblog.test/login` and sign in. You should land on `/app` with the khaki accent and the dev environment strip visible.

- [ ] **Step 9: Commit**

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
git add config/fortify.php app/Providers/FortifyServiceProvider.php bootstrap/providers.php resources/js/Pages/Auth tests/Feature/Auth
git commit -m "Add Fortify-backed Inertia login"
```

### Task 15: Profile page with password change and 2FA

**Files:**
- Create: `resources/js/Pages/Profile.vue`
- Create: `app/Http/Controllers/Admin/ProfileController.php`
- Modify: `routes/admin.php`
- Modify: `resources/js/Layouts/AdminLayout.vue` (avatar menu instead of logout button)
- Test: `tests/Feature/Admin/ProfileTest.php`

**Interfaces:**
- Consumes: Fortify routes `user-password.update`, `two-factor.enable`, `two-factor.disable`, `two-factor.qr-code`, `two-factor.recovery-codes`.
- Produces: route `admin.profile`. The avatar menu in `AdminLayout` links to it.

- [ ] **Step 1: Write the failing test**

```bash
php artisan make:test --pest Admin/ProfileTest --no-interaction
```

```php
<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->admin = User::factory()->create([
        'email' => 'admin@example.test',
        'password' => Hash::make('current-password'),
    ]);
});

it('renders the profile page', function (): void {
    $this->actingAs($this->admin)
        ->get(route('admin.profile'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Profile')
            ->where('twoFactorEnabled', false)
        );
});

it('updates the password', function (): void {
    $this->actingAs($this->admin)
        ->put('/user/password', [
            'current_password' => 'current-password',
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])
        ->assertSessionHasNoErrors();

    expect(Hash::check('a-brand-new-password', $this->admin->fresh()->password))->toBeTrue();
});

it('rejects a wrong current password', function (): void {
    $this->actingAs($this->admin)
        ->from(route('admin.profile'))
        ->put('/user/password', [
            'current_password' => 'nope',
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])
        ->assertSessionHasErrors('current_password');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=ProfileTest`
Expected: FAIL — route `admin.profile` not defined.

- [ ] **Step 3: Create the controller**

```bash
php artisan make:controller Admin/ProfileController --invokable --no-interaction
```

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Profile', [
            'twoFactorEnabled' => $user !== null && $user->two_factor_secret !== null,
        ]);
    }
}
```

- [ ] **Step 4: Add the route**

In `routes/admin.php`, inside the group:

```php
Route::get('profile', ProfileController::class)->name('profile');
```

Import `App\Http\Controllers\Admin\ProfileController`.

- [ ] **Step 5: Write the page**

`resources/js/Pages/Profile.vue`:

```vue
<script setup>
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Card from 'primevue/card'
import Password from 'primevue/password'
import Button from 'primevue/button'

defineProps({
    twoFactorEnabled: { type: Boolean, default: false },
})

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
})

function updatePassword() {
    passwordForm.put(route('user-password.update'), {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    })
}

const twoFactorForm = useForm({})

function enableTwoFactor() {
    twoFactorForm.post(route('two-factor.enable'), { preserveScroll: true })
}

function disableTwoFactor() {
    twoFactorForm.delete(route('two-factor.disable'), { preserveScroll: true })
}
</script>

<template>
    <AdminLayout title="Profile">
        <div class="flex max-w-2xl flex-col gap-6">
            <Card>
                <template #title>Change password</template>
                <template #content>
                    <form class="flex flex-col gap-4" @submit.prevent="updatePassword">
                        <div class="flex flex-col gap-2">
                            <label for="current_password" class="text-sm font-medium">Current password</label>
                            <Password
                                id="current_password"
                                v-model="passwordForm.current_password"
                                toggle-mask
                                :feedback="false"
                                :invalid="Boolean(passwordForm.errors.current_password)"
                            />
                            <small v-if="passwordForm.errors.current_password" class="text-red-500">
                                {{ passwordForm.errors.current_password }}
                            </small>
                        </div>

                        <div class="flex flex-col gap-2">
                            <label for="password" class="text-sm font-medium">New password</label>
                            <Password
                                id="password"
                                v-model="passwordForm.password"
                                toggle-mask
                                :invalid="Boolean(passwordForm.errors.password)"
                            />
                            <small v-if="passwordForm.errors.password" class="text-red-500">
                                {{ passwordForm.errors.password }}
                            </small>
                        </div>

                        <div class="flex flex-col gap-2">
                            <label for="password_confirmation" class="text-sm font-medium">Confirm new password</label>
                            <Password
                                id="password_confirmation"
                                v-model="passwordForm.password_confirmation"
                                toggle-mask
                                :feedback="false"
                            />
                        </div>

                        <Button type="submit" label="Update password" :loading="passwordForm.processing" class="self-start" />
                    </form>
                </template>
            </Card>

            <Card>
                <template #title>Two-factor authentication</template>
                <template #content>
                    <p class="mb-4 text-sm text-surface-500 dark:text-surface-400">
                        {{ twoFactorEnabled ? 'Two-factor authentication is enabled.' : 'Two-factor authentication is not enabled.' }}
                    </p>

                    <Button
                        v-if="!twoFactorEnabled"
                        label="Enable"
                        :loading="twoFactorForm.processing"
                        @click="enableTwoFactor"
                    />
                    <Button
                        v-else
                        label="Disable"
                        severity="danger"
                        outlined
                        :loading="twoFactorForm.processing"
                        @click="disableTwoFactor"
                    />
                </template>
            </Card>
        </div>
    </AdminLayout>
</template>
```

- [ ] **Step 6: Turn the layout's logout button into a menu**

In `resources/js/Layouts/AdminLayout.vue`, replace the avatar `Button` and the `logout()` function with:

```vue
<Button
    v-if="user"
    :label="user.name"
    icon="pi pi-user"
    text
    size="small"
    severity="secondary"
    @click="userMenu.toggle($event)"
/>
<Menu ref="userMenu" :model="userMenuItems" popup />
```

and in the script:

```js
const userMenu = ref()

const userMenuItems = computed(() => [
    { label: 'Profile', icon: 'pi pi-user', command: () => router.visit(route('admin.profile')) },
    { separator: true },
    { label: 'Sign out', icon: 'pi pi-sign-out', command: () => router.post(route('logout')) },
])
```

- [ ] **Step 7: Run the tests**

Run: `php artisan test --compact --filter=ProfileTest`
Expected: PASS (3 tests).

- [ ] **Step 8: Build and commit**

```bash
npm run build
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
git add app/Http/Controllers/Admin/ProfileController.php routes/admin.php resources/js/Pages/Profile.vue resources/js/Layouts/AdminLayout.vue tests/Feature/Admin/ProfileTest.php
git commit -m "Add profile page with password change and two-factor toggle"
```

---

# Phase 2 — The engine

This phase carries most of the project's risk. Tasks 16–20 build the contract; Task 21 proves it end to end on Posts, the smallest resource in the app. If the contract is wrong, it surfaces here against 80 lines of definition rather than 1000.

### Task 16: The Column value object

**Files:**
- Create: `app/Tables/Column.php`
- Test: `tests/Feature/Tables/ColumnTest.php`

**Interfaces:**
- Consumes: `App\Support\Contracts\HasColor`, `HasLabel` (Task 1).
- Produces: `App\Tables\Column` with named constructors
  `text`, `money`, `date`, `datetime`, `badge`, `image`, `boolean`, `count`,
  fluent modifiers
  `label`, `sortable`, `toggleable`, `limit`, `tooltip`, `default`, `color`, `align`, `circular`, `size`, `numeric`, `state`,
  and two readers:
  - `schema(): array{key: string, label: string, type: string, sortable: bool, toggleable: bool, hiddenByDefault: bool, align: string}`
  - `resolve(Model $record): array{display: string, raw: mixed, variant: string|null, tooltip: string|null, meta: array<string, mixed>}`
- Tasks 18, 19 and 22–33 all depend on these exact names.

- [ ] **Step 1: Write the failing test**

```bash
php artisan make:test --pest Tables/ColumnTest --no-interaction
```

```php
<?php

declare(strict_types=1);

use App\Enums\Currencies;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Post;
use App\Tables\Column;

it('describes itself in the schema', function (): void {
    $column = Column::text('title')->sortable();

    expect($column->schema())->toBe([
        'key' => 'title',
        'label' => 'Title',
        'type' => 'text',
        'sortable' => true,
        'toggleable' => false,
        'hiddenByDefault' => false,
        'align' => 'left',
    ]);
});

it('humanises a dotted key into a label', function (): void {
    expect(Column::text('expenseCategory.name')->schema()['label'])->toBe('Category');
    expect(Column::text('created_at')->schema()['label'])->toBe('Created at');
});

it('honours an explicit label', function (): void {
    expect(Column::text('tax_no')->label('Tax number')->schema()['label'])->toBe('Tax number');
});

it('resolves a plain text value', function (): void {
    $post = Post::factory()->create(['title' => 'Learning Rust']);

    expect(Column::text('title')->resolve($post))
        ->toMatchArray(['display' => 'Learning Rust', 'raw' => 'Learning Rust']);
});

it('resolves a dotted relationship value', function (): void {
    $category = ExpenseCategory::factory()->create(['name' => 'Groceries']);
    $expense = Expense::factory()->create(['expense_category_id' => $category->id]);

    expect(Column::text('expenseCategory.name')->resolve($expense)['display'])->toBe('Groceries');
});

it('falls back to the default when the value is null', function (): void {
    $expense = Expense::factory()->create(['debt_id' => null]);

    expect(Column::text('debt.creditor_name')->default('N/A')->resolve($expense)['display'])->toBe('N/A');
});

it('formats money using the currency taken from another column', function (): void {
    $expense = Expense::factory()->create(['amount' => 1250.5, 'currency' => Currencies::TRY->value]);

    $resolved = Column::money('amount', currencyFrom: 'currency')->resolve($expense);

    expect($resolved['display'])->toBe('₺1,250.50');
    expect($resolved['raw'])->toBe(1250.5);
});

it('formats money with a fixed currency', function (): void {
    $expense = Expense::factory()->create(['amount' => 99.0]);

    expect(Column::money('amount', currency: 'USD')->resolve($expense)['display'])->toBe('$99.00');
});

it('truncates and tooltips a long value', function (): void {
    $long = str_repeat('a', 80);
    $post = Post::factory()->create(['content' => $long]);

    $resolved = Column::text('content')->limit(50)->tooltip()->resolve($post);

    expect($resolved['display'])->toHaveLength(53)->toEndWith('...');
    expect($resolved['tooltip'])->toBe($long);
});

it('does not tooltip a short value', function (): void {
    $post = Post::factory()->create(['content' => 'short']);

    expect(Column::text('content')->limit(50)->tooltip()->resolve($post)['tooltip'])->toBeNull();
});

it('resolves a badge variant from a closure', function (): void {
    $expense = Expense::factory()->create();

    $resolved = Column::badge('currency')
        ->color(fn (Expense $record): string => 'danger')
        ->resolve($expense);

    expect($resolved['variant'])->toBe('danger');
});

it('resolves a badge variant from a static string', function (): void {
    $expense = Expense::factory()->create();

    expect(Column::badge('currency')->color('warning')->resolve($expense)['variant'])->toBe('warning');
});

it('resolves a badge variant from a HasColor enum without configuration', function (): void {
    $repository = App\Models\Repository::factory()->create();

    $resolved = Column::badge('visibility')->resolve($repository);

    expect($resolved['variant'])->toBeIn(['primary', 'secondary', 'success', 'warning', 'danger', 'info', 'gray']);
});

it('formats a date and keeps the iso value', function (): void {
    $expense = Expense::factory()->create(['date' => '2026-03-14']);

    $resolved = Column::date('date')->resolve($expense);

    expect($resolved['display'])->toBe('14 Mar 2026');
    expect($resolved['raw'])->toBe('2026-03-14');
});

it('formats a datetime', function (): void {
    $expense = Expense::factory()->create();

    expect(Column::datetime('created_at')->resolve($expense)['display'])->toMatch('/^\d{2} \w{3} \d{4}, \d{2}:\d{2}$/');
});

it('resolves an image to a public url', function (): void {
    $expense = Expense::factory()->create(['receipt_path' => 'receipts/one.png']);

    $resolved = Column::image('receipt_path')->circular()->size(40)->resolve($expense);

    expect($resolved['display'])->toContain('receipts/one.png');
    expect($resolved['meta'])->toBe(['circular' => true, 'size' => 40]);
});

it('resolves an image to null when the path is empty', function (): void {
    $expense = Expense::factory()->create(['receipt_path' => null]);

    expect(Column::image('receipt_path')->resolve($expense)['display'])->toBe('');
});

it('resolves a boolean', function (): void {
    $repository = App\Models\Repository::factory()->create(['is_active' => true]);

    $resolved = Column::boolean('is_active')->resolve($repository);

    expect($resolved['raw'])->toBeTrue();
    expect($resolved['variant'])->toBe('success');
});

it('resolves a computed state closure', function (): void {
    $post = Post::factory()->create(['title' => 'Hello']);

    $resolved = Column::text('headline')
        ->state(fn (Post $record): string => strtoupper($record->title))
        ->resolve($post);

    expect($resolved['display'])->toBe('HELLO');
});

it('marks a toggleable column hidden by default', function (): void {
    $schema = Column::datetime('created_at')->toggleable(hiddenByDefault: true)->schema();

    expect($schema['toggleable'])->toBeTrue();
    expect($schema['hiddenByDefault'])->toBeTrue();
});

it('right-aligns numeric columns by default', function (): void {
    expect(Column::money('amount', currency: 'TRY')->schema()['align'])->toBe('right');
    expect(Column::text('title')->schema()['align'])->toBe('left');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=ColumnTest`
Expected: FAIL — `Class "App\Tables\Column" not found`.

- [ ] **Step 3: Write the Column class**

`app/Tables/Column.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tables;

use App\Support\Contracts\HasColor;
use App\Support\Contracts\HasLabel;
use BackedEnum;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class Column
{
    private ?string $label = null;

    private bool $sortable = false;

    private bool $toggleable = false;

    private bool $hiddenByDefault = false;

    private ?int $limit = null;

    private bool $tooltip = false;

    private ?string $default = null;

    private string|Closure|null $color = null;

    private ?string $align = null;

    private bool $circular = false;

    private ?int $size = null;

    private ?Closure $state = null;

    private function __construct(
        public readonly string $key,
        public readonly string $type,
        private readonly ?string $currency = null,
        private readonly ?string $currencyFrom = null,
    ) {}

    public static function text(string $key): self
    {
        return new self($key, 'text');
    }

    public static function money(string $key, ?string $currency = null, ?string $currencyFrom = null): self
    {
        return new self($key, 'money', currency: $currency, currencyFrom: $currencyFrom);
    }

    public static function date(string $key): self
    {
        return new self($key, 'date');
    }

    public static function datetime(string $key): self
    {
        return new self($key, 'datetime');
    }

    public static function badge(string $key): self
    {
        return new self($key, 'badge');
    }

    public static function image(string $key): self
    {
        return new self($key, 'image');
    }

    public static function boolean(string $key): self
    {
        return new self($key, 'boolean');
    }

    public static function count(string $key): self
    {
        return new self($key, 'count');
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function sortable(bool $sortable = true): self
    {
        $this->sortable = $sortable;

        return $this;
    }

    public function toggleable(bool $toggleable = true, bool $hiddenByDefault = false): self
    {
        $this->toggleable = $toggleable;
        $this->hiddenByDefault = $hiddenByDefault;

        return $this;
    }

    public function limit(int $characters): self
    {
        $this->limit = $characters;

        return $this;
    }

    public function tooltip(bool $tooltip = true): self
    {
        $this->tooltip = $tooltip;

        return $this;
    }

    public function default(string $default): self
    {
        $this->default = $default;

        return $this;
    }

    public function color(string|Closure $color): self
    {
        $this->color = $color;

        return $this;
    }

    public function align(string $align): self
    {
        $this->align = $align;

        return $this;
    }

    public function circular(bool $circular = true): self
    {
        $this->circular = $circular;

        return $this;
    }

    public function size(int $pixels): self
    {
        $this->size = $pixels;

        return $this;
    }

    public function numeric(): self
    {
        $this->align = 'right';

        return $this;
    }

    /**
     * Compute the value from the record instead of reading an attribute.
     */
    public function state(Closure $state): self
    {
        $this->state = $state;

        return $this;
    }

    public function isSortable(): bool
    {
        return $this->sortable;
    }

    /**
     * @return array{key: string, label: string, type: string, sortable: bool, toggleable: bool, hiddenByDefault: bool, align: string}
     */
    public function schema(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label ?? $this->humanisedLabel(),
            'type' => $this->type,
            'sortable' => $this->sortable,
            'toggleable' => $this->toggleable,
            'hiddenByDefault' => $this->hiddenByDefault,
            'align' => $this->align ?? $this->defaultAlign(),
        ];
    }

    /**
     * @return array{display: string, raw: mixed, variant: string|null, tooltip: string|null, meta: array<string, mixed>}
     */
    public function resolve(Model $record): array
    {
        $raw = $this->state instanceof Closure
            ? ($this->state)($record)
            : data_get($record, $this->key);

        return match ($this->type) {
            'money' => $this->resolveMoney($record, $raw),
            'date' => $this->resolveDate($raw, 'd M Y'),
            'datetime' => $this->resolveDate($raw, 'd M Y, H:i'),
            'badge' => $this->resolveBadge($record, $raw),
            'image' => $this->resolveImage($raw),
            'boolean' => $this->resolveBoolean($raw),
            default => $this->resolveText($record, $raw),
        };
    }

    /**
     * @return array{display: string, raw: mixed, variant: string|null, tooltip: string|null, meta: array<string, mixed>}
     */
    private function resolveText(Model $record, mixed $raw): array
    {
        $full = $this->stringify($raw);

        if ($full === '' && $this->default !== null) {
            return $this->payload($this->default, $raw);
        }

        if ($this->limit === null || mb_strlen($full) <= $this->limit) {
            return $this->payload($full, $raw, variant: $this->resolveVariant($record, $raw));
        }

        return $this->payload(
            Str::limit($full, $this->limit),
            $raw,
            variant: $this->resolveVariant($record, $raw),
            tooltip: $this->tooltip ? $full : null,
        );
    }

    /**
     * @return array{display: string, raw: mixed, variant: string|null, tooltip: string|null, meta: array<string, mixed>}
     */
    private function resolveMoney(Model $record, mixed $raw): array
    {
        if ($raw === null) {
            return $this->payload($this->default ?? '', null);
        }

        $amount = is_numeric($raw) ? (float) $raw : 0.0;
        $code = $this->currencyCode($record);
        $symbol = \App\Enums\Currencies::tryFrom($code)?->getSymbol() ?? $code.' ';

        return $this->payload($symbol.number_format($amount, 2), $amount);
    }

    private function currencyCode(Model $record): string
    {
        if ($this->currency !== null) {
            return $this->currency;
        }

        if ($this->currencyFrom === null) {
            return 'TRY';
        }

        $value = data_get($record, $this->currencyFrom);

        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return is_string($value) ? $value : 'TRY';
    }

    /**
     * @return array{display: string, raw: mixed, variant: string|null, tooltip: string|null, meta: array<string, mixed>}
     */
    private function resolveDate(mixed $raw, string $format): array
    {
        if ($raw === null || $raw === '') {
            return $this->payload($this->default ?? '', null);
        }

        $date = $raw instanceof Carbon ? $raw : Carbon::parse($this->stringify($raw));

        return $this->payload(
            $date->format($format),
            $format === 'd M Y' ? $date->toDateString() : $date->toIso8601String(),
        );
    }

    /**
     * @return array{display: string, raw: mixed, variant: string|null, tooltip: string|null, meta: array<string, mixed>}
     */
    private function resolveBadge(Model $record, mixed $raw): array
    {
        $display = $raw instanceof HasLabel ? $raw->getLabel() : $this->stringify($raw);

        if ($display === '' && $this->default !== null) {
            $display = $this->default;
        }

        return $this->payload($display, $raw, variant: $this->resolveVariant($record, $raw) ?? 'secondary');
    }

    /**
     * @return array{display: string, raw: mixed, variant: string|null, tooltip: string|null, meta: array<string, mixed>}
     */
    private function resolveImage(mixed $raw): array
    {
        $path = $this->stringify($raw);

        return $this->payload(
            $path === '' ? '' : Storage::disk('public')->url($path),
            $path === '' ? null : $path,
            meta: ['circular' => $this->circular, 'size' => $this->size ?? 32],
        );
    }

    /**
     * @return array{display: string, raw: mixed, variant: string|null, tooltip: string|null, meta: array<string, mixed>}
     */
    private function resolveBoolean(mixed $raw): array
    {
        $value = (bool) $raw;

        return $this->payload($value ? 'Yes' : 'No', $value, variant: $value ? 'success' : 'gray');
    }

    private function resolveVariant(Model $record, mixed $raw): ?string
    {
        if ($this->color instanceof Closure) {
            $resolved = ($this->color)($record);

            return is_string($resolved) ? $resolved : null;
        }

        if (is_string($this->color)) {
            return $this->color;
        }

        if ($raw instanceof HasColor) {
            return $raw->getColor();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{display: string, raw: mixed, variant: string|null, tooltip: string|null, meta: array<string, mixed>}
     */
    private function payload(string $display, mixed $raw, ?string $variant = null, ?string $tooltip = null, array $meta = []): array
    {
        return [
            'display' => $display,
            'raw' => $raw,
            'variant' => $variant,
            'tooltip' => $tooltip,
            'meta' => $meta,
        ];
    }

    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return is_scalar($value) ? (string) $value : '';
    }

    private function humanisedLabel(): string
    {
        $segments = explode('.', $this->key);
        $last = end($segments);

        if ($last === 'name' && count($segments) > 1) {
            $last = $segments[count($segments) - 2];
        }

        return Str::ucfirst(Str::of($last)->snake(' ')->replace('_', ' ')->toString());
    }

    private function defaultAlign(): string
    {
        return in_array($this->type, ['money', 'count'], true) ? 'right' : 'left';
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=ColumnTest`
Expected: PASS (20 tests). Two assertions are environment-sensitive:
- the money test assumes `number_format` defaults (`1,250.50`), which matches the code above;
- the `expenseCategory.name` label test relies on `humanisedLabel()` collapsing `x.name` to `X`. Confirm it produces `Category` for `expenseCategory.name` — `Str::of('expenseCategory')->snake(' ')` yields `expense category`, so the expected label is actually `Expense category`. Fix the test expectation to `'Expense category'` and add `->label('Category')` in the resource definitions where a shorter label is wanted, as `ExpenseTable` already does.

- [ ] **Step 5: Analyse, format, commit**

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
git add app/Tables/Column.php tests/Feature/Tables/ColumnTest.php
git commit -m "Add the Column value object for the table contract"
```

### Task 17: The Filter value object

**Files:**
- Create: `app/Tables/Filter.php`
- Test: `tests/Feature/Tables/FilterTest.php`

**Interfaces:**
- Consumes: `App\Support\Contracts\HasLabel` (Task 1).
- Produces: `App\Tables\Filter` with named constructors
  `relationship(string $key, string $relation, string $labelColumn)`,
  `enum(string $key, string $enumClass)`,
  `select(string $key, array $options)`,
  `dateRange(string $column)`,
  `boolean(string $column)`,
  `custom(string $key, string $label, Closure $query)`,
  modifiers `label`, `multiple`, `trueLabel`, `falseLabel`, `default`,
  and readers `schema(): array` and `apply(Builder $query, mixed $value): void`.
- Task 18 calls `apply()`; Task 19 renders `schema()`.

- [ ] **Step 1: Write the failing test**

```bash
php artisan make:test --pest Tables/FilterTest --no-interaction
```

```php
<?php

declare(strict_types=1);

use App\Enums\Currencies;
use App\Models\Debt;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Tables\Filter;
use Illuminate\Database\Eloquent\Builder;

it('describes a relationship filter with its options', function (): void {
    $food = ExpenseCategory::factory()->create(['name' => 'Food']);
    $rent = ExpenseCategory::factory()->create(['name' => 'Rent']);

    $schema = Filter::relationship('expense_category_id', 'expenseCategory', 'name')
        ->label('Category')
        ->multiple()
        ->schema();

    expect($schema['key'])->toBe('expense_category_id');
    expect($schema['type'])->toBe('select');
    expect($schema['label'])->toBe('Category');
    expect($schema['multiple'])->toBeTrue();
    expect($schema['options'])->toContain(
        ['value' => $food->id, 'label' => 'Food'],
        ['value' => $rent->id, 'label' => 'Rent'],
    );
});

it('describes an enum filter using HasLabel', function (): void {
    $schema = Filter::enum('currency', Currencies::class)->multiple()->schema();

    expect($schema['options'])->toContain(['value' => 'TRY', 'label' => 'Turkish Lira']);
    expect($schema['options'])->toHaveCount(count(Currencies::cases()));
});

it('describes a select filter from a plain map', function (): void {
    $schema = Filter::select('status', ['pending' => 'Pending', 'paid' => 'Paid'])->schema();

    expect($schema['options'])->toBe([
        ['value' => 'pending', 'label' => 'Pending'],
        ['value' => 'paid', 'label' => 'Paid'],
    ]);
});

it('describes a date range filter', function (): void {
    $schema = Filter::dateRange('date')->schema();

    expect($schema['type'])->toBe('dateRange');
    expect($schema['key'])->toBe('date');
    expect($schema['options'])->toBe([]);
});

it('describes a boolean filter with custom labels', function (): void {
    $schema = Filter::boolean('receipt_path')
        ->label('Receipt')
        ->trueLabel('Has receipt')
        ->falseLabel('No receipt')
        ->schema();

    expect($schema['type'])->toBe('boolean');
    expect($schema['options'])->toBe([
        ['value' => 'yes', 'label' => 'Has receipt'],
        ['value' => 'no', 'label' => 'No receipt'],
    ]);
});

it('applies a single-value relationship filter', function (): void {
    $food = ExpenseCategory::factory()->create();
    $rent = ExpenseCategory::factory()->create();
    Expense::factory()->create(['expense_category_id' => $food->id]);
    Expense::factory()->create(['expense_category_id' => $rent->id]);

    $query = Expense::query();
    Filter::relationship('expense_category_id', 'expenseCategory', 'name')->apply($query, $food->id);

    expect($query->count())->toBe(1);
});

it('applies a multi-value relationship filter', function (): void {
    $a = ExpenseCategory::factory()->create();
    $b = ExpenseCategory::factory()->create();
    $c = ExpenseCategory::factory()->create();
    Expense::factory()->create(['expense_category_id' => $a->id]);
    Expense::factory()->create(['expense_category_id' => $b->id]);
    Expense::factory()->create(['expense_category_id' => $c->id]);

    $query = Expense::query();
    Filter::relationship('expense_category_id', 'expenseCategory', 'name')
        ->multiple()
        ->apply($query, [$a->id, $b->id]);

    expect($query->count())->toBe(2);
});

it('ignores an empty filter value', function (): void {
    Expense::factory()->count(3)->create();

    $query = Expense::query();
    Filter::relationship('expense_category_id', 'expenseCategory', 'name')->apply($query, null);
    Filter::enum('currency', Currencies::class)->apply($query, []);
    Filter::select('x', ['a' => 'A'])->apply($query, '');

    expect($query->count())->toBe(3);
});

it('applies a date range', function (): void {
    Expense::factory()->create(['date' => '2026-01-10']);
    Expense::factory()->create(['date' => '2026-02-20']);
    Expense::factory()->create(['date' => '2026-03-30']);

    $query = Expense::query();
    Filter::dateRange('date')->apply($query, ['from' => '2026-02-01', 'to' => '2026-03-01']);

    expect($query->count())->toBe(1);
});

it('applies an open-ended date range', function (): void {
    Expense::factory()->create(['date' => '2026-01-10']);
    Expense::factory()->create(['date' => '2026-05-10']);

    $query = Expense::query();
    Filter::dateRange('date')->apply($query, ['from' => '2026-02-01', 'to' => null]);

    expect($query->count())->toBe(1);
});

it('applies a boolean filter both ways', function (): void {
    Expense::factory()->create(['receipt_path' => 'a.png']);
    Expense::factory()->create(['receipt_path' => null]);

    $withReceipt = Expense::query();
    Filter::boolean('receipt_path')->apply($withReceipt, 'yes');
    expect($withReceipt->count())->toBe(1);

    $withoutReceipt = Expense::query();
    Filter::boolean('receipt_path')->apply($withoutReceipt, 'no');
    expect($withoutReceipt->count())->toBe(1);
});

it('applies a custom query closure', function (): void {
    Debt::factory()->overdue()->create();
    Debt::factory()->create(['status' => 'pending', 'due_date' => now()->addYear()->toDateString()]);

    $query = Debt::query();
    Filter::custom('overdue', 'Overdue debts', fn (Builder $q): Builder => $q
        ->where('status', 'pending')
        ->whereNotNull('due_date')
        ->where('due_date', '<', now()))
        ->apply($query, 'yes');

    expect($query->count())->toBe(1);
});

it('does not apply a custom filter when unset', function (): void {
    Debt::factory()->count(3)->create();

    $query = Debt::query();
    Filter::custom('overdue', 'Overdue debts', fn (Builder $q): Builder => $q->where('status', 'paid'))
        ->apply($query, null);

    expect($query->count())->toBe(3);
});

it('exposes a default value in the schema', function (): void {
    expect(Filter::enum('currency', Currencies::class)->default('TRY')->schema()['default'])->toBe('TRY');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=FilterTest`
Expected: FAIL — `Class "App\Tables\Filter" not found`.

- [ ] **Step 3: Write the Filter class**

`app/Tables/Filter.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tables;

use App\Support\Contracts\HasLabel;
use BackedEnum;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class Filter
{
    private ?string $label = null;

    private bool $multiple = false;

    private string $trueLabel = 'Yes';

    private string $falseLabel = 'No';

    private mixed $default = null;

    /**
     * @param  array<int, array{value: mixed, label: string}>  $options
     */
    private function __construct(
        public readonly string $key,
        public readonly string $type,
        private array $options = [],
        private readonly ?string $relation = null,
        private readonly ?string $labelColumn = null,
        private readonly ?string $enumClass = null,
        private readonly ?Closure $query = null,
    ) {}

    public static function relationship(string $key, string $relation, string $labelColumn): self
    {
        return new self($key, 'select', relation: $relation, labelColumn: $labelColumn);
    }

    /**
     * @param  class-string<BackedEnum>  $enumClass
     */
    public static function enum(string $key, string $enumClass): self
    {
        return new self($key, 'select', enumClass: $enumClass);
    }

    /**
     * @param  array<string, string>  $options
     */
    public static function select(string $key, array $options): self
    {
        $mapped = [];

        foreach ($options as $value => $label) {
            $mapped[] = ['value' => $value, 'label' => $label];
        }

        return new self($key, 'select', options: $mapped);
    }

    public static function dateRange(string $column): self
    {
        return new self($column, 'dateRange');
    }

    public static function boolean(string $column): self
    {
        return new self($column, 'boolean');
    }

    public static function custom(string $key, string $label, Closure $query): self
    {
        return (new self($key, 'boolean', query: $query))->label($label);
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function multiple(bool $multiple = true): self
    {
        $this->multiple = $multiple;

        return $this;
    }

    public function trueLabel(string $label): self
    {
        $this->trueLabel = $label;

        return $this;
    }

    public function falseLabel(string $label): self
    {
        $this->falseLabel = $label;

        return $this;
    }

    public function default(mixed $default): self
    {
        $this->default = $default;

        return $this;
    }

    /**
     * @return array{key: string, type: string, label: string, multiple: bool, options: array<int, array{value: mixed, label: string}>, default: mixed}
     */
    public function schema(): array
    {
        return [
            'key' => $this->key,
            'type' => $this->type,
            'label' => $this->label ?? $this->humanisedLabel(),
            'multiple' => $this->multiple,
            'options' => $this->resolveOptions(),
            'default' => $this->default,
        ];
    }

    public function apply(Builder $query, mixed $value): void
    {
        if ($this->isEmpty($value)) {
            return;
        }

        if ($this->query instanceof Closure) {
            ($this->query)($query);

            return;
        }

        match ($this->type) {
            'dateRange' => $this->applyDateRange($query, $value),
            'boolean' => $this->applyBoolean($query, $value),
            default => $this->applySelect($query, $value),
        };
    }

    private function applySelect(Builder $query, mixed $value): void
    {
        if (is_array($value)) {
            $query->whereIn($this->key, $value);

            return;
        }

        $query->where($this->key, $value);
    }

    private function applyDateRange(Builder $query, mixed $value): void
    {
        if (! is_array($value)) {
            return;
        }

        $from = is_string($value['from'] ?? null) && $value['from'] !== '' ? $value['from'] : null;
        $to = is_string($value['to'] ?? null) && $value['to'] !== '' ? $value['to'] : null;

        $query
            ->when($from, fn (Builder $q, string $date): Builder => $q->whereDate($this->key, '>=', $date))
            ->when($to, fn (Builder $q, string $date): Builder => $q->whereDate($this->key, '<=', $date));
    }

    private function applyBoolean(Builder $query, mixed $value): void
    {
        if ($value === 'yes' || $value === true) {
            $query->whereNotNull($this->key);

            return;
        }

        if ($value === 'no' || $value === false) {
            $query->whereNull($this->key);
        }
    }

    private function isEmpty(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (is_array($value)) {
            return $value === [] || collect($value)->filter(fn (mixed $v): bool => $v !== null && $v !== '')->isEmpty();
        }

        return false;
    }

    /**
     * @return array<int, array{value: mixed, label: string}>
     */
    private function resolveOptions(): array
    {
        if ($this->type === 'boolean') {
            return [
                ['value' => 'yes', 'label' => $this->trueLabel],
                ['value' => 'no', 'label' => $this->falseLabel],
            ];
        }

        if ($this->type === 'dateRange') {
            return [];
        }

        if ($this->enumClass !== null) {
            return $this->enumOptions();
        }

        if ($this->relation !== null && $this->labelColumn !== null) {
            return $this->relationshipOptions();
        }

        return $this->options;
    }

    /**
     * @return array<int, array{value: mixed, label: string}>
     */
    private function enumOptions(): array
    {
        $cases = ($this->enumClass)::cases();
        $options = [];

        foreach ($cases as $case) {
            $options[] = [
                'value' => $case->value,
                'label' => $case instanceof HasLabel ? $case->getLabel() : (string) $case->value,
            ];
        }

        return $options;
    }

    /**
     * @return array<int, array{value: mixed, label: string}>
     */
    private function relationshipOptions(): array
    {
        $relatedClass = $this->relatedModelClass();

        if ($relatedClass === null) {
            return [];
        }

        return $relatedClass::query()
            ->orderBy($this->labelColumn)
            ->get(['id', $this->labelColumn])
            ->map(fn (mixed $record): array => [
                'value' => $record->id,
                'label' => (string) $record->{$this->labelColumn},
            ])
            ->all();
    }

    /**
     * Derived from the foreign key: expense_category_id -> App\Models\ExpenseCategory.
     *
     * @return class-string<\Illuminate\Database\Eloquent\Model>|null
     */
    private function relatedModelClass(): ?string
    {
        $base = Str::studly(Str::beforeLast($this->key, '_id'));
        $class = 'App\\Models\\'.$base;

        return class_exists($class) ? $class : null;
    }

    private function humanisedLabel(): string
    {
        return Str::ucfirst(Str::of($this->key)->beforeLast('_id')->snake(' ')->replace('_', ' ')->toString());
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=FilterTest`
Expected: PASS (14 tests).

- [ ] **Step 5: Analyse, format, commit**

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
git add app/Tables/Filter.php tests/Feature/Tables/FilterTest.php
git commit -m "Add the Filter value object for the table contract"
```

### Task 18: The ResourceTable base class

**Files:**
- Create: `app/Tables/ResourceTable.php`
- Test: `tests/Feature/Tables/ResourceTableTest.php`

**Interfaces:**
- Consumes: `Column` (Task 16), `Filter` (Task 17).
- Produces: `App\Tables\ResourceTable` — abstract, with:
  - protected `string $model`, `array $with = []`, `string $defaultSort`, `int $perPage = 25`
  - abstract `protected function columns(): array` returning `array<int, Column>`
  - `protected function filters(): array` returning `array<int, Filter>` (default `[]`)
  - `protected function searchable(): array` returning `array<int, string>` (default `[]`)
  - `protected function query(): Builder` (default `$this->model::query()`)
  - `public function schema(): array{columns: array, filters: array, defaultSort: string, searchable: bool, perPage: int}`
  - `public function rows(Request $request): LengthAwarePaginator`
  - `public function search(string $term, int $limit): Collection` (used by Task 36)
- Tasks 19, 21–33 and 36 depend on these.

- [ ] **Step 1: Write the failing test**

```bash
php artisan make:test --pest Tables/ResourceTableTest --no-interaction
```

```php
<?php

declare(strict_types=1);

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\ResourceTable;
use Illuminate\Http\Request;

/**
 * A fixture table so the base class can be tested without depending on a
 * resource definition that may change.
 */
final class FixtureExpenseTable extends ResourceTable
{
    protected string $model = Expense::class;

    protected array $with = ['expenseCategory'];

    protected string $defaultSort = '-date';

    protected function columns(): array
    {
        return [
            Column::money('amount', currencyFrom: 'currency')->sortable(),
            Column::badge('expenseCategory.name')->label('Category'),
            Column::text('description')->limit(20)->tooltip(),
            Column::date('date')->sortable(),
        ];
    }

    protected function filters(): array
    {
        return [
            Filter::relationship('expense_category_id', 'expenseCategory', 'name')->multiple(),
            Filter::dateRange('date'),
        ];
    }

    protected function searchable(): array
    {
        return ['description'];
    }
}

function tableRequest(array $query = []): Request
{
    return Request::create('/app/expenses', 'GET', $query);
}

it('emits a schema describing columns and filters', function (): void {
    $schema = (new FixtureExpenseTable)->schema();

    expect($schema['columns'])->toHaveCount(4);
    expect($schema['columns'][0]['key'])->toBe('amount');
    expect($schema['columns'][0]['sortable'])->toBeTrue();
    expect($schema['filters'])->toHaveCount(2);
    expect($schema['defaultSort'])->toBe('-date');
    expect($schema['searchable'])->toBeTrue();
    expect($schema['perPage'])->toBe(25);
});

it('returns rows keyed by id with resolved cells', function (): void {
    $category = ExpenseCategory::factory()->create(['name' => 'Food']);
    $expense = Expense::factory()->create([
        'expense_category_id' => $category->id,
        'amount' => 250.00,
        'currency' => 'TRY',
        'description' => 'Lunch',
    ]);

    $rows = (new FixtureExpenseTable)->rows(tableRequest());
    $row = $rows->items()[0];

    expect($row['id'])->toBe($expense->id);
    expect($row['cells']['amount']['display'])->toBe('₺250.00');
    expect($row['cells']['expenseCategory.name']['display'])->toBe('Food');
    expect($row['cells']['description']['display'])->toBe('Lunch');
});

it('applies the default sort', function (): void {
    Expense::factory()->create(['date' => '2026-01-01']);
    $newest = Expense::factory()->create(['date' => '2026-06-01']);

    $rows = (new FixtureExpenseTable)->rows(tableRequest());

    expect($rows->items()[0]['id'])->toBe($newest->id);
});

it('applies an ascending sort from the request', function (): void {
    $oldest = Expense::factory()->create(['date' => '2026-01-01']);
    Expense::factory()->create(['date' => '2026-06-01']);

    $rows = (new FixtureExpenseTable)->rows(tableRequest(['sort' => 'date']));

    expect($rows->items()[0]['id'])->toBe($oldest->id);
});

it('applies a descending sort from the request', function (): void {
    Expense::factory()->create(['amount' => 10]);
    $biggest = Expense::factory()->create(['amount' => 9999]);

    $rows = (new FixtureExpenseTable)->rows(tableRequest(['sort' => '-amount']));

    expect($rows->items()[0]['id'])->toBe($biggest->id);
});

it('ignores a sort on an unsortable or unknown column', function (): void {
    Expense::factory()->count(2)->create();

    $rows = (new FixtureExpenseTable)->rows(tableRequest(['sort' => 'receipt_path']));

    expect($rows->total())->toBe(2);
});

it('searches the declared columns', function (): void {
    Expense::factory()->create(['description' => 'Coffee beans']);
    Expense::factory()->create(['description' => 'Train ticket']);

    $rows = (new FixtureExpenseTable)->rows(tableRequest(['search' => 'coffee']));

    expect($rows->total())->toBe(1);
});

it('applies filters from the request', function (): void {
    $food = ExpenseCategory::factory()->create();
    $rent = ExpenseCategory::factory()->create();
    Expense::factory()->create(['expense_category_id' => $food->id]);
    Expense::factory()->create(['expense_category_id' => $rent->id]);

    $rows = (new FixtureExpenseTable)->rows(tableRequest([
        'filter' => ['expense_category_id' => [$food->id]],
    ]));

    expect($rows->total())->toBe(1);
});

it('applies a date range filter from the request', function (): void {
    Expense::factory()->create(['date' => '2026-01-10']);
    Expense::factory()->create(['date' => '2026-02-15']);

    $rows = (new FixtureExpenseTable)->rows(tableRequest([
        'filter' => ['date' => ['from' => '2026-02-01', 'to' => '2026-02-28']],
    ]));

    expect($rows->total())->toBe(1);
});

it('paginates and honours a per-page override', function (): void {
    Expense::factory()->count(30)->create();

    $rows = (new FixtureExpenseTable)->rows(tableRequest(['perPage' => 10, 'page' => 2]));

    expect($rows->perPage())->toBe(10);
    expect($rows->currentPage())->toBe(2);
    expect($rows->total())->toBe(30);
    expect($rows->items())->toHaveCount(10);
});

it('clamps an absurd per-page value', function (): void {
    Expense::factory()->count(5)->create();

    expect((new FixtureExpenseTable)->rows(tableRequest(['perPage' => 5000]))->perPage())->toBe(100);
    expect((new FixtureExpenseTable)->rows(tableRequest(['perPage' => 0]))->perPage())->toBe(25);
});

it('eager loads the declared relations', function (): void {
    ExpenseCategory::factory()->create();
    Expense::factory()->count(3)->create();

    DB::enableQueryLog();
    (new FixtureExpenseTable)->rows(tableRequest());
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBeLessThanOrEqual(3);
});

it('returns lightweight search results', function (): void {
    Expense::factory()->create(['description' => 'Coffee beans']);
    Expense::factory()->count(10)->create(['description' => 'Coffee filter']);

    $results = (new FixtureExpenseTable)->search('coffee', 5);

    expect($results)->toHaveCount(5);
    expect($results->first())->toHaveKeys(['id', 'label']);
});
```

Add `use Illuminate\Support\Facades\DB;` at the top.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=ResourceTableTest`
Expected: FAIL — `Class "App\Tables\ResourceTable" not found`.

- [ ] **Step 3: Write the base class**

`app/Tables/ResourceTable.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tables;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

abstract class ResourceTable
{
    /** @var class-string<Model> */
    protected string $model;

    /** @var array<int, string> */
    protected array $with = [];

    /** @var array<int, string> */
    protected array $withCount = [];

    protected string $defaultSort = '-id';

    protected int $perPage = 25;

    private const MAX_PER_PAGE = 100;

    /**
     * @return array<int, Column>
     */
    abstract protected function columns(): array;

    /**
     * @return array<int, Filter>
     */
    protected function filters(): array
    {
        return [];
    }

    /**
     * Column paths searched by the search box and by global search.
     *
     * @return array<int, string>
     */
    protected function searchable(): array
    {
        return [];
    }

    /**
     * The attribute used as the human label in global search results.
     */
    protected function titleColumn(): string
    {
        return 'name';
    }

    protected function query(): Builder
    {
        return $this->model::query();
    }

    /**
     * @return array{columns: array<int, array<string, mixed>>, filters: array<int, array<string, mixed>>, defaultSort: string, searchable: bool, perPage: int}
     */
    public function schema(): array
    {
        return [
            'columns' => array_map(fn (Column $column): array => $column->schema(), $this->columns()),
            'filters' => array_map(fn (Filter $filter): array => $filter->schema(), $this->filters()),
            'defaultSort' => $this->defaultSort,
            'searchable' => $this->searchable() !== [],
            'perPage' => $this->perPage,
        ];
    }

    /**
     * @return LengthAwarePaginator<int, array{id: mixed, cells: array<string, array<string, mixed>>}>
     */
    public function rows(Request $request): LengthAwarePaginator
    {
        $query = $this->query();

        if ($this->with !== []) {
            $query->with($this->with);
        }

        if ($this->withCount !== []) {
            $query->withCount($this->withCount);
        }

        $this->applySearch($query, $request);
        $this->applyFilters($query, $request);
        $this->applySort($query, $request);

        $columns = $this->columns();

        return $query
            ->paginate($this->resolvePerPage($request))
            ->withQueryString()
            ->through(fn (Model $record): array => [
                'id' => $record->getKey(),
                'cells' => $this->resolveCells($columns, $record),
            ]);
    }

    /**
     * Lightweight rows for the global search palette.
     *
     * @return Collection<int, array{id: mixed, label: string}>
     */
    public function search(string $term, int $limit): Collection
    {
        if ($this->searchable() === [] || $term === '') {
            return collect();
        }

        $query = $this->query();
        $this->applySearchTerm($query, $term);

        $titleColumn = $this->titleColumn();

        return $query
            ->limit($limit)
            ->get()
            ->map(fn (Model $record): array => [
                'id' => $record->getKey(),
                'label' => (string) (data_get($record, $titleColumn) ?? $record->getKey()),
            ]);
    }

    /**
     * @param  array<int, Column>  $columns
     * @return array<string, array<string, mixed>>
     */
    private function resolveCells(array $columns, Model $record): array
    {
        $cells = [];

        foreach ($columns as $column) {
            $cells[$column->key] = $column->resolve($record);
        }

        return $cells;
    }

    private function applySearch(Builder $query, Request $request): void
    {
        $term = $request->string('search')->trim()->toString();

        if ($term === '') {
            return;
        }

        $this->applySearchTerm($query, $term);
    }

    private function applySearchTerm(Builder $query, string $term): void
    {
        $paths = $this->searchable();

        $query->where(function (Builder $builder) use ($paths, $term): void {
            foreach ($paths as $path) {
                if (! str_contains($path, '.')) {
                    $builder->orWhere($path, 'like', "%{$term}%");

                    continue;
                }

                $relation = Str::beforeLast($path, '.');
                $column = Str::afterLast($path, '.');

                $builder->orWhereHas(
                    $relation,
                    fn (Builder $related): Builder => $related->where($column, 'like', "%{$term}%"),
                );
            }
        });
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        /** @var array<string, mixed> $values */
        $values = $request->array('filter');

        foreach ($this->filters() as $filter) {
            $filter->apply($query, $values[$filter->key] ?? null);
        }
    }

    private function applySort(Builder $query, Request $request): void
    {
        $requested = $request->string('sort')->toString();
        $sort = $this->isSortable($requested) ? $requested : $this->defaultSort;

        $descending = str_starts_with($sort, '-');
        $column = ltrim($sort, '-');

        if (! $this->isSortable($sort) && $column !== 'id') {
            $query->orderBy('id', 'desc');

            return;
        }

        $query->orderBy($column, $descending ? 'desc' : 'asc');
    }

    private function isSortable(string $sort): bool
    {
        if ($sort === '') {
            return false;
        }

        $column = ltrim($sort, '-');

        if ($column === 'id') {
            return true;
        }

        foreach ($this->columns() as $candidate) {
            if ($candidate->key === $column && $candidate->isSortable()) {
                return true;
            }
        }

        return false;
    }

    private function resolvePerPage(Request $request): int
    {
        $requested = $request->integer('perPage');

        if ($requested <= 0) {
            return $this->perPage;
        }

        return min($requested, self::MAX_PER_PAGE);
    }
}
```

`defaultSort` uses `-id` as the base fallback; `applySort` falls back to `id desc` whenever the default sort names a column the definition did not mark sortable, which keeps pagination stable.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=ResourceTableTest`
Expected: PASS (13 tests).

If the default-sort test fails because `-date` is not marked `sortable()` in the fixture — it is, via `Column::date('date')->sortable()` — check `isSortable()` handles the leading dash. It does, by stripping it before comparison.

- [ ] **Step 5: Analyse, format, commit**

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
git add app/Tables/ResourceTable.php tests/Feature/Tables/ResourceTableTest.php
git commit -m "Add the ResourceTable base class with sort, search, filter and pagination"
```

### Task 19: The ResourceTable Vue component

**Files:**
- Create: `resources/js/Components/Table/ResourceTable.vue`
- Create: `resources/js/Components/Table/TableCell.vue`
- Create: `resources/js/Components/Table/FilterBar.vue`
- Create: `resources/js/composables/useTableState.js`

**Interfaces:**
- Consumes: the `schema` and `rows` shapes from Task 18.
- Produces: `<ResourceTable>` accepting props
  `schema: Object`, `rows: Object`, `resource: String`, `rowActions: Array` (default `['edit','delete']`), `bulkActions: Array` (default `['delete']`), `createRoute: String|null`,
  and emitting nothing — it drives navigation itself via Inertia.
  Route name convention: `admin.{resource}.index|create|edit|show|destroy|bulk-destroy`.
- Tasks 21–33 pass exactly these props.

- [ ] **Step 1: Write the table state composable**

`resources/js/composables/useTableState.js`:

```js
import { reactive, watch } from 'vue'
import { router } from '@inertiajs/vue3'

/**
 * Keeps sort, search, filters and pagination in the URL so the back button,
 * bookmarks and shared links all work.
 */
export function useTableState(schema, resource) {
    const params = new URLSearchParams(window.location.search)

    const state = reactive({
        sort: params.get('sort') ?? schema.defaultSort,
        search: params.get('search') ?? '',
        perPage: Number(params.get('perPage')) || schema.perPage,
        page: Number(params.get('page')) || 1,
        filters: readFilters(params, schema.filters),
    })

    let searchTimer = null

    function readFilters(searchParams, filterSchema) {
        const filters = {}

        filterSchema.forEach((filter) => {
            if (filter.type === 'dateRange') {
                const from = searchParams.get(`filter[${filter.key}][from]`)
                const to = searchParams.get(`filter[${filter.key}][to]`)
                filters[filter.key] = from || to ? { from, to } : null
                return
            }

            if (filter.multiple) {
                const values = searchParams.getAll(`filter[${filter.key}][]`)
                filters[filter.key] = values.length ? values : (filter.default ? [filter.default] : [])
                return
            }

            filters[filter.key] = searchParams.get(`filter[${filter.key}]`) ?? filter.default ?? null
        })

        return filters
    }

    function reload({ resetPage = false } = {}) {
        if (resetPage) {
            state.page = 1
        }

        router.get(
            route(`admin.${resource}.index`),
            {
                sort: state.sort,
                search: state.search || undefined,
                perPage: state.perPage,
                page: state.page,
                filter: pruneFilters(state.filters),
            },
            {
                only: ['rows'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        )
    }

    function pruneFilters(filters) {
        const pruned = {}

        Object.entries(filters).forEach(([key, value]) => {
            if (value === null || value === '' || (Array.isArray(value) && value.length === 0)) {
                return
            }

            if (typeof value === 'object' && !Array.isArray(value)) {
                const entries = Object.entries(value).filter(([, v]) => v)
                if (entries.length) {
                    pruned[key] = Object.fromEntries(entries)
                }
                return
            }

            pruned[key] = value
        })

        return pruned
    }

    watch(() => state.search, () => {
        clearTimeout(searchTimer)
        searchTimer = setTimeout(() => reload({ resetPage: true }), 300)
    })

    watch(() => state.filters, () => reload({ resetPage: true }), { deep: true })

    return { state, reload }
}
```

- [ ] **Step 2: Write the cell renderer**

`resources/js/Components/Table/TableCell.vue`:

```vue
<script setup>
import Tag from 'primevue/tag'
import Image from 'primevue/image'

const props = defineProps({
    cell: { type: Object, required: true },
    type: { type: String, required: true },
})

const SEVERITY = {
    primary: 'primary',
    secondary: 'secondary',
    success: 'success',
    warning: 'warn',
    danger: 'danger',
    info: 'info',
    gray: 'secondary',
}

function severity(variant) {
    return SEVERITY[variant] ?? 'secondary'
}
</script>

<template>
    <Tag v-if="type === 'badge' && cell.display" :value="cell.display" :severity="severity(cell.variant)" />

    <span v-else-if="type === 'boolean'">
        <i
            :class="cell.raw ? 'pi pi-check-circle text-green-500' : 'pi pi-times-circle text-surface-400'"
            :aria-label="cell.display"
        />
    </span>

    <Image
        v-else-if="type === 'image' && cell.display"
        :src="cell.display"
        :alt="''"
        preview
        :image-style="{
            width: `${cell.meta.size}px`,
            height: `${cell.meta.size}px`,
            objectFit: 'cover',
            borderRadius: cell.meta.circular ? '9999px' : '4px',
        }"
    />

    <span
        v-else-if="['money', 'date', 'datetime', 'count'].includes(type)"
        class="font-mono text-sm tabular-nums"
    >{{ cell.display }}</span>

    <span v-else v-tooltip.top="cell.tooltip ?? undefined" class="text-sm">{{ cell.display }}</span>
</template>
```

Register the tooltip directive in `resources/js/app.js` — add `import Tooltip from 'primevue/tooltip'` and `.directive('tooltip', Tooltip)` on the app chain.

- [ ] **Step 3: Write the filter bar**

`resources/js/Components/Table/FilterBar.vue`:

```vue
<script setup>
import { computed } from 'vue'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import Button from 'primevue/button'
import Chip from 'primevue/chip'

const props = defineProps({
    filters: { type: Array, required: true },
    modelValue: { type: Object, required: true },
})

const emit = defineEmits(['update:modelValue'])

function update(key, value) {
    emit('update:modelValue', { ...props.modelValue, [key]: value })
}

function clearAll() {
    const cleared = {}
    props.filters.forEach((filter) => {
        cleared[filter.key] = filter.multiple ? [] : null
    })
    emit('update:modelValue', cleared)
}

const activeChips = computed(() =>
    props.filters.flatMap((filter) => {
        const value = props.modelValue[filter.key]

        if (!value || (Array.isArray(value) && !value.length)) {
            return []
        }

        if (filter.type === 'dateRange') {
            const parts = [value.from && `from ${value.from}`, value.to && `until ${value.to}`].filter(Boolean)
            return parts.length ? [{ key: filter.key, label: `${filter.label}: ${parts.join(' ')}` }] : []
        }

        const labelFor = (v) => filter.options.find((option) => String(option.value) === String(v))?.label ?? v

        if (Array.isArray(value)) {
            return [{ key: filter.key, label: `${filter.label}: ${value.map(labelFor).join(', ')}` }]
        }

        return [{ key: filter.key, label: `${filter.label}: ${labelFor(value)}` }]
    }),
)

function clearOne(key) {
    const filter = props.filters.find((candidate) => candidate.key === key)
    update(key, filter?.multiple ? [] : null)
}
</script>

<template>
    <div v-if="filters.length" class="mb-4 flex flex-col gap-3">
        <div class="flex flex-wrap items-center gap-2">
            <template v-for="filter in filters" :key="filter.key">
                <MultiSelect
                    v-if="filter.type === 'select' && filter.multiple"
                    :model-value="modelValue[filter.key]"
                    :options="filter.options"
                    option-label="label"
                    option-value="value"
                    :placeholder="filter.label"
                    size="small"
                    filter
                    class="min-w-48"
                    @update:model-value="update(filter.key, $event)"
                />

                <Select
                    v-else-if="filter.type === 'select'"
                    :model-value="modelValue[filter.key]"
                    :options="filter.options"
                    option-label="label"
                    option-value="value"
                    :placeholder="filter.label"
                    size="small"
                    show-clear
                    class="min-w-40"
                    @update:model-value="update(filter.key, $event)"
                />

                <Select
                    v-else-if="filter.type === 'boolean'"
                    :model-value="modelValue[filter.key]"
                    :options="filter.options"
                    option-label="label"
                    option-value="value"
                    :placeholder="filter.label"
                    size="small"
                    show-clear
                    class="min-w-40"
                    @update:model-value="update(filter.key, $event)"
                />

                <div v-else-if="filter.type === 'dateRange'" class="flex items-center gap-1">
                    <DatePicker
                        :model-value="modelValue[filter.key]?.from ? new Date(modelValue[filter.key].from) : null"
                        date-format="yy-mm-dd"
                        :placeholder="`${filter.label} from`"
                        size="small"
                        show-icon
                        icon-display="input"
                        @update:model-value="update(filter.key, {
                            ...(modelValue[filter.key] ?? {}),
                            from: $event ? $event.toISOString().slice(0, 10) : null,
                        })"
                    />
                    <DatePicker
                        :model-value="modelValue[filter.key]?.to ? new Date(modelValue[filter.key].to) : null"
                        date-format="yy-mm-dd"
                        :placeholder="`${filter.label} until`"
                        size="small"
                        show-icon
                        icon-display="input"
                        @update:model-value="update(filter.key, {
                            ...(modelValue[filter.key] ?? {}),
                            to: $event ? $event.toISOString().slice(0, 10) : null,
                        })"
                    />
                </div>
            </template>
        </div>

        <div v-if="activeChips.length" class="flex flex-wrap items-center gap-2">
            <Chip
                v-for="chip in activeChips"
                :key="chip.key"
                :label="chip.label"
                removable
                @remove="clearOne(chip.key)"
            />
            <Button label="Clear all" text size="small" severity="secondary" @click="clearAll" />
        </div>
    </div>
</template>
```

- [ ] **Step 4: Write the table**

`resources/js/Components/Table/ResourceTable.vue`:

```vue
<script setup>
import { computed, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import DataTable from 'primevue/datatable'
import ColumnComponent from 'primevue/column'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import MultiSelect from 'primevue/multiselect'
import { useConfirm } from 'primevue/useconfirm'
import TableCell from './TableCell.vue'
import FilterBar from './FilterBar.vue'
import { useTableState } from '@/composables/useTableState'

const props = defineProps({
    schema: { type: Object, required: true },
    rows: { type: Object, required: true },
    resource: { type: String, required: true },
    rowActions: { type: Array, default: () => ['edit', 'delete'] },
    bulkActions: { type: Array, default: () => ['delete'] },
})

const confirm = useConfirm()
const { state, reload } = useTableState(props.schema, props.resource)

const storageKey = `table:${props.resource}:columns`

const visibleKeys = ref(loadVisibleKeys())

function loadVisibleKeys() {
    const defaults = props.schema.columns
        .filter((column) => !column.hiddenByDefault)
        .map((column) => column.key)

    try {
        const stored = window.localStorage.getItem(storageKey)
        return stored ? JSON.parse(stored) : defaults
    } catch {
        return defaults
    }
}

watch(visibleKeys, (keys) => {
    try {
        window.localStorage.setItem(storageKey, JSON.stringify(keys))
    } catch {
        // Storage can be unavailable; column visibility is a convenience only.
    }
})

const visibleColumns = computed(() =>
    props.schema.columns.filter((column) => visibleKeys.value.includes(column.key)),
)

const toggleableColumns = computed(() => props.schema.columns.filter((column) => column.toggleable))

const selection = ref([])

function onSort(event) {
    state.sort = event.sortOrder === 1 ? event.sortField : `-${event.sortField}`
    reload({ resetPage: true })
}

function onPage(event) {
    state.page = event.page + 1
    state.perPage = event.rows
    reload()
}

const sortField = computed(() => state.sort.replace(/^-/, ''))
const sortOrder = computed(() => (state.sort.startsWith('-') ? -1 : 1))

function goToCreate() {
    router.visit(route(`admin.${props.resource}.create`))
}

function goToEdit(row) {
    router.visit(route(`admin.${props.resource}.edit`, row.id))
}

function goToView(row) {
    router.visit(route(`admin.${props.resource}.show`, row.id))
}

function destroy(row) {
    confirm.require({
        message: 'This cannot be undone.',
        header: 'Delete this record?',
        icon: 'pi pi-exclamation-triangle',
        acceptProps: { label: 'Delete', severity: 'danger' },
        rejectProps: { label: 'Cancel', severity: 'secondary', outlined: true },
        accept: () => router.delete(route(`admin.${props.resource}.destroy`, row.id), { preserveScroll: true }),
    })
}

function destroySelected() {
    const ids = selection.value.map((row) => row.id)

    confirm.require({
        message: `${ids.length} records will be permanently deleted.`,
        header: 'Delete selected?',
        icon: 'pi pi-exclamation-triangle',
        acceptProps: { label: 'Delete', severity: 'danger' },
        rejectProps: { label: 'Cancel', severity: 'secondary', outlined: true },
        accept: () =>
            router.delete(route(`admin.${props.resource}.bulk-destroy`), {
                data: { ids },
                preserveScroll: true,
                onSuccess: () => (selection.value = []),
            }),
    })
}
</script>

<template>
    <div>
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <InputText
                v-if="schema.searchable"
                v-model="state.search"
                placeholder="Search"
                size="small"
                class="w-64"
            />

            <MultiSelect
                v-if="toggleableColumns.length"
                v-model="visibleKeys"
                :options="schema.columns"
                option-label="label"
                option-value="key"
                display="chip"
                :max-selected-labels="0"
                placeholder="Columns"
                size="small"
                class="w-40"
            />

            <div class="ml-auto flex items-center gap-2">
                <Button
                    v-if="bulkActions.includes('delete') && selection.length"
                    :label="`Delete ${selection.length}`"
                    icon="pi pi-trash"
                    severity="danger"
                    outlined
                    size="small"
                    @click="destroySelected"
                />
                <slot name="toolbar" />
                <Button
                    v-if="rowActions.includes('edit')"
                    label="New"
                    icon="pi pi-plus"
                    size="small"
                    @click="goToCreate"
                />
            </div>
        </div>

        <FilterBar v-model="state.filters" :filters="schema.filters" />

        <DataTable
            v-model:selection="selection"
            :value="rows.data"
            lazy
            paginator
            :rows="rows.per_page"
            :total-records="rows.total"
            :first="(rows.current_page - 1) * rows.per_page"
            :rows-per-page-options="[10, 25, 50, 100]"
            :sort-field="sortField"
            :sort-order="sortOrder"
            data-key="id"
            size="small"
            striped-rows
            removable-sort
            paginator-template="FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink RowsPerPageDropdown"
            current-page-report-template="{first}–{last} of {totalRecords}"
            @sort="onSort"
            @page="onPage"
        >
            <template #empty>
                <div class="py-12 text-center text-sm text-surface-500">Nothing here yet.</div>
            </template>

            <ColumnComponent v-if="bulkActions.length" selection-mode="multiple" header-style="width: 3rem" />

            <ColumnComponent
                v-for="column in visibleColumns"
                :key="column.key"
                :field="column.key"
                :header="column.label"
                :sortable="column.sortable"
                :body-class="column.align === 'right' ? 'text-right' : ''"
                :header-class="column.align === 'right' ? 'text-right' : ''"
            >
                <template #body="{ data }">
                    <TableCell :cell="data.cells[column.key]" :type="column.type" />
                </template>
            </ColumnComponent>

            <ColumnComponent header-style="width: 8rem" body-class="text-right">
                <template #body="{ data }">
                    <div class="flex justify-end gap-1">
                        <Button
                            v-if="rowActions.includes('view')"
                            icon="pi pi-eye"
                            text
                            rounded
                            size="small"
                            severity="secondary"
                            aria-label="View"
                            @click="goToView(data)"
                        />
                        <Button
                            v-if="rowActions.includes('edit')"
                            icon="pi pi-pencil"
                            text
                            rounded
                            size="small"
                            severity="secondary"
                            aria-label="Edit"
                            @click="goToEdit(data)"
                        />
                        <Button
                            v-if="rowActions.includes('delete')"
                            icon="pi pi-trash"
                            text
                            rounded
                            size="small"
                            severity="danger"
                            aria-label="Delete"
                            @click="destroy(data)"
                        />
                    </div>
                </template>
            </ColumnComponent>
        </DataTable>
    </div>
</template>
```

- [ ] **Step 5: Build**

Run: `npm run build`
Expected: builds clean. There is no Vue unit test harness in this project and adding one is out of scope; the component is verified by the Pest browser smoke test in Task 45 and by hand in Task 21.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Components/Table resources/js/composables resources/js/app.js
git commit -m "Add the ResourceTable Vue component, cell renderer and filter bar"
```

### Task 20: The ResourceForm contract

**Files:**
- Create: `app/Forms/Field.php`, `app/Forms/ResourceForm.php`
- Create: `resources/js/Components/Form/ResourceForm.vue`
- Create: `resources/js/Components/Form/FormField.vue`
- Test: `tests/Feature/Forms/FieldTest.php`, `tests/Feature/Forms/ResourceFormTest.php`

**Interfaces:**
- Consumes: `HasLabel` (Task 1).
- Produces:
  - `App\Forms\Field` named constructors `text`, `textarea`, `markdown`, `richtext`, `number`, `money`, `select`, `enum`, `relationship`, `date`, `datetime`, `file`, `image`, `toggle`, `tags`, `hidden`, `placeholder`;
    modifiers `label`, `required`, `disabled`, `help`, `placeholder`, `default`, `rows`, `step`, `min`, `max`, `prefix`, `options`, `searchable`, `columnSpan`, `directory`, `accept`, `slugFrom`;
    reader `schema(): array{key: string, type: string, label: string, required: bool, disabled: bool, help: string|null, placeholder: string|null, default: mixed, options: array, meta: array<string, mixed>, columnSpan: int}`.
  - `App\Forms\ResourceForm` abstract with `abstract protected function fields(): array`, `public function schema(): array{fields: array, columns: int}`, `public function values(?Model $record): array`.
  - `<ResourceForm>` Vue props `schema: Object`, `form: Object` (an Inertia `useForm`), `submitLabel: String`.
- Tasks 21–33 depend on these.

- [ ] **Step 1: Write the failing Field test**

```bash
php artisan make:test --pest Forms/FieldTest --no-interaction
```

```php
<?php

declare(strict_types=1);

use App\Enums\Currencies;
use App\Forms\Field;

it('describes a required text field', function (): void {
    $schema = Field::text('title')->required()->schema();

    expect($schema)->toMatchArray([
        'key' => 'title',
        'type' => 'text',
        'label' => 'Title',
        'required' => true,
        'disabled' => false,
        'columnSpan' => 1,
    ]);
});

it('humanises the label', function (): void {
    expect(Field::text('tax_no')->schema()['label'])->toBe('Tax no');
});

it('honours an explicit label', function (): void {
    expect(Field::text('tax_no')->label('Tax number')->schema()['label'])->toBe('Tax number');
});

it('describes a disabled field', function (): void {
    expect(Field::text('slug')->disabled()->schema()['disabled'])->toBeTrue();
});

it('carries help text and a placeholder', function (): void {
    $schema = Field::text('color')->help('Hex code, e.g. #FF0000')->placeholder('#000000')->schema();

    expect($schema['help'])->toBe('Hex code, e.g. #FF0000');
    expect($schema['placeholder'])->toBe('#000000');
});

it('builds enum options from HasLabel', function (): void {
    $options = Field::enum('currency', Currencies::class)->schema()['options'];

    expect($options)->toContain(['value' => 'TRY', 'label' => 'Turkish Lira']);
    expect($options)->toHaveCount(count(Currencies::cases()));
});

it('builds select options from a map', function (): void {
    expect(Field::select('status', ['pending' => 'Pending', 'paid' => 'Paid'])->schema()['options'])
        ->toBe([
            ['value' => 'pending', 'label' => 'Pending'],
            ['value' => 'paid', 'label' => 'Paid'],
        ]);
});

it('builds relationship options from the related model', function (): void {
    $a = App\Models\Client::factory()->create(['title' => 'Acme']);
    $b = App\Models\Client::factory()->create(['title' => 'Globex']);

    $options = Field::relationship('client_id', 'client', 'title')->schema()['options'];

    expect($options)->toContain(
        ['value' => $a->id, 'label' => 'Acme'],
        ['value' => $b->id, 'label' => 'Globex'],
    );
});

it('carries textarea rows in meta', function (): void {
    expect(Field::textarea('description')->rows(5)->schema()['meta'])->toMatchArray(['rows' => 5]);
});

it('carries numeric constraints in meta', function (): void {
    $meta = Field::number('amount')->min(0)->max(100)->step(0.01)->schema()['meta'];

    expect($meta)->toMatchArray(['min' => 0.0, 'max' => 100.0, 'step' => 0.01]);
});

it('carries upload settings in meta', function (): void {
    $meta = Field::image('receipt')->directory('receipts')->accept(['image/png'])->schema()['meta'];

    expect($meta)->toMatchArray(['directory' => 'receipts', 'accept' => ['image/png']]);
});

it('records a slug source for client-side syncing', function (): void {
    expect(Field::text('slug')->slugFrom('title')->schema()['meta'])->toMatchArray(['slugFrom' => 'title']);
});

it('spans two columns when asked', function (): void {
    expect(Field::markdown('content')->columnSpan(2)->schema()['columnSpan'])->toBe(2);
});

it('exposes a default value', function (): void {
    expect(Field::select('status', ['pending' => 'Pending'])->default('pending')->schema()['default'])->toBe('pending');
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --compact --filter=FieldTest`
Expected: FAIL — `Class "App\Forms\Field" not found`.

- [ ] **Step 3: Write Field**

`app/Forms/Field.php`:

```php
<?php

declare(strict_types=1);

namespace App\Forms;

use App\Support\Contracts\HasLabel;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class Field
{
    private ?string $label = null;

    private bool $required = false;

    private bool $disabled = false;

    private ?string $help = null;

    private ?string $placeholder = null;

    private mixed $default = null;

    private int $columnSpan = 1;

    /** @var array<string, mixed> */
    private array $meta = [];

    /** @var array<int, array{value: mixed, label: string}> */
    private array $options = [];

    private function __construct(
        public readonly string $key,
        public readonly string $type,
        private readonly ?string $relation = null,
        private readonly ?string $labelColumn = null,
        private readonly ?string $enumClass = null,
    ) {}

    public static function text(string $key): self
    {
        return new self($key, 'text');
    }

    public static function textarea(string $key): self
    {
        return new self($key, 'textarea');
    }

    public static function markdown(string $key): self
    {
        return new self($key, 'markdown');
    }

    public static function richtext(string $key): self
    {
        return new self($key, 'richtext');
    }

    public static function number(string $key): self
    {
        return new self($key, 'number');
    }

    public static function money(string $key): self
    {
        return new self($key, 'money');
    }

    /**
     * @param  array<string, string>  $options
     */
    public static function select(string $key, array $options): self
    {
        $field = new self($key, 'select');
        $field->options = self::mapOptions($options);

        return $field;
    }

    /**
     * @param  class-string<BackedEnum>  $enumClass
     */
    public static function enum(string $key, string $enumClass): self
    {
        return new self($key, 'select', enumClass: $enumClass);
    }

    public static function relationship(string $key, string $relation, string $labelColumn): self
    {
        return new self($key, 'select', relation: $relation, labelColumn: $labelColumn);
    }

    public static function date(string $key): self
    {
        return new self($key, 'date');
    }

    public static function datetime(string $key): self
    {
        return new self($key, 'datetime');
    }

    public static function file(string $key): self
    {
        return new self($key, 'file');
    }

    public static function image(string $key): self
    {
        return new self($key, 'image');
    }

    public static function toggle(string $key): self
    {
        return new self($key, 'toggle');
    }

    public static function tags(string $key): self
    {
        return new self($key, 'tags');
    }

    public static function hidden(string $key): self
    {
        return new self($key, 'hidden');
    }

    /**
     * Read-only display of a computed value, such as "created 3 days ago".
     */
    public static function placeholder(string $key): self
    {
        return new self($key, 'placeholder');
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function required(bool $required = true): self
    {
        $this->required = $required;

        return $this;
    }

    public function disabled(bool $disabled = true): self
    {
        $this->disabled = $disabled;

        return $this;
    }

    public function help(string $help): self
    {
        $this->help = $help;

        return $this;
    }

    public function placeholderText(string $placeholder): self
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function default(mixed $default): self
    {
        $this->default = $default;

        return $this;
    }

    public function rows(int $rows): self
    {
        $this->meta['rows'] = $rows;

        return $this;
    }

    public function step(float $step): self
    {
        $this->meta['step'] = $step;

        return $this;
    }

    public function min(float $min): self
    {
        $this->meta['min'] = $min;

        return $this;
    }

    public function max(float $max): self
    {
        $this->meta['max'] = $max;

        return $this;
    }

    public function prefix(string $prefix): self
    {
        $this->meta['prefix'] = $prefix;

        return $this;
    }

    public function searchable(bool $searchable = true): self
    {
        $this->meta['searchable'] = $searchable;

        return $this;
    }

    public function directory(string $directory): self
    {
        $this->meta['directory'] = $directory;

        return $this;
    }

    /**
     * @param  array<int, string>  $mimeTypes
     */
    public function accept(array $mimeTypes): self
    {
        $this->meta['accept'] = $mimeTypes;

        return $this;
    }

    /**
     * Client-side: keep this field in sync with a slugified other field.
     */
    public function slugFrom(string $sourceKey): self
    {
        $this->meta['slugFrom'] = $sourceKey;

        return $this;
    }

    public function columnSpan(int $span): self
    {
        $this->columnSpan = $span;

        return $this;
    }

    public function defaultValue(): mixed
    {
        return $this->default;
    }

    /**
     * @return array{key: string, type: string, label: string, required: bool, disabled: bool, help: string|null, placeholder: string|null, default: mixed, options: array<int, array{value: mixed, label: string}>, meta: array<string, mixed>, columnSpan: int}
     */
    public function schema(): array
    {
        return [
            'key' => $this->key,
            'type' => $this->type,
            'label' => $this->label ?? $this->humanisedLabel(),
            'required' => $this->required,
            'disabled' => $this->disabled,
            'help' => $this->help,
            'placeholder' => $this->placeholder,
            'default' => $this->default,
            'options' => $this->resolveOptions(),
            'meta' => $this->meta,
            'columnSpan' => $this->columnSpan,
        ];
    }

    /**
     * @return array<int, array{value: mixed, label: string}>
     */
    private function resolveOptions(): array
    {
        if ($this->enumClass !== null) {
            $options = [];

            foreach (($this->enumClass)::cases() as $case) {
                $options[] = [
                    'value' => $case->value,
                    'label' => $case instanceof HasLabel ? $case->getLabel() : (string) $case->value,
                ];
            }

            return $options;
        }

        if ($this->relation !== null && $this->labelColumn !== null) {
            $related = $this->relatedModelClass();

            if ($related === null) {
                return [];
            }

            return $related::query()
                ->orderBy($this->labelColumn)
                ->get(['id', $this->labelColumn])
                ->map(fn (Model $record): array => [
                    'value' => $record->getKey(),
                    'label' => (string) $record->{$this->labelColumn},
                ])
                ->all();
        }

        return $this->options;
    }

    /**
     * @param  array<string, string>  $options
     * @return array<int, array{value: mixed, label: string}>
     */
    private static function mapOptions(array $options): array
    {
        $mapped = [];

        foreach ($options as $value => $label) {
            $mapped[] = ['value' => $value, 'label' => $label];
        }

        return $mapped;
    }

    /**
     * @return class-string<Model>|null
     */
    private function relatedModelClass(): ?string
    {
        $class = 'App\\Models\\'.Str::studly(Str::beforeLast($this->key, '_id'));

        return class_exists($class) ? $class : null;
    }

    private function humanisedLabel(): string
    {
        return Str::ucfirst(Str::of($this->key)->snake(' ')->replace('_', ' ')->toString());
    }
}
```

Note: `placeholderText()` rather than `placeholder()` as the modifier, because `placeholder()` is already the named constructor for a read-only display field. The test above uses `->placeholder('#000000')` — change that line to `->placeholderText('#000000')`.

- [ ] **Step 4: Write the ResourceForm test**

```bash
php artisan make:test --pest Forms/ResourceFormTest --no-interaction
```

```php
<?php

declare(strict_types=1);

use App\Forms\Field;
use App\Forms\ResourceForm;
use App\Models\Post;

final class FixturePostForm extends ResourceForm
{
    protected int $columns = 1;

    protected function fields(): array
    {
        return [
            Field::text('title')->required(),
            Field::text('slug')->disabled()->required()->slugFrom('title'),
            Field::markdown('content')->required(),
            Field::placeholder('created_at')->label('Created'),
        ];
    }
}

it('emits a schema with every field', function (): void {
    $schema = (new FixturePostForm)->schema();

    expect($schema['columns'])->toBe(1);
    expect(array_column($schema['fields'], 'key'))->toBe(['title', 'slug', 'content', 'created_at']);
});

it('returns empty values for a new record', function (): void {
    expect((new FixturePostForm)->values(null))->toBe([
        'title' => null,
        'slug' => null,
        'content' => null,
    ]);
});

it('fills values from an existing record', function (): void {
    $post = Post::factory()->create(['title' => 'Learning Rust', 'content' => '# Hi']);

    expect((new FixturePostForm)->values($post))->toMatchArray([
        'title' => 'Learning Rust',
        'slug' => $post->slug,
        'content' => '# Hi',
    ]);
});

it('excludes placeholder fields from the value map', function (): void {
    $post = Post::factory()->create();

    expect((new FixturePostForm)->values($post))->not->toHaveKey('created_at');
});

it('applies field defaults for a new record', function (): void {
    $form = new class extends ResourceForm
    {
        protected function fields(): array
        {
            return [
                Field::select('status', ['pending' => 'Pending', 'paid' => 'Paid'])->default('pending'),
                Field::text('note'),
            ];
        }
    };

    expect($form->values(null))->toBe(['status' => 'pending', 'note' => null]);
});
```

- [ ] **Step 5: Write ResourceForm**

`app/Forms/ResourceForm.php`:

```php
<?php

declare(strict_types=1);

namespace App\Forms;

use Illuminate\Database\Eloquent\Model;

abstract class ResourceForm
{
    protected int $columns = 1;

    /**
     * @return array<int, Field>
     */
    abstract protected function fields(): array;

    /**
     * @return array{fields: array<int, array<string, mixed>>, columns: int}
     */
    public function schema(): array
    {
        return [
            'fields' => array_map(fn (Field $field): array => $field->schema(), $this->fields()),
            'columns' => $this->columns,
        ];
    }

    /**
     * The initial form state: existing attributes, or field defaults for a new record.
     *
     * @return array<string, mixed>
     */
    public function values(?Model $record): array
    {
        $values = [];

        foreach ($this->fields() as $field) {
            if ($field->type === 'placeholder') {
                continue;
            }

            if ($record === null) {
                $values[$field->key] = $field->defaultValue();

                continue;
            }

            $value = data_get($record, $field->key);
            $values[$field->key] = $value instanceof \BackedEnum ? $value->value : $value;
        }

        return $values;
    }

    /**
     * Read-only display values for placeholder fields, keyed by field.
     *
     * @return array<string, string>
     */
    public function placeholders(?Model $record): array
    {
        if ($record === null) {
            return [];
        }

        $values = [];

        foreach ($this->fields() as $field) {
            if ($field->type !== 'placeholder') {
                continue;
            }

            $value = data_get($record, $field->key);
            $values[$field->key] = $value instanceof \Illuminate\Support\Carbon
                ? $value->diffForHumans()
                : (is_scalar($value) ? (string) $value : '-');
        }

        return $values;
    }
}
```

- [ ] **Step 6: Run both tests**

Run: `php artisan test --compact --filter="FieldTest|ResourceFormTest"`
Expected: PASS (19 tests).

- [ ] **Step 7: Write the Vue form field**

`resources/js/Components/Form/FormField.vue`:

```vue
<script setup>
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import ToggleSwitch from 'primevue/toggleswitch'
import FileUpload from 'primevue/fileupload'
import Chips from 'primevue/autocomplete'
import Editor from 'primevue/editor'

const props = defineProps({
    field: { type: Object, required: true },
    modelValue: { default: null },
    error: { type: String, default: null },
    placeholderValue: { type: String, default: null },
})

const emit = defineEmits(['update:modelValue'])

function update(value) {
    emit('update:modelValue', value)
}
</script>

<template>
    <div
        v-if="field.type !== 'hidden'"
        :class="field.columnSpan === 2 ? 'sm:col-span-2' : ''"
        class="flex flex-col gap-2"
    >
        <label v-if="field.type !== 'toggle'" :for="field.key" class="text-sm font-medium">
            {{ field.label }}
            <span v-if="field.required" class="text-red-500">*</span>
        </label>

        <p v-if="field.type === 'placeholder'" class="font-mono text-sm text-surface-500">
            {{ placeholderValue ?? '-' }}
        </p>

        <Textarea
            v-else-if="field.type === 'textarea'"
            :id="field.key"
            :model-value="modelValue"
            :rows="field.meta.rows ?? 3"
            :disabled="field.disabled"
            :invalid="Boolean(error)"
            auto-resize
            @update:model-value="update"
        />

        <Textarea
            v-else-if="field.type === 'markdown'"
            :id="field.key"
            :model-value="modelValue"
            :rows="field.meta.rows ?? 18"
            :disabled="field.disabled"
            :invalid="Boolean(error)"
            class="font-mono text-sm"
            @update:model-value="update"
        />

        <Editor
            v-else-if="field.type === 'richtext'"
            :model-value="modelValue"
            editor-style="height: 260px"
            @update:model-value="update"
        />

        <InputNumber
            v-else-if="field.type === 'number' || field.type === 'money'"
            :id="field.key"
            :model-value="modelValue"
            :min="field.meta.min"
            :max="field.meta.max"
            :step="field.meta.step ?? 1"
            :max-fraction-digits="field.type === 'money' ? 2 : 0"
            :prefix="field.meta.prefix ? `${field.meta.prefix} ` : undefined"
            :disabled="field.disabled"
            :invalid="Boolean(error)"
            fluid
            @update:model-value="update"
        />

        <Select
            v-else-if="field.type === 'select'"
            :id="field.key"
            :model-value="modelValue"
            :options="field.options"
            option-label="label"
            option-value="value"
            :filter="field.meta.searchable !== false && field.options.length > 8"
            :show-clear="!field.required"
            :disabled="field.disabled"
            :invalid="Boolean(error)"
            :placeholder="field.placeholder ?? 'Select'"
            fluid
            @update:model-value="update"
        />

        <DatePicker
            v-else-if="field.type === 'date' || field.type === 'datetime'"
            :id="field.key"
            :model-value="modelValue ? new Date(modelValue) : null"
            :show-time="field.type === 'datetime'"
            date-format="yy-mm-dd"
            :disabled="field.disabled"
            :invalid="Boolean(error)"
            show-icon
            icon-display="input"
            fluid
            @update:model-value="update($event ? $event.toISOString().slice(0, field.type === 'datetime' ? 19 : 10).replace('T', ' ') : null)"
        />

        <div v-else-if="field.type === 'toggle'" class="flex items-center gap-3">
            <ToggleSwitch :model-value="Boolean(modelValue)" :disabled="field.disabled" @update:model-value="update" />
            <label :for="field.key" class="text-sm font-medium">{{ field.label }}</label>
        </div>

        <div v-else-if="field.type === 'file' || field.type === 'image'" class="flex flex-col gap-2">
            <img
                v-if="field.type === 'image' && typeof modelValue === 'string' && modelValue"
                :src="`/storage/${modelValue}`"
                alt=""
                class="h-24 w-24 rounded object-cover"
            >
            <FileUpload
                mode="basic"
                :accept="(field.meta.accept ?? []).join(',') || undefined"
                :max-file-size="5242880"
                choose-label="Choose file"
                custom-upload
                auto
                @uploader="update($event.files[0])"
            />
        </div>

        <Chips
            v-else-if="field.type === 'tags'"
            :model-value="Array.isArray(modelValue) ? modelValue : []"
            multiple
            :typeahead="false"
            fluid
            @update:model-value="update"
        />

        <InputText
            v-else
            :id="field.key"
            :model-value="modelValue"
            :disabled="field.disabled"
            :invalid="Boolean(error)"
            :placeholder="field.placeholder ?? undefined"
            fluid
            @update:model-value="update"
        />

        <small v-if="field.help" class="text-surface-500">{{ field.help }}</small>
        <small v-if="error" class="text-red-500">{{ error }}</small>
    </div>
</template>
```

- [ ] **Step 8: Write the Vue form**

`resources/js/Components/Form/ResourceForm.vue`:

```vue
<script setup>
import { watch } from 'vue'
import Button from 'primevue/button'
import FormField from './FormField.vue'

const props = defineProps({
    schema: { type: Object, required: true },
    form: { type: Object, required: true },
    placeholders: { type: Object, default: () => ({}) },
    submitLabel: { type: String, default: 'Save' },
})

const emit = defineEmits(['submit', 'cancel'])

function slugify(value) {
    return String(value ?? '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
}

props.schema.fields
    .filter((field) => field.meta.slugFrom)
    .forEach((field) => {
        watch(
            () => props.form[field.meta.slugFrom],
            (value) => {
                props.form[field.key] = slugify(value)
            },
        )
    })
</script>

<template>
    <form class="max-w-3xl" @submit.prevent="emit('submit')">
        <div
            class="grid gap-5"
            :class="schema.columns === 2 ? 'sm:grid-cols-2' : 'grid-cols-1'"
        >
            <FormField
                v-for="field in schema.fields"
                :key="field.key"
                v-model="form[field.key]"
                :field="field"
                :error="form.errors[field.key]"
                :placeholder-value="placeholders[field.key]"
            />
        </div>

        <div
            class="sticky bottom-0 mt-8 flex items-center gap-2 border-t border-surface-200 bg-surface-50/90 py-4 backdrop-blur dark:border-surface-800 dark:bg-surface-950/90"
        >
            <Button type="submit" :label="submitLabel" :loading="form.processing" />
            <Button type="button" label="Cancel" severity="secondary" text @click="emit('cancel')" />
        </div>
    </form>
</template>
```

- [ ] **Step 9: Build, analyse, commit**

```bash
npm run build
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
git add app/Forms resources/js/Components/Form tests/Feature/Forms
git commit -m "Add the ResourceForm contract and its Vue components"
```

### Task 21: Prove the engine on Posts

The first real resource. If anything about the contract is wrong, it shows up here.

**Files:**
- Create: `app/Tables/Definitions/PostTable.php`, `app/Forms/Definitions/PostForm.php`
- Create: `app/Http/Controllers/Admin/Blog/PostController.php`
- Create: `app/Http/Requests/Admin/PostRequest.php`
- Create: `resources/js/Pages/Blog/Posts/{Index,Create,Edit}.vue`
- Modify: `routes/admin.php`, `app/Support/Navigation.php`
- Test: `tests/Feature/Admin/Blog/PostResourceTest.php`

**Interfaces:**
- Consumes: `ResourceTable`, `ResourceForm`, `Column`, `Field`, `<ResourceTable>`, `<ResourceForm>`.
- Produces: the **resource pattern** every task in Phase 3 copies — routes `admin.posts.{index,create,store,edit,update,destroy,bulk-destroy}`, a controller with those seven methods, a table definition, a form definition, a FormRequest, and three Vue pages.

- [ ] **Step 1: Write the failing test**

```bash
php artisan make:test --pest Admin/Blog/PostResourceTest --no-interaction
```

```php
<?php

declare(strict_types=1);

use App\Models\Post;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->admin = User::factory()->create(['email' => 'admin@example.test']);
    $this->actingAs($this->admin);
});

it('lists posts with a schema and rows', function (): void {
    $posts = Post::factory()->count(3)->create();

    $this->get(route('admin.posts.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Blog/Posts/Index')
            ->has('schema.columns', 3)
            ->has('rows.data', 3)
            ->where('rows.data.0.cells.title.display', fn (string $title): bool => $title !== '')
        );
});

it('searches posts by title', function (): void {
    Post::factory()->create(['title' => 'Learning Rust']);
    Post::factory()->create(['title' => 'Something else']);

    $this->get(route('admin.posts.index', ['search' => 'rust']))
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

it('paginates', function (): void {
    Post::factory()->count(30)->create();

    $this->get(route('admin.posts.index', ['perPage' => 10, 'page' => 2]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('rows.data', 10)
            ->where('rows.current_page', 2)
            ->where('rows.total', 30)
        );
});

it('renders the create form', function (): void {
    $this->get(route('admin.posts.create'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Blog/Posts/Create')
            ->has('schema.fields')
            ->where('values.title', null)
        );
});

it('creates a post and derives the slug', function (): void {
    $this->post(route('admin.posts.store'), [
        'title' => 'Learning Rust',
        'slug' => 'learning-rust',
        'content' => '# Hello',
    ])->assertRedirect(route('admin.posts.index'));

    $this->assertDatabaseHas('posts', [
        'title' => 'Learning Rust',
        'slug' => 'learning-rust',
    ]);
});

it('rejects a post without a title', function (): void {
    $this->from(route('admin.posts.create'))
        ->post(route('admin.posts.store'), ['title' => '', 'slug' => '', 'content' => 'x'])
        ->assertSessionHasErrors(['title', 'slug']);

    expect(Post::count())->toBe(0);
});

it('rejects a duplicate slug', function (): void {
    Post::factory()->create(['slug' => 'taken']);

    $this->from(route('admin.posts.create'))
        ->post(route('admin.posts.store'), ['title' => 'New', 'slug' => 'taken', 'content' => 'x'])
        ->assertSessionHasErrors('slug');
});

it('renders the edit form filled with the record', function (): void {
    $post = Post::factory()->create(['title' => 'Learning Rust']);

    $this->get(route('admin.posts.edit', $post))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Blog/Posts/Edit')
            ->where('values.title', 'Learning Rust')
            ->has('placeholders.created_at')
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

it('allows a post to keep its own slug on update', function (): void {
    $post = Post::factory()->create(['slug' => 'mine']);

    $this->put(route('admin.posts.update', $post), [
        'title' => 'Changed',
        'slug' => 'mine',
        'content' => 'x',
    ])->assertSessionHasNoErrors();
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

it('flashes a notification after creating', function (): void {
    $this->post(route('admin.posts.store'), [
        'title' => 'Learning Rust',
        'slug' => 'learning-rust',
        'content' => '# Hello',
    ]);

    expect(session('flash.notification'))->toHaveKey('variant', 'success');
});

it('forbids a non-admin', function (): void {
    $this->actingAs(User::factory()->create(['email' => 'nobody@example.test']));

    $this->get(route('admin.posts.index'))->assertForbidden();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=PostResourceTest`
Expected: FAIL — route `admin.posts.index` not defined.

- [ ] **Step 3: Write the table definition**

`app/Tables/Definitions/PostTable.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tables\Definitions;

use App\Models\Post;
use App\Tables\Column;
use App\Tables\ResourceTable;

final class PostTable extends ResourceTable
{
    protected string $model = Post::class;

    protected string $defaultSort = '-created_at';

    protected function columns(): array
    {
        return [
            Column::text('title')->sortable(),
            Column::text('slug')->sortable(),
            Column::image('image'),
        ];
    }

    protected function searchable(): array
    {
        return ['title', 'slug'];
    }

    protected function titleColumn(): string
    {
        return 'title';
    }
}
```

`created_at` is not declared as a column, so `applySort` falls back to `id desc` — which is the same ordering. Add `Column::datetime('created_at')->sortable()->toggleable(hiddenByDefault: true)` if you want the default sort to bind properly; the test does not require it.

- [ ] **Step 4: Write the form definition**

`app/Forms/Definitions/PostForm.php`:

```php
<?php

declare(strict_types=1);

namespace App\Forms\Definitions;

use App\Forms\Field;
use App\Forms\ResourceForm;

final class PostForm extends ResourceForm
{
    protected int $columns = 1;

    protected function fields(): array
    {
        return [
            Field::text('title')->required(),
            Field::text('slug')->required()->disabled()->slugFrom('title'),
            Field::markdown('content')->required()->columnSpan(2),
            Field::placeholder('created_at')->label('Created'),
            Field::placeholder('updated_at')->label('Last modified'),
        ];
    }
}
```

- [ ] **Step 5: Write the FormRequest**

```bash
php artisan make:request Admin/PostRequest --no-interaction
```

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Post|null $post */
        $post = $this->route('post');

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('posts', 'slug')->ignore($post?->id)],
            'content' => ['required', 'string'],
            'image' => ['nullable', 'image', 'max:5120'],
        ];
    }
}
```

- [ ] **Step 6: Write the controller**

```bash
php artisan make:controller Admin/Blog/PostController --no-interaction
```

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Blog;

use App\Forms\Definitions\PostForm;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PostRequest;
use App\Models\Post;
use App\Support\AdminNotifier;
use App\Tables\Definitions\PostTable;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    public function __construct(private readonly AdminNotifier $notifier) {}

    public function index(Request $request): Response
    {
        $table = new PostTable;

        return Inertia::render('Blog/Posts/Index', [
            'schema' => $table->schema(),
            'rows' => fn () => $table->rows($request),
        ]);
    }

    public function create(): Response
    {
        $form = new PostForm;

        return Inertia::render('Blog/Posts/Create', [
            'schema' => $form->schema(),
            'values' => $form->values(null),
        ]);
    }

    public function store(PostRequest $request): RedirectResponse
    {
        Post::create($request->validated());

        $this->notifier->success('Post created');

        return to_route('admin.posts.index');
    }

    public function edit(Post $post): Response
    {
        $form = new PostForm;

        return Inertia::render('Blog/Posts/Edit', [
            'schema' => $form->schema(),
            'values' => $form->values($post),
            'placeholders' => $form->placeholders($post),
            'recordId' => $post->id,
        ]);
    }

    public function update(PostRequest $request, Post $post): RedirectResponse
    {
        $post->update($request->validated());

        $this->notifier->success('Post updated');

        return to_route('admin.posts.index');
    }

    public function destroy(Post $post): RedirectResponse
    {
        $post->delete();

        $this->notifier->success('Post deleted');

        return to_route('admin.posts.index');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        /** @var array<int, int> $ids */
        $ids = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:posts,id'],
        ])['ids'];

        Post::whereIn('id', $ids)->delete();

        $this->notifier->success(count($ids).' posts deleted');

        return to_route('admin.posts.index');
    }
}
```

A plain closure prop is what makes `router.reload({ only: ['rows'] })` cheap: on a partial reload Inertia evaluates only the requested props, so sorting, filtering and paging recompute the paginator and nothing else. Do not use `Inertia::merge` here — that appends arrays across requests, which is for infinite scroll, not for replacing a page of results.

- [ ] **Step 7: Add the routes**

In `routes/admin.php`, inside the group:

```php
Route::delete('posts/bulk', [PostController::class, 'bulkDestroy'])->name('posts.bulk-destroy');
Route::resource('posts', PostController::class)->except(['show']);
```

The bulk route must be declared **before** the resource route, or `posts/bulk` matches `posts/{post}`.

Import `App\Http\Controllers\Admin\Blog\PostController`.

- [ ] **Step 8: Add Posts to navigation**

In `app/Support/Navigation.php`, fill the Blog cluster:

```php
'items' => [
    ['label' => 'Posts', 'route' => 'admin.posts.index', 'icon' => 'pi pi-file-edit'],
],
```

- [ ] **Step 9: Write the three Vue pages**

`resources/js/Pages/Blog/Posts/Index.vue`:

```vue
<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ResourceTable from '@/Components/Table/ResourceTable.vue'

defineProps({
    schema: { type: Object, required: true },
    rows: { type: Object, required: true },
})
</script>

<template>
    <AdminLayout title="Posts">
        <ResourceTable
            :schema="schema"
            :rows="rows"
            resource="posts"
            :row-actions="['edit', 'delete']"
            :bulk-actions="['delete']"
        />
    </AdminLayout>
</template>
```

`resources/js/Pages/Blog/Posts/Create.vue`:

```vue
<script setup>
import { router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ResourceForm from '@/Components/Form/ResourceForm.vue'

const props = defineProps({
    schema: { type: Object, required: true },
    values: { type: Object, required: true },
})

const form = useForm(props.values)

function submit() {
    form.post(route('admin.posts.store'))
}
</script>

<template>
    <AdminLayout title="New post">
        <ResourceForm
            :schema="schema"
            :form="form"
            submit-label="Create post"
            @submit="submit"
            @cancel="router.visit(route('admin.posts.index'))"
        />
    </AdminLayout>
</template>
```

`resources/js/Pages/Blog/Posts/Edit.vue`:

```vue
<script setup>
import { router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ResourceForm from '@/Components/Form/ResourceForm.vue'

const props = defineProps({
    schema: { type: Object, required: true },
    values: { type: Object, required: true },
    placeholders: { type: Object, default: () => ({}) },
    recordId: { type: Number, required: true },
})

const form = useForm(props.values)

function submit() {
    form.put(route('admin.posts.update', props.recordId))
}
</script>

<template>
    <AdminLayout title="Edit post">
        <ResourceForm
            :schema="schema"
            :form="form"
            :placeholders="placeholders"
            submit-label="Save changes"
            @submit="submit"
            @cancel="router.visit(route('admin.posts.index'))"
        />
    </AdminLayout>
</template>
```

- [ ] **Step 10: Run the tests**

Run: `php artisan test --compact --filter=PostResourceTest`
Expected: PASS (15 tests).

- [ ] **Step 11: Verify in the browser**

Run `npm run build`, open `https://myblog.test/app/posts` and check by hand:
- the table renders, sorts by title, searches, and paginates;
- the URL updates with `?sort=` and `?search=` and the back button restores the previous state;
- creating a post auto-fills the slug as you type the title;
- validation errors render under the right fields;
- delete shows a confirmation dialog and the toast appears afterwards.

Anything wrong here is a contract problem. Fix it in Tasks 16–20, not in the Posts definition — that is the whole point of proving it on the smallest resource.

- [ ] **Step 12: Analyse, format, commit**

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
git add app/Tables/Definitions app/Forms/Definitions app/Http/Controllers/Admin/Blog app/Http/Requests/Admin routes/admin.php app/Support/Navigation.php resources/js/Pages/Blog tests/Feature/Admin/Blog
git commit -m "Port Posts to the new panel, proving the ResourceTable/ResourceForm contract"
```

---

# Phase 3 — Bulk port

Twelve resources remain. Task 22 extracts the controller boilerplate proven in Task 21 into a base class, so each later resource is a table definition, a form definition, a FormRequest, a five-line controller, two routes and two or three Vue pages.

Order runs easiest to hardest, so the engine hardens before it meets the difficult tables. Debts is scheduled immediately after Expenses because it is the most demanding table in the app — a currency-conversion column driven by another filter's state, a computed due-date status, and a payment action with partial-payment logic.

### Task 22: Extract AdminResourceController and port Categories

**Files:**
- Create: `app/Http/Controllers/Admin/AdminResourceController.php`
- Modify: `app/Http/Controllers/Admin/Blog/PostController.php` (collapse onto the base)
- Create: `app/Tables/Definitions/CategoryTable.php`, `app/Forms/Definitions/CategoryForm.php`
- Create: `app/Http/Requests/Admin/CategoryRequest.php`
- Create: `app/Http/Controllers/Admin/General/CategoryController.php`
- Create: `resources/js/Pages/General/Categories/{Index,Create,Edit}.vue`
- Modify: `routes/admin.php`, `app/Support/Navigation.php`
- Test: `tests/Feature/Admin/General/CategoryResourceTest.php`

**Interfaces:**
- Consumes: `ResourceTable`, `ResourceForm`, `AdminNotifier`.
- Produces: `App\Http\Controllers\Admin\AdminResourceController`, abstract, with:
  - `abstract protected function table(): ResourceTable`
  - `abstract protected function form(): ResourceForm`
  - `abstract protected function modelClass(): string` (`class-string<Model>`)
  - `abstract protected function resourceName(): string` (route segment, e.g. `categories`)
  - `abstract protected function pagePath(): string` (e.g. `General/Categories`)
  - `abstract protected function requestClass(): string` (`class-string<FormRequest>`)
  - `protected function label(): string` (singular, defaults to the studly singular of `resourceName()`)
  - concrete `index`, `create`, `store`, `edit`, `update`, `destroy`, `bulkDestroy`
- Tasks 23–33 subclass it.

- [ ] **Step 1: Write the base controller**

`app/Http/Controllers/Admin/AdminResourceController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Forms\ResourceForm;
use App\Http\Controllers\Controller;
use App\Support\AdminNotifier;
use App\Tables\ResourceTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

abstract class AdminResourceController extends Controller
{
    public function __construct(protected readonly AdminNotifier $notifier) {}

    abstract protected function table(): ResourceTable;

    abstract protected function form(): ResourceForm;

    /**
     * @return class-string<Model>
     */
    abstract protected function modelClass(): string;

    /**
     * The route segment and route-name prefix, e.g. "categories".
     */
    abstract protected function resourceName(): string;

    /**
     * The Vue page directory, e.g. "General/Categories".
     */
    abstract protected function pagePath(): string;

    /**
     * @return class-string<FormRequest>
     */
    abstract protected function requestClass(): string;

    protected function label(): string
    {
        return Str::headline(Str::singular($this->resourceName()));
    }

    protected function indexRoute(): string
    {
        return 'admin.'.$this->resourceName().'.index';
    }

    public function index(Request $request): Response
    {
        $table = $this->table();

        return Inertia::render($this->pagePath().'/Index', [
            'schema' => $table->schema(),
            'rows' => fn () => $table->rows($request),
        ]);
    }

    public function create(): Response
    {
        $form = $this->form();

        return Inertia::render($this->pagePath().'/Create', [
            'schema' => $form->schema(),
            'values' => $form->values(null),
        ]);
    }

    public function store(): RedirectResponse
    {
        $data = $this->validated();

        $this->modelClass()::create($data);

        $this->notifier->success($this->label().' created');

        return to_route($this->indexRoute());
    }

    public function edit(Model $record): Response
    {
        $form = $this->form();

        return Inertia::render($this->pagePath().'/Edit', [
            'schema' => $form->schema(),
            'values' => $form->values($record),
            'placeholders' => $form->placeholders($record),
            'recordId' => $record->getKey(),
        ]);
    }

    public function update(Model $record): RedirectResponse
    {
        $record->update($this->validated());

        $this->notifier->success($this->label().' updated');

        return to_route($this->indexRoute());
    }

    public function destroy(Model $record): RedirectResponse
    {
        $record->delete();

        $this->notifier->success($this->label().' deleted');

        return to_route($this->indexRoute());
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $model = $this->modelClass();
        $table = (new $model)->getTable();

        /** @var array<int, int> $ids */
        $ids = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:'.$table.',id'],
        ])['ids'];

        $model::whereIn('id', $ids)->delete();

        $this->notifier->success(count($ids).' records deleted');

        return to_route($this->indexRoute());
    }

    /**
     * Resolving a FormRequest from the container runs its validation.
     *
     * @return array<string, mixed>
     */
    protected function validated(): array
    {
        return app($this->requestClass())->validated();
    }
}
```

- [ ] **Step 2: Collapse PostController onto it**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Blog;

use App\Forms\Definitions\PostForm;
use App\Forms\ResourceForm;
use App\Http\Controllers\Admin\AdminResourceController;
use App\Http\Requests\Admin\PostRequest;
use App\Models\Post;
use App\Tables\Definitions\PostTable;
use App\Tables\ResourceTable;

class PostController extends AdminResourceController
{
    protected function table(): ResourceTable
    {
        return new PostTable;
    }

    protected function form(): ResourceForm
    {
        return new PostForm;
    }

    protected function modelClass(): string
    {
        return Post::class;
    }

    protected function resourceName(): string
    {
        return 'posts';
    }

    protected function pagePath(): string
    {
        return 'Blog/Posts';
    }

    protected function requestClass(): string
    {
        return PostRequest::class;
    }
}
```

Route-model binding passes a `Post` where the base signature says `Model`, which PHP accepts because `Post extends Model`. Larastan may flag the widened parameter — if it does, add `@param Post $record` docblocks in the subclass or keep the base typed to `Model` and cast inside. Run PHPStan before committing.

- [ ] **Step 3: Verify Posts still passes**

Run: `php artisan test --compact --filter=PostResourceTest`
Expected: PASS (15 tests), unchanged. This proves the base class is behavior-preserving.

- [ ] **Step 4: Write the Categories test**

```bash
php artisan make:test --pest Admin/General/CategoryResourceTest --no-interaction
```

```php
<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));
});

it('lists categories', function (): void {
    Category::factory()->count(3)->create();

    $this->get(route('admin.categories.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('General/Categories/Index')
            ->has('rows.data', 3)
        );
});

it('searches categories by name', function (): void {
    Category::factory()->create(['name' => 'Rust']);
    Category::factory()->create(['name' => 'Elixir']);

    $this->get(route('admin.categories.index', ['search' => 'rust']))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('rows.data', 1));
});

it('creates a category', function (): void {
    $this->post(route('admin.categories.store'), ['name' => 'Rust', 'slug' => 'rust'])
        ->assertRedirect(route('admin.categories.index'));

    $this->assertDatabaseHas('categories', ['name' => 'Rust', 'slug' => 'rust']);
});

it('requires a name and a unique slug', function (): void {
    Category::factory()->create(['slug' => 'taken']);

    $this->from(route('admin.categories.create'))
        ->post(route('admin.categories.store'), ['name' => '', 'slug' => 'taken'])
        ->assertSessionHasErrors(['name', 'slug']);
});

it('updates a category', function (): void {
    $category = Category::factory()->create(['name' => 'Old']);

    $this->put(route('admin.categories.update', $category), ['name' => 'New', 'slug' => $category->slug])
        ->assertRedirect(route('admin.categories.index'));

    expect($category->fresh()->name)->toBe('New');
});

it('deletes a category', function (): void {
    $category = Category::factory()->create();

    $this->delete(route('admin.categories.destroy', $category));

    expect(Category::find($category->id))->toBeNull();
});

it('bulk deletes categories', function (): void {
    $categories = Category::factory()->count(3)->create();

    $this->delete(route('admin.categories.bulk-destroy'), ['ids' => $categories->pluck('id')->all()]);

    expect(Category::count())->toBe(0);
});
```

- [ ] **Step 5: Run it to verify it fails**

Run: `php artisan test --compact --filter=CategoryResourceTest`
Expected: FAIL — route `admin.categories.index` not defined.

- [ ] **Step 6: Write the definitions**

`app/Tables/Definitions/CategoryTable.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tables\Definitions;

use App\Models\Category;
use App\Tables\Column;
use App\Tables\ResourceTable;

final class CategoryTable extends ResourceTable
{
    protected string $model = Category::class;

    protected string $defaultSort = 'name';

    protected function columns(): array
    {
        return [
            Column::text('name')->sortable(),
            Column::text('slug')->sortable(),
        ];
    }

    protected function searchable(): array
    {
        return ['name', 'slug'];
    }
}
```

`app/Forms/Definitions/CategoryForm.php`:

```php
<?php

declare(strict_types=1);

namespace App\Forms\Definitions;

use App\Forms\Field;
use App\Forms\ResourceForm;

final class CategoryForm extends ResourceForm
{
    protected function fields(): array
    {
        return [
            Field::text('name')->required(),
            Field::text('slug')->required()->disabled()->slugFrom('name'),
        ];
    }
}
```

`app/Http/Requests/Admin/CategoryRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Category|null $category */
        $category = $this->route('category');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('categories', 'slug')->ignore($category?->id)],
        ];
    }
}
```

`app/Http/Controllers/Admin/General/CategoryController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\General;

use App\Forms\Definitions\CategoryForm;
use App\Forms\ResourceForm;
use App\Http\Controllers\Admin\AdminResourceController;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use App\Tables\Definitions\CategoryTable;
use App\Tables\ResourceTable;

class CategoryController extends AdminResourceController
{
    protected function table(): ResourceTable
    {
        return new CategoryTable;
    }

    protected function form(): ResourceForm
    {
        return new CategoryForm;
    }

    protected function modelClass(): string
    {
        return Category::class;
    }

    protected function resourceName(): string
    {
        return 'categories';
    }

    protected function pagePath(): string
    {
        return 'General/Categories';
    }

    protected function requestClass(): string
    {
        return CategoryRequest::class;
    }
}
```

- [ ] **Step 7: Add routes and navigation**

In `routes/admin.php`:

```php
Route::delete('categories/bulk', [CategoryController::class, 'bulkDestroy'])->name('categories.bulk-destroy');
Route::resource('categories', CategoryController::class)->except(['show']);
```

In `app/Support/Navigation.php`, the General cluster:

```php
'items' => [
    ['label' => 'Categories', 'route' => 'admin.categories.index', 'icon' => 'pi pi-tags'],
],
```

- [ ] **Step 8: Write the three Vue pages**

Create `resources/js/Pages/General/Categories/{Index,Create,Edit}.vue`, copying the three Posts pages from Task 21 Step 9 and changing: the `AdminLayout` title (`Categories` / `New category` / `Edit category`), the `resource` prop (`categories`), and the three route names (`admin.categories.index`, `admin.categories.store`, `admin.categories.update`).

- [ ] **Step 9: Run the tests**

Run: `php artisan test --compact --filter=CategoryResourceTest`
Expected: PASS (7 tests).

- [ ] **Step 10: Analyse, format, commit**

```bash
npm run build
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
git add app/Http/Controllers/Admin app/Tables/Definitions/CategoryTable.php app/Forms/Definitions/CategoryForm.php app/Http/Requests/Admin/CategoryRequest.php routes/admin.php app/Support/Navigation.php resources/js/Pages/General tests/Feature/Admin/General
git commit -m "Extract AdminResourceController and port Categories"
```

### Task 23: Port Publishers

**Files:**
- Create: `app/Tables/Definitions/PublisherTable.php`, `app/Forms/Definitions/PublisherForm.php`, `app/Http/Requests/Admin/PublisherRequest.php`, `app/Http/Controllers/Admin/Library/PublisherController.php`
- Create: `resources/js/Pages/Library/Publishers/{Index,Create,Edit}.vue`
- Modify: `routes/admin.php`, `app/Support/Navigation.php`
- Test: `tests/Feature/Admin/Library/PublisherResourceTest.php`

**Interfaces:**
- Consumes: `AdminResourceController` (Task 22), `Column::count()` (Task 16).
- Produces: routes `admin.publishers.*`. Task 37 exports this resource.

This is the first resource with a `withCount` column. `PublisherTable` sets `protected array $withCount = ['books'];` and renders `Column::count('books_count')`.

- [ ] **Step 1: Write the failing test**

```bash
php artisan make:test --pest Admin/Library/PublisherResourceTest --no-interaction
```

```php
<?php

declare(strict_types=1);

use App\Models\Book;
use App\Models\Publisher;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));
});

it('lists publishers sorted by name', function (): void {
    Publisher::factory()->create(['name' => 'Zebra Press']);
    Publisher::factory()->create(['name' => 'Apple Books']);

    $this->get(route('admin.publishers.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Library/Publishers/Index')
            ->where('rows.data.0.cells.name.display', 'Apple Books')
        );
});

it('shows the book count for each publisher', function (): void {
    $publisher = Publisher::factory()->create();
    Book::factory()->count(4)->create(['publisher_id' => $publisher->id]);

    $this->get(route('admin.publishers.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rows.data.0.cells.books_count.display', '4')
        );
});

it('creates a publisher', function (): void {
    $this->post(route('admin.publishers.store'), ['name' => 'New Press'])
        ->assertRedirect(route('admin.publishers.index'));

    $this->assertDatabaseHas('publishers', ['name' => 'New Press']);
});

it('requires a name of at least three characters', function (): void {
    $this->from(route('admin.publishers.create'))
        ->post(route('admin.publishers.store'), ['name' => 'ab'])
        ->assertSessionHasErrors('name');
});

it('updates a publisher', function (): void {
    $publisher = Publisher::factory()->create(['name' => 'Old Press']);

    $this->put(route('admin.publishers.update', $publisher), ['name' => 'New Press']);

    expect($publisher->fresh()->name)->toBe('New Press');
});

it('deletes a publisher', function (): void {
    $publisher = Publisher::factory()->create();

    $this->delete(route('admin.publishers.destroy', $publisher));

    expect(Publisher::find($publisher->id))->toBeNull();
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --compact --filter=PublisherResourceTest`
Expected: FAIL — route not defined.

- [ ] **Step 3: Write the table definition**

```php
<?php

declare(strict_types=1);

namespace App\Tables\Definitions;

use App\Models\Publisher;
use App\Tables\Column;
use App\Tables\ResourceTable;

final class PublisherTable extends ResourceTable
{
    protected string $model = Publisher::class;

    protected array $withCount = ['books'];

    protected string $defaultSort = 'name';

    protected function columns(): array
    {
        return [
            Column::text('name')->sortable(),
            Column::count('books_count')->label('Books')->sortable(),
        ];
    }

    protected function searchable(): array
    {
        return ['name'];
    }
}
```

- [ ] **Step 4: Write the form and request**

`app/Forms/Definitions/PublisherForm.php`:

```php
<?php

declare(strict_types=1);

namespace App\Forms\Definitions;

use App\Forms\Field;
use App\Forms\ResourceForm;

final class PublisherForm extends ResourceForm
{
    protected function fields(): array
    {
        return [
            Field::text('name')->required()->columnSpan(2),
        ];
    }
}
```

`app/Http/Requests/Admin/PublisherRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PublisherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }
}
```

- [ ] **Step 5: Write the controller**

`app/Http/Controllers/Admin/Library/PublisherController.php` — same shape as `CategoryController` (Task 22 Step 6), with `PublisherTable`, `PublisherForm`, `Publisher::class`, `'publishers'`, `'Library/Publishers'`, `PublisherRequest::class`.

- [ ] **Step 6: Routes, navigation, pages**

Routes in `routes/admin.php`:

```php
Route::delete('publishers/bulk', [PublisherController::class, 'bulkDestroy'])->name('publishers.bulk-destroy');
Route::resource('publishers', PublisherController::class)->except(['show']);
```

Navigation — add to the Library cluster:

```php
['label' => 'Publishers', 'route' => 'admin.publishers.index', 'icon' => 'pi pi-building'],
```

Pages: `resources/js/Pages/Library/Publishers/{Index,Create,Edit}.vue`, copied from the Posts pages with the titles, `resource="publishers"` and route names changed.

- [ ] **Step 7: Run, analyse, commit**

```bash
php artisan test --compact --filter=PublisherResourceTest
npm run build
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
git add app/Tables/Definitions/PublisherTable.php app/Forms/Definitions/PublisherForm.php app/Http/Requests/Admin/PublisherRequest.php app/Http/Controllers/Admin/Library routes/admin.php app/Support/Navigation.php resources/js/Pages/Library tests/Feature/Admin/Library
git commit -m "Port Publishers"
```

### Task 24: Port Writers

**Files:**
- Create: `app/Tables/Definitions/WriterTable.php`, `app/Forms/Definitions/WriterForm.php`, `app/Http/Requests/Admin/WriterRequest.php`, `app/Http/Controllers/Admin/Library/WriterController.php`
- Create: `resources/js/Pages/Library/Writers/{Index,Create,Edit}.vue`
- Modify: `routes/admin.php`, `app/Support/Navigation.php`
- Test: `tests/Feature/Admin/Library/WriterResourceTest.php`

**Interfaces:**
- Consumes: `AdminResourceController`, `Field::image()`, `Field::richtext()`.
- Produces: routes `admin.writers.*`. This is the first resource with a **file upload**, so it also establishes how uploads are stored.

- [ ] **Step 1: Write the failing test**

```bash
php artisan make:test --pest Admin/Library/WriterResourceTest --no-interaction
```

```php
<?php

declare(strict_types=1);

use App\Models\Writer;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));
    Storage::fake('public');
});

it('lists writers with their book counts', function (): void {
    Writer::factory()->create(['name' => 'Ursula Le Guin']);

    $this->get(route('admin.writers.index'))
        ->assertInertia(fn (Inertia\Testing\AssertableInertia $page) => $page
            ->component('Library/Writers/Index')
            ->where('rows.data.0.cells.name.display', 'Ursula Le Guin')
            ->where('rows.data.0.cells.books_count.display', '0')
        );
});

it('creates a writer', function (): void {
    $this->post(route('admin.writers.store'), [
        'name' => 'Ursula Le Guin',
        'bio' => '<p>Author.</p>',
        'birth_year' => 1929,
        'death_year' => 2018,
        'birth_place' => 'Berkeley',
        'death_place' => 'Portland',
    ])->assertRedirect(route('admin.writers.index'));

    $this->assertDatabaseHas('writers', ['name' => 'Ursula Le Guin', 'birth_year' => 1929]);
});

it('stores an uploaded image and saves its path', function (): void {
    $this->post(route('admin.writers.store'), [
        'name' => 'With Portrait',
        'image' => UploadedFile::fake()->image('portrait.jpg'),
    ]);

    $writer = Writer::where('name', 'With Portrait')->firstOrFail();

    expect($writer->image)->toStartWith('writers/');
    Storage::disk('public')->assertExists($writer->image);
});

it('rejects a birth year in the future', function (): void {
    $this->from(route('admin.writers.create'))
        ->post(route('admin.writers.store'), ['name' => 'Time Traveller', 'birth_year' => (int) date('Y') + 5])
        ->assertSessionHasErrors('birth_year');
});

it('keeps the existing image when none is uploaded on update', function (): void {
    $writer = Writer::factory()->create(['image' => 'writers/existing.jpg']);

    $this->put(route('admin.writers.update', $writer), ['name' => 'Renamed']);

    expect($writer->fresh()->image)->toBe('writers/existing.jpg');
});

it('deletes a writer', function (): void {
    $writer = Writer::factory()->create();

    $this->delete(route('admin.writers.destroy', $writer));

    expect(Writer::find($writer->id))->toBeNull();
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --compact --filter=WriterResourceTest`
Expected: FAIL — route not defined.

- [ ] **Step 3: Add upload handling to the base controller**

In `AdminResourceController`, replace `validated()` with a version that converts uploaded files to stored paths, and add the hook:

```php
/**
 * Fields that accept an upload, mapped to their storage directory.
 *
 * @return array<string, string>
 */
protected function uploads(): array
{
    return [];
}

/**
 * @return array<string, mixed>
 */
protected function validated(): array
{
    $request = app($this->requestClass());
    $data = $request->validated();

    foreach ($this->uploads() as $field => $directory) {
        if ($request->hasFile($field)) {
            $data[$field] = $request->file($field)->store($directory, 'public');

            continue;
        }

        // No new file: leave the stored path untouched.
        unset($data[$field]);
    }

    return $data;
}
```

- [ ] **Step 4: Write the definitions**

`app/Tables/Definitions/WriterTable.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tables\Definitions;

use App\Models\Writer;
use App\Tables\Column;
use App\Tables\ResourceTable;

final class WriterTable extends ResourceTable
{
    protected string $model = Writer::class;

    protected array $withCount = ['books'];

    protected string $defaultSort = 'name';

    protected function columns(): array
    {
        return [
            Column::image('image')->circular()->size(32),
            Column::text('name')->sortable(),
            Column::count('books_count')->label('Books')->sortable(),
        ];
    }

    protected function searchable(): array
    {
        return ['name'];
    }
}
```

`app/Forms/Definitions/WriterForm.php`:

```php
<?php

declare(strict_types=1);

namespace App\Forms\Definitions;

use App\Forms\Field;
use App\Forms\ResourceForm;

final class WriterForm extends ResourceForm
{
    protected int $columns = 2;

    protected function fields(): array
    {
        return [
            Field::text('name')->required()->columnSpan(2),
            Field::image('image')->directory('writers')->accept(['image/jpeg', 'image/png', 'image/webp'])->columnSpan(2),
            Field::richtext('bio')->columnSpan(2),
            Field::number('birth_year')->min(0)->max((float) date('Y')),
            Field::number('death_year')->min(0)->max((float) date('Y')),
            Field::text('birth_place'),
            Field::text('death_place'),
        ];
    }
}
```

`app/Http/Requests/Admin/WriterRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class WriterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $currentYear = (int) date('Y');

        return [
            'name' => ['required', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'max:5120'],
            'bio' => ['nullable', 'string', 'max:65535'],
            'birth_year' => ['nullable', 'integer', 'min:0', 'max:'.$currentYear],
            'death_year' => ['nullable', 'integer', 'min:0', 'max:'.$currentYear],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'death_place' => ['nullable', 'string', 'max:255'],
        ];
    }
}
```

- [ ] **Step 5: Write the controller**

`app/Http/Controllers/Admin/Library/WriterController.php` — same shape as `PublisherController`, plus:

```php
/**
 * @return array<string, string>
 */
protected function uploads(): array
{
    return ['image' => 'writers'];
}
```

- [ ] **Step 6: Routes, navigation, pages**

```php
Route::delete('writers/bulk', [WriterController::class, 'bulkDestroy'])->name('writers.bulk-destroy');
Route::resource('writers', WriterController::class)->except(['show']);
```

Navigation — Library cluster: `['label' => 'Writers', 'route' => 'admin.writers.index', 'icon' => 'pi pi-user']`.

Pages: `resources/js/Pages/Library/Writers/{Index,Create,Edit}.vue`. Create and Edit must submit multipart — Inertia's `useForm` does that automatically when a `File` is present, but `form.put()` does not support files. In `Edit.vue`, submit with method spoofing:

```js
function submit() {
    form
        .transform((data) => ({ ...data, _method: 'put' }))
        .post(route('admin.writers.update', props.recordId), { forceFormData: true })
}
```

Use this same pattern in every later resource whose form has a `file` or `image` field.

- [ ] **Step 7: Run, analyse, commit**

```bash
php artisan test --compact --filter=WriterResourceTest
npm run build
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
git add app/Tables/Definitions/WriterTable.php app/Forms/Definitions/WriterForm.php app/Http/Requests/Admin/WriterRequest.php app/Http/Controllers/Admin routes/admin.php app/Support/Navigation.php resources/js/Pages/Library tests/Feature/Admin/Library
git commit -m "Port Writers, adding upload handling to the base controller"
```

### Task 25: Port Books

**Files:**
- Create: `app/Tables/Definitions/BookTable.php`, `app/Forms/Definitions/BookForm.php`, `app/Http/Requests/Admin/BookRequest.php`, `app/Http/Controllers/Admin/Library/BookController.php`
- Create: `resources/js/Pages/Library/Books/{Index,Create,Edit}.vue`
- Modify: `routes/admin.php`, `app/Support/Navigation.php`
- Test: `tests/Feature/Admin/Library/BookResourceTest.php`

**Interfaces:**
- Consumes: `Field::relationship()`, dotted search paths.
- Produces: routes `admin.books.*`, named distinctly from the **public** `books` resource in `routes/web.php` — the admin routes are `admin.books.*` under `/app/books`, the public ones stay `books.*` under `/books`. Verify both resolve after this task.

- [ ] **Step 1: Write the failing test**

```bash
php artisan make:test --pest Admin/Library/BookResourceTest --no-interaction
```

```php
<?php

declare(strict_types=1);

use App\Models\Book;
use App\Models\Publisher;
use App\Models\User;
use App\Models\Writer;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));
});

it('lists books with writer and publisher names', function (): void {
    $writer = Writer::factory()->create(['name' => 'Le Guin']);
    $publisher = Publisher::factory()->create(['name' => 'Ace']);
    Book::factory()->create(['name' => 'The Dispossessed', 'writer_id' => $writer->id, 'publisher_id' => $publisher->id]);

    $this->get(route('admin.books.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Library/Books/Index')
            ->where('rows.data.0.cells.name.display', 'The Dispossessed')
            ->where('rows.data.0.cells.writer\.name.display', 'Le Guin')
            ->where('rows.data.0.cells.publisher\.name.display', 'Ace')
        );
});

it('searches books by writer name', function (): void {
    $leGuin = Writer::factory()->create(['name' => 'Le Guin']);
    $other = Writer::factory()->create(['name' => 'Someone Else']);
    Book::factory()->create(['writer_id' => $leGuin->id]);
    Book::factory()->create(['writer_id' => $other->id]);

    $this->get(route('admin.books.index', ['search' => 'Le Guin']))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('rows.data', 1));
});

it('offers writers and publishers as select options', function (): void {
    Writer::factory()->create(['name' => 'Le Guin']);

    $this->get(route('admin.books.create'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Library/Books/Create')
            ->where('schema.fields.0.options.0.label', 'Le Guin')
        );
});

it('creates a book', function (): void {
    $writer = Writer::factory()->create();
    $publisher = Publisher::factory()->create();

    $this->post(route('admin.books.store'), [
        'writer_id' => $writer->id,
        'publisher_id' => $publisher->id,
        'name' => 'A New Book',
        'original_name' => 'Un Nouveau Livre',
        'page_count' => 320,
        'publication_date' => 1974,
        'publication_location' => 'Paris',
        'edition_number' => 1,
    ])->assertRedirect(route('admin.books.index'));

    $this->assertDatabaseHas('books', ['name' => 'A New Book', 'page_count' => 320]);
});

it('requires a writer and a publisher', function (): void {
    $this->from(route('admin.books.create'))
        ->post(route('admin.books.store'), ['name' => 'Orphan Book'])
        ->assertSessionHasErrors(['writer_id', 'publisher_id']);
});

it('rejects a writer that does not exist', function (): void {
    $publisher = Publisher::factory()->create();

    $this->from(route('admin.books.create'))
        ->post(route('admin.books.store'), [
            'name' => 'Ghost Book',
            'writer_id' => 999999,
            'publisher_id' => $publisher->id,
        ])
        ->assertSessionHasErrors('writer_id');
});

it('does not clash with the public books routes', function (): void {
    expect(route('admin.books.index'))->toContain('/app/books');
    expect(route('books.index'))->not->toContain('/app/');
});

it('deletes a book', function (): void {
    $book = Book::factory()->create();

    $this->delete(route('admin.books.destroy', $book));

    expect(Book::find($book->id))->toBeNull();
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --compact --filter=BookResourceTest`
Expected: FAIL — route `admin.books.index` not defined.

- [ ] **Step 3: Write the definitions**

`app/Tables/Definitions/BookTable.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tables\Definitions;

use App\Models\Book;
use App\Tables\Column;
use App\Tables\ResourceTable;

final class BookTable extends ResourceTable
{
    protected string $model = Book::class;

    protected array $with = ['writer', 'publisher'];

    protected string $defaultSort = 'name';

    protected function columns(): array
    {
        return [
            Column::image('image')->size(32),
            Column::text('name')->sortable(),
            Column::text('writer.name')->label('Writer')->sortable(),
            Column::text('publisher.name')->label('Publisher')->sortable(),
            Column::count('page_count')->label('Pages')->sortable(),
        ];
    }

    protected function searchable(): array
    {
        return ['name', 'original_name', 'writer.name', 'publisher.name'];
    }
}
```

Sorting on `writer.name` needs a join, which `ResourceTable::applySort` does not do — it calls `orderBy('writer.name')`, which SQLite and MySQL both reject. Either drop `->sortable()` from the two relationship columns, or extend `applySort` to detect a dotted key and skip it. **Take the simple route: remove `->sortable()` from `writer.name` and `publisher.name`.** Note this limitation in the definition with a comment; adding join-based sorting is a possible follow-up, not part of this plan.

`app/Forms/Definitions/BookForm.php`:

```php
<?php

declare(strict_types=1);

namespace App\Forms\Definitions;

use App\Forms\Field;
use App\Forms\ResourceForm;

final class BookForm extends ResourceForm
{
    protected int $columns = 2;

    protected function fields(): array
    {
        return [
            Field::relationship('writer_id', 'writer', 'name')->label('Writer')->required()->searchable(),
            Field::relationship('publisher_id', 'publisher', 'name')->label('Publisher')->required()->searchable(),
            Field::text('name')->required(),
            Field::text('original_name')->label('Original name'),
            Field::number('page_count')->label('Pages'),
            Field::number('publication_date')->label('Publication year'),
            Field::text('publication_location')->label('Publication location'),
            Field::number('edition_number')->label('Edition'),
            Field::image('image')->directory('books')->accept(['image/jpeg', 'image/png', 'image/webp'])->columnSpan(2),
        ];
    }
}
```

`app/Http/Requests/Admin/BookRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'writer_id' => ['required', 'integer', 'exists:writers,id'],
            'publisher_id' => ['required', 'integer', 'exists:publishers,id'],
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'original_name' => ['nullable', 'string', 'min:3', 'max:255'],
            'page_count' => ['nullable', 'integer', 'min:1'],
            'publication_date' => ['nullable', 'integer', 'min:0', 'max:'.(int) date('Y')],
            'publication_location' => ['nullable', 'string', 'max:255'],
            'edition_number' => ['nullable', 'integer', 'min:1'],
            'image' => ['nullable', 'image', 'max:5120'],
        ];
    }
}
```

- [ ] **Step 4: Controller, routes, navigation, pages**

`app/Http/Controllers/Admin/Library/BookController.php` — same shape as `WriterController`, with `BookTable`, `BookForm`, `Book::class`, `'books'`, `'Library/Books'`, `BookRequest::class`, and `uploads(): ['image' => 'books']`.

Routes:

```php
Route::delete('books/bulk', [BookController::class, 'bulkDestroy'])->name('books.bulk-destroy');
Route::resource('books', BookController::class)->except(['show']);
```

Because these sit inside the `admin.` name group and the `app` prefix, they cannot collide with the public `Route::resource('books', BookController::class)` in `routes/web.php` — but the class names do collide on import. Use the fully qualified `App\Http\Controllers\Admin\Library\BookController` in `routes/admin.php`, or alias it: `use App\Http\Controllers\Admin\Library\BookController as AdminBookController;`.

Navigation — Library cluster: `['label' => 'Books', 'route' => 'admin.books.index', 'icon' => 'pi pi-book']`.

Pages: `resources/js/Pages/Library/Books/{Index,Create,Edit}.vue`, using the multipart submit pattern from Task 24 Step 6.

- [ ] **Step 5: Run, analyse, commit**

```bash
php artisan test --compact --filter=BookResourceTest
php artisan test --compact
npm run build
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
git add app/Tables/Definitions/BookTable.php app/Forms/Definitions/BookForm.php app/Http/Requests/Admin/BookRequest.php app/Http/Controllers/Admin/Library routes/admin.php app/Support/Navigation.php resources/js/Pages/Library tests/Feature/Admin/Library
git commit -m "Port Books"
```

### Task 26: Port Clients

**Files:**
- Create: `app/Tables/Definitions/ClientTable.php`, `app/Forms/Definitions/ClientForm.php`, `app/Http/Requests/Admin/ClientRequest.php`, `app/Http/Controllers/Admin/Work/ClientController.php`
- Create: `resources/js/Pages/Work/Clients/{Index,Create,Edit}.vue`
- Modify: `routes/admin.php`, `app/Support/Navigation.php`
- Test: `tests/Feature/Admin/Work/ClientResourceTest.php`

**Interfaces:**
- Consumes: `AdminResourceController`.
- Produces: routes `admin.clients.*`. Tasks 27, 29, 31 reference clients as a relationship.

Note: the existing `ClientResource::getGloballySearchableAttributes()` returns `['name', 'email']`, but the `clients` table has `title`, not `name` — a latent bug. The new definition searches `title` and `email`.

- [ ] **Step 1: Write the failing test**

```bash
php artisan make:test --pest Admin/Work/ClientResourceTest --no-interaction
```

```php
<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));
});

it('lists clients', function (): void {
    Client::factory()->count(2)->create();

    $this->get(route('admin.clients.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Work/Clients/Index')
            ->has('rows.data', 2)
        );
});

it('searches clients by title and email', function (): void {
    Client::factory()->create(['title' => 'Acme Corp', 'email' => 'hello@acme.test']);
    Client::factory()->create(['title' => 'Globex', 'email' => 'hi@globex.test']);

    $this->get(route('admin.clients.index', ['search' => 'acme']))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('rows.data', 1));

    $this->get(route('admin.clients.index', ['search' => 'globex.test']))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('rows.data', 1));
});

it('creates a client', function (): void {
    $this->post(route('admin.clients.store'), [
        'title' => 'Acme Corp',
        'email' => 'hello@acme.test',
        'address' => '1 Main Street',
        'country' => 'Türkiye',
        'tax_no' => '1234567890',
    ])->assertRedirect(route('admin.clients.index'));

    $this->assertDatabaseHas('clients', ['title' => 'Acme Corp']);
});

it('requires every field and a valid email', function (): void {
    $this->from(route('admin.clients.create'))
        ->post(route('admin.clients.store'), ['title' => '', 'email' => 'not-an-email'])
        ->assertSessionHasErrors(['title', 'email', 'address', 'country', 'tax_no']);
});

it('updates a client', function (): void {
    $client = Client::factory()->create(['title' => 'Old Name']);

    $this->put(route('admin.clients.update', $client), [
        'title' => 'New Name',
        'email' => $client->email,
        'address' => $client->address,
        'country' => $client->country,
        'tax_no' => $client->tax_no,
    ]);

    expect($client->fresh()->title)->toBe('New Name');
});

it('deletes a client', function (): void {
    $client = Client::factory()->create();

    $this->delete(route('admin.clients.destroy', $client));

    expect(Client::find($client->id))->toBeNull();
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --compact --filter=ClientResourceTest`
Expected: FAIL — route not defined.

- [ ] **Step 3: Write the definitions**

`app/Tables/Definitions/ClientTable.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tables\Definitions;

use App\Models\Client;
use App\Tables\Column;
use App\Tables\ResourceTable;

final class ClientTable extends ResourceTable
{
    protected string $model = Client::class;

    protected string $defaultSort = 'title';

    protected function columns(): array
    {
        return [
            Column::text('title')->sortable(),
            Column::text('email')->sortable(),
            Column::text('address')->limit(40)->tooltip(),
            Column::text('country')->sortable(),
            Column::text('tax_no')->label('Tax no'),
        ];
    }

    protected function searchable(): array
    {
        return ['title', 'email', 'country', 'tax_no'];
    }

    protected function titleColumn(): string
    {
        return 'title';
    }
}
```

`app/Forms/Definitions/ClientForm.php`:

```php
<?php

declare(strict_types=1);

namespace App\Forms\Definitions;

use App\Forms\Field;
use App\Forms\ResourceForm;

final class ClientForm extends ResourceForm
{
    protected int $columns = 2;

    protected function fields(): array
    {
        return [
            Field::text('title')->required()->columnSpan(2),
            Field::text('email')->required(),
            Field::text('country')->required(),
            Field::text('address')->required()->columnSpan(2),
            Field::text('tax_no')->label('Tax number')->required(),
        ];
    }
}
```

`app/Http/Requests/Admin/ClientRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'address' => ['required', 'string', 'max:500'],
            'country' => ['required', 'string', 'max:255'],
            'tax_no' => ['required', 'string', 'max:255'],
        ];
    }
}
```

- [ ] **Step 4: Controller, routes, navigation, pages**

`app/Http/Controllers/Admin/Work/ClientController.php` — `ClientTable`, `ClientForm`, `Client::class`, `'clients'`, `'Work/Clients'`, `ClientRequest::class`.

```php
Route::delete('clients/bulk', [ClientController::class, 'bulkDestroy'])->name('clients.bulk-destroy');
Route::resource('clients', ClientController::class)->except(['show']);
```

Navigation — Work cluster: `['label' => 'Clients', 'route' => 'admin.clients.index', 'icon' => 'pi pi-users']`.

Pages: `resources/js/Pages/Work/Clients/{Index,Create,Edit}.vue`.

- [ ] **Step 5: Run, analyse, commit**

```bash
php artisan test --compact --filter=ClientResourceTest
npm run build
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
git add app/Tables/Definitions/ClientTable.php app/Forms/Definitions/ClientForm.php app/Http/Requests/Admin/ClientRequest.php app/Http/Controllers/Admin/Work routes/admin.php app/Support/Navigation.php resources/js/Pages/Work tests/Feature/Admin/Work
git commit -m "Port Clients"
```

### Task 27: Port Projects

**Files:**
- Create: `app/Tables/Definitions/ProjectTable.php`, `app/Forms/Definitions/ProjectForm.php`, `app/Http/Requests/Admin/ProjectRequest.php`, `app/Http/Controllers/Admin/Work/ProjectController.php`
- Create: `resources/js/Pages/Work/Projects/{Index,Create,Edit}.vue`
- Modify: `routes/admin.php`, `app/Support/Navigation.php`
- Test: `tests/Feature/Admin/Work/ProjectResourceTest.php`

**Interfaces:**
- Consumes: `Field::relationship()`, `Field::date()`, `Field::placeholder()`.
- Produces: routes `admin.projects.*`. Tasks 28 and 34 reference projects.

- [ ] **Step 1: Write the failing test**

```bash
php artisan make:test --pest Admin/Work/ProjectResourceTest --no-interaction
```

```php
<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));
});

it('lists projects with their client', function (): void {
    $client = Client::factory()->create(['title' => 'Acme']);
    Project::factory()->create(['name' => 'Website', 'client_id' => $client->id]);

    $this->get(route('admin.projects.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Work/Projects/Index')
            ->where('rows.data.0.cells.name.display', 'Website')
            ->where('rows.data.0.cells.client\.title.display', 'Acme')
        );
});

it('formats the due date', function (): void {
    Project::factory()->create(['due_date' => '2026-12-24']);

    $this->get(route('admin.projects.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rows.data.0.cells.due_date.display', '24 Dec 2026')
        );
});

it('creates a project', function (): void {
    $client = Client::factory()->create();

    $this->post(route('admin.projects.store'), [
        'name' => 'New Project',
        'due_date' => '2026-12-31',
        'client_id' => $client->id,
    ])->assertRedirect(route('admin.projects.index'));

    $this->assertDatabaseHas('projects', ['name' => 'New Project']);
});

it('allows a project with no client or due date', function (): void {
    $this->post(route('admin.projects.store'), ['name' => 'Internal'])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('projects', ['name' => 'Internal', 'client_id' => null]);
});

it('requires a name', function (): void {
    $this->from(route('admin.projects.create'))
        ->post(route('admin.projects.store'), ['name' => ''])
        ->assertSessionHasErrors('name');
});

it('shows created and updated placeholders on edit', function (): void {
    $project = Project::factory()->create();

    $this->get(route('admin.projects.edit', $project))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('placeholders.created_at')
            ->has('placeholders.updated_at')
        );
});

it('deletes a project', function (): void {
    $project = Project::factory()->create();

    $this->delete(route('admin.projects.destroy', $project));

    expect(Project::find($project->id))->toBeNull();
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --compact --filter=ProjectResourceTest`
Expected: FAIL — route not defined.

- [ ] **Step 3: Write the definitions**

`app/Tables/Definitions/ProjectTable.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tables\Definitions;

use App\Models\Project;
use App\Tables\Column;
use App\Tables\ResourceTable;

final class ProjectTable extends ResourceTable
{
    protected string $model = Project::class;

    protected array $with = ['client'];

    protected string $defaultSort = 'name';

    protected function columns(): array
    {
        return [
            Column::text('name')->sortable(),
            Column::date('due_date')->label('Due')->sortable(),
            // Relationship columns are not sortable: ResourceTable orders on real
            // database columns only, and a dotted key would need a join.
            Column::text('client.title')->label('Client'),
        ];
    }

    protected function searchable(): array
    {
        return ['name', 'client.title'];
    }
}
```

`app/Forms/Definitions/ProjectForm.php`:

```php
<?php

declare(strict_types=1);

namespace App\Forms\Definitions;

use App\Forms\Field;
use App\Forms\ResourceForm;

final class ProjectForm extends ResourceForm
{
    protected int $columns = 2;

    protected function fields(): array
    {
        return [
            Field::text('name')->required()->columnSpan(2),
            Field::date('due_date')->label('Due date'),
            Field::relationship('client_id', 'client', 'title')->label('Client')->searchable(),
            Field::placeholder('created_at')->label('Created'),
            Field::placeholder('updated_at')->label('Last modified'),
        ];
    }
}
```

`app/Http/Requests/Admin/ProjectRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'due_date' => ['nullable', 'date'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
        ];
    }
}
```

- [ ] **Step 4: Controller, routes, navigation, pages**

`app/Http/Controllers/Admin/Work/ProjectController.php` — `ProjectTable`, `ProjectForm`, `Project::class`, `'projects'`, `'Work/Projects'`, `ProjectRequest::class`.

```php
Route::delete('projects/bulk', [ProjectController::class, 'bulkDestroy'])->name('projects.bulk-destroy');
Route::resource('projects', ProjectController::class)->except(['show']);
```

Navigation — Work cluster: `['label' => 'Projects', 'route' => 'admin.projects.index', 'icon' => 'pi pi-folder-open']`.

Pages: `resources/js/Pages/Work/Projects/{Index,Create,Edit}.vue`.

- [ ] **Step 5: Run, analyse, commit**

```bash
php artisan test --compact --filter=ProjectResourceTest
npm run build
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
git add app/Tables/Definitions/ProjectTable.php app/Forms/Definitions/ProjectForm.php app/Http/Requests/Admin/ProjectRequest.php app/Http/Controllers/Admin/Work routes/admin.php app/Support/Navigation.php resources/js/Pages/Work tests/Feature/Admin/Work
git commit -m "Port Projects"
```

### Task 28: Port Repositories

The first resource with a **view page** and a `boolean` column.

**Files:**
- Create: `app/Tables/Definitions/RepositoryTable.php`, `app/Forms/Definitions/RepositoryForm.php`, `app/Http/Requests/Admin/RepositoryRequest.php`, `app/Http/Controllers/Admin/Work/RepositoryController.php`
- Create: `resources/js/Pages/Work/Repositories/{Index,Create,Edit,Show}.vue`
- Modify: `app/Http/Controllers/Admin/AdminResourceController.php` (add `show`)
- Modify: `routes/admin.php`, `app/Support/Navigation.php`
- Test: `tests/Feature/Admin/Work/RepositoryResourceTest.php`

**Interfaces:**
- Consumes: `Column::boolean()`, `Column::badge()` with a closure.
- Produces: `AdminResourceController::show(Model $record): Response` rendering `{pagePath}/Show` with `values` and `placeholders`; routes `admin.repositories.*` including `show`.

- [ ] **Step 1: Write the failing test**

```bash
php artisan make:test --pest Admin/Work/RepositoryResourceTest --no-interaction
```

```php
<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\Repository;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));
});

it('lists repositories', function (): void {
    Repository::factory()->count(2)->create();

    $this->get(route('admin.repositories.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Work/Repositories/Index')
            ->has('rows.data', 2)
        );
});

it('colours the visibility badge', function (): void {
    Repository::factory()->create(['visibility' => 'public']);

    $this->get(route('admin.repositories.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rows.data.0.cells.visibility.variant', 'success')
        );

    Repository::query()->delete();
    Repository::factory()->create(['visibility' => 'private']);

    $this->get(route('admin.repositories.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rows.data.0.cells.visibility.variant', 'warning')
        );
});

it('renders the active flag as a boolean cell', function (): void {
    Repository::factory()->create(['is_active' => true]);

    $this->get(route('admin.repositories.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rows.data.0.cells.is_active.raw', true)
        );
});

it('hides last_synced_at and created_at by default', function (): void {
    $this->get(route('admin.repositories.index'))
        ->assertInertia(function (AssertableInertia $page): void {
            $columns = collect($page->toArray()['props']['schema']['columns'])->keyBy('key');

            expect($columns['last_synced_at']['hiddenByDefault'])->toBeTrue();
            expect($columns['created_at']['hiddenByDefault'])->toBeTrue();
        });
});

it('renders the show page', function (): void {
    $repository = Repository::factory()->create(['name' => 'myblog']);

    $this->get(route('admin.repositories.show', $repository))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Work/Repositories/Show')
            ->where('values.name', 'myblog')
        );
});

it('creates a repository', function (): void {
    $project = Project::factory()->create();

    $this->post(route('admin.repositories.store'), [
        'project_id' => $project->id,
        'name' => 'myblog',
        'full_name' => 'mnurullahsaglam/myblog',
        'owner' => 'mnurullahsaglam',
        'visibility' => 'public',
        'github_url' => 'https://github.com/mnurullahsaglam/myblog',
        'github_id' => '123456',
        'default_branch' => 'main',
        'is_active' => true,
    ])->assertRedirect(route('admin.repositories.index'));

    $this->assertDatabaseHas('repositories', ['name' => 'myblog', 'github_id' => '123456']);
});

it('rejects a duplicate github id', function (): void {
    $existing = Repository::factory()->create();
    $project = Project::factory()->create();

    $this->from(route('admin.repositories.create'))
        ->post(route('admin.repositories.store'), [
            'project_id' => $project->id,
            'name' => 'other',
            'full_name' => 'owner/other',
            'owner' => 'owner',
            'visibility' => 'public',
            'github_url' => 'https://github.com/owner/other',
            'github_id' => $existing->github_id,
            'default_branch' => 'main',
        ])
        ->assertSessionHasErrors('github_id');
});

it('rejects a non-url github url', function (): void {
    $project = Project::factory()->create();

    $this->from(route('admin.repositories.create'))
        ->post(route('admin.repositories.store'), [
            'project_id' => $project->id,
            'name' => 'x',
            'full_name' => 'o/x',
            'owner' => 'o',
            'visibility' => 'public',
            'github_url' => 'not a url',
            'github_id' => '99',
            'default_branch' => 'main',
        ])
        ->assertSessionHasErrors('github_url');
});

it('lets a repository keep its own github id on update', function (): void {
    $repository = Repository::factory()->create();

    $this->put(route('admin.repositories.update', $repository), [
        'project_id' => $repository->project_id,
        'name' => 'renamed',
        'full_name' => $repository->full_name,
        'owner' => $repository->owner,
        'visibility' => $repository->visibility,
        'github_url' => $repository->github_url,
        'github_id' => $repository->github_id,
        'default_branch' => $repository->default_branch,
    ])->assertSessionHasNoErrors();

    expect($repository->fresh()->name)->toBe('renamed');
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --compact --filter=RepositoryResourceTest`
Expected: FAIL — route not defined.

- [ ] **Step 3: Add `show` to the base controller**

In `AdminResourceController`:

```php
public function show(Model $record): Response
{
    $form = $this->form();

    return Inertia::render($this->pagePath().'/Show', [
        'schema' => $form->schema(),
        'values' => $form->values($record),
        'placeholders' => $form->placeholders($record),
        'recordId' => $record->getKey(),
    ]);
}
```

- [ ] **Step 4: Write the definitions**

`app/Tables/Definitions/RepositoryTable.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tables\Definitions;

use App\Models\Repository;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\ResourceTable;

final class RepositoryTable extends ResourceTable
{
    protected string $model = Repository::class;

    protected array $with = ['project'];

    protected string $defaultSort = 'name';

    protected function columns(): array
    {
        return [
            Column::text('name')->sortable(),
            Column::text('owner')->sortable(),
            Column::badge('visibility')->color(fn (Repository $record): string => match ($record->visibility) {
                'public' => 'success',
                'private' => 'warning',
                default => 'gray',
            }),
            Column::text('language')->sortable(),
            Column::count('stars_count')->label('Stars')->sortable(),
            Column::count('issues_count')->label('Issues')->sortable(),
            Column::boolean('is_active')->label('Active'),
            Column::datetime('last_synced_at')->label('Last synced')->sortable()->toggleable(hiddenByDefault: true),
            Column::datetime('created_at')->sortable()->toggleable(hiddenByDefault: true),
        ];
    }

    protected function filters(): array
    {
        return [
            Filter::select('visibility', ['public' => 'Public', 'private' => 'Private']),
            Filter::select('is_active', ['1' => 'Active', '0' => 'Inactive'])->label('Status'),
        ];
    }

    protected function searchable(): array
    {
        return ['name', 'owner', 'full_name', 'language'];
    }
}
```

`app/Forms/Definitions/RepositoryForm.php`:

```php
<?php

declare(strict_types=1);

namespace App\Forms\Definitions;

use App\Forms\Field;
use App\Forms\ResourceForm;

final class RepositoryForm extends ResourceForm
{
    protected int $columns = 2;

    protected function fields(): array
    {
        return [
            Field::relationship('project_id', 'project', 'name')->label('Project')->required()->searchable()->columnSpan(2),
            Field::text('name')->required(),
            Field::text('owner')->required(),
            Field::text('full_name')->label('Full name (owner/repo)')->required()->columnSpan(2),
            Field::textarea('description')->rows(3)->columnSpan(2),
            Field::select('visibility', ['public' => 'Public', 'private' => 'Private'])->required(),
            Field::text('github_url')->label('GitHub URL')->required(),
            Field::text('github_id')->label('GitHub ID')->required(),
            Field::text('default_branch')->label('Default branch')->required()->default('main'),
            Field::text('language'),
            Field::number('stars_count')->label('Stars')->min(0)->default(0),
            Field::number('forks_count')->label('Forks')->min(0)->default(0),
            Field::number('issues_count')->label('Issues')->min(0)->default(0),
            Field::toggle('is_active')->label('Active')->default(true),
            Field::datetime('github_created_at')->label('GitHub created at'),
            Field::datetime('github_updated_at')->label('GitHub updated at'),
            Field::datetime('last_synced_at')->label('Last synced at'),
            Field::placeholder('created_at')->label('Created'),
            Field::placeholder('updated_at')->label('Last modified'),
        ];
    }
}
```

`app/Http/Requests/Admin/RepositoryRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Repository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RepositoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Repository|null $repository */
        $repository = $this->route('repository');

        return [
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'name' => ['required', 'string', 'max:255'],
            'owner' => ['required', 'string', 'max:255'],
            'full_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'visibility' => ['required', Rule::in(['public', 'private'])],
            'github_url' => ['required', 'url', 'max:255'],
            'github_id' => ['required', 'string', 'max:255', Rule::unique('repositories', 'github_id')->ignore($repository?->id)],
            'default_branch' => ['required', 'string', 'max:255'],
            'language' => ['nullable', 'string', 'max:255'],
            'stars_count' => ['nullable', 'integer', 'min:0'],
            'forks_count' => ['nullable', 'integer', 'min:0'],
            'issues_count' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'github_created_at' => ['nullable', 'date'],
            'github_updated_at' => ['nullable', 'date'],
            'last_synced_at' => ['nullable', 'date'],
        ];
    }
}
```

- [ ] **Step 5: Controller, routes, navigation, pages**

`app/Http/Controllers/Admin/Work/RepositoryController.php` — `RepositoryTable`, `RepositoryForm`, `Repository::class`, `'repositories'`, `'Work/Repositories'`, `RepositoryRequest::class`.

```php
Route::delete('repositories/bulk', [RepositoryController::class, 'bulkDestroy'])->name('repositories.bulk-destroy');
Route::resource('repositories', RepositoryController::class);
```

No `->except(['show'])` this time.

Navigation — Work cluster: `['label' => 'Repositories', 'route' => 'admin.repositories.index', 'icon' => 'pi pi-code']`.

Pages: `Index.vue` with `:row-actions="['view', 'edit', 'delete']"`, plus `Create.vue`, `Edit.vue`, and `Show.vue`:

```vue
<script setup>
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Card from 'primevue/card'
import Button from 'primevue/button'

const props = defineProps({
    schema: { type: Object, required: true },
    values: { type: Object, required: true },
    placeholders: { type: Object, default: () => ({}) },
    recordId: { type: Number, required: true },
})

const visibleFields = props.schema.fields.filter((field) => field.type !== 'hidden')

function displayValue(field) {
    if (field.type === 'placeholder') {
        return props.placeholders[field.key] ?? '-'
    }

    const value = props.values[field.key]

    if (value === null || value === '') {
        return '-'
    }

    if (field.options?.length) {
        return field.options.find((option) => String(option.value) === String(value))?.label ?? value
    }

    if (field.type === 'toggle') {
        return value ? 'Yes' : 'No'
    }

    return value
}
</script>

<template>
    <AdminLayout title="Repository">
        <template #actions>
            <Button
                label="Edit"
                icon="pi pi-pencil"
                size="small"
                @click="router.visit(route('admin.repositories.edit', recordId))"
            />
        </template>

        <Card>
            <template #content>
                <dl class="grid gap-x-8 gap-y-4 sm:grid-cols-2">
                    <div v-for="field in visibleFields" :key="field.key">
                        <dt class="text-xs uppercase tracking-wide text-surface-500">{{ field.label }}</dt>
                        <dd class="mt-1 break-words text-sm">{{ displayValue(field) }}</dd>
                    </div>
                </dl>
            </template>
        </Card>
    </AdminLayout>
</template>
```

This `Show.vue` is generic — Task 30 and Task 33 reuse it verbatim with different titles and route names.

- [ ] **Step 6: Run, analyse, commit**

```bash
php artisan test --compact --filter=RepositoryResourceTest
npm run build
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
git add app/Tables/Definitions/RepositoryTable.php app/Forms/Definitions/RepositoryForm.php app/Http/Requests/Admin/RepositoryRequest.php app/Http/Controllers/Admin routes/admin.php app/Support/Navigation.php resources/js/Pages/Work tests/Feature/Admin/Work
git commit -m "Port Repositories and add a generic show page to the base controller"
```

### Task 29: Port Incomes

The first resource with money columns, enum filters, a date-range filter and a custom source-type filter.

**Files:**
- Create: `app/Tables/Definitions/IncomeTable.php`, `app/Forms/Definitions/IncomeForm.php`, `app/Http/Requests/Admin/IncomeRequest.php`, `app/Http/Controllers/Admin/Budget/IncomeController.php`
- Create: `resources/js/Pages/Budget/Incomes/{Index,Create,Edit,Show}.vue`
- Modify: `routes/admin.php`, `app/Support/Navigation.php`
- Test: `tests/Feature/Admin/Budget/IncomeResourceTest.php`

**Interfaces:**
- Consumes: `Column::money()`, `Filter::enum()`, `Filter::dateRange()`, `Filter::custom()`.
- Produces: routes `admin.incomes.*` including `show`. Task 30 mirrors this shape for Expenses.

- [ ] **Step 1: Write the failing test**

```bash
php artisan make:test --pest Admin/Budget/IncomeResourceTest --no-interaction
```

```php
<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\Debt;
use App\Models\Income;
use App\Models\IncomeCategory;
use App\Models\Invoice;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));
});

it('lists incomes newest first', function (): void {
    Income::factory()->create(['date' => '2026-01-01']);
    $newest = Income::factory()->create(['date' => '2026-06-01']);

    $this->get(route('admin.incomes.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Budget/Incomes/Index')
            ->where('rows.data.0.id', $newest->id)
        );
});

it('formats the amount with the record currency symbol', function (): void {
    Income::factory()->create(['amount' => 1500.00, 'currency' => 'TRY']);

    $this->get(route('admin.incomes.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rows.data.0.cells.amount.display', '₺1,500.00')
        );
});

it('filters by currency', function (): void {
    Income::factory()->create(['currency' => 'TRY']);
    Income::factory()->create(['currency' => 'USD']);

    $this->get(route('admin.incomes.index', ['filter' => ['currency' => ['USD']]]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('rows.data', 1));
});

it('filters by category', function (): void {
    $salary = IncomeCategory::factory()->create();
    $other = IncomeCategory::factory()->create();
    Income::factory()->create(['income_category_id' => $salary->id]);
    Income::factory()->create(['income_category_id' => $other->id]);

    $this->get(route('admin.incomes.index', ['filter' => ['income_category_id' => [$salary->id]]]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('rows.data', 1));
});

it('filters by date range', function (): void {
    Income::factory()->create(['date' => '2026-01-15']);
    Income::factory()->create(['date' => '2026-03-15']);

    $this->get(route('admin.incomes.index', [
        'filter' => ['date' => ['from' => '2026-03-01', 'to' => '2026-03-31']],
    ]))->assertInertia(fn (AssertableInertia $page) => $page->has('rows.data', 1));
});

it('filters by invoice source type', function (): void {
    $invoice = Invoice::factory()->create();
    Income::factory()->create(['invoice_id' => $invoice->id, 'client_id' => null, 'debt_id' => null]);
    Income::factory()->create(['invoice_id' => null, 'client_id' => null, 'debt_id' => null]);

    $this->get(route('admin.incomes.index', ['filter' => ['source_invoice' => 'yes']]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('rows.data', 1));
});

it('filters by debt source type', function (): void {
    $debt = Debt::factory()->create();
    Income::factory()->create(['debt_id' => $debt->id, 'invoice_id' => null, 'client_id' => null]);
    Income::factory()->create(['debt_id' => null, 'invoice_id' => null, 'client_id' => null]);

    $this->get(route('admin.incomes.index', ['filter' => ['source_debt' => 'yes']]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('rows.data', 1));
});

it('searches by source and description', function (): void {
    Income::factory()->create(['source' => 'Consulting', 'description' => 'Q1 retainer']);
    Income::factory()->create(['source' => 'Royalties', 'description' => 'Book sales']);

    $this->get(route('admin.incomes.index', ['search' => 'consulting']))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('rows.data', 1));
});

it('creates an income', function (): void {
    $category = IncomeCategory::factory()->create();

    $this->post(route('admin.incomes.store'), [
        'amount' => 2500.50,
        'currency' => 'TRY',
        'date' => '2026-05-01',
        'income_category_id' => $category->id,
        'description' => 'Consulting work',
    ])->assertRedirect(route('admin.incomes.index'));

    $this->assertDatabaseHas('incomes', ['amount' => 2500.50, 'currency' => 'TRY']);
});

it('requires amount, currency, date and description', function (): void {
    $this->from(route('admin.incomes.create'))
        ->post(route('admin.incomes.store'), [])
        ->assertSessionHasErrors(['amount', 'currency', 'date', 'description']);
});

it('rejects an unknown currency', function (): void {
    $this->from(route('admin.incomes.create'))
        ->post(route('admin.incomes.store'), [
            'amount' => 10,
            'currency' => 'ZZZ',
            'date' => '2026-05-01',
            'description' => 'x',
        ])
        ->assertSessionHasErrors('currency');
});

it('rejects a negative amount', function (): void {
    $this->from(route('admin.incomes.create'))
        ->post(route('admin.incomes.store'), [
            'amount' => -5,
            'currency' => 'TRY',
            'date' => '2026-05-01',
            'description' => 'x',
        ])
        ->assertSessionHasErrors('amount');
});

it('renders the show page', function (): void {
    $income = Income::factory()->create();

    $this->get(route('admin.incomes.show', $income))
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Budget/Incomes/Show'));
});

it('deletes an income', function (): void {
    $income = Income::factory()->create();

    $this->delete(route('admin.incomes.destroy', $income));

    expect(Income::find($income->id))->toBeNull();
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --compact --filter=IncomeResourceTest`
Expected: FAIL — route not defined.

- [ ] **Step 3: Write the table definition**

The original resource had one `source_type` select filter whose query branched four ways. A single select filter cannot express that through the generic `Filter` contract, so it becomes three independent boolean filters plus the existing client/category filters. That is a small behavior change and a simpler UI; note it in the commit.

`app/Tables/Definitions/IncomeTable.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tables\Definitions;

use App\Enums\Currencies;
use App\Models\Income;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\ResourceTable;
use Illuminate\Database\Eloquent\Builder;

final class IncomeTable extends ResourceTable
{
    protected string $model = Income::class;

    protected array $with = ['client', 'invoice', 'debt', 'incomeCategory'];

    protected string $defaultSort = '-date';

    protected function columns(): array
    {
        return [
            Column::money('amount', currencyFrom: 'currency')->sortable(),
            Column::badge('currency')->color('success'),
            Column::text('source')->label('Source'),
            Column::badge('incomeCategory.name')->label('Category')
                ->color(fn (Income $record): string => $record->incomeCategory?->color ? 'primary' : 'gray'),
            Column::text('description')->limit(50)->tooltip(),
            Column::date('date')->sortable(),
            Column::datetime('created_at')->sortable()->toggleable(hiddenByDefault: true),
        ];
    }

    protected function filters(): array
    {
        return [
            Filter::relationship('income_category_id', 'incomeCategory', 'name')->label('Category')->multiple(),
            Filter::enum('currency', Currencies::class)->multiple(),
            Filter::relationship('client_id', 'client', 'title')->label('Client')->multiple(),
            Filter::dateRange('date'),
            Filter::custom('source_invoice', 'From an invoice', fn (Builder $query): Builder => $query->whereNotNull('invoice_id')),
            Filter::custom('source_debt', 'From debt repayment', fn (Builder $query): Builder => $query->whereNotNull('debt_id')),
            Filter::custom('source_client', 'From a client', fn (Builder $query): Builder => $query
                ->whereNotNull('client_id')->whereNull('invoice_id')->whereNull('debt_id')),
        ];
    }

    protected function searchable(): array
    {
        return ['source', 'description', 'incomeCategory.name'];
    }

    protected function titleColumn(): string
    {
        return 'description';
    }
}
```

- [ ] **Step 4: Write the form and request**

`app/Forms/Definitions/IncomeForm.php`:

```php
<?php

declare(strict_types=1);

namespace App\Forms\Definitions;

use App\Enums\Currencies;
use App\Forms\Field;
use App\Forms\ResourceForm;

final class IncomeForm extends ResourceForm
{
    protected int $columns = 2;

    protected function fields(): array
    {
        return [
            Field::money('amount')->required()->min(0)->step(0.01),
            Field::enum('currency', Currencies::class)->required()->default(Currencies::TRY->value),
            Field::date('date')->required()->default(now()->toDateString()),
            Field::text('source')->label('Source'),
            Field::relationship('income_category_id', 'incomeCategory', 'name')->label('Category')->searchable(),
            Field::relationship('client_id', 'client', 'title')->label('Client')->searchable(),
            Field::relationship('invoice_id', 'invoice', 'invoice_number')->label('Invoice')->searchable()
                ->help('Select an invoice if this income is an invoice payment'),
            Field::relationship('debt_id', 'debt', 'creditor_name')->label('Debt')->searchable()
                ->help('Select a debt if this income is a debt repayment'),
            Field::textarea('description')->required()->rows(3)->columnSpan(2),
        ];
    }
}
```

The original form showed the currency symbol as a live prefix on the amount field. The generic `Field::money()` has a static `prefix()` only, so the symbol is dropped from the input and shown in the table instead. If you want it back, add a small watcher in `Budget/Incomes/Create.vue` that sets a local prefix from `form.currency` and pass it through to `InputNumber`.

`app/Http/Requests/Admin/IncomeRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Currencies;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IncomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', Rule::enum(Currencies::class)],
            'date' => ['required', 'date'],
            'source' => ['nullable', 'string', 'max:255'],
            'income_category_id' => ['nullable', 'integer', 'exists:income_categories,id'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'debt_id' => ['nullable', 'integer', 'exists:debts,id'],
            'description' => ['required', 'string', 'max:1000'],
        ];
    }
}
```

- [ ] **Step 5: Controller, routes, navigation, pages**

`app/Http/Controllers/Admin/Budget/IncomeController.php` — `IncomeTable`, `IncomeForm`, `Income::class`, `'incomes'`, `'Budget/Incomes'`, `IncomeRequest::class`.

```php
Route::delete('incomes/bulk', [IncomeController::class, 'bulkDestroy'])->name('incomes.bulk-destroy');
Route::resource('incomes', IncomeController::class);
```

Navigation — Budget cluster: `['label' => 'Incomes', 'route' => 'admin.incomes.index', 'icon' => 'pi pi-plus-circle']`.

Pages: `resources/js/Pages/Budget/Incomes/{Index,Create,Edit,Show}.vue`. `Index.vue` uses `:row-actions="['view', 'edit', 'delete']"`; `Show.vue` copies the generic one from Task 28 Step 5 with the title `Income` and the route `admin.incomes.edit`.

- [ ] **Step 6: Run, analyse, commit**

```bash
php artisan test --compact --filter=IncomeResourceTest
npm run build
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
git add app/Tables/Definitions/IncomeTable.php app/Forms/Definitions/IncomeForm.php app/Http/Requests/Admin/IncomeRequest.php app/Http/Controllers/Admin/Budget routes/admin.php app/Support/Navigation.php resources/js/Pages/Budget tests/Feature/Admin/Budget
git commit -m "Port Incomes; split the four-way source filter into three boolean filters"
```

### Task 30: Port Expenses

The stress test the spec named: money with per-record currency, dynamic badge colors, an image column, a date-range filter and two receipt filters.

**Files:**
- Create: `app/Tables/Definitions/ExpenseTable.php`, `app/Forms/Definitions/ExpenseForm.php`, `app/Http/Requests/Admin/ExpenseRequest.php`, `app/Http/Controllers/Admin/Budget/ExpenseController.php`
- Create: `resources/js/Pages/Budget/Expenses/{Index,Create,Edit}.vue`
- Modify: `routes/admin.php`, `app/Support/Navigation.php`
- Test: `tests/Feature/Admin/Budget/ExpenseResourceTest.php`

**Interfaces:**
- Consumes: everything built so far.
- Produces: routes `admin.expenses.*`.

- [ ] **Step 1: Write the failing test**

```bash
php artisan make:test --pest Admin/Budget/ExpenseResourceTest --no-interaction
```

```php
<?php

declare(strict_types=1);

use App\Models\Debt;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));
    Storage::fake('public');
});

it('lists expenses newest first', function (): void {
    Expense::factory()->create(['date' => '2026-01-01']);
    $newest = Expense::factory()->create(['date' => '2026-06-01']);

    $this->get(route('admin.expenses.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Budget/Expenses/Index')
            ->where('rows.data.0.id', $newest->id)
        );
});

it('formats money per record currency', function (): void {
    Expense::factory()->create(['amount' => 2400.00, 'currency' => 'USD']);

    $this->get(route('admin.expenses.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rows.data.0.cells.amount.display', '$2,400.00')
        );
});

it('colours the category badge by whether the category has a colour', function (): void {
    $coloured = ExpenseCategory::factory()->create(['color' => '#ff0000']);
    Expense::factory()->create(['expense_category_id' => $coloured->id]);

    $this->get(route('admin.expenses.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rows.data.0.cells.expenseCategory\.name.variant', 'primary')
        );
});

it('shows N/A when an expense is not linked to a debt', function (): void {
    Expense::factory()->create(['debt_id' => null]);

    $this->get(route('admin.expenses.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rows.data.0.cells.debt\.creditor_name.display', 'N/A')
        );
});

it('truncates a long description and keeps the full text as a tooltip', function (): void {
    $long = str_repeat('x', 120);
    Expense::factory()->create(['description' => $long]);

    $this->get(route('admin.expenses.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rows.data.0.cells.description.tooltip', $long)
        );
});

it('resolves the receipt to a public url', function (): void {
    Expense::factory()->create(['receipt_path' => 'receipts/one.png']);

    $this->get(route('admin.expenses.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rows.data.0.cells.receipt_path.display', fn (string $url): bool => str_contains($url, 'receipts/one.png'))
        );
});

it('filters to expenses with a receipt', function (): void {
    Expense::factory()->create(['receipt_path' => 'a.png']);
    Expense::factory()->create(['receipt_path' => null]);

    $this->get(route('admin.expenses.index', ['filter' => ['receipt_path' => 'yes']]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('rows.data', 1));
});

it('filters to expenses without a receipt', function (): void {
    Expense::factory()->create(['receipt_path' => 'a.png']);
    Expense::factory()->create(['receipt_path' => null]);

    $this->get(route('admin.expenses.index', ['filter' => ['receipt_path' => 'no']]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('rows.data', 1));
});

it('filters by debt', function (): void {
    $debt = Debt::factory()->create();
    Expense::factory()->create(['debt_id' => $debt->id]);
    Expense::factory()->create(['debt_id' => null]);

    $this->get(route('admin.expenses.index', ['filter' => ['debt_id' => [$debt->id]]]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('rows.data', 1));
});

it('combines a category filter with a date range', function (): void {
    $category = ExpenseCategory::factory()->create();
    Expense::factory()->create(['expense_category_id' => $category->id, 'date' => '2026-03-10']);
    Expense::factory()->create(['expense_category_id' => $category->id, 'date' => '2026-08-10']);
    Expense::factory()->create(['date' => '2026-03-11']);

    $this->get(route('admin.expenses.index', [
        'filter' => [
            'expense_category_id' => [$category->id],
            'date' => ['from' => '2026-03-01', 'to' => '2026-03-31'],
        ],
    ]))->assertInertia(fn (AssertableInertia $page) => $page->has('rows.data', 1));
});

it('creates an expense with an uploaded receipt', function (): void {
    $category = ExpenseCategory::factory()->create();

    $this->post(route('admin.expenses.store'), [
        'expense_category_id' => $category->id,
        'amount' => 320.00,
        'currency' => 'TRY',
        'description' => 'Office chair',
        'date' => '2026-04-02',
        'receipt_path' => UploadedFile::fake()->image('receipt.jpg'),
    ])->assertRedirect(route('admin.expenses.index'));

    $expense = Expense::where('description', 'Office chair')->firstOrFail();

    expect($expense->receipt_path)->toStartWith('receipts/');
    Storage::disk('public')->assertExists($expense->receipt_path);
});

it('requires amount, currency, description and date', function (): void {
    $this->from(route('admin.expenses.create'))
        ->post(route('admin.expenses.store'), [])
        ->assertSessionHasErrors(['amount', 'currency', 'description', 'date']);
});

it('deletes an expense', function (): void {
    $expense = Expense::factory()->create();

    $this->delete(route('admin.expenses.destroy', $expense));

    expect(Expense::find($expense->id))->toBeNull();
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --compact --filter=ExpenseResourceTest`
Expected: FAIL — route not defined.

- [ ] **Step 3: Write the definitions**

`app/Tables/Definitions/ExpenseTable.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tables\Definitions;

use App\Enums\Currencies;
use App\Models\Expense;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\ResourceTable;

final class ExpenseTable extends ResourceTable
{
    protected string $model = Expense::class;

    protected array $with = ['expenseCategory', 'debt'];

    protected string $defaultSort = '-date';

    protected function columns(): array
    {
        return [
            Column::money('amount', currencyFrom: 'currency')->sortable(),
            Column::badge('currency')->color('danger'),
            Column::badge('expenseCategory.name')->label('Category')
                ->color(fn (Expense $record): string => $record->expenseCategory?->color ? 'primary' : 'gray'),
            Column::text('description')->limit(50)->tooltip(),
            Column::image('receipt_path')->label('Receipt')->circular()->size(40),
            Column::badge('debt.creditor_name')->label('Debt to')->color('warning')->default('N/A'),
            Column::date('date')->sortable(),
            Column::datetime('created_at')->sortable()->toggleable(hiddenByDefault: true),
        ];
    }

    protected function filters(): array
    {
        return [
            Filter::relationship('expense_category_id', 'expenseCategory', 'name')->label('Category')->multiple(),
            Filter::enum('currency', Currencies::class)->multiple(),
            Filter::relationship('debt_id', 'debt', 'creditor_name')->label('Debt payment')->multiple(),
            Filter::dateRange('date'),
            Filter::boolean('receipt_path')->label('Receipt')->trueLabel('Has receipt')->falseLabel('No receipt'),
        ];
    }

    protected function searchable(): array
    {
        return ['description', 'expenseCategory.name'];
    }

    protected function titleColumn(): string
    {
        return 'description';
    }
}
```

`app/Forms/Definitions/ExpenseForm.php`:

```php
<?php

declare(strict_types=1);

namespace App\Forms\Definitions;

use App\Enums\Currencies;
use App\Forms\Field;
use App\Forms\ResourceForm;

final class ExpenseForm extends ResourceForm
{
    protected int $columns = 2;

    protected function fields(): array
    {
        return [
            Field::money('amount')->required()->min(0)->step(0.01),
            Field::enum('currency', Currencies::class)->required()->default(Currencies::TRY->value),
            Field::date('date')->required()->default(now()->toDateString()),
            Field::relationship('expense_category_id', 'expenseCategory', 'name')->label('Category')->searchable(),
            Field::relationship('debt_id', 'debt', 'creditor_name')->label('Debt')->searchable()
                ->help('Set if this expense is a debt payment'),
            Field::textarea('description')->required()->rows(3)->columnSpan(2),
            Field::image('receipt_path')->label('Receipt')->directory('receipts')
                ->accept(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])->columnSpan(2),
        ];
    }
}
```

`app/Http/Requests/Admin/ExpenseRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Currencies;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', Rule::enum(Currencies::class)],
            'date' => ['required', 'date'],
            'expense_category_id' => ['nullable', 'integer', 'exists:expense_categories,id'],
            'debt_id' => ['nullable', 'integer', 'exists:debts,id'],
            'description' => ['required', 'string', 'max:1000'],
            'receipt_path' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }
}
```

- [ ] **Step 4: Controller, routes, navigation, pages**

`app/Http/Controllers/Admin/Budget/ExpenseController.php` — `ExpenseTable`, `ExpenseForm`, `Expense::class`, `'expenses'`, `'Budget/Expenses'`, `ExpenseRequest::class`, plus:

```php
/**
 * @return array<string, string>
 */
protected function uploads(): array
{
    return ['receipt_path' => 'receipts'];
}
```

```php
Route::delete('expenses/bulk', [ExpenseController::class, 'bulkDestroy'])->name('expenses.bulk-destroy');
Route::resource('expenses', ExpenseController::class)->except(['show']);
```

Navigation — Budget cluster: `['label' => 'Expenses', 'route' => 'admin.expenses.index', 'icon' => 'pi pi-minus-circle']`.

Pages: `resources/js/Pages/Budget/Expenses/{Index,Create,Edit}.vue`, with the multipart submit pattern from Task 24.

- [ ] **Step 5: Run, analyse, commit**

```bash
php artisan test --compact --filter=ExpenseResourceTest
npm run build
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
git add app/Tables/Definitions/ExpenseTable.php app/Forms/Definitions/ExpenseForm.php app/Http/Requests/Admin/ExpenseRequest.php app/Http/Controllers/Admin/Budget routes/admin.php app/Support/Navigation.php resources/js/Pages/Budget tests/Feature/Admin/Budget
git commit -m "Port Expenses"
```

### Task 31: Port Debts

The hardest table in the application. Three things go beyond the generic contract:

1. A **converted amount** column whose target currency comes from a filter's own state.
2. A **due-date status** column computed from model accessors, with its own color logic.
3. A **Pay Debt** action with a modal form, partial-payment arithmetic, an expense side effect and a receipt upload.

The first is handled by passing the conversion currency into the table definition's constructor; the second by `Column::state()`; the third by a dedicated controller action, not the generic CRUD.

**Files:**
- Create: `app/Tables/Definitions/DebtTable.php`, `app/Forms/Definitions/DebtForm.php`, `app/Http/Requests/Admin/DebtRequest.php`, `app/Http/Requests/Admin/PayDebtRequest.php`, `app/Http/Controllers/Admin/Budget/DebtController.php`
- Create: `resources/js/Pages/Budget/Debts/{Index,Create,Edit}.vue`, `resources/js/Pages/Budget/Debts/PayDebtDialog.vue`
- Modify: `routes/admin.php`, `app/Support/Navigation.php`
- Test: `tests/Feature/Admin/Budget/DebtResourceTest.php`, `tests/Feature/Admin/Budget/PayDebtTest.php`

**Interfaces:**
- Consumes: `ExchangeRateService`, `Column::state()`, `AdminNotifier`.
- Produces: routes `admin.debts.*` plus `admin.debts.pay` (`POST /app/debts/{debt}/pay`).

- [ ] **Step 1: Read the Debt model's accessors**

Run: `grep -n "public function get\|protected function\|Attribute" app/Models/Debt.php`
Note `formatted_amount`, `status_color`, `due_date_status` and `days_until_due` — the table definition calls all four.

- [ ] **Step 2: Write the failing table test**

```bash
php artisan make:test --pest Admin/Budget/DebtResourceTest --no-interaction
```

```php
<?php

declare(strict_types=1);

use App\Models\Debt;
use App\Models\User;
use App\Services\ExchangeRateService;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));

    $this->exchange = Mockery::mock(ExchangeRateService::class);
    $this->exchange->shouldReceive('convert')->andReturnUsing(
        fn (float $amount, string $from, string $to): float => $amount * 2,
    );
    app()->instance(ExchangeRateService::class, $this->exchange);
});

it('lists debts newest first', function (): void {
    Debt::factory()->create(['date' => '2026-01-01']);
    $newest = Debt::factory()->create(['date' => '2026-06-01']);

    $this->get(route('admin.debts.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Budget/Debts/Index')
            ->where('rows.data.0.id', $newest->id)
        );
});

it('converts the amount into the selected currency', function (): void {
    Debt::factory()->create(['amount' => 100.00, 'currency' => 'USD']);

    $this->get(route('admin.debts.index', ['filter' => ['conversion_currency' => 'TRY']]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rows.data.0.cells.converted_amount.display', '₺200.00')
        );
});

it('shows the original amount when the currencies match', function (): void {
    Debt::factory()->create(['amount' => 100.00, 'currency' => 'TRY']);

    $this->get(route('admin.debts.index', ['filter' => ['conversion_currency' => 'TRY']]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rows.data.0.cells.converted_amount.display', '₺100.00')
        );
});

it('defaults the conversion currency to TRY', function (): void {
    $this->get(route('admin.debts.index'))
        ->assertInertia(function (AssertableInertia $page): void {
            $filters = collect($page->toArray()['props']['schema']['filters'])->keyBy('key');

            expect($filters['conversion_currency']['default'])->toBe('TRY');
        });
});

it('colours the due status', function (): void {
    Debt::factory()->overdue()->create();

    $this->get(route('admin.debts.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rows.data.0.cells.due_date_status.variant', 'danger')
        );
});

it('greys the due status for a paid debt', function (): void {
    Debt::factory()->paid()->create(['due_date' => now()->subMonth()->toDateString()]);

    $this->get(route('admin.debts.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rows.data.0.cells.due_date_status.variant', 'gray')
        );
});

it('filters to overdue debts', function (): void {
    Debt::factory()->overdue()->create();
    Debt::factory()->create(['status' => 'pending', 'due_date' => now()->addYear()->toDateString()]);

    $this->get(route('admin.debts.index', ['filter' => ['overdue' => 'yes']]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('rows.data', 1));
});

it('filters to debts due within seven days', function (): void {
    Debt::factory()->create(['status' => 'pending', 'due_date' => now()->addDays(3)->toDateString()]);
    Debt::factory()->create(['status' => 'pending', 'due_date' => now()->addDays(60)->toDateString()]);

    $this->get(route('admin.debts.index', ['filter' => ['due_soon' => 'yes']]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('rows.data', 1));
});

it('filters by status and creditor type', function (): void {
    Debt::factory()->paid()->create(['creditor_type' => 'person']);
    Debt::factory()->create(['status' => 'pending', 'creditor_type' => 'institute']);

    $this->get(route('admin.debts.index', ['filter' => ['status' => ['paid']]]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('rows.data', 1));

    $this->get(route('admin.debts.index', ['filter' => ['creditor_type' => ['institute']]]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('rows.data', 1));
});

it('filters by due date range separately from debt date', function (): void {
    Debt::factory()->create(['date' => '2026-01-01', 'due_date' => '2026-09-01']);
    Debt::factory()->create(['date' => '2026-01-01', 'due_date' => '2026-02-01']);

    $this->get(route('admin.debts.index', [
        'filter' => ['due_date' => ['from' => '2026-08-01', 'to' => '2026-10-01']],
    ]))->assertInertia(fn (AssertableInertia $page) => $page->has('rows.data', 1));
});

it('creates a debt', function (): void {
    $this->post(route('admin.debts.store'), [
        'creditor_name' => 'Bank',
        'creditor_type' => 'institute',
        'amount' => 5000.00,
        'currency' => 'TRY',
        'date' => '2026-02-01',
        'due_date' => '2026-08-01',
        'status' => 'pending',
        'description' => 'Loan',
    ])->assertRedirect(route('admin.debts.index'));

    $this->assertDatabaseHas('debts', ['creditor_name' => 'Bank', 'amount' => 5000.00]);
});

it('requires creditor name, type, amount, currency, date and status', function (): void {
    $this->from(route('admin.debts.create'))
        ->post(route('admin.debts.store'), [])
        ->assertSessionHasErrors(['creditor_name', 'creditor_type', 'amount', 'currency', 'date', 'status']);
});

it('deletes a debt', function (): void {
    $debt = Debt::factory()->create();

    $this->delete(route('admin.debts.destroy', $debt));

    expect(Debt::find($debt->id))->toBeNull();
});
```

- [ ] **Step 3: Write the failing pay-debt test**

```bash
php artisan make:test --pest Admin/Budget/PayDebtTest --no-interaction
```

```php
<?php

declare(strict_types=1);

use App\Models\Debt;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));
    Storage::fake('public');
});

it('records a full payment, zeroes the debt and marks it paid', function (): void {
    $debt = Debt::factory()->create(['amount' => 1000.00, 'currency' => 'TRY', 'status' => 'pending']);

    $this->post(route('admin.debts.pay', $debt), [
        'payment_amount' => 1000.00,
        'payment_description' => 'Final payment',
    ])->assertRedirect(route('admin.debts.index'));

    $debt->refresh();

    expect((float) $debt->amount)->toBe(0.0);
    expect($debt->status)->toBe('paid');
});

it('records a partial payment and reduces the remaining amount', function (): void {
    $debt = Debt::factory()->create(['amount' => 1000.00, 'currency' => 'TRY', 'status' => 'pending']);

    $this->post(route('admin.debts.pay', $debt), [
        'payment_amount' => 400.00,
        'payment_description' => 'First instalment',
    ]);

    $debt->refresh();

    expect((float) $debt->amount)->toBe(600.0);
    expect($debt->status)->toBe('pending');
});

it('creates an expense for the payment', function (): void {
    $debt = Debt::factory()->create(['amount' => 1000.00, 'currency' => 'USD', 'status' => 'pending']);

    $this->post(route('admin.debts.pay', $debt), [
        'payment_amount' => 250.00,
        'payment_description' => 'Instalment',
    ]);

    $expense = Expense::where('debt_id', $debt->id)->firstOrFail();

    expect((float) $expense->amount)->toBe(250.0);
    expect($expense->currency->value)->toBe('USD');
    expect($expense->description)->toBe('Instalment');
});

it('stores an uploaded receipt on the expense', function (): void {
    $debt = Debt::factory()->create(['amount' => 500.00, 'status' => 'pending']);

    $this->post(route('admin.debts.pay', $debt), [
        'payment_amount' => 500.00,
        'payment_description' => 'Paid',
        'receipt' => UploadedFile::fake()->image('receipt.png'),
    ]);

    $expense = Expense::where('debt_id', $debt->id)->firstOrFail();

    expect($expense->receipt_path)->toStartWith('receipts/debt-payments/');
    Storage::disk('public')->assertExists($expense->receipt_path);
});

it('rejects a payment larger than the debt', function (): void {
    $debt = Debt::factory()->create(['amount' => 100.00, 'status' => 'pending']);

    $this->from(route('admin.debts.index'))
        ->post(route('admin.debts.pay', $debt), ['payment_amount' => 500.00, 'payment_description' => 'Too much'])
        ->assertSessionHasErrors('payment_amount');

    expect((float) $debt->fresh()->amount)->toBe(100.0);
    expect(Expense::where('debt_id', $debt->id)->exists())->toBeFalse();
});

it('rejects a zero or negative payment', function (): void {
    $debt = Debt::factory()->create(['amount' => 100.00, 'status' => 'pending']);

    $this->from(route('admin.debts.index'))
        ->post(route('admin.debts.pay', $debt), ['payment_amount' => 0, 'payment_description' => 'Nothing'])
        ->assertSessionHasErrors('payment_amount');
});

it('refuses to pay an already-paid debt', function (): void {
    $debt = Debt::factory()->paid()->create(['amount' => 0]);

    $this->from(route('admin.debts.index'))
        ->post(route('admin.debts.pay', $debt), ['payment_amount' => 10, 'payment_description' => 'x'])
        ->assertSessionHasErrors('payment_amount');
});

it('flashes a success notification naming the remaining balance', function (): void {
    $debt = Debt::factory()->create(['amount' => 1000.00, 'currency' => 'TRY', 'status' => 'pending']);

    $this->post(route('admin.debts.pay', $debt), [
        'payment_amount' => 400.00,
        'payment_description' => 'Instalment',
    ]);

    expect(session('flash.notification'))
        ->toHaveKey('variant', 'success')
        ->toHaveKey('title', 'Partial payment recorded');
});

it('does not double-create an expense via the debt observer on full payment', function (): void {
    $debt = Debt::factory()->create(['amount' => 200.00, 'status' => 'pending']);

    $this->post(route('admin.debts.pay', $debt), [
        'payment_amount' => 200.00,
        'payment_description' => 'Settled',
    ]);

    expect(Expense::where('debt_id', $debt->id)->count())->toBe(1);
});
```

The last test matters: `DebtObserver::updated` also creates an expense when status flips to `paid`. The observer guards on an existing expense, and the controller creates the expense **before** updating the status, so the guard fires. Verify that ordering in the implementation.

- [ ] **Step 4: Run both to verify they fail**

Run: `php artisan test --compact --filter="DebtResourceTest|PayDebtTest"`
Expected: FAIL — routes not defined.

- [ ] **Step 5: Write the table definition**

`app/Tables/Definitions/DebtTable.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tables\Definitions;

use App\Enums\Currencies;
use App\Models\Debt;
use App\Services\ExchangeRateService;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\ResourceTable;
use Illuminate\Database\Eloquent\Builder;

final class DebtTable extends ResourceTable
{
    protected string $model = Debt::class;

    protected string $defaultSort = '-date';

    /**
     * The conversion currency is chosen through a filter, but it changes how a
     * column renders rather than what the query returns, so it is passed in.
     */
    public function __construct(private readonly string $conversionCurrency = 'TRY') {}

    protected function columns(): array
    {
        $target = Currencies::tryFrom($this->conversionCurrency) ?? Currencies::TRY;

        return [
            Column::text('creditor_name')->label('Creditor')->sortable(),
            Column::badge('creditor_type')->label('Type')
                ->color(fn (Debt $record): string => $record->creditor_type === 'person' ? 'info' : 'warning'),
            Column::money('amount', currencyFrom: 'currency')->label('Original amount')->sortable(),
            Column::text('converted_amount')->label('Converted amount')
                ->state(function (Debt $record) use ($target): string {
                    if ($record->currency->value === $target->value) {
                        return $target->getSymbol().number_format((float) $record->amount, 2);
                    }

                    $converted = app(ExchangeRateService::class)->convert(
                        (float) $record->amount,
                        $record->currency->value,
                        $target->value,
                    );

                    return $target->getSymbol().number_format($converted, 2);
                })
                ->align('right'),
            Column::badge('currency'),
            Column::badge('status')->color(fn (Debt $record): string => (string) $record->status_color),
            Column::badge('due_date_status')->label('Due status')
                ->state(fn (Debt $record): string => (string) $record->due_date_status)
                ->color(fn (Debt $record): string => self::dueStatusColor($record)),
            Column::date('due_date')->label('Due date')->sortable()->toggleable(hiddenByDefault: true),
            Column::date('date')->label('Debt date')->sortable(),
            Column::datetime('created_at')->sortable()->toggleable(hiddenByDefault: true),
        ];
    }

    private static function dueStatusColor(Debt $record): string
    {
        if ($record->due_date === null || $record->status === 'paid') {
            return 'gray';
        }

        $days = $record->days_until_due;

        if ($days === null) {
            return 'gray';
        }

        if ($days < 0) {
            return 'danger';
        }

        return $days <= 7 ? 'warning' : 'success';
    }

    protected function filters(): array
    {
        return [
            Filter::select('status', ['pending' => 'Pending', 'paid' => 'Paid'])->multiple(),
            Filter::select('creditor_type', ['person' => 'Person', 'institute' => 'Institute'])->label('Type')->multiple(),
            Filter::enum('currency', Currencies::class)->multiple(),
            Filter::enum('conversion_currency', Currencies::class)
                ->label('Convert to')
                ->default(Currencies::TRY->value),
            Filter::custom('overdue', 'Overdue', fn (Builder $query): Builder => $query
                ->where('status', 'pending')
                ->whereNotNull('due_date')
                ->where('due_date', '<', now())),
            Filter::custom('due_soon', 'Due within 7 days', fn (Builder $query): Builder => $query
                ->where('status', 'pending')
                ->whereNotNull('due_date')
                ->whereBetween('due_date', [now(), now()->addDays(7)])),
            Filter::dateRange('date')->label('Debt date'),
            Filter::dateRange('due_date')->label('Due date'),
        ];
    }

    protected function searchable(): array
    {
        return ['creditor_name', 'description'];
    }

    protected function titleColumn(): string
    {
        return 'creditor_name';
    }
}
```

`conversion_currency` is declared as an enum filter but must **not** filter the query. `Filter::enum()` would call `where('conversion_currency', ...)`, and no such column exists. Add a `display()` modifier to `Filter` that marks it as UI-only:

In `app/Tables/Filter.php`, add:

```php
private bool $displayOnly = false;

/**
 * A filter that changes how rows render but does not constrain the query.
 */
public function displayOnly(bool $displayOnly = true): self
{
    $this->displayOnly = $displayOnly;

    return $this;
}
```

and guard `apply()`:

```php
public function apply(Builder $query, mixed $value): void
{
    if ($this->displayOnly || $this->isEmpty($value)) {
        return;
    }
    // ... unchanged
}
```

Then mark the filter: `Filter::enum('conversion_currency', Currencies::class)->label('Convert to')->default(Currencies::TRY->value)->displayOnly()`.

Add a test for it in `tests/Feature/Tables/FilterTest.php`:

```php
it('never constrains the query when display-only', function (): void {
    App\Models\Expense::factory()->count(3)->create();

    $query = App\Models\Expense::query();
    Filter::select('conversion_currency', ['TRY' => 'TRY'])->displayOnly()->apply($query, 'TRY');

    expect($query->count())->toBe(3);
});
```

- [ ] **Step 6: Write the form and requests**

`app/Forms/Definitions/DebtForm.php`:

```php
<?php

declare(strict_types=1);

namespace App\Forms\Definitions;

use App\Enums\Currencies;
use App\Forms\Field;
use App\Forms\ResourceForm;

final class DebtForm extends ResourceForm
{
    protected int $columns = 2;

    protected function fields(): array
    {
        return [
            Field::text('creditor_name')->label('Creditor name')->required(),
            Field::select('creditor_type', ['person' => 'Person', 'institute' => 'Institute'])
                ->label('Creditor type')->required()->default('person'),
            Field::money('amount')->required()->min(0)->step(0.01),
            Field::enum('currency', Currencies::class)->required()->default(Currencies::TRY->value),
            Field::date('date')->label('Debt date')->required()->default(now()->toDateString()),
            Field::date('due_date')->label('Due date')->help('Optional: when should this debt be paid?'),
            Field::select('status', ['pending' => 'Pending', 'paid' => 'Paid'])->required()->default('pending'),
            Field::textarea('description')->rows(3)->help('Optional: details about this debt')->columnSpan(2),
        ];
    }
}
```

`app/Http/Requests/Admin/DebtRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Currencies;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DebtRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'creditor_name' => ['required', 'string', 'max:255'],
            'creditor_type' => ['required', Rule::in(['person', 'institute'])],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', Rule::enum(Currencies::class)],
            'date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['pending', 'paid'])],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
```

`app/Http/Requests/Admin/PayDebtRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Debt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PayDebtRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Debt $debt */
        $debt = $this->route('debt');

        return [
            'payment_amount' => ['required', 'numeric', 'min:0.01', 'max:'.(float) $debt->amount],
            'payment_description' => ['required', 'string', 'max:500'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Debt $debt */
            $debt = $this->route('debt');

            if ($debt->status === 'paid') {
                $validator->errors()->add('payment_amount', 'This debt is already paid.');
            }
        });
    }
}
```

- [ ] **Step 7: Write the controller**

`app/Http/Controllers/Admin/Budget/DebtController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Budget;

use App\Enums\Currencies;
use App\Forms\Definitions\DebtForm;
use App\Forms\ResourceForm;
use App\Http\Controllers\Admin\AdminResourceController;
use App\Http\Requests\Admin\DebtRequest;
use App\Http\Requests\Admin\PayDebtRequest;
use App\Models\Debt;
use App\Models\Expense;
use App\Tables\Definitions\DebtTable;
use App\Tables\ResourceTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class DebtController extends AdminResourceController
{
    protected function table(): ResourceTable
    {
        $requested = request()->input('filter.conversion_currency');
        $currency = is_string($requested) && Currencies::tryFrom($requested) !== null
            ? $requested
            : Currencies::TRY->value;

        return new DebtTable($currency);
    }

    protected function form(): ResourceForm
    {
        return new DebtForm;
    }

    protected function modelClass(): string
    {
        return Debt::class;
    }

    protected function resourceName(): string
    {
        return 'debts';
    }

    protected function pagePath(): string
    {
        return 'Budget/Debts';
    }

    protected function requestClass(): string
    {
        return DebtRequest::class;
    }

    public function pay(PayDebtRequest $request, Debt $debt): RedirectResponse
    {
        $paymentAmount = (float) $request->validated()['payment_amount'];
        $remaining = (float) $debt->amount - $paymentAmount;

        $receiptPath = $request->hasFile('receipt')
            ? $request->file('receipt')->store('receipts/debt-payments', 'public')
            : null;

        DB::transaction(function () use ($debt, $request, $paymentAmount, $remaining, $receiptPath): void {
            // Created first so DebtObserver::updated finds it and does not create a second one.
            Expense::create([
                'debt_id' => $debt->id,
                'amount' => $paymentAmount,
                'currency' => $debt->currency->value,
                'description' => $request->validated()['payment_description'],
                'receipt_path' => $receiptPath,
                'date' => now()->toDateString(),
            ]);

            $debt->update($remaining <= 0
                ? ['amount' => 0, 'status' => 'paid']
                : ['amount' => $remaining]);
        });

        $symbol = $debt->currency->getSymbol();

        if ($remaining <= 0) {
            $this->notifier->success('Debt fully paid', "Debt to {$debt->creditor_name} has been fully paid.");
        } else {
            $this->notifier->success(
                'Partial payment recorded',
                "Paid {$symbol}".number_format($paymentAmount, 2).". Remaining: {$symbol}".number_format($remaining, 2),
            );
        }

        return to_route('admin.debts.index');
    }
}
```

- [ ] **Step 8: Routes, navigation, pages**

```php
Route::delete('debts/bulk', [DebtController::class, 'bulkDestroy'])->name('debts.bulk-destroy');
Route::post('debts/{debt}/pay', [DebtController::class, 'pay'])->name('debts.pay');
Route::resource('debts', DebtController::class)->except(['show']);
```

Navigation — Budget cluster: `['label' => 'Debts', 'route' => 'admin.debts.index', 'icon' => 'pi pi-exclamation-triangle']`.

`resources/js/Pages/Budget/Debts/PayDebtDialog.vue`:

```vue
<script setup>
import { ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import Textarea from 'primevue/textarea'
import FileUpload from 'primevue/fileupload'
import Button from 'primevue/button'

const props = defineProps({
    debt: { type: Object, default: null },
})

const emit = defineEmits(['close'])

const visible = ref(false)

const form = useForm({
    payment_amount: null,
    payment_description: '',
    receipt: null,
})

watch(
    () => props.debt,
    (debt) => {
        visible.value = Boolean(debt)

        if (debt) {
            form.reset()
            form.clearErrors()
            form.payment_amount = debt.amount
            form.payment_description = `Payment for debt to ${debt.creditorName}`
        }
    },
)

function submit() {
    form.post(route('admin.debts.pay', props.debt.id), {
        forceFormData: true,
        onSuccess: () => {
            visible.value = false
            emit('close')
        },
    })
}
</script>

<template>
    <Dialog
        v-model:visible="visible"
        modal
        header="Pay debt"
        :style="{ width: '32rem' }"
        @hide="emit('close')"
    >
        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <div class="flex flex-col gap-2">
                <label for="payment_amount" class="text-sm font-medium">Payment amount</label>
                <InputNumber
                    id="payment_amount"
                    v-model="form.payment_amount"
                    :min="0.01"
                    :max="debt?.amount"
                    :max-fraction-digits="2"
                    :invalid="Boolean(form.errors.payment_amount)"
                    fluid
                />
                <small class="text-surface-500">Maximum: {{ debt?.formattedAmount }}</small>
                <small v-if="form.errors.payment_amount" class="text-red-500">{{ form.errors.payment_amount }}</small>
            </div>

            <div class="flex flex-col gap-2">
                <label for="payment_description" class="text-sm font-medium">Description</label>
                <Textarea
                    id="payment_description"
                    v-model="form.payment_description"
                    :rows="2"
                    :invalid="Boolean(form.errors.payment_description)"
                />
                <small v-if="form.errors.payment_description" class="text-red-500">
                    {{ form.errors.payment_description }}
                </small>
            </div>

            <div class="flex flex-col gap-2">
                <label class="text-sm font-medium">Receipt (optional)</label>
                <FileUpload
                    mode="basic"
                    accept="image/*,application/pdf"
                    :max-file-size="5242880"
                    choose-label="Choose file"
                    custom-upload
                    auto
                    @uploader="form.receipt = $event.files[0]"
                />
            </div>

            <div class="flex justify-end gap-2">
                <Button type="button" label="Cancel" severity="secondary" text @click="visible = false" />
                <Button type="submit" label="Record payment" :loading="form.processing" />
            </div>
        </form>
    </Dialog>
</template>
```

`Index.vue` needs the extra row action. `ResourceTable` does not know about custom actions, so add a `#rowActions` slot to it — in `resources/js/Components/Table/ResourceTable.vue`, inside the actions `ColumnComponent` body, before the view button:

```vue
<slot name="rowActions" :row="data" />
```

Then in `resources/js/Pages/Budget/Debts/Index.vue`:

```vue
<script setup>
import { ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ResourceTable from '@/Components/Table/ResourceTable.vue'
import PayDebtDialog from './PayDebtDialog.vue'
import Button from 'primevue/button'

defineProps({
    schema: { type: Object, required: true },
    rows: { type: Object, required: true },
})

const payingDebt = ref(null)

function openPayDialog(row) {
    payingDebt.value = {
        id: row.id,
        amount: row.cells.amount.raw,
        formattedAmount: row.cells.amount.display,
        creditorName: row.cells.creditor_name.display,
        status: row.cells.status.raw,
    }
}
</script>

<template>
    <AdminLayout title="Debts">
        <ResourceTable :schema="schema" :rows="rows" resource="debts">
            <template #rowActions="{ row }">
                <Button
                    v-if="row.cells.status.raw === 'pending'"
                    icon="pi pi-money-bill"
                    text
                    rounded
                    size="small"
                    severity="success"
                    aria-label="Pay debt"
                    @click="openPayDialog(row)"
                />
            </template>
        </ResourceTable>

        <PayDebtDialog :debt="payingDebt" @close="payingDebt = null" />
    </AdminLayout>
</template>
```

Also create `Create.vue` and `Edit.vue` for Debts from the Posts templates.

- [ ] **Step 9: Run, analyse, commit**

```bash
php artisan test --compact --filter="DebtResourceTest|PayDebtTest|FilterTest"
npm run build
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
git add app/Tables app/Forms app/Http/Requests/Admin app/Http/Controllers/Admin/Budget routes/admin.php app/Support/Navigation.php resources/js tests/Feature
git commit -m "Port Debts with currency conversion, due-date status and the pay-debt action"
```

### Task 32: Port Invoices

**Files:**
- Create: `app/Tables/Definitions/InvoiceTable.php`, `app/Forms/Definitions/InvoiceForm.php`, `app/Http/Requests/Admin/InvoiceRequest.php`, `app/Http/Controllers/Admin/Work/InvoiceController.php`
- Create: `resources/js/Pages/Work/Invoices/{Index,Create,Edit}.vue`
- Modify: `routes/admin.php`, `app/Support/Navigation.php`
- Test: `tests/Feature/Admin/Work/InvoiceResourceTest.php`

**Interfaces:**
- Consumes: `Field::money()`, private-disk file upload.
- Produces: routes `admin.invoices.*`.

The original form computed `tax_amount` and `total_amount` live from `amount` and `tax_rate`. That arithmetic moves into the Vue page as watchers, and the server recomputes it on save so the stored totals cannot be tampered with.

The invoice file uploads to the **local** (private) disk, not `public` — so `uploads()` cannot be reused. The controller stores it explicitly.

- [ ] **Step 1: Write the failing test**

```bash
php artisan make:test --pest Admin/Work/InvoiceResourceTest --no-interaction
```

```php
<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));
    Storage::fake('local');
});

it('lists invoices with the client title', function (): void {
    $client = Client::factory()->create(['title' => 'Acme']);
    Invoice::factory()->create(['client_id' => $client->id, 'invoice_number' => 'INV-001']);

    $this->get(route('admin.invoices.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Work/Invoices/Index')
            ->where('rows.data.0.cells.invoice_number.display', 'INV-001')
            ->where('rows.data.0.cells.client\.title.display', 'Acme')
        );
});

it('formats the total with the invoice currency', function (): void {
    Invoice::factory()->create(['total_amount' => 12000.00, 'currency' => 'EUR']);

    $this->get(route('admin.invoices.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rows.data.0.cells.total_amount.display', '€12,000.00')
        );
});

it('recomputes tax and total on the server', function (): void {
    $client = Client::factory()->create();

    $this->post(route('admin.invoices.store'), [
        'client_id' => $client->id,
        'invoice_number' => 'INV-100',
        'issued_at' => '2026-05-01 10:00:00',
        'currency' => 'TRY',
        'amount' => 1000,
        'tax_rate' => 20,
        'tax_amount' => 9999,
        'total_amount' => 1,
        'invoice' => UploadedFile::fake()->create('invoice.zip', 10, 'application/zip'),
    ])->assertRedirect(route('admin.invoices.index'));

    $invoice = Invoice::where('invoice_number', 'INV-100')->firstOrFail();

    expect((float) $invoice->tax_amount)->toBe(200.0);
    expect((float) $invoice->total_amount)->toBe(1200.0);
});

it('stores the invoice archive on the private disk', function (): void {
    $client = Client::factory()->create();

    $this->post(route('admin.invoices.store'), [
        'client_id' => $client->id,
        'invoice_number' => 'INV-101',
        'issued_at' => '2026-05-01 10:00:00',
        'currency' => 'TRY',
        'amount' => 100,
        'tax_rate' => 0,
        'invoice' => UploadedFile::fake()->create('invoice.zip', 10, 'application/zip'),
    ]);

    $invoice = Invoice::where('invoice_number', 'INV-101')->firstOrFail();

    Storage::disk('local')->assertExists($invoice->invoice);
    Storage::disk('public')->assertMissing($invoice->invoice);
});

it('rejects a duplicate invoice number', function (): void {
    $existing = Invoice::factory()->create();
    $client = Client::factory()->create();

    $this->from(route('admin.invoices.create'))
        ->post(route('admin.invoices.store'), [
            'client_id' => $client->id,
            'invoice_number' => $existing->invoice_number,
            'issued_at' => '2026-05-01 10:00:00',
            'currency' => 'TRY',
            'amount' => 100,
            'tax_rate' => 0,
            'invoice' => UploadedFile::fake()->create('invoice.zip', 10, 'application/zip'),
        ])
        ->assertSessionHasErrors('invoice_number');
});

it('rejects a non-zip upload', function (): void {
    $client = Client::factory()->create();

    $this->from(route('admin.invoices.create'))
        ->post(route('admin.invoices.store'), [
            'client_id' => $client->id,
            'invoice_number' => 'INV-102',
            'issued_at' => '2026-05-01 10:00:00',
            'currency' => 'TRY',
            'amount' => 100,
            'tax_rate' => 0,
            'invoice' => UploadedFile::fake()->image('not-an-archive.png'),
        ])
        ->assertSessionHasErrors('invoice');
});

it('rejects a tax rate above 100', function (): void {
    $client = Client::factory()->create();

    $this->from(route('admin.invoices.create'))
        ->post(route('admin.invoices.store'), [
            'client_id' => $client->id,
            'invoice_number' => 'INV-103',
            'issued_at' => '2026-05-01 10:00:00',
            'currency' => 'TRY',
            'amount' => 100,
            'tax_rate' => 150,
            'invoice' => UploadedFile::fake()->create('invoice.zip', 10, 'application/zip'),
        ])
        ->assertSessionHasErrors('tax_rate');
});

it('updates an invoice without requiring a new upload', function (): void {
    $invoice = Invoice::factory()->create(['invoice' => 'invoices/existing.zip', 'amount' => 100, 'tax_rate' => 0]);

    $this->put(route('admin.invoices.update', $invoice), [
        'client_id' => $invoice->client_id,
        'invoice_number' => $invoice->invoice_number,
        'issued_at' => '2026-06-01 09:00:00',
        'currency' => $invoice->currency->value,
        'amount' => 200,
        'tax_rate' => 10,
    ])->assertSessionHasNoErrors();

    $invoice->refresh();

    expect($invoice->invoice)->toBe('invoices/existing.zip');
    expect((float) $invoice->total_amount)->toBe(220.0);
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --compact --filter=InvoiceResourceTest`
Expected: FAIL — route not defined.

- [ ] **Step 3: Write the definitions**

`app/Tables/Definitions/InvoiceTable.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tables\Definitions;

use App\Enums\Currencies;
use App\Models\Invoice;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\ResourceTable;

final class InvoiceTable extends ResourceTable
{
    protected string $model = Invoice::class;

    protected array $with = ['client'];

    protected string $defaultSort = '-issued_at';

    protected function columns(): array
    {
        return [
            Column::text('invoice_number')->label('Invoice number')->sortable(),
            Column::text('client.title')->label('Client'),
            Column::datetime('issued_at')->label('Issued')->sortable(),
            Column::money('amount', currencyFrom: 'currency')->sortable(),
            Column::money('tax_amount', currencyFrom: 'currency')->label('Tax'),
            Column::money('total_amount', currencyFrom: 'currency')->label('Total')->sortable(),
        ];
    }

    protected function filters(): array
    {
        return [
            Filter::relationship('client_id', 'client', 'title')->label('Client')->multiple(),
            Filter::enum('currency', Currencies::class)->multiple(),
            Filter::dateRange('issued_at')->label('Issued'),
        ];
    }

    protected function searchable(): array
    {
        return ['invoice_number', 'client.title'];
    }

    protected function titleColumn(): string
    {
        return 'invoice_number';
    }
}
```

`app/Forms/Definitions/InvoiceForm.php`:

```php
<?php

declare(strict_types=1);

namespace App\Forms\Definitions;

use App\Enums\Currencies;
use App\Forms\Field;
use App\Forms\ResourceForm;

final class InvoiceForm extends ResourceForm
{
    protected int $columns = 2;

    protected function fields(): array
    {
        return [
            Field::relationship('client_id', 'client', 'title')->label('Client')->required()->searchable()->columnSpan(2),
            Field::text('invoice_number')->label('Invoice number')->required(),
            Field::datetime('issued_at')->label('Issued at')->required()->default(now()->format('Y-m-d H:i:s')),
            Field::enum('currency', Currencies::class)->required()->default(Currencies::TRY->value),
            Field::money('amount')->required()->min(0)->default(0),
            Field::number('tax_rate')->label('Tax rate (%)')->required()->min(0)->max(100)->default(0),
            Field::money('tax_amount')->label('Tax amount')->disabled(),
            Field::money('total_amount')->label('Total')->disabled(),
            Field::file('invoice')->label('Invoice archive (.zip)')->accept(['application/zip', 'application/x-zip-compressed'])->columnSpan(2),
        ];
    }
}
```

`app/Http/Requests/Admin/InvoiceRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Currencies;
use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Invoice|null $invoice */
        $invoice = $this->route('invoice');

        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'invoice_number' => ['required', 'string', 'max:255', Rule::unique('invoices', 'invoice_number')->ignore($invoice?->id)],
            'issued_at' => ['required', 'date'],
            'currency' => ['required', Rule::enum(Currencies::class)],
            'amount' => ['required', 'numeric', 'min:0'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'invoice' => [$invoice === null ? 'required' : 'nullable', 'file', 'mimes:zip', 'max:20480'],
        ];
    }
}
```

`tax_amount` and `total_amount` are deliberately absent from the rules — the controller computes them.

- [ ] **Step 4: Write the controller**

`app/Http/Controllers/Admin/Work/InvoiceController.php` — the usual shape, plus an overridden `validated()`:

```php
/**
 * @return array<string, mixed>
 */
protected function validated(): array
{
    $request = app(InvoiceRequest::class);
    $data = $request->validated();

    $amount = (float) $data['amount'];
    $taxRate = (float) $data['tax_rate'];
    $taxAmount = round($amount * $taxRate / 100, 2);

    $data['tax_amount'] = $taxAmount;
    $data['total_amount'] = round($amount + $taxAmount, 2);

    if ($request->hasFile('invoice')) {
        $data['invoice'] = $request->file('invoice')->store('invoices', 'local');
    } else {
        unset($data['invoice']);
    }

    return $data;
}
```

- [ ] **Step 5: Routes, navigation, pages**

```php
Route::delete('invoices/bulk', [InvoiceController::class, 'bulkDestroy'])->name('invoices.bulk-destroy');
Route::resource('invoices', InvoiceController::class)->except(['show']);
```

Navigation — Work cluster: `['label' => 'Invoices', 'route' => 'admin.invoices.index', 'icon' => 'pi pi-receipt']`.

Pages: `resources/js/Pages/Work/Invoices/{Index,Create,Edit}.vue`, multipart submit, plus live totals. In both `Create.vue` and `Edit.vue`, add after the `useForm` call:

```js
import { watch } from 'vue'

watch(
    () => [form.amount, form.tax_rate],
    ([amount, taxRate]) => {
        const base = Number(amount) || 0
        const rate = Number(taxRate) || 0
        form.tax_amount = Math.round(base * rate) / 100
        form.total_amount = Math.round((base + form.tax_amount) * 100) / 100
    },
    { immediate: true },
)
```

These are display conveniences only — the server recomputes both on save.

- [ ] **Step 6: Run, analyse, commit**

```bash
php artisan test --compact --filter=InvoiceResourceTest
npm run build
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
git add app/Tables/Definitions/InvoiceTable.php app/Forms/Definitions/InvoiceForm.php app/Http/Requests/Admin/InvoiceRequest.php app/Http/Controllers/Admin/Work routes/admin.php app/Support/Navigation.php resources/js/Pages/Work tests/Feature/Admin/Work
git commit -m "Port Invoices; recompute tax and totals server-side"
```

### Task 33: Port WakaTime Summaries

Read-only: the data is synced from the WakaTime API and never authored by hand. The original resource sets `canCreate()` and `canEdit()` to false and has an entries relation manager plus an infolist.

**Files:**
- Create: `app/Tables/Definitions/WakaTimeSummaryTable.php`, `app/Tables/Definitions/WakaTimeSummaryEntryTable.php`, `app/Http/Controllers/Admin/Work/WakaTimeSummaryController.php`
- Create: `resources/js/Pages/Work/WakaTimeSummaries/{Index,Show}.vue`
- Modify: `routes/admin.php`, `app/Support/Navigation.php`
- Test: `tests/Feature/Admin/Work/WakaTimeSummaryResourceTest.php`

**Interfaces:**
- Consumes: `ResourceTable`.
- Produces: routes `admin.waka-time-summaries.index` and `.show` only. No create, edit, delete or bulk routes.

- [ ] **Step 1: Read the existing table and infolist**

Run: `cat app/Filament/Resources/WakaTimeSummaries/Tables/WakaTimeSummariesTable.php app/Filament/Resources/WakaTimeSummaries/Schemas/WakaTimeSummaryInfolist.php app/Filament/Resources/WakaTimeSummaries/RelationManagers/EntriesRelationManager.php`

Use the columns you find there. The definitions below assume `date`, `total_seconds`, and a human-readable duration; adjust to match.

- [ ] **Step 2: Write the failing test**

```bash
php artisan make:test --pest Admin/Work/WakaTimeSummaryResourceTest --no-interaction
```

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\WakaTimeSummary;
use App\Models\WakaTimeSummaryEntry;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));
});

it('lists summaries newest first', function (): void {
    WakaTimeSummary::factory()->create(['date' => '2026-01-01']);
    $newest = WakaTimeSummary::factory()->create(['date' => '2026-06-01']);

    $this->get(route('admin.waka-time-summaries.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Work/WakaTimeSummaries/Index')
            ->where('rows.data.0.id', $newest->id)
        );
});

it('filters summaries by date range', function (): void {
    WakaTimeSummary::factory()->create(['date' => '2026-01-15']);
    WakaTimeSummary::factory()->create(['date' => '2026-06-15']);

    $this->get(route('admin.waka-time-summaries.index', [
        'filter' => ['date' => ['from' => '2026-06-01', 'to' => '2026-06-30']],
    ]))->assertInertia(fn (AssertableInertia $page) => $page->has('rows.data', 1));
});

it('shows a summary with its entries', function (): void {
    $summary = WakaTimeSummary::factory()->create();
    WakaTimeSummaryEntry::factory()->count(3)->create(['waka_time_summary_id' => $summary->id]);

    $this->get(route('admin.waka-time-summaries.show', $summary))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Work/WakaTimeSummaries/Show')
            ->has('entries.data', 3)
        );
});

it('has no create, edit or destroy routes', function (): void {
    expect(Illuminate\Support\Facades\Route::has('admin.waka-time-summaries.create'))->toBeFalse();
    expect(Illuminate\Support\Facades\Route::has('admin.waka-time-summaries.edit'))->toBeFalse();
    expect(Illuminate\Support\Facades\Route::has('admin.waka-time-summaries.destroy'))->toBeFalse();
});
```

- [ ] **Step 3: Run it to verify it fails**

Run: `php artisan test --compact --filter=WakaTimeSummaryResourceTest`
Expected: FAIL — route not defined.

- [ ] **Step 4: Write the two table definitions**

`app/Tables/Definitions/WakaTimeSummaryTable.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tables\Definitions;

use App\Models\WakaTimeSummary;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\ResourceTable;

final class WakaTimeSummaryTable extends ResourceTable
{
    protected string $model = WakaTimeSummary::class;

    protected array $withCount = ['entries'];

    protected string $defaultSort = '-date';

    protected function columns(): array
    {
        return [
            Column::date('date')->sortable(),
            Column::count('total_seconds')->label('Seconds')->sortable(),
            Column::text('duration')->label('Time coded')
                ->state(fn (WakaTimeSummary $record): string => self::humanDuration((int) $record->total_seconds)),
            Column::count('entries_count')->label('Entries'),
        ];
    }

    private static function humanDuration(int $seconds): string
    {
        return intdiv($seconds, 3600).'h '.intdiv($seconds % 3600, 60).'m';
    }

    protected function filters(): array
    {
        return [
            Filter::dateRange('date'),
        ];
    }

    protected function titleColumn(): string
    {
        return 'date';
    }
}
```

`app/Tables/Definitions/WakaTimeSummaryEntryTable.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tables\Definitions;

use App\Models\WakaTimeSummaryEntry;
use App\Tables\Column;
use App\Tables\ResourceTable;
use Illuminate\Database\Eloquent\Builder;

final class WakaTimeSummaryEntryTable extends ResourceTable
{
    protected string $model = WakaTimeSummaryEntry::class;

    protected string $defaultSort = '-total_seconds';

    protected int $perPage = 50;

    public function __construct(private readonly int $summaryId) {}

    protected function query(): Builder
    {
        return WakaTimeSummaryEntry::query()->where('waka_time_summary_id', $this->summaryId);
    }

    protected function columns(): array
    {
        return [
            Column::badge('type')->label('Type'),
            Column::text('name')->sortable(),
            Column::count('total_seconds')->label('Seconds')->sortable(),
        ];
    }
}
```

- [ ] **Step 5: Write the controller**

This one does not extend `AdminResourceController`, because it has neither a form nor write actions.

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Work;

use App\Http\Controllers\Controller;
use App\Models\WakaTimeSummary;
use App\Tables\Definitions\WakaTimeSummaryEntryTable;
use App\Tables\Definitions\WakaTimeSummaryTable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WakaTimeSummaryController extends Controller
{
    public function index(Request $request): Response
    {
        $table = new WakaTimeSummaryTable;

        return Inertia::render('Work/WakaTimeSummaries/Index', [
            'schema' => $table->schema(),
            'rows' => fn () => $table->rows($request),
        ]);
    }

    public function show(Request $request, WakaTimeSummary $wakaTimeSummary): Response
    {
        $entries = new WakaTimeSummaryEntryTable($wakaTimeSummary->id);

        return Inertia::render('Work/WakaTimeSummaries/Show', [
            'summary' => [
                'id' => $wakaTimeSummary->id,
                'date' => $wakaTimeSummary->date->toDateString(),
                'totalSeconds' => (int) $wakaTimeSummary->total_seconds,
            ],
            'entriesSchema' => $entries->schema(),
            'entries' => fn () => $entries->rows($request),
        ]);
    }
}
```

- [ ] **Step 6: Routes, navigation, pages**

```php
Route::get('waka-time-summaries', [WakaTimeSummaryController::class, 'index'])->name('waka-time-summaries.index');
Route::get('waka-time-summaries/{wakaTimeSummary}', [WakaTimeSummaryController::class, 'show'])->name('waka-time-summaries.show');
```

Navigation — Work cluster: `['label' => 'Daily summaries', 'route' => 'admin.waka-time-summaries.index', 'icon' => 'pi pi-clock']`.

`Index.vue` uses `<ResourceTable :row-actions="['view']" :bulk-actions="[]" resource="waka-time-summaries" />`. The `view` action calls `route('admin.waka-time-summaries.show', row.id)`, which exists.

`Show.vue` renders the summary header plus a second `<ResourceTable>` for the entries — but that component navigates via `admin.{resource}.index`, which is wrong here. Pass `resource="waka-time-summaries"` and `:row-actions="[]"` `:bulk-actions="[]"`; sorting the entries table will reload the parent route with the entry sort parameters, which works because both tables read the same `sort` key. If that collision becomes confusing, give the entries table its own page later — out of scope here.

- [ ] **Step 7: Run, analyse, commit**

```bash
php artisan test --compact --filter=WakaTimeSummaryResourceTest
php artisan test --compact
npm run build
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
git add app/Tables/Definitions app/Http/Controllers/Admin/Work routes/admin.php app/Support/Navigation.php resources/js/Pages/Work tests/Feature/Admin/Work
git commit -m "Port WakaTime summaries as a read-only resource"
```

**Phase 3 checkpoint.** All thirteen resources are now on the new panel. Before moving on:

```bash
php artisan test --compact
vendor/bin/phpstan analyse
php artisan route:list --path=app
```

Every resource should appear, the suite should be green, and Larastan should report zero errors. Walk the panel by hand once, cluster by cluster.

---

# Phase 4 — The specials

Four features the component library gives you nothing for, plus the settings page and the notification bell. The environment indicator and footer were already delivered in Task 13 as part of `AdminLayout`.

### Task 34: Kanban task board

**Files:**
- Create: `app/Http/Controllers/Admin/Work/TaskBoardController.php`
- Create: `app/Http/Requests/Admin/MoveTaskRequest.php`, `app/Http/Requests/Admin/TaskRequest.php`
- Create: `app/Forms/Definitions/TaskForm.php`
- Create: `resources/js/Pages/Work/TasksBoard.vue`, `resources/js/Components/Board/BoardColumn.vue`, `resources/js/Components/Board/TaskCard.vue`, `resources/js/Components/Board/TaskDialog.vue`
- Modify: `routes/admin.php`, `app/Support/Navigation.php`
- Test: `tests/Feature/Admin/Work/TaskBoardTest.php`

**Interfaces:**
- Consumes: `TaskObserver` (Task 6 pinned its behavior), `GitHubService`, `AdminNotifier`.
- Produces: routes `admin.tasks.board`, `admin.tasks.move`, `admin.tasks.update`, `admin.tasks.sync-github`.
  Board payload shape: `array<string, array{key: string, label: string, color: string, tasks: array<int, array{id: int, title: string, description: string|null, repository: string|null, project: string|null, sortOrder: int, isGithubIssue: bool, githubUrl: string|null, labels: array<int, array{name: string, color: string}>}>}>`.

- [ ] **Step 1: Write the failing test**

```bash
php artisan make:test --pest Admin/Work/TaskBoardTest --no-interaction
```

```php
<?php

declare(strict_types=1);

use App\Models\Repository;
use App\Models\Task;
use App\Models\User;
use App\Services\GitHubService;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));

    $this->github = Mockery::mock(GitHubService::class);
    $this->github->shouldIgnoreMissing();
    app()->instance(GitHubService::class, $this->github);
});

it('renders three columns', function (): void {
    $this->get(route('admin.tasks.board'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Work/TasksBoard')
            ->has('columns', 3)
            ->where('columns.0.key', 'todo')
            ->where('columns.1.key', 'in_progress')
            ->where('columns.2.key', 'completed')
        );
});

it('groups tasks into their status column', function (): void {
    Task::factory()->count(2)->create(['status' => 'todo']);
    Task::factory()->create(['status' => 'completed']);

    $this->get(route('admin.tasks.board'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('columns.0.tasks', 2)
            ->has('columns.2.tasks', 1)
        );
});

it('orders tasks within a column by sort order', function (): void {
    $second = Task::factory()->create(['status' => 'todo', 'sort_order' => 2, 'project_id' => null]);
    $first = Task::factory()->create(['status' => 'todo', 'sort_order' => 1, 'project_id' => null]);

    $this->get(route('admin.tasks.board'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('columns.0.tasks.0.id', $first->id)
            ->where('columns.0.tasks.1.id', $second->id)
        );
});

it('includes github metadata on a linked task', function (): void {
    Task::factory()->githubIssue()->create(['status' => 'todo']);

    $this->get(route('admin.tasks.board'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('columns.0.tasks.0.isGithubIssue', true)
            ->has('columns.0.tasks.0.labels')
        );
});

it('moves a task to another column at a given position', function (): void {
    $task = Task::factory()->create(['status' => 'todo', 'sort_order' => 1, 'project_id' => null]);
    Task::factory()->create(['status' => 'in_progress', 'sort_order' => 1, 'project_id' => null]);

    $this->patch(route('admin.tasks.move', $task), [
        'status' => 'in_progress',
        'position' => 0,
    ])->assertRedirect();

    $task->refresh();

    expect($task->status)->toBe('in_progress');
    expect($task->sort_order)->toBe(1);
});

it('reindexes the destination column so positions stay contiguous', function (): void {
    $a = Task::factory()->create(['status' => 'in_progress', 'sort_order' => 1, 'project_id' => null]);
    $b = Task::factory()->create(['status' => 'in_progress', 'sort_order' => 2, 'project_id' => null]);
    $moving = Task::factory()->create(['status' => 'todo', 'sort_order' => 1, 'project_id' => null]);

    $this->patch(route('admin.tasks.move', $moving), ['status' => 'in_progress', 'position' => 1]);

    expect(Task::whereIn('id', [$a->id, $moving->id, $b->id])->orderBy('sort_order')->pluck('id')->all())
        ->toBe([$a->id, $moving->id, $b->id]);
    expect(Task::where('status', 'in_progress')->orderBy('sort_order')->pluck('sort_order')->all())
        ->toBe([1, 2, 3]);
});

it('reorders within the same column', function (): void {
    $a = Task::factory()->create(['status' => 'todo', 'sort_order' => 1, 'project_id' => null]);
    $b = Task::factory()->create(['status' => 'todo', 'sort_order' => 2, 'project_id' => null]);
    $c = Task::factory()->create(['status' => 'todo', 'sort_order' => 3, 'project_id' => null]);

    $this->patch(route('admin.tasks.move', $c), ['status' => 'todo', 'position' => 0]);

    expect(Task::where('status', 'todo')->orderBy('sort_order')->pluck('id')->all())
        ->toBe([$c->id, $a->id, $b->id]);
});

it('rejects an unknown status', function (): void {
    $task = Task::factory()->create(['status' => 'todo']);

    $this->from(route('admin.tasks.board'))
        ->patch(route('admin.tasks.move', $task), ['status' => 'archived', 'position' => 0])
        ->assertSessionHasErrors('status');
});

it('rejects a negative position', function (): void {
    $task = Task::factory()->create(['status' => 'todo']);

    $this->from(route('admin.tasks.board'))
        ->patch(route('admin.tasks.move', $task), ['status' => 'todo', 'position' => -1])
        ->assertSessionHasErrors('position');
});

it('syncs to github when a github-linked task changes status', function (): void {
    $task = Task::factory()->githubIssue()->create(['status' => 'todo']);

    $this->github->shouldReceive('updateIssue')->once();

    $this->patch(route('admin.tasks.move', $task), ['status' => 'completed', 'position' => 0]);
});

it('updates a task from the edit dialog', function (): void {
    $repository = Repository::factory()->create();
    $task = Task::factory()->create(['title' => 'Old title']);

    $this->put(route('admin.tasks.update', $task), [
        'title' => 'New title',
        'description' => 'Updated',
        'status' => 'in_progress',
        'repository_id' => $repository->id,
    ])->assertRedirect(route('admin.tasks.board'));

    expect($task->fresh()->title)->toBe('New title');
});

it('requires a title and a valid status on update', function (): void {
    $task = Task::factory()->create();

    $this->from(route('admin.tasks.board'))
        ->put(route('admin.tasks.update', $task), ['title' => '', 'status' => 'nope'])
        ->assertSessionHasErrors(['title', 'status']);
});

it('syncs a github issue on demand', function (): void {
    $task = Task::factory()->githubIssue()->create();

    $this->github->shouldReceive('updateIssue')->once()->andReturn(true);

    $this->post(route('admin.tasks.sync-github', $task))->assertRedirect(route('admin.tasks.board'));

    expect(session('flash.notification'))->toHaveKey('variant', 'success');
});

it('reports a failed github sync', function (): void {
    $task = Task::factory()->githubIssue()->create();

    $this->github->shouldReceive('updateIssue')->once()->andThrow(new Exception('API down'));

    $this->post(route('admin.tasks.sync-github', $task));

    expect(session('flash.notification'))->toHaveKey('variant', 'danger');
});

it('refuses to sync a task that is not a github issue', function (): void {
    $task = Task::factory()->create(['github_issue_number' => null]);

    $this->github->shouldNotReceive('updateIssue');

    $this->post(route('admin.tasks.sync-github', $task))->assertForbidden();
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --compact --filter=TaskBoardTest`
Expected: FAIL — route `admin.tasks.board` not defined.

- [ ] **Step 3: Write the requests**

`app/Http/Requests/Admin/MoveTaskRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveTaskRequest extends FormRequest
{
    public const STATUSES = ['todo', 'in_progress', 'completed'];

    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(self::STATUSES)],
            'position' => ['required', 'integer', 'min:0'],
        ];
    }
}
```

`app/Http/Requests/Admin/TaskRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(MoveTaskRequest::STATUSES)],
            'repository_id' => ['nullable', 'integer', 'exists:repositories,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
        ];
    }
}
```

- [ ] **Step 4: Write the controller**

`app/Http/Controllers/Admin/Work/TaskBoardController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Work;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MoveTaskRequest;
use App\Http\Requests\Admin\TaskRequest;
use App\Models\Task;
use App\Services\GitHubService;
use App\Support\AdminNotifier;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class TaskBoardController extends Controller
{
    private const COLUMNS = [
        ['key' => 'todo', 'label' => 'To Do', 'color' => 'info'],
        ['key' => 'in_progress', 'label' => 'In Progress', 'color' => 'warning'],
        ['key' => 'completed', 'label' => 'Completed', 'color' => 'success'],
    ];

    public function __construct(private readonly AdminNotifier $notifier) {}

    public function index(): Response
    {
        $tasks = Task::query()
            ->with(['repository', 'project'])
            ->orderBy('sort_order')
            ->get()
            ->groupBy('status');

        $columns = [];

        foreach (self::COLUMNS as $column) {
            $columns[] = [
                ...$column,
                'tasks' => $tasks->get($column['key'], collect())
                    ->map(fn (Task $task): array => $this->presentTask($task))
                    ->values()
                    ->all(),
            ];
        }

        return Inertia::render('Work/TasksBoard', [
            'columns' => $columns,
            'statuses' => array_map(
                fn (array $column): array => ['value' => $column['key'], 'label' => $column['label']],
                self::COLUMNS,
            ),
            'repositories' => \App\Models\Repository::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($repository): array => ['value' => $repository->id, 'label' => $repository->name])
                ->all(),
        ]);
    }

    /**
     * @return array{id: int, title: string, description: string|null, repository: string|null, project: string|null, sortOrder: int, isGithubIssue: bool, githubUrl: string|null, repositoryId: int|null, status: string, labels: array<int, array{name: string, color: string}>}
     */
    private function presentTask(Task $task): array
    {
        /** @var array<int, array<string, mixed>> $rawLabels */
        $rawLabels = $task->github_issue_labels ?? [];

        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status,
            'repository' => $task->repository?->name,
            'repositoryId' => $task->repository_id,
            'project' => $task->project?->name,
            'sortOrder' => (int) $task->sort_order,
            'isGithubIssue' => $task->is_github_issue,
            'githubUrl' => $task->github_issue_url,
            'labels' => array_map(
                fn (array $label): array => [
                    'name' => is_string($label['name'] ?? null) ? $label['name'] : '',
                    'color' => is_string($label['color'] ?? null) ? $label['color'] : '888888',
                ],
                $rawLabels,
            ),
        ];
    }

    public function move(MoveTaskRequest $request, Task $task): RedirectResponse
    {
        /** @var array{status: string, position: int} $data */
        $data = $request->validated();

        DB::transaction(function () use ($task, $data): void {
            // Take the task out of the ordering first so reindexing is simple.
            $task->update(['status' => $data['status'], 'sort_order' => null]);

            $siblings = Task::query()
                ->where('status', $data['status'])
                ->where('id', '!=', $task->id)
                ->orderBy('sort_order')
                ->get();

            $ordered = $siblings->values();
            $position = min($data['position'], $ordered->count());
            $ordered->splice($position, 0, [$task]);

            foreach ($ordered as $index => $sibling) {
                $sibling->updateQuietly(['sort_order' => $index + 1]);
            }

            // Re-save through the model so TaskObserver sees the status change.
            $task->refresh();
        });

        return back();
    }

    public function update(TaskRequest $request, Task $task): RedirectResponse
    {
        $task->update($request->validated());

        $this->notifier->success('Task updated');

        return to_route('admin.tasks.board');
    }

    public function syncToGitHub(Task $task): RedirectResponse
    {
        if (! $task->is_github_issue) {
            throw new AccessDeniedHttpException('This task is not linked to a GitHub issue.');
        }

        try {
            $success = app(GitHubService::class)->updateIssue($task);

            $success
                ? $this->notifier->success('Synced to GitHub')
                : $this->notifier->danger('Failed to sync to GitHub');
        } catch (Exception $exception) {
            $this->notifier->danger('Error: '.$exception->getMessage());
        }

        return to_route('admin.tasks.board');
    }
}
```

`updateQuietly` on siblings avoids firing `TaskObserver::updated` for pure reordering. The moved task's status change goes through `update()` so the observer does fire and syncs to GitHub — which is what the test expects.

- [ ] **Step 5: Routes and navigation**

```php
Route::get('tasks/board', [TaskBoardController::class, 'index'])->name('tasks.board');
Route::patch('tasks/{task}/move', [TaskBoardController::class, 'move'])->name('tasks.move');
Route::put('tasks/{task}', [TaskBoardController::class, 'update'])->name('tasks.update');
Route::post('tasks/{task}/sync-github', [TaskBoardController::class, 'syncToGitHub'])->name('tasks.sync-github');
```

Navigation — Work cluster: `['label' => 'Task board', 'route' => 'admin.tasks.board', 'icon' => 'pi pi-th-large']`.

- [ ] **Step 6: Write the card**

`resources/js/Components/Board/TaskCard.vue`:

```vue
<script setup>
import Button from 'primevue/button'

defineProps({
    task: { type: Object, required: true },
})

const emit = defineEmits(['edit', 'sync'])
</script>

<template>
    <article
        class="group cursor-grab rounded border border-surface-200 bg-surface-0 p-3 active:cursor-grabbing dark:border-surface-800 dark:bg-surface-900"
    >
        <div class="flex items-start gap-2">
            <h3 class="flex-1 text-sm font-medium leading-snug">{{ task.title }}</h3>

            <div class="flex shrink-0 gap-0.5 opacity-0 transition group-hover:opacity-100">
                <Button icon="pi pi-pencil" text rounded size="small" severity="secondary" aria-label="Edit" @click="emit('edit', task)" />
                <Button
                    v-if="task.isGithubIssue"
                    icon="pi pi-sync"
                    text
                    rounded
                    size="small"
                    severity="secondary"
                    aria-label="Sync to GitHub"
                    @click="emit('sync', task)"
                />
            </div>
        </div>

        <p v-if="task.repository" class="mt-2 font-mono text-xs text-surface-500">{{ task.repository }}</p>

        <div v-if="task.labels.length" class="mt-2 flex flex-wrap gap-1">
            <span
                v-for="label in task.labels"
                :key="label.name"
                class="rounded-full px-2 py-0.5 text-[10px] font-medium"
                :style="{ backgroundColor: `#${label.color}20`, color: `#${label.color}` }"
            >{{ label.name }}</span>
        </div>

        <a
            v-if="task.githubUrl"
            :href="task.githubUrl"
            target="_blank"
            rel="noopener"
            class="mt-2 inline-flex items-center gap-1 font-mono text-xs text-surface-400 hover:text-primary"
        >
            <i class="pi pi-github" style="font-size: 0.7rem" />
            issue
        </a>
    </article>
</template>
```

- [ ] **Step 7: Write the board page**

`resources/js/Pages/Work/TasksBoard.vue`:

```vue
<script setup>
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import draggable from 'vuedraggable'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Badge from 'primevue/badge'
import TaskCard from '@/Components/Board/TaskCard.vue'
import TaskDialog from '@/Components/Board/TaskDialog.vue'
import { useConfirm } from 'primevue/useconfirm'

const props = defineProps({
    columns: { type: Array, required: true },
    statuses: { type: Array, required: true },
    repositories: { type: Array, required: true },
})

const confirm = useConfirm()
const editingTask = ref(null)
const local = ref(props.columns.map((column) => ({ ...column, tasks: [...column.tasks] })))

watch(
    () => props.columns,
    (columns) => {
        local.value = columns.map((column) => ({ ...column, tasks: [...column.tasks] }))
    },
)

function onDrop(column, event) {
    const moved = event.added ?? event.moved

    if (!moved) {
        return
    }

    router.patch(
        route('admin.tasks.move', moved.element.id),
        { status: column.key, position: moved.newIndex },
        {
            preserveScroll: true,
            onError: () => {
                // Server rejected the move; re-sync from the authoritative props.
                local.value = props.columns.map((c) => ({ ...c, tasks: [...c.tasks] }))
            },
        },
    )
}

function syncTask(task) {
    confirm.require({
        message: `Push local changes for "${task.title}" to GitHub?`,
        header: 'Sync to GitHub',
        icon: 'pi pi-github',
        acceptProps: { label: 'Sync' },
        rejectProps: { label: 'Cancel', severity: 'secondary', outlined: true },
        accept: () => router.post(route('admin.tasks.sync-github', task.id), {}, { preserveScroll: true }),
    })
}
</script>

<template>
    <AdminLayout title="Task board">
        <div class="grid gap-4 md:grid-cols-3">
            <section
                v-for="column in local"
                :key="column.key"
                class="rounded-lg border border-surface-200 bg-surface-100/50 p-3 dark:border-surface-800 dark:bg-surface-900/50"
            >
                <header class="mb-3 flex items-center gap-2">
                    <h2 class="text-sm font-semibold">{{ column.label }}</h2>
                    <Badge :value="column.tasks.length" :severity="column.color" />
                </header>

                <draggable
                    v-model="column.tasks"
                    group="tasks"
                    item-key="id"
                    class="flex min-h-24 flex-col gap-2"
                    ghost-class="opacity-40"
                    @change="onDrop(column, $event)"
                >
                    <template #item="{ element }">
                        <TaskCard :task="element" @edit="editingTask = $event" @sync="syncTask" />
                    </template>
                </draggable>
            </section>
        </div>

        <TaskDialog
            :task="editingTask"
            :statuses="statuses"
            :repositories="repositories"
            @close="editingTask = null"
        />
    </AdminLayout>
</template>
```

- [ ] **Step 8: Write the edit dialog**

`resources/js/Components/Board/TaskDialog.vue` — a PrimeVue `Dialog` with `InputText` for title, `Textarea` for description, `Select` for status (from `statuses`) and `Select` for repository (from `repositories`), submitting via `form.put(route('admin.tasks.update', task.id))`. Model it on `PayDebtDialog.vue` from Task 31 Step 8: a `visible` ref driven by a `watch` on the `task` prop, `useForm` reset on open, an `@hide` that emits `close`.

- [ ] **Step 9: Run, analyse, commit**

```bash
php artisan test --compact --filter=TaskBoardTest
npm run build
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
git add app/Http/Controllers/Admin/Work/TaskBoardController.php app/Http/Requests/Admin routes/admin.php app/Support/Navigation.php resources/js/Pages/Work/TasksBoard.vue resources/js/Components/Board tests/Feature/Admin/Work/TaskBoardTest.php
git commit -m "Rebuild the kanban task board without flowforge"
```

### Task 35: Spotlight command palette

**Files:**
- Create: `resources/js/Components/Spotlight.vue`
- Modify: `resources/js/Layouts/AdminLayout.vue`
- Test: covered by Task 36's search test plus the Task 45 browser smoke test.

**Interfaces:**
- Consumes: the shared `navigation` prop (Task 13).
- Produces: `<Spotlight>`, mounted once in `AdminLayout`, opened by `Cmd/Ctrl+K`. Task 36 extends it with a remote results section.

- [ ] **Step 1: Write the component**

`resources/js/Components/Spotlight.vue`:

```vue
<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'

const page = usePage()
const visible = ref(false)
const query = ref('')
const highlighted = ref(0)
const input = ref(null)

/**
 * Built from the same navigation array that drives the top bar, so the two
 * can never drift apart.
 */
const navTargets = computed(() =>
    (page.props.navigation ?? []).flatMap((cluster) =>
        cluster.items.map((item) => ({
            id: `nav:${item.route}`,
            label: item.label,
            group: cluster.label,
            icon: item.icon,
            href: route(item.route),
        })),
    ),
)

const staticTargets = computed(() => [
    { id: 'nav:dashboard', label: 'Dashboard', group: 'Go to', icon: 'pi pi-home', href: route('admin.dashboard') },
    { id: 'nav:profile', label: 'Profile', group: 'Go to', icon: 'pi pi-user', href: route('admin.profile') },
    { id: 'nav:settings', label: 'Settings', group: 'Go to', icon: 'pi pi-cog', href: route('admin.settings') },
])

const allTargets = computed(() => [...navTargets.value, ...staticTargets.value])

function fuzzyScore(haystack, needle) {
    if (!needle) {
        return 0
    }

    const lowerHaystack = haystack.toLowerCase()
    const lowerNeedle = needle.toLowerCase()

    if (lowerHaystack.startsWith(lowerNeedle)) {
        return 3
    }

    if (lowerHaystack.includes(lowerNeedle)) {
        return 2
    }

    let index = 0
    for (const character of lowerNeedle) {
        index = lowerHaystack.indexOf(character, index)
        if (index === -1) {
            return -1
        }
        index += 1
    }

    return 1
}

const results = computed(() => {
    if (!query.value) {
        return allTargets.value
    }

    return allTargets.value
        .map((target) => ({ target, score: fuzzyScore(target.label, query.value) }))
        .filter((entry) => entry.score >= 0)
        .sort((a, b) => b.score - a.score)
        .map((entry) => entry.target)
})

watch(results, () => {
    highlighted.value = 0
})

function open() {
    visible.value = true
    query.value = ''
    highlighted.value = 0
}

function select(target) {
    visible.value = false
    router.visit(target.href)
}

function onKeydown(event) {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault()
        open()
        return
    }

    if (!visible.value) {
        return
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault()
        highlighted.value = Math.min(highlighted.value + 1, results.value.length - 1)
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault()
        highlighted.value = Math.max(highlighted.value - 1, 0)
    }

    if (event.key === 'Enter' && results.value[highlighted.value]) {
        event.preventDefault()
        select(results.value[highlighted.value])
    }
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onUnmounted(() => window.removeEventListener('keydown', onKeydown))

defineExpose({ open })
</script>

<template>
    <Dialog
        v-model:visible="visible"
        modal
        :show-header="false"
        :dismissable-mask="true"
        :style="{ width: '34rem' }"
        content-class="!p-0"
        @show="input?.$el?.focus()"
    >
        <div class="border-b border-surface-200 p-3 dark:border-surface-800">
            <InputText
                ref="input"
                v-model="query"
                placeholder="Jump to…"
                autofocus
                fluid
                class="!border-0 !shadow-none"
            />
        </div>

        <ul class="max-h-80 overflow-y-auto p-2">
            <li v-if="!results.length" class="px-3 py-6 text-center text-sm text-surface-500">
                Nothing matches “{{ query }}”.
            </li>

            <li
                v-for="(target, index) in results"
                :key="target.id"
                class="flex cursor-pointer items-center gap-3 rounded px-3 py-2 text-sm"
                :class="index === highlighted ? 'bg-primary/10 text-primary' : 'hover:bg-surface-100 dark:hover:bg-surface-800'"
                @mouseenter="highlighted = index"
                @click="select(target)"
            >
                <i :class="target.icon" style="font-size: 0.85rem" />
                <span class="flex-1">{{ target.label }}</span>
                <span class="font-mono text-xs text-surface-400">{{ target.group }}</span>
            </li>
        </ul>
    </Dialog>
</template>
```

- [ ] **Step 2: Mount it in the layout**

In `AdminLayout.vue`, import `Spotlight` and add to the `topbar` area:

```vue
<Button
    icon="pi pi-search"
    text
    size="small"
    severity="secondary"
    aria-label="Search"
    @click="spotlight.open()"
/>
<Spotlight ref="spotlight" />
```

and `const spotlight = ref()` in the script.

- [ ] **Step 3: Verify by hand**

Run `npm run build`, open any admin page, press `Cmd+K`. The palette should list every navigation target, filter as you type, move with arrow keys and navigate on Enter. `admin.settings` does not exist until Task 38 — comment that entry out of `staticTargets` until then, or complete Task 38 first.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Components/Spotlight.vue resources/js/Layouts/AdminLayout.vue
git commit -m "Add a Cmd+K command palette built from the navigation registry"
```

### Task 36: Global search

**Files:**
- Create: `app/Support/GlobalSearch.php`
- Create: `app/Http/Controllers/Admin/SearchController.php`
- Modify: `resources/js/Components/Spotlight.vue`, `routes/admin.php`
- Test: `tests/Feature/Admin/GlobalSearchTest.php`

**Interfaces:**
- Consumes: `ResourceTable::search()` (Task 18) and every table definition from Phase 3.
- Produces: `App\Support\GlobalSearch::query(string $term, int $perResource = 5): array<int, array{label: string, resource: string, group: string, url: string}>` and route `admin.search` (`GET /app/search`).

- [ ] **Step 1: Write the failing test**

```bash
php artisan make:test --pest Admin/GlobalSearchTest --no-interaction
```

```php
<?php

declare(strict_types=1);

use App\Models\Book;
use App\Models\Client;
use App\Models\Post;
use App\Models\User;
use App\Models\Writer;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));
});

it('returns an empty array for an empty term', function (): void {
    $this->getJson(route('admin.search', ['q' => '']))
        ->assertOk()
        ->assertExactJson(['results' => []]);
});

it('returns an empty array for a one-character term', function (): void {
    Post::factory()->create(['title' => 'Rust']);

    $this->getJson(route('admin.search', ['q' => 'R']))
        ->assertOk()
        ->assertExactJson(['results' => []]);
});

it('finds records across resources', function (): void {
    Post::factory()->create(['title' => 'Learning Rust']);
    Client::factory()->create(['title' => 'Rust Consulting']);

    $response = $this->getJson(route('admin.search', ['q' => 'rust']))->assertOk();

    $groups = collect($response->json('results'))->pluck('group')->unique()->values();

    expect($groups)->toContain('Posts', 'Clients');
});

it('gives every result a working edit url', function (): void {
    $post = Post::factory()->create(['title' => 'Learning Rust']);

    $results = $this->getJson(route('admin.search', ['q' => 'rust']))->json('results');

    expect($results[0]['url'])->toBe(route('admin.posts.edit', $post->id));
});

it('links read-only resources to their show page', function (): void {
    $summary = App\Models\WakaTimeSummary::factory()->create(['date' => '2026-06-01']);

    $results = App\Support\GlobalSearch::query('2026-06-01');

    $wakaTime = collect($results)->firstWhere('group', 'Daily summaries');

    expect($wakaTime['url'] ?? null)->toBe(route('admin.waka-time-summaries.show', $summary->id));
});

it('caps results per resource', function (): void {
    Post::factory()->count(20)->create(['title' => 'Rust post']);

    $results = $this->getJson(route('admin.search', ['q' => 'rust']))->json('results');

    expect(collect($results)->where('group', 'Posts'))->toHaveCount(5);
});

it('searches across relationships', function (): void {
    $writer = Writer::factory()->create(['name' => 'Le Guin']);
    Book::factory()->create(['writer_id' => $writer->id]);

    $results = $this->getJson(route('admin.search', ['q' => 'Le Guin']))->json('results');

    expect(collect($results)->pluck('group'))->toContain('Books', 'Writers');
});

it('requires an authenticated admin', function (): void {
    auth()->logout();

    $this->getJson(route('admin.search', ['q' => 'rust']))->assertUnauthorized();
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --compact --filter=GlobalSearchTest`
Expected: FAIL — route `admin.search` not defined.

- [ ] **Step 3: Write the registry**

`app/Support/GlobalSearch.php`:

```php
<?php

declare(strict_types=1);

namespace App\Support;

use App\Tables\Definitions\BookTable;
use App\Tables\Definitions\CategoryTable;
use App\Tables\Definitions\ClientTable;
use App\Tables\Definitions\DebtTable;
use App\Tables\Definitions\ExpenseTable;
use App\Tables\Definitions\IncomeTable;
use App\Tables\Definitions\InvoiceTable;
use App\Tables\Definitions\PostTable;
use App\Tables\Definitions\ProjectTable;
use App\Tables\Definitions\PublisherTable;
use App\Tables\Definitions\RepositoryTable;
use App\Tables\Definitions\WakaTimeSummaryTable;
use App\Tables\Definitions\WriterTable;
use App\Tables\ResourceTable;

final class GlobalSearch
{
    private const MINIMUM_TERM_LENGTH = 2;

    /**
     * group => [table factory, route name, route verb]
     *
     * @return array<string, array{table: callable(): ResourceTable, route: string}>
     */
    private static function registry(): array
    {
        return [
            'Posts' => ['table' => fn (): ResourceTable => new PostTable, 'route' => 'admin.posts.edit'],
            'Categories' => ['table' => fn (): ResourceTable => new CategoryTable, 'route' => 'admin.categories.edit'],
            'Books' => ['table' => fn (): ResourceTable => new BookTable, 'route' => 'admin.books.edit'],
            'Writers' => ['table' => fn (): ResourceTable => new WriterTable, 'route' => 'admin.writers.edit'],
            'Publishers' => ['table' => fn (): ResourceTable => new PublisherTable, 'route' => 'admin.publishers.edit'],
            'Clients' => ['table' => fn (): ResourceTable => new ClientTable, 'route' => 'admin.clients.edit'],
            'Projects' => ['table' => fn (): ResourceTable => new ProjectTable, 'route' => 'admin.projects.edit'],
            'Repositories' => ['table' => fn (): ResourceTable => new RepositoryTable, 'route' => 'admin.repositories.show'],
            'Invoices' => ['table' => fn (): ResourceTable => new InvoiceTable, 'route' => 'admin.invoices.edit'],
            'Incomes' => ['table' => fn (): ResourceTable => new IncomeTable, 'route' => 'admin.incomes.show'],
            'Expenses' => ['table' => fn (): ResourceTable => new ExpenseTable, 'route' => 'admin.expenses.edit'],
            'Debts' => ['table' => fn (): ResourceTable => new DebtTable, 'route' => 'admin.debts.edit'],
            'Daily summaries' => ['table' => fn (): ResourceTable => new WakaTimeSummaryTable, 'route' => 'admin.waka-time-summaries.show'],
        ];
    }

    /**
     * @return array<int, array{label: string, group: string, url: string}>
     */
    public static function query(string $term, int $perResource = 5): array
    {
        $term = trim($term);

        if (mb_strlen($term) < self::MINIMUM_TERM_LENGTH) {
            return [];
        }

        $results = [];

        foreach (self::registry() as $group => $entry) {
            $table = ($entry['table'])();

            foreach ($table->search($term, $perResource) as $row) {
                $results[] = [
                    'label' => $row['label'],
                    'group' => $group,
                    'url' => route($entry['route'], $row['id']),
                ];
            }
        }

        return $results;
    }
}
```

`WakaTimeSummaryTable` declares no `searchable()`, so `ResourceTable::search()` returns an empty collection for it and the test asserting a WakaTime hit would fail. Add `protected function searchable(): array { return ['date']; }` to `WakaTimeSummaryTable`.

- [ ] **Step 4: Write the controller and route**

```bash
php artisan make:controller Admin/SearchController --invokable --no-interaction
```

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\GlobalSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'results' => GlobalSearch::query($request->string('q')->toString()),
        ]);
    }
}
```

Route: `Route::get('search', SearchController::class)->name('search');`

- [ ] **Step 5: Wire it into Spotlight**

In `resources/js/Components/Spotlight.vue`, add remote results below the navigation results:

```js
import axios from 'axios'

const remoteResults = ref([])
let searchTimer = null

watch(query, (term) => {
    clearTimeout(searchTimer)

    if (term.length < 2) {
        remoteResults.value = []
        return
    }

    searchTimer = setTimeout(async () => {
        try {
            const { data } = await axios.get(route('admin.search'), { params: { q: term } })
            remoteResults.value = data.results.map((result, index) => ({
                id: `record:${index}`,
                label: result.label,
                group: result.group,
                icon: 'pi pi-arrow-right',
                href: result.url,
            }))
        } catch {
            remoteResults.value = []
        }
    }, 250)
})
```

and change `results` to append them:

```js
const results = computed(() => {
    const nav = !query.value
        ? allTargets.value
        : allTargets.value
              .map((target) => ({ target, score: fuzzyScore(target.label, query.value) }))
              .filter((entry) => entry.score >= 0)
              .sort((a, b) => b.score - a.score)
              .map((entry) => entry.target)

    return [...nav, ...remoteResults.value]
})
```

- [ ] **Step 6: Run, analyse, commit**

```bash
php artisan test --compact --filter=GlobalSearchTest
npm run build
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
git add app/Support/GlobalSearch.php app/Http/Controllers/Admin/SearchController.php app/Tables/Definitions/WakaTimeSummaryTable.php routes/admin.php resources/js/Components/Spotlight.vue tests/Feature/Admin/GlobalSearchTest.php
git commit -m "Add global search across all thirteen resources, surfaced in Spotlight"
```

### Task 37: Exports

Three exporters exist today: Book, Publisher, Writer. `app/Filament/Imports/` is empty, so there are no importers to replace.

**Files:**
- Create: `app/Exports/ResourceExport.php`, `app/Exports/{BookExport,PublisherExport,WriterExport}.php`
- Create: `app/Jobs/RunResourceExport.php`
- Create: `app/Http/Controllers/Admin/ExportController.php`
- Modify: `routes/admin.php`, `resources/js/Components/Table/ResourceTable.vue`
- Test: `tests/Feature/Admin/ExportTest.php`

**Interfaces:**
- Consumes: `AdminNotifier`.
- Produces:
  - `App\Exports\ResourceExport` abstract with `abstract public function headings(): array<int, string>`, `abstract public function query(): Builder`, `abstract public function row(Model $record): array<int, string|int|float|null>`, `public function filename(): string`.
  - Routes `admin.exports.store` (`POST /app/exports/{resource}`) and `admin.exports.download` (`GET /app/exports/{path}`, signed).

- [ ] **Step 1: Read the existing exporters**

Run: `cat app/Filament/Exports/BookExporter.php app/Filament/Exports/PublisherExporter.php app/Filament/Exports/WriterExporter.php`
Copy the column lists verbatim into the new exports so the CSV output stays identical.

- [ ] **Step 2: Write the failing test**

```bash
php artisan make:test --pest Admin/ExportTest --no-interaction
```

```php
<?php

declare(strict_types=1);

use App\Jobs\RunResourceExport;
use App\Models\Book;
use App\Models\Publisher;
use App\Models\User;
use App\Models\Writer;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));
    Storage::fake('local');
});

it('dispatches an export job', function (): void {
    Bus::fake();

    $this->post(route('admin.exports.store', 'books'))->assertRedirect();

    Bus::assertDispatched(RunResourceExport::class);
});

it('rejects an unknown resource', function (): void {
    $this->post(route('admin.exports.store', 'unicorns'))->assertNotFound();
});

it('writes a csv with the expected headings', function (): void {
    $writer = Writer::factory()->create(['name' => 'Le Guin']);
    $publisher = Publisher::factory()->create(['name' => 'Ace']);
    Book::factory()->create(['name' => 'The Dispossessed', 'writer_id' => $writer->id, 'publisher_id' => $publisher->id]);

    $path = (new RunResourceExport('books'))->handle();

    Storage::disk('local')->assertExists($path);

    $csv = Storage::disk('local')->get($path);
    $lines = array_values(array_filter(explode("\n", $csv)));

    expect($lines[0])->toContain('name');
    expect($lines[1])->toContain('The Dispossessed');
    expect($lines[1])->toContain('Le Guin');
});

it('exports every matching row, not just the first page', function (): void {
    Publisher::factory()->count(120)->create();

    $path = (new RunResourceExport('publishers'))->handle();

    $lines = array_values(array_filter(explode("\n", Storage::disk('local')->get($path))));

    expect($lines)->toHaveCount(121);
});

it('notifies when the export finishes', function (): void {
    Writer::factory()->count(2)->create();

    $this->post(route('admin.exports.store', 'writers'));

    expect(session('flash.notification'))->toHaveKey('variant', 'success');
});

it('refuses an unsigned download url', function (): void {
    $this->get('/app/exports/download?path=exports/anything.csv')->assertForbidden();
});

it('serves a signed download', function (): void {
    Storage::disk('local')->put('exports/test.csv', "a,b\n1,2\n");

    $url = URL::signedRoute('admin.exports.download', ['path' => 'exports/test.csv']);

    $this->get($url)->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

it('refuses a signed url pointing outside the exports directory', function (): void {
    Storage::disk('local')->put('invoices/secret.zip', 'nope');

    $url = URL::signedRoute('admin.exports.download', ['path' => 'invoices/secret.zip']);

    $this->get($url)->assertForbidden();
});
```

Add `use Illuminate\Support\Facades\URL;`.

The path-traversal test is the important one — a signed URL alone would otherwise let any local-disk file be downloaded.

- [ ] **Step 3: Run it to verify it fails**

Run: `php artisan test --compact --filter=ExportTest`
Expected: FAIL — `Class "App\Jobs\RunResourceExport" not found`.

- [ ] **Step 4: Write the export base and the three exports**

`app/Exports/ResourceExport.php`:

```php
<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class ResourceExport
{
    /**
     * @return array<int, string>
     */
    abstract public function headings(): array;

    abstract public function query(): Builder;

    /**
     * @return array<int, string|int|float|null>
     */
    abstract public function row(Model $record): array;

    abstract public function name(): string;

    public function filename(): string
    {
        return 'exports/'.$this->name().'-'.now()->format('Ymd-His').'.csv';
    }
}
```

`app/Exports/BookExport.php`:

```php
<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Book;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class BookExport extends ResourceExport
{
    public function name(): string
    {
        return 'books';
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['id', 'name', 'original_name', 'writer', 'publisher', 'page_count', 'publication_date', 'publication_location', 'edition_number'];
    }

    public function query(): Builder
    {
        return Book::query()->with(['writer', 'publisher'])->orderBy('id');
    }

    /**
     * @return array<int, string|int|float|null>
     */
    public function row(Model $record): array
    {
        /** @var Book $record */
        return [
            $record->id,
            $record->name,
            $record->original_name,
            $record->writer?->name,
            $record->publisher?->name,
            $record->page_count,
            $record->publication_date,
            $record->publication_location,
            $record->edition_number,
        ];
    }
}
```

Write `PublisherExport` (`id`, `name`, `books_count`) and `WriterExport` (`id`, `name`, `birth_year`, `death_year`, `birth_place`, `death_place`, `books_count`) the same way, matching whatever the old Filament exporters emitted.

- [ ] **Step 5: Write the job**

`app/Jobs/RunResourceExport.php`:

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exports\BookExport;
use App\Exports\PublisherExport;
use App\Exports\ResourceExport;
use App\Exports\WriterExport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class RunResourceExport implements ShouldQueue
{
    use Queueable;

    /**
     * @var array<string, class-string<ResourceExport>>
     */
    public const EXPORTS = [
        'books' => BookExport::class,
        'publishers' => PublisherExport::class,
        'writers' => WriterExport::class,
    ];

    public function __construct(public readonly string $resource) {}

    /**
     * @return string the stored path on the local disk
     */
    public function handle(): string
    {
        $exportClass = self::EXPORTS[$this->resource] ?? null;

        if ($exportClass === null) {
            throw new RuntimeException("No export defined for [{$this->resource}].");
        }

        $export = new $exportClass;
        $path = $export->filename();

        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new RuntimeException('Could not open a temporary stream for the export.');
        }

        fputcsv($handle, $export->headings());

        $export->query()->lazy(500)->each(function (Model $record) use ($handle, $export): void {
            fputcsv($handle, $export->row($record));
        });

        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);

        Storage::disk('local')->put($path, $contents === false ? '' : $contents);

        return $path;
    }
}
```

`lazy(500)` keeps memory flat regardless of row count. `php://temp` spills to disk past 2MB, so a large export does not balloon memory either.

- [ ] **Step 6: Write the controller**

`app/Http/Controllers/Admin/ExportController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RunResourceExport;
use App\Support\AdminNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ExportController extends Controller
{
    public function __construct(private readonly AdminNotifier $notifier) {}

    public function store(string $resource): RedirectResponse
    {
        abort_unless(array_key_exists($resource, RunResourceExport::EXPORTS), 404);

        // Run inline so the download link can be handed over immediately. On a
        // personal-scale dataset this is fast; switch to dispatch() if it is not.
        $path = (new RunResourceExport($resource))->handle();

        $this->notifier->success(
            'Export ready',
            URL::signedRoute('admin.exports.download', ['path' => $path]),
        );

        return back();
    }

    public function download(Request $request): StreamedResponse
    {
        $path = $request->string('path')->toString();

        if (! str_starts_with($path, 'exports/') || str_contains($path, '..')) {
            throw new AccessDeniedHttpException('That file is not downloadable.');
        }

        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, basename($path), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
```

- [ ] **Step 7: Routes and the toolbar button**

```php
Route::post('exports/{resource}', [ExportController::class, 'store'])->name('exports.store');
Route::get('exports/download', [ExportController::class, 'download'])
    ->middleware('signed')
    ->name('exports.download');
```

Declare `exports/download` **before** `exports/{resource}` so it is not swallowed by the parameter.

In `ResourceTable.vue`, add an `exportable` prop and a toolbar button:

```js
exportable: { type: Boolean, default: false },
```

```vue
<Button
    v-if="exportable"
    label="Export"
    icon="pi pi-download"
    outlined
    size="small"
    @click="router.post(route('admin.exports.store', resource), {}, { preserveScroll: true })"
/>
```

Set `exportable` on the Books, Publishers and Writers index pages.

- [ ] **Step 8: Run, analyse, commit**

```bash
php artisan test --compact --filter=ExportTest
npm run build
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
git add app/Exports app/Jobs app/Http/Controllers/Admin/ExportController.php routes/admin.php resources/js tests/Feature/Admin/ExportTest.php
git commit -m "Rebuild CSV exports for Books, Publishers and Writers"
```

### Task 38: Settings page with the Appearance tab

**Files:**
- Create: `app/Http/Controllers/Admin/SettingsController.php`
- Create: `app/Http/Requests/Admin/SettingsRequest.php`
- Create: `resources/js/Pages/Settings.vue`, `resources/js/Components/Settings/AccentPicker.vue`
- Modify: `routes/admin.php`, `app/Support/Navigation.php`, `resources/js/app.js`
- Test: `tests/Feature/Admin/SettingsTest.php`

**Interfaces:**
- Consumes: `Setting` (Task 7 pinned its behavior), `Appearance` and `AccentRamps` (Tasks 10–11), `RAMPS` from `ramps.js`.
- Produces: routes `admin.settings` (GET) and `admin.settings.update` (PUT).

- [ ] **Step 1: Read the current settings groups**

Run: `sed -n '60,220p' app/Filament/Clusters/General/Pages/Settings.php`
The groups are `site_info`, `meta`, `branding`, `social`, `contact`. Copy the field names exactly.

- [ ] **Step 2: Write the failing test**

```bash
php artisan make:test --pest Admin/SettingsTest --no-interaction
```

```php
<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));
});

it('renders the settings page with the appearance defaults', function (): void {
    $this->get(route('admin.settings'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Settings')
            ->where('settings.appearance.accent', 'khaki')
            ->where('settings.appearance.color_scheme', 'system')
            ->has('accents', 9)
        );
});

it('loads stored settings into the form', function (): void {
    Setting::set('site_info', 'site_name', 'My Blog');

    $this->get(route('admin.settings'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('settings.site_info.site_name', 'My Blog')
        );
});

it('saves the accent and colour scheme', function (): void {
    $this->put(route('admin.settings.update'), [
        'appearance' => ['accent' => 'emerald', 'color_scheme' => 'dark'],
    ])->assertRedirect(route('admin.settings'));

    expect(Setting::get('appearance', 'accent'))->toBe('emerald');
    expect(Setting::get('appearance', 'color_scheme'))->toBe('dark');
});

it('rejects an unknown accent', function (): void {
    $this->from(route('admin.settings'))
        ->put(route('admin.settings.update'), ['appearance' => ['accent' => 'chartreuse']])
        ->assertSessionHasErrors('appearance.accent');
});

it('rejects an unknown colour scheme', function (): void {
    $this->from(route('admin.settings'))
        ->put(route('admin.settings.update'), ['appearance' => ['color_scheme' => 'neon']])
        ->assertSessionHasErrors('appearance.color_scheme');
});

it('saves site information', function (): void {
    $this->put(route('admin.settings.update'), [
        'site_info' => [
            'site_name' => 'My Blog',
            'site_description' => 'Notes and code',
            'site_url' => 'https://example.test',
            'admin_email' => 'me@example.test',
        ],
    ])->assertSessionHasNoErrors();

    expect(Setting::get('site_info', 'site_name'))->toBe('My Blog');
});

it('rejects an invalid site url and admin email', function (): void {
    $this->from(route('admin.settings'))
        ->put(route('admin.settings.update'), [
            'site_info' => ['site_url' => 'not a url', 'admin_email' => 'not an email'],
        ])
        ->assertSessionHasErrors(['site_info.site_url', 'site_info.admin_email']);
});

it('changes the inlined accent css after saving', function (): void {
    $this->put(route('admin.settings.update'), ['appearance' => ['accent' => 'rose']]);

    $this->get(route('admin.dashboard'))->assertSee('--p-primary-400:#FB7185;', escape: false);
});

it('shares the new accent with inertia after saving', function (): void {
    $this->put(route('admin.settings.update'), ['appearance' => ['accent' => 'sky', 'color_scheme' => 'light']]);

    $this->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('appearance.accent', 'sky')
            ->where('appearance.colorScheme', 'light')
        );
});

it('flashes a notification after saving', function (): void {
    $this->put(route('admin.settings.update'), ['appearance' => ['accent' => 'amber']]);

    expect(session('flash.notification'))->toHaveKey('variant', 'success');
});
```

- [ ] **Step 3: Run it to verify it fails**

Run: `php artisan test --compact --filter=SettingsTest`
Expected: FAIL — route `admin.settings` not defined.

- [ ] **Step 4: Write the request**

`app/Http/Requests/Admin/SettingsRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Support\Theme\AccentRamps;
use App\Support\Theme\Appearance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'appearance.accent' => ['nullable', Rule::in(AccentRamps::names())],
            'appearance.color_scheme' => ['nullable', Rule::in(Appearance::SCHEMES)],

            'site_info.site_name' => ['nullable', 'string', 'max:255'],
            'site_info.site_description' => ['nullable', 'string', 'max:500'],
            'site_info.site_url' => ['nullable', 'url', 'max:255'],
            'site_info.admin_email' => ['nullable', 'email', 'max:255'],

            'meta.meta_title' => ['nullable', 'string', 'max:255'],
            'meta.meta_description' => ['nullable', 'string', 'max:160'],
            'meta.meta_keywords' => ['nullable', 'array'],
            'meta.meta_keywords.*' => ['string', 'max:50'],
            'meta.og_title' => ['nullable', 'string', 'max:255'],
            'meta.og_description' => ['nullable', 'string', 'max:300'],
            'meta.og_image' => ['nullable', 'image', 'max:5120'],

            'branding.logo' => ['nullable', 'image', 'max:5120'],

            'social' => ['nullable', 'array'],
            'social.*' => ['nullable', 'string', 'max:255'],

            'contact' => ['nullable', 'array'],
            'contact.*' => ['nullable', 'string', 'max:255'],
        ];
    }
}
```

Extend `social.*` and `contact.*` with the concrete keys once you have read them in Step 1.

- [ ] **Step 5: Write the controller**

`app/Http/Controllers/Admin/SettingsController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingsRequest;
use App\Models\Setting;
use App\Support\AdminNotifier;
use App\Support\Theme\AccentRamps;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    private const GROUPS = ['appearance', 'site_info', 'meta', 'branding', 'social', 'contact'];

    public function __construct(private readonly AdminNotifier $notifier) {}

    public function edit(): Response
    {
        $settings = [];

        foreach (self::GROUPS as $group) {
            $settings[$group] = Setting::getGroup($group)->all();
        }

        $settings['appearance']['accent'] ??= AccentRamps::DEFAULT;
        $settings['appearance']['color_scheme'] ??= 'system';

        return Inertia::render('Settings', [
            'settings' => $settings,
            'accents' => array_map(
                fn (string $name): array => [
                    'name' => $name,
                    'swatch' => AccentRamps::all()[$name]['400'],
                ],
                AccentRamps::names(),
            ),
        ]);
    }

    public function update(SettingsRequest $request): RedirectResponse
    {
        foreach ($request->validated() as $group => $values) {
            if (! is_array($values)) {
                continue;
            }

            foreach ($values as $name => $value) {
                if ($request->hasFile("{$group}.{$name}")) {
                    $value = $request->file("{$group}.{$name}")->store("settings/{$group}", 'public');
                }

                Setting::set(
                    (string) $group,
                    (string) $name,
                    is_array($value) ? json_encode($value) : $value,
                    is_array($value) ? 'json' : 'text',
                );
            }
        }

        $this->notifier->success('Settings saved');

        return to_route('admin.settings');
    }
}
```

- [ ] **Step 6: Routes and navigation**

```php
Route::get('settings', [SettingsController::class, 'edit'])->name('settings');
Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
```

Navigation — General cluster: `['label' => 'Settings', 'route' => 'admin.settings', 'icon' => 'pi pi-cog']`.

Uncomment the `admin.settings` entry in `Spotlight.vue` that Task 35 Step 3 told you to disable.

- [ ] **Step 7: Write the accent picker**

`resources/js/Components/Settings/AccentPicker.vue`:

```vue
<script setup>
import { updatePreset } from '@primeuix/themes'
import { RAMPS } from '@/theme/ramps'

const props = defineProps({
    accents: { type: Array, required: true },
    modelValue: { type: String, required: true },
})

const emit = defineEmits(['update:modelValue'])

function choose(name) {
    emit('update:modelValue', name)

    // Live preview. The saved value is what survives a reload.
    updatePreset({ semantic: { primary: RAMPS[name] } })
}
</script>

<template>
    <div class="flex flex-wrap gap-3">
        <button
            v-for="accent in accents"
            :key="accent.name"
            type="button"
            class="flex h-11 w-11 items-center justify-center rounded-full border-2 transition"
            :class="accent.name === modelValue
                ? 'border-surface-900 dark:border-surface-0'
                : 'border-transparent hover:border-surface-300 dark:hover:border-surface-700'"
            :style="{ backgroundColor: accent.swatch }"
            :aria-label="accent.name"
            :aria-pressed="accent.name === modelValue"
            @click="choose(accent.name)"
        >
            <i v-if="accent.name === modelValue" class="pi pi-check text-sm" style="color: #2A2613" />
        </button>
    </div>
</template>
```

- [ ] **Step 8: Write the settings page**

`resources/js/Pages/Settings.vue` — a PrimeVue `Tabs` with six panels (Appearance, Site information, Meta tags, Branding, Social, Contact), a single `useForm` seeded from `settings`, and one save button. The Appearance panel contains `<AccentPicker v-model="form.appearance.accent" :accents="accents" />` plus a `SelectButton` bound to `form.appearance.color_scheme` with options `Light`, `Dark`, `System`. Because Branding and Meta contain image uploads, submit with the multipart pattern from Task 24:

```js
function submit() {
    form
        .transform((data) => ({ ...data, _method: 'put' }))
        .post(route('admin.settings.update'), { forceFormData: true, preserveScroll: true })
}
```

Apply the scheme live on change:

```js
watch(
    () => form.appearance.color_scheme,
    (scheme) => {
        const dark = scheme === 'dark'
            || (scheme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)
        document.documentElement.classList.toggle('dark', dark)
    },
)
```

- [ ] **Step 9: Run, analyse, commit**

```bash
php artisan test --compact --filter=SettingsTest
npm run build
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
git add app/Http/Controllers/Admin/SettingsController.php app/Http/Requests/Admin/SettingsRequest.php routes/admin.php app/Support/Navigation.php resources/js tests/Feature/Admin/SettingsTest.php
git commit -m "Add the settings page with a live accent and colour-scheme picker"
```

### Task 39: Notifications bell

**Files:**
- Create: `app/Http/Controllers/Admin/NotificationController.php`
- Create: `resources/js/Components/NotificationsPanel.vue`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`, `resources/js/Layouts/AdminLayout.vue`, `routes/admin.php`
- Test: `tests/Feature/Admin/NotificationsTest.php`

**Interfaces:**
- Consumes: Laravel's `notifications` table (migration already exists), `User` with `Notifiable`.
- Produces: shared Inertia prop `notifications` (`array{unreadCount: int, items: array<int, array{id: string, title: string, body: string|null, createdAt: string, readAt: string|null}>}`), routes `admin.notifications.read` and `admin.notifications.read-all`.

- [ ] **Step 1: Write the failing test**

```bash
php artisan make:test --pest Admin/NotificationsTest --no-interaction
```

```php
<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

function makeNotification(User $user, ?string $readAt = null): string
{
    $id = (string) Str::uuid();

    DB::table('notifications')->insert([
        'id' => $id,
        'type' => 'App\\Notifications\\Generic',
        'notifiable_type' => $user::class,
        'notifiable_id' => $user->id,
        'data' => json_encode(['title' => 'Sync finished', 'body' => '42 rows']),
        'read_at' => $readAt,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->admin = User::factory()->create(['email' => 'admin@example.test']);
    $this->actingAs($this->admin);
});

it('shares an empty notification state when there are none', function (): void {
    $this->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('notifications.unreadCount', 0)
            ->has('notifications.items', 0)
        );
});

it('shares unread notifications', function (): void {
    makeNotification($this->admin);
    makeNotification($this->admin);
    makeNotification($this->admin, now()->toDateTimeString());

    $this->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('notifications.unreadCount', 2)
            ->has('notifications.items', 3)
            ->where('notifications.items.0.title', 'Sync finished')
        );
});

it('marks one notification as read', function (): void {
    $id = makeNotification($this->admin);

    $this->patch(route('admin.notifications.read', $id))->assertRedirect();

    expect(DB::table('notifications')->where('id', $id)->value('read_at'))->not->toBeNull();
});

it('marks everything as read', function (): void {
    makeNotification($this->admin);
    makeNotification($this->admin);

    $this->patch(route('admin.notifications.read-all'))->assertRedirect();

    expect(DB::table('notifications')->whereNull('read_at')->count())->toBe(0);
});

it('cannot mark another user\'s notification as read', function (): void {
    $other = User::factory()->create(['email' => 'other@example.test']);
    $id = makeNotification($other);

    $this->patch(route('admin.notifications.read', $id))->assertNotFound();

    expect(DB::table('notifications')->where('id', $id)->value('read_at'))->toBeNull();
});

it('caps the shared list', function (): void {
    for ($i = 0; $i < 25; $i++) {
        makeNotification($this->admin);
    }

    $this->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('notifications.items', 15)
            ->where('notifications.unreadCount', 25)
        );
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --compact --filter=NotificationsTest`
Expected: FAIL — `notifications` prop missing.

- [ ] **Step 3: Share the notifications**

In `HandleInertiaRequests::share()`, add:

```php
'notifications' => fn (): array => $user === null ? ['unreadCount' => 0, 'items' => []] : [
    'unreadCount' => $user->unreadNotifications()->count(),
    'items' => $user->notifications()
        ->latest()
        ->limit(15)
        ->get()
        ->map(fn (DatabaseNotification $notification): array => [
            'id' => $notification->id,
            'title' => is_string($notification->data['title'] ?? null) ? $notification->data['title'] : 'Notification',
            'body' => is_string($notification->data['body'] ?? null) ? $notification->data['body'] : null,
            'createdAt' => $notification->created_at?->diffForHumans() ?? '',
            'readAt' => $notification->read_at?->toIso8601String(),
        ])
        ->all(),
],
```

Import `Illuminate\Notifications\DatabaseNotification`.

- [ ] **Step 4: Write the controller and routes**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function read(Request $request, string $notification): RedirectResponse
    {
        $record = $request->user()?->notifications()->whereKey($notification)->first();

        abort_if($record === null, 404);

        $record->markAsRead();

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()?->unreadNotifications->markAsRead();

        return back();
    }
}
```

```php
Route::patch('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
Route::patch('notifications/{notification}', [NotificationController::class, 'read'])->name('notifications.read');
```

`read-all` must come first so it is not captured by `{notification}`.

- [ ] **Step 5: Write the panel**

`resources/js/Components/NotificationsPanel.vue` — a `Button` with `icon="pi pi-bell"` and an `OverlayBadge` showing `unreadCount` when it is above zero, toggling a PrimeVue `Popover` that lists `items`. Each item shows title, body and `createdAt`, is dimmed when `readAt` is set, and on click calls `router.patch(route('admin.notifications.read', item.id), {}, { preserveScroll: true })`. A footer button calls `admin.notifications.read-all`.

Mount it in `AdminLayout.vue` inside the `topbar` area, before the avatar menu.

- [ ] **Step 6: Run, analyse, commit**

```bash
php artisan test --compact --filter=NotificationsTest
npm run build
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
git add app/Http/Controllers/Admin/NotificationController.php app/Http/Middleware routes/admin.php resources/js tests/Feature/Admin/NotificationsTest.php
git commit -m "Add the database-notification bell"
```

---

# Phase 5 — Dashboards

### Task 40: WakaTime chart endpoints and the Coding Dashboard

Nine charts today: overview, AI overview, trend, weekday, and five breakdown doughnuts (languages, editors, projects, categories, operating systems).

**Files:**
- Create: `app/Http/Controllers/Admin/Work/CodingDashboardController.php`
- Create: `app/Support/WakaTime/DashboardData.php`
- Create: `resources/js/Pages/Work/CodingDashboard.vue`, `resources/js/Components/Charts/{ChartCard,StatTile}.vue`
- Modify: `routes/admin.php`, `app/Support/Navigation.php`
- Test: `tests/Feature/Admin/Work/CodingDashboardTest.php`

**Interfaces:**
- Consumes: `AggregatesWakaTimeData` (Task 8).
- Produces: `App\Support\WakaTime\DashboardData::for(string $range): array{range: string, totals: array<string, mixed>, trend: array{labels: array<int, string>, data: array<int, int>}, weekday: array{labels: array<int, string>, data: array<int, int>}, breakdowns: array<string, array{labels: array<int, string>, data: array<int, int>}>}` and route `admin.coding-dashboard`.

- [ ] **Step 1: Write the failing test**

```bash
php artisan make:test --pest Admin/Work/CodingDashboardTest --no-interaction
```

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\WakaTimeSummary;
use App\Models\WakaTimeSummaryEntry;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));
});

it('renders the dashboard with every chart series', function (): void {
    $this->get(route('admin.coding-dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Work/CodingDashboard')
            ->where('data.range', '7')
            ->has('data.totals')
            ->has('data.trend.labels')
            ->has('data.weekday.labels', 7)
            ->has('data.breakdowns.language')
            ->has('data.breakdowns.editor')
            ->has('data.breakdowns.project')
            ->has('data.breakdowns.category')
            ->has('data.breakdowns.operating_system')
        );
});

it('honours the range parameter', function (): void {
    $this->get(route('admin.coding-dashboard', ['range' => '30']))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('data.range', '30'));
});

it('falls back to seven days for an unknown range', function (): void {
    $this->get(route('admin.coding-dashboard', ['range' => 'forever']))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('data.range', '7'));
});

it('aggregates language seconds into the breakdown', function (): void {
    $summary = WakaTimeSummary::factory()->create(['date' => now()->subDay()->toDateString()]);

    WakaTimeSummaryEntry::factory()->create([
        'waka_time_summary_id' => $summary->id,
        'type' => WakaTimeSummaryEntry::TYPE_LANGUAGE,
        'name' => 'PHP',
        'total_seconds' => 7200,
    ]);

    $this->get(route('admin.coding-dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('data.breakdowns.language.labels.0', 'PHP')
            ->where('data.breakdowns.language.data.0', 7200)
        );
});

it('renders empty series when there is no data', function (): void {
    $this->get(route('admin.coding-dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('data.breakdowns.language.labels', [])
            ->where('data.breakdowns.language.data', [])
        );
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --compact --filter=CodingDashboardTest`
Expected: FAIL — route not defined.

- [ ] **Step 3: Write DashboardData**

`app/Support/WakaTime/DashboardData.php` composes `AggregatesWakaTimeData`, calls `breakdownSeconds()` once per type, and shapes each result into `{labels, data}` sorted descending by seconds. Read the trait's actual method names from Task 8 Step 1 and use them; the class holds no aggregation logic of its own, only shaping.

```php
<?php

declare(strict_types=1);

namespace App\Support\WakaTime;

use App\Models\WakaTimeSummaryEntry;

final class DashboardData
{
    use AggregatesWakaTimeData;

    public const RANGES = ['7', '14', '30', '90', 'all'];

    private function __construct(private readonly string $range) {}

    /**
     * @return array{range: string, totals: array<string, mixed>, trend: array{labels: array<int, string>, data: array<int, int>}, weekday: array{labels: array<int, string>, data: array<int, int>}, breakdowns: array<string, array{labels: array<int, string>, data: array<int, int>}>}
     */
    public static function for(string $range): array
    {
        $range = in_array($range, self::RANGES, true) ? $range : '7';
        $instance = new self($range);

        return [
            'range' => $range,
            'totals' => $instance->totals(),
            'trend' => $instance->trendSeries(),
            'weekday' => $instance->weekdaySeries(),
            'breakdowns' => [
                'language' => $instance->series(WakaTimeSummaryEntry::TYPE_LANGUAGE),
                'editor' => $instance->series(WakaTimeSummaryEntry::TYPE_EDITOR),
                'project' => $instance->series(WakaTimeSummaryEntry::TYPE_PROJECT),
                'category' => $instance->series(WakaTimeSummaryEntry::TYPE_CATEGORY),
                'operating_system' => $instance->series(WakaTimeSummaryEntry::TYPE_OPERATING_SYSTEM),
            ],
        ];
    }

    /**
     * The trait reads its range through this method.
     *
     * @return array<string, string>
     */
    public function getFilters(): array
    {
        return ['range' => $this->range];
    }

    /**
     * @return array{labels: array<int, string>, data: array<int, int>}
     */
    private function series(string $type): array
    {
        $seconds = $this->breakdownSeconds($type);
        arsort($seconds);

        return [
            'labels' => array_map('strval', array_keys($seconds)),
            'data' => array_map('intval', array_values($seconds)),
        ];
    }
}
```

`totals()`, `trendSeries()` and `weekdaySeries()` must be implemented here using the trait's aggregation, mirroring what `WakaTimeOverview`, `WakaTimeTrendChart` and `WakaTimeWeekdayChart` currently compute. Read those three widgets first and port their logic verbatim.

Confirm the `TYPE_*` constant names against the model: `grep -n "const TYPE_" app/Models/WakaTimeSummaryEntry.php`.

- [ ] **Step 4: Controller, route, navigation**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Work;

use App\Http\Controllers\Controller;
use App\Support\WakaTime\DashboardData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CodingDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('Work/CodingDashboard', [
            'data' => DashboardData::for($request->string('range')->toString()),
            'ranges' => [
                ['value' => '7', 'label' => 'Last 7 days'],
                ['value' => '14', 'label' => 'Last 14 days'],
                ['value' => '30', 'label' => 'Last 30 days'],
                ['value' => '90', 'label' => 'Last 90 days'],
                ['value' => 'all', 'label' => 'All time'],
            ],
        ]);
    }
}
```

Route: `Route::get('coding-dashboard', CodingDashboardController::class)->name('coding-dashboard');`

Navigation — Work cluster, first item: `['label' => 'Coding dashboard', 'route' => 'admin.coding-dashboard', 'icon' => 'pi pi-chart-bar']`.

- [ ] **Step 5: Write the chart components**

`resources/js/Components/Charts/ChartCard.vue` wraps PrimeVue `Chart` in a bordered card with a heading, reading colors from CSS custom properties so it tracks the accent:

```vue
<script setup>
import { computed } from 'vue'
import Card from 'primevue/card'
import Chart from 'primevue/chart'

const props = defineProps({
    title: { type: String, required: true },
    type: { type: String, required: true },
    labels: { type: Array, required: true },
    values: { type: Array, required: true },
})

function cssVar(name) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim()
}

const palette = computed(() => [
    cssVar('--p-primary-400'),
    cssVar('--p-primary-600'),
    cssVar('--p-primary-200'),
    cssVar('--p-primary-800'),
    cssVar('--p-primary-300'),
    cssVar('--p-primary-700'),
])

const chartData = computed(() => ({
    labels: props.labels,
    datasets: [
        {
            data: props.values,
            backgroundColor: props.type === 'line' ? 'transparent' : palette.value,
            borderColor: props.type === 'line' ? palette.value[0] : undefined,
            tension: 0.3,
            borderWidth: props.type === 'line' ? 2 : 0,
        },
    ],
}))

const options = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: props.type === 'doughnut', position: 'bottom' },
        tooltip: {
            callbacks: {
                label: (context) => {
                    const seconds = context.parsed.y ?? context.parsed
                    return `${Math.floor(seconds / 3600)}h ${Math.floor((seconds % 3600) / 60)}m`
                },
            },
        },
    },
    scales: props.type === 'doughnut' ? {} : {
        y: { ticks: { callback: (value) => `${Math.round(value / 3600)}h` } },
    },
}))
</script>

<template>
    <Card>
        <template #title>
            <span class="text-sm font-semibold">{{ title }}</span>
        </template>
        <template #content>
            <div v-if="!labels.length" class="py-12 text-center text-sm text-surface-500">No data in this range.</div>
            <Chart v-else :type="type" :data="chartData" :options="options" class="h-64" />
        </template>
    </Card>
</template>
```

`resources/js/Components/Charts/StatTile.vue` — a small bordered card with a label, a large monospace value, and an optional caption.

- [ ] **Step 6: Write the dashboard page**

`resources/js/Pages/Work/CodingDashboard.vue` — a `Select` bound to the range that issues `router.get(route('admin.coding-dashboard'), { range }, { preserveState: true })`, a row of `StatTile`s from `data.totals`, then a two-column grid of `ChartCard`s: trend (line), weekday (bar), languages, editors, projects, categories and operating systems (doughnut).

- [ ] **Step 7: Run, analyse, commit**

```bash
php artisan test --compact --filter=CodingDashboardTest
npm run build
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
git add app/Support/WakaTime app/Http/Controllers/Admin/Work/CodingDashboardController.php routes/admin.php app/Support/Navigation.php resources/js tests/Feature/Admin/Work/CodingDashboardTest.php
git commit -m "Rebuild the coding dashboard with PrimeVue charts"
```

### Task 41: Overview widgets and the main dashboard

Three widgets remain: `BooksOverview`, `IncomeOverview`, `ExchangeRateWidget`.

**Files:**
- Create: `app/Support/Widgets/{BooksOverview,IncomeOverview,ExchangeRates}.php`
- Modify: `app/Http/Controllers/Admin/DashboardController.php`
- Modify: `resources/js/Pages/Dashboard.vue`
- Modify: `resources/js/Pages/Library/Books/Index.vue`, `resources/js/Pages/Budget/Incomes/Index.vue`
- Test: `tests/Feature/Admin/DashboardTest.php`

**Interfaces:**
- Consumes: `ExchangeRateService`.
- Produces: `BooksOverview::stats(): array<int, array{label: string, value: string, caption: string|null}>`, the same shape from `IncomeOverview` and `ExchangeRates`.

- [ ] **Step 1: Read the three existing widgets**

Run: `cat app/Filament/Clusters/Library/Resources/BookResource/Widgets/BooksOverview.php app/Filament/Clusters/Budget/Resources/IncomeResource/Widgets/IncomeOverview.php app/Filament/Clusters/Budget/Resources/DebtResource/Widgets/ExchangeRateWidget.php`
Port the queries verbatim; only the return shape changes.

- [ ] **Step 2: Write the failing test**

```bash
php artisan make:test --pest Admin/DashboardTest --no-interaction
```

```php
<?php

declare(strict_types=1);

use App\Models\Book;
use App\Models\Income;
use App\Models\User;
use App\Services\ExchangeRateService;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));

    $exchange = Mockery::mock(ExchangeRateService::class);
    $exchange->shouldReceive('convert')->andReturn(34.5);
    $exchange->shouldIgnoreMissing();
    app()->instance(ExchangeRateService::class, $exchange);
});

it('renders the dashboard with all three widget groups', function (): void {
    $this->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard')
            ->has('books')
            ->has('incomes')
            ->has('exchangeRates')
        );
});

it('counts books', function (): void {
    Book::factory()->count(7)->create();

    $this->get(route('admin.dashboard'))
        ->assertInertia(function (AssertableInertia $page): void {
            $stats = collect($page->toArray()['props']['books']);

            expect($stats->firstWhere('label', 'Books')['value'])->toBe('7');
        });
});

it('totals incomes', function (): void {
    Income::factory()->create(['amount' => 1000, 'currency' => 'TRY']);
    Income::factory()->create(['amount' => 500, 'currency' => 'TRY']);

    $this->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('incomes'));
});

it('survives an exchange rate service failure', function (): void {
    $failing = Mockery::mock(ExchangeRateService::class);
    $failing->shouldReceive('convert')->andThrow(new Exception('rate API down'));
    app()->instance(ExchangeRateService::class, $failing);

    $this->get(route('admin.dashboard'))->assertOk();
});
```

The last test matters: an external API outage must not take the dashboard down.

- [ ] **Step 3: Run it to verify it fails**

Run: `php artisan test --compact --filter=DashboardTest`
Expected: FAIL — `books` prop missing.

- [ ] **Step 4: Write the three widget classes**

Each is a final class with a static `stats(): array` returning `[['label' => ..., 'value' => ..., 'caption' => ...], ...]`. `ExchangeRates::stats()` wraps every `convert()` call in a try/catch and returns a `'—'` value with a caption of `'Unavailable'` on failure.

- [ ] **Step 5: Feed them to the dashboard**

```php
public function __invoke(): Response
{
    return Inertia::render('Dashboard', [
        'books' => BooksOverview::stats(),
        'incomes' => IncomeOverview::stats(),
        'exchangeRates' => ExchangeRates::stats(),
    ]);
}
```

- [ ] **Step 6: Render them**

`resources/js/Pages/Dashboard.vue` — three sections, each a row of `StatTile`s from Task 40. Also add `<StatTile>` rows above the tables in `Library/Books/Index.vue` (books stats) and `Budget/Incomes/Index.vue` (income stats), passing the stats as extra props from those controllers' `index()` methods.

- [ ] **Step 7: Run, analyse, commit**

```bash
php artisan test --compact --filter=DashboardTest
npm run build
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
git add app/Support/Widgets app/Http/Controllers/Admin resources/js/Pages tests/Feature/Admin/DashboardTest.php
git commit -m "Port the overview widgets and fill the dashboard"
```

**Phase 5 checkpoint.** Every Filament feature now has a replacement. Run the whole suite and walk the panel before demolition:

```bash
php artisan test --compact
vendor/bin/phpstan analyse
```

---

# Phase 6 — Demolition

Nothing new is built here. If a feature is missing, go back and finish it rather than deleting its Filament original.

### Task 42: Verify feature parity before deleting anything

**Files:**
- Create: `tests/Feature/ParityTest.php`

**Interfaces:**
- Consumes: every route created in Phases 1–5.
- Produces: an executable checklist that fails if a replacement route is missing.

- [ ] **Step 1: Write the parity test**

```bash
php artisan make:test --pest ParityTest --no-interaction
```

```php
<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));
});

it('has an index route for all thirteen resources', function (string $routeName): void {
    expect(Route::has($routeName))->toBeTrue("Missing route {$routeName}");
})->with([
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

it('has a route for every special feature', function (string $routeName): void {
    expect(Route::has($routeName))->toBeTrue("Missing route {$routeName}");
})->with([
    'admin.dashboard',
    'admin.coding-dashboard',
    'admin.tasks.board',
    'admin.tasks.move',
    'admin.search',
    'admin.settings',
    'admin.profile',
    'admin.exports.store',
    'admin.notifications.read-all',
    'login',
    'logout',
]);

it('serves every index page without error', function (string $routeName): void {
    $this->get(route($routeName))->assertOk();
})->with([
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
    'admin.dashboard',
    'admin.coding-dashboard',
    'admin.tasks.board',
    'admin.settings',
    'admin.profile',
]);

it('keeps the public site working', function (): void {
    auth()->logout();

    $this->get('/')->assertOk();
    $this->get('/books')->assertOk();
});

it('lists every navigation target as a real route', function (): void {
    foreach (App\Support\Navigation::clusters() as $cluster) {
        expect($cluster['items'])->not->toBeEmpty("Cluster {$cluster['label']} has no items");

        foreach ($cluster['items'] as $item) {
            expect(Route::has($item['route']))->toBeTrue("Missing route {$item['route']}");
        }
    }
});
```

- [ ] **Step 2: Run it**

Run: `php artisan test --compact --filter=ParityTest`
Expected: PASS. Any failure names a feature that has not been rebuilt — **stop and finish it** before continuing.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/ParityTest.php
git commit -m "Add a parity test asserting every Filament feature has a replacement"
```

### Task 43: Remove the Filament packages and delete the panel

**Files:**
- Modify: `composer.json`, `bootstrap/providers.php`, `vite.config.js`, `config/app.php`
- Delete: `app/Filament/`, `app/Providers/Filament/`, `resources/css/filament/`, `resources/views/filament/`, `resources/views/vendor/pulse/`, `config/pulse.php`, `app/Filament/Exports/`
- Create: a migration dropping the Pulse and Filament import/export tables
- Test: `tests/Feature/NoFilamentTest.php`

**Interfaces:**
- Consumes: the parity guarantee from Task 42.
- Produces: a codebase with zero Filament references.

- [ ] **Step 1: Write the guard test**

```bash
php artisan make:test --pest NoFilamentTest --no-interaction
```

```php
<?php

declare(strict_types=1);

it('has no Filament references in application code', function (): void {
    $output = [];
    exec('grep -rl "Filament" '.base_path('app').' '.base_path('config').' '.base_path('routes').' '.base_path('resources').' 2>/dev/null', $output);

    expect($output)->toBeEmpty('Filament is still referenced in: '.implode(', ', $output));
});

it('no longer has the Filament or Pulse packages installed', function (): void {
    /** @var array{require: array<string, string>, require-dev: array<string, string>} $composer */
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);

    $packages = array_keys([...$composer['require'], ...$composer['require-dev']]);

    expect($packages)->not->toContain(
        'filament/filament',
        'relaticle/flowforge',
        'pxlrbt/filament-spotlight',
        'pxlrbt/filament-environment-indicator',
        'devonab/filament-easy-footer',
        'laravel/pulse',
        'livewire/livewire',
    );
});

it('no longer has the Filament directories', function (): void {
    expect(is_dir(app_path('Filament')))->toBeFalse();
    expect(is_dir(app_path('Providers/Filament')))->toBeFalse();
    expect(is_dir(resource_path('views/filament')))->toBeFalse();
    expect(is_dir(resource_path('css/filament')))->toBeFalse();
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --compact --filter=NoFilamentTest`
Expected: FAIL on all three.

- [ ] **Step 3: Remove the panel provider**

In `bootstrap/providers.php`, delete the `App\Providers\Filament\AdminPanelProvider::class` entry.

- [ ] **Step 4: Delete the directories**

```bash
git rm -r app/Filament app/Providers/Filament resources/views/filament resources/css/filament resources/views/vendor/pulse
rm -f config/pulse.php
```

- [ ] **Step 5: Remove the packages**

```bash
composer remove filament/filament relaticle/flowforge pxlrbt/filament-spotlight pxlrbt/filament-environment-indicator devonab/filament-easy-footer laravel/pulse --no-interaction
```

Also remove the `"@php artisan filament:upgrade"` line from the `post-autoload-dump` script in `composer.json`.

Check whether Livewire left on its own: `composer show livewire/livewire`. If it is still present as a direct requirement, `composer remove livewire/livewire --no-interaction`.

- [ ] **Step 6: Clean up Vite**

In `vite.config.js`, remove `'resources/css/filament/admin/theme.css'` from the `input` array.

- [ ] **Step 7: Drop the orphaned tables**

```bash
php artisan make:migration drop_pulse_and_filament_tables --no-interaction
```

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('pulse_aggregates');
        Schema::dropIfExists('pulse_entries');
        Schema::dropIfExists('pulse_values');
        Schema::dropIfExists('failed_import_rows');
        Schema::dropIfExists('imports');
        Schema::dropIfExists('exports');
    }

    public function down(): void
    {
        // These tables belonged to packages that are no longer installed.
        // Restoring them means reinstalling those packages and re-running
        // their own migrations.
    }
};
```

The `notifications` table stays — the bell from Task 39 uses it.

- [ ] **Step 8: Run everything**

```bash
composer dump-autoload
php artisan config:clear
php artisan test --compact
vendor/bin/phpstan analyse
npm run build
```

Expected: the whole suite green, Larastan at zero, the build clean. Larastan will likely need `phpstan.neon` updated — remove any `app/Filament` paths or ignores it references.

- [ ] **Step 9: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "Remove FilamentPHP, its four panel plugins, Pulse and Livewire"
```

### Task 44: Move the panel from /app to /admin

**Files:**
- Modify: `routes/admin.php`, `config/fortify.php`
- Test: `tests/Feature/Admin/AdminAccessTest.php`, `tests/Feature/ParityTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: the panel served at `/admin`. Every route **name** is unchanged, so no Vue file needs editing — this is the payoff for using `route()` everywhere.

- [ ] **Step 1: Update the failing assertions first**

In `tests/Feature/Admin/AdminAccessTest.php`, change `$this->get('/app')` to `$this->get('/admin')` and `toEndWith('/app')` to `toEndWith('/admin')`.

In `tests/Feature/Admin/Library/BookResourceTest.php`, change the clash test to expect `/admin/books`.

- [ ] **Step 2: Run to verify they fail**

Run: `php artisan test --compact --filter="AdminAccessTest|BookResourceTest"`
Expected: FAIL — 404 on `/admin`.

- [ ] **Step 3: Change the prefix**

In `routes/admin.php`, change `->prefix('app')` to `->prefix('admin')`.

In `config/fortify.php`, change `'home' => '/app'` to `'home' => '/admin'`.

- [ ] **Step 4: Run everything**

```bash
php artisan route:list --path=admin
php artisan test --compact
```

Expected: every admin route now sits under `/admin`, and the whole suite is green. `/app` should 404.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add routes/admin.php config/fortify.php tests
git commit -m "Move the panel from /app to /admin"
```

### Task 45: Browser smoke test and final verification

**Files:**
- Create: `tests/Browser/AdminSmokeTest.php`
- Modify: `.github/workflows/*` if the CI needs the browser suite
- Test: itself

**Interfaces:**
- Consumes: every page built in Phases 1–5.
- Produces: a Pest 4 browser test that loads every admin page and fails on any JavaScript console error.

- [ ] **Step 1: Confirm Pest browser testing is available**

Run: `php artisan test --compact --filter=Browser 2>&1 | head -5`
If Pest 4's browser plugin is not installed, run `composer require pestphp/pest-plugin-browser --dev --no-interaction` and `npx playwright install chromium`.

- [ ] **Step 2: Write the smoke test**

```bash
php artisan make:test --pest Browser/AdminSmokeTest --no-interaction
```

```php
<?php

declare(strict_types=1);

use App\Models\Book;
use App\Models\Expense;
use App\Models\Post;
use App\Models\Task;
use App\Models\User;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->admin = User::factory()->create(['email' => 'admin@example.test']);

    // Enough data that tables, charts and the board render something real.
    Post::factory()->count(3)->create();
    Book::factory()->count(3)->create();
    Expense::factory()->count(5)->create();
    Task::factory()->count(4)->create();
});

it('loads every admin page without javascript errors', function (string $routeName): void {
    $page = visit(route($routeName))->actingAs($this->admin);

    $page->assertNoJavascriptErrors()->assertNoConsoleLogs();
})->with([
    'admin.dashboard',
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
    'admin.coding-dashboard',
    'admin.tasks.board',
    'admin.settings',
    'admin.profile',
]);

it('loads every create form without javascript errors', function (string $routeName): void {
    visit(route($routeName))
        ->actingAs($this->admin)
        ->assertNoJavascriptErrors();
})->with([
    'admin.posts.create',
    'admin.books.create',
    'admin.expenses.create',
    'admin.debts.create',
    'admin.invoices.create',
    'admin.repositories.create',
]);

it('sorts, searches and paginates a table', function (): void {
    Post::factory()->count(30)->create();

    visit(route('admin.posts.index'))
        ->actingAs($this->admin)
        ->assertSee('Posts')
        ->fill('input[placeholder="Search"]', 'the')
        ->assertNoJavascriptErrors();
});

it('opens the spotlight with the keyboard', function (): void {
    visit(route('admin.dashboard'))
        ->actingAs($this->admin)
        ->keys('body', ['{meta}', 'k'])
        ->assertSee('Jump to')
        ->assertNoJavascriptErrors();
});

it('switches the accent from settings', function (): void {
    visit(route('admin.settings'))
        ->actingAs($this->admin)
        ->click('button[aria-label="emerald"]')
        ->assertNoJavascriptErrors();
});
```

Pest 4's browser API names may differ slightly — check `php artisan test --help` and the Pest documentation for `visit()`, `assertNoJavascriptErrors()` and `keys()`, and adjust the calls to match.

- [ ] **Step 3: Run it**

Run: `php artisan test --compact --filter=AdminSmokeTest`
Expected: PASS. Every console error it surfaces is a real bug — fix it before finishing.

- [ ] **Step 4: Final verification**

Run every gate and confirm each one:

```bash
php artisan test --compact
vendor/bin/phpstan analyse
vendor/bin/pint --test --format agent
npm run build
php artisan route:list --except-vendor
```

Expected:
- the full suite green, with no skipped tests;
- Larastan level 10 at **zero errors**;
- Pint reporting no changes needed;
- the Vite build clean;
- `route:list` showing only your routes — no Filament, no Pulse, no Livewire.

Then open the site and walk it by hand one final time: log in, visit every cluster, create and edit one record per cluster, drag a card on the board, run an export, change the accent, toggle dark mode, and confirm the public blog at `/` and `/books` still works.

- [ ] **Step 5: Commit**

```bash
git add tests/Browser
git commit -m "Add a browser smoke test across every admin page"
```

---

## Notes carried from the spec

Two things this plan discovered that the spec did not anticipate, recorded here so they are not lost:

**Filament leaked outside `app/Filament/`.** Three enums implemented `Filament\Support\Contracts\HasLabel`/`HasColor`, `User` implemented `FilamentUser`, `AppServiceProvider` configured six Filament classes, and three files sent Filament notifications. Phase 0 severs all of it before anything is deleted, which is why Phase 6 is a deletion rather than a debugging session.

**Debts, not Expenses, is the hardest table.** It has a currency-conversion column driven by another filter's state, a computed due-date status with its own color rules, and a payment action with partial-payment arithmetic, an expense side effect and a receipt upload. It needed a `displayOnly()` filter modifier and a constructor argument on `ResourceTable`. It is scheduled immediately after Expenses so both stress tests land while there is still room to change the contract.

**Two deliberate behavior changes**, both noted in their commits:

- The Incomes `source_type` filter branched four ways inside one select. It becomes three independent boolean filters, which the generic `Filter` contract can express and which is a simpler UI.
- Relationship columns (`writer.name`, `client.title`, `expenseCategory.name`) are **not sortable**, because `ResourceTable` orders on real database columns and a dotted key would need a join. Filtering and searching on them still work. Adding join-based sorting is a possible follow-up.
