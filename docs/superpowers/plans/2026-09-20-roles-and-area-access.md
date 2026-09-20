# Roles and Area Access Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A second person signs in and reaches exactly Utilities, Budget and Library — fully, including paying bills and settling debts — while Blog, Work and Settings stay invisible everywhere the application could leak them.

**Architecture:** An `Area` enum matching the navigation clusters, a `UserRole` enum mapping role to areas, and one gate. Enforcement happens twice and independently: route groups per area, and an abstract `AdminRequest` whose `area()` method is abstract so a new request cannot compile without declaring where it belongs. Four leak surfaces — navigation, global search, dashboard, exports — each ask the same gate.

**Tech Stack:** PHP 8.5, Laravel 13, Pest 5, Inertia + Vue 3.

**Spec:** `docs/superpowers/specs/2026-09-20-roles-and-area-access-design.md`

## Global Constraints

- **Work directly on `master`.** No feature branches. Commit messages carry no AI attribution trailer.
- **Conventional Commits:** `feat:`, `fix:`, `chore:`, `refactor:`, `test:`, `docs:`.
- **One migration file per table.** Do not add `add_x_to_y` migrations; fold the column into `create_users_table`.
- **Every class is `final`** except the five documented abstract bases. `AdminRequest` becomes the sixth and must be added to `tests/Feature/ArchTest.php`.
- **PHPStan `level: max`, `--memory-limit=1G`.** No baseline, no `ignoreErrors`, no casting `mixed`.
- **Type coverage stays at 100%.**
- **`ADMIN_EMAIL` always wins.** Whatever `users.role` says, that address reaches every area. This is the lockout guarantee and must never be conditional on the column.
- **Refusal is 404, via `Response::denyAsNotFound()`** — a real Laravel deny, not a thrown `NotFoundHttpException`.
- **No table gains a `user_id`.** Household data is shared; nothing is scoped by user.
- **Tests never depend on the local `.env`;** set values with `config([...])` inside the test.
- **Gates before every push:** `composer ci:check`, `npm run lint:check`, `npm run format:check`. Check the exit status, not the tail of the output.

---

## File Structure

**Created:**

| Path | Responsibility |
| --- | --- |
| `app/Enums/Area.php` | The six areas, matching the navigation clusters |
| `app/Enums/UserRole.php` | Role to areas |
| `app/Http/Requests/Admin/AdminRequest.php` | Abstract base forcing an area declaration |
| `tests/Unit/AreaTest.php` | Area and role mapping |
| `tests/Feature/Auth/AreaAccessMatrixTest.php` | 137 routes × 2 roles, generated |
| `tests/Feature/Auth/LeakSurfaceTest.php` | Navigation, search, dashboard, exports |

**Modified:** `database/migrations/0001_01_01_000000_create_users_table.php`, `app/Models/User.php`, `database/factories/UserFactory.php`, `app/Providers/AppServiceProvider.php`, `routes/admin.php`, `app/Support/Navigation.php`, `app/Support/GlobalSearch.php`, `app/Http/Controllers/Admin/DashboardController.php`, `app/Http/Controllers/Admin/ExportController.php`, `app/Actions/Exports/ExportResource.php`, `app/Http/Middleware/HandleInertiaRequests.php`, all 19 files in `app/Http/Requests/Admin/`, `database/seeders/DatabaseSeeder.php`, `.env.example`, `tests/Feature/ArchTest.php`, `CHANGELOG.md`.

---

### Task 1: The `Area` and `UserRole` enums

**Files:**
- Create: `app/Enums/Area.php`, `app/Enums/UserRole.php`
- Test: `tests/Unit/AreaTest.php`

**Interfaces:**
- Produces: `Area` (six cases), `UserRole` with `areas(): array<int, Area>`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/AreaTest.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\Area;
use App\Enums\UserRole;

it('has one area per navigation cluster', function (): void {
    expect(array_column(Area::cases(), 'value'))
        ->toEqualCanonicalizing(['blog', 'budget', 'work', 'library', 'utilities', 'general']);
});

it('gives an admin every area', function (): void {
    expect(UserRole::Admin->areas())->toEqualCanonicalizing(Area::cases());
});

it('gives a member the household areas only', function (): void {
    expect(UserRole::Member->areas())
        ->toEqualCanonicalizing([Area::Budget, Area::Utilities, Area::Library]);
});

it('keeps blog, work and general away from a member', function (Area $area): void {
    expect(UserRole::Member->areas())->not->toContain($area);
})->with([
    'blog' => [Area::Blog],
    'work' => [Area::Work],
    'general' => [Area::General],
]);

/**
 * A new role that forgets its areas would silently grant nothing, which looks
 * like a permissions bug rather than an incomplete enum.
 */
it('gives every role at least one area', function (): void {
    foreach (UserRole::cases() as $role) {
        expect($role->areas())->not->toBeEmpty();
    }

    expect(UserRole::cases())->not->toBeEmpty();
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --filter=AreaTest`
Expected: FAIL — `Class "App\Enums\Area" not found`.

- [ ] **Step 3: Write the enums**

Create `app/Enums/Area.php`:

```php
<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The parts of the panel access is granted over.
 *
 * These match the navigation clusters deliberately: it is the grouping the panel
 * already presents and the one both users already think in.
 */
enum Area: string
{
    case Blog = 'blog';
    case Budget = 'budget';
    case Work = 'work';
    case Library = 'library';
    case Utilities = 'utilities';
    case General = 'general';
}
```

Create `app/Enums/UserRole.php`:

```php
<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * users.role is a plain string column cast to this enum, not a database enum,
 * so adding a role is one line here and no migration.
 *
 * The enum implements no presentation contracts because no screen lists users
 * yet; HasColor and HasLabel can be added the day one does.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Member = 'member';

    /**
     * @return array<int, Area>
     */
    public function areas(): array
    {
        return match ($this) {
            self::Admin => Area::cases(),
            self::Member => [Area::Budget, Area::Utilities, Area::Library],
        };
    }
}
```

- [ ] **Step 4: Run the tests**

Run: `php artisan test --filter=AreaTest`
Expected: PASS.

- [ ] **Step 5: Verify the gates and commit**

```bash
composer lint && composer types:check && composer type-coverage
git add app/Enums/Area.php app/Enums/UserRole.php tests/Unit/AreaTest.php
git commit -m "feat: add area and role enums" -- app/Enums/Area.php app/Enums/UserRole.php tests/Unit/AreaTest.php
```

---

### Task 2: The role column and `User::canAccess()`

**Files:**
- Modify: `database/migrations/0001_01_01_000000_create_users_table.php`, `app/Models/User.php`, `database/factories/UserFactory.php`
- Test: `tests/Feature/Models/UserTest.php`

**Interfaces:**
- Consumes: `Area`, `UserRole` from Task 1.
- Produces: `User::$role` cast to `UserRole`, `User::canAccess(Area $area): bool`, and factory states `admin()` and `member()`.

**Why the migration is edited rather than added:** the repository was squashed to
one migration per table in `v0.8.0`. A fresh `migrate` is the expected way to
pick this up locally.

- [ ] **Step 1: Add the column**

In `database/migrations/0001_01_01_000000_create_users_table.php`, inside the
`users` table, after `$table->string('password');`:

```php
// A plain string, not a database enum: App\Enums\UserRole is the only list,
// so adding a role never needs a migration. Defaults to the less privileged
// value, so a row created without one cannot accidentally be an admin.
$table->string('role')->default('member');
```

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/Models/UserTest.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\Area;
use App\Enums\UserRole;
use App\Models\User;

beforeEach(function (): void {
    config(['app.admin_email' => 'owner@example.test']);
});

it('defaults a new row to the least privileged role', function (): void {
    $user = User::factory()->create(['email' => 'someone@example.test']);

    expect($user->role)->toBe(UserRole::Member);
});

it('casts the role to the enum', function (): void {
    expect(User::factory()->admin()->create()->role)->toBe(UserRole::Admin);
});

it('gives a member the household areas', function (Area $area): void {
    $user = User::factory()->member()->create(['email' => 'her@example.test']);

    expect($user->canAccess($area))->toBeTrue();
})->with([
    'budget' => [Area::Budget],
    'utilities' => [Area::Utilities],
    'library' => [Area::Library],
]);

it('keeps a member out of the rest', function (Area $area): void {
    $user = User::factory()->member()->create(['email' => 'her@example.test']);

    expect($user->canAccess($area))->toBeFalse();
})->with([
    'blog' => [Area::Blog],
    'work' => [Area::Work],
    'general' => [Area::General],
]);

it('gives a role-admin every area', function (): void {
    $user = User::factory()->admin()->create(['email' => 'other@example.test']);

    foreach (Area::cases() as $area) {
        expect($user->canAccess($area))->toBeTrue();
    }
});

/**
 * The lockout guarantee. A wrong migration, a bad seed or an empty column must
 * never lock the owner out, so ADMIN_EMAIL is checked before the role is.
 */
it('lets the configured owner reach everything whatever the column says', function (): void {
    $owner = User::factory()->member()->create(['email' => 'owner@example.test']);

    foreach (Area::cases() as $area) {
        expect($owner->canAccess($area))->toBeTrue();
    }
});

it('lets the configured owner in even with an empty role column', function (): void {
    $owner = User::factory()->create(['email' => 'owner@example.test']);
    DB::table('users')->where('id', $owner->id)->update(['role' => '']);

    foreach (Area::cases() as $area) {
        expect($owner->fresh()->canAccess($area))->toBeTrue();
    }
});
```

- [ ] **Step 3: Run it and watch it fail**

Run: `php artisan migrate:fresh --no-interaction && php artisan test --filter=UserTest`
Expected: FAIL — `canAccess()` does not exist.

- [ ] **Step 4: Extend the model**

In `app/Models/User.php`, add the import `use App\Enums\Area;` and
`use App\Enums\UserRole;`, then add to `casts()`:

```php
'role' => UserRole::class,
```

And add:

```php
/**
 * Whether this user reaches an area.
 *
 * ADMIN_EMAIL is checked first and unconditionally: a bad migration, an empty
 * column or a fat-fingered seed must never lock the owner out of their own panel.
 */
public function canAccess(Area $area): bool
{
    if ($this->isAdmin()) {
        return true;
    }

    return $this->role instanceof UserRole && in_array($area, $this->role->areas(), true);
}
```

Task 3 refactors this into `areas()` plus a one-line `canAccess()`. Write it as
shown here first, so this task's tests pass on their own.

The `instanceof` guard is what makes the empty-column test pass: an unrecognised
value casts to null rather than throwing, and a null role reaches nothing.

**If the cast throws on an empty string** rather than yielding null, wrap the
role read in a `tryFrom` helper instead:

```php
$role = $this->getRawOriginal('role');
$role = is_string($role) ? UserRole::tryFrom($role) : null;
```

Use whichever the test proves; do not keep both.

- [ ] **Step 5: Add the factory states**

In `database/factories/UserFactory.php`, after `definition()`:

```php
public function admin(): static
{
    return $this->state(fn (array $attributes): array => ['role' => UserRole::Admin->value]);
}

public function member(): static
{
    return $this->state(fn (array $attributes): array => ['role' => UserRole::Member->value]);
}
```

Import `use App\Enums\UserRole;`.

- [ ] **Step 6: Run the tests**

Run: `php artisan test --filter=UserTest`
Expected: PASS, 11 tests.

- [ ] **Step 7: Run the whole suite**

Run: `php artisan test`
Expected: PASS. Existing tests create users through the factory, which now
defaults to member — but every existing test also sets
`config(['app.admin_email' => ...])` to the created user's address, so the
lockout bypass keeps them admin. If any test fails here, it is one that created
a user *without* matching `ADMIN_EMAIL`; give it `->admin()`.

- [ ] **Step 8: Commit**

```bash
composer lint && composer types:check
git add database/migrations database/factories app/Models/User.php tests/Feature/Models/UserTest.php
git commit -m "feat: give users a role and an area check" -- database/migrations database/factories app/Models/User.php tests/Feature/Models/UserTest.php
```

---

### Task 3: The gate

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Auth/AreaGateTest.php`

**Interfaces:**
- Consumes: `User::canAccess()` from Task 2.
- Produces: gate `access-area` taking an `Area`, denying as 404. The existing
  `access-admin` gate stays, now meaning `Area::General`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Auth/AreaGateTest.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\Area;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    config(['app.admin_email' => 'owner@example.test']);
});

it('allows an area the user reaches', function (): void {
    $user = User::factory()->member()->create(['email' => 'her@example.test']);

    expect(Gate::forUser($user)->allows('access-area', Area::Budget))->toBeTrue();
});

it('denies an area the user does not reach', function (): void {
    $user = User::factory()->member()->create(['email' => 'her@example.test']);

    expect(Gate::forUser($user)->allows('access-area', Area::Work))->toBeFalse();
});

/**
 * The refusal has to be a 404 rather than a 403, and it has to come from
 * Laravel's own deny mechanism so the exception handler renders it properly.
 */
it('denies as not found rather than forbidden', function (): void {
    $user = User::factory()->member()->create(['email' => 'her@example.test']);

    $response = Gate::forUser($user)->inspect('access-area', Area::Work);

    expect($response->denied())->toBeTrue()
        ->and($response->status())->toBe(404);
});

it('allows the configured owner every area', function (Area $area): void {
    $owner = User::factory()->member()->create(['email' => 'owner@example.test']);

    expect(Gate::forUser($owner)->allows('access-area', $area))->toBeTrue();
})->with(array_map(fn (Area $area): array => [$area], Area::cases()));

it('lets anyone with an area through the panel door', function (): void {
    $member = User::factory()->member()->create(['email' => 'her@example.test']);

    expect(Gate::forUser($member)->allows('access-panel'))->toBeTrue();
});

it('keeps a user with no areas out of the panel entirely', function (): void {
    $stranger = User::factory()->create(['email' => 'stranger@example.test']);
    DB::table('users')->where('id', $stranger->id)->update(['role' => '']);

    expect(Gate::forUser($stranger->fresh())->allows('access-panel'))->toBeFalse();
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --filter=AreaGateTest`
Expected: FAIL — the gate is not defined.

- [ ] **Step 3: Define the gate**

In `app/Providers/AppServiceProvider.php`, add imports
`use App\Enums\Area;` and `use Illuminate\Auth\Access\Response;`, then beside the
existing `access-admin` definition:

```php
// denyAsNotFound() is Laravel's own mechanism, so a refusal becomes a genuine
// 404 rather than a faked exception. A forbidden URL and a nonexistent one are
// indistinguishable, which is what was asked for.
Gate::define('access-area', fn (User $user, Area $area): Response => $user->canAccess($area)
    ? Response::allow()
    : Response::denyAsNotFound());

// The door. Anyone with at least one area belongs in the panel; which screens
// they reach is decided by the per-area groups inside.
Gate::define('access-panel', fn (User $user): bool => $user->areas() !== []);
```

**`access-admin` is deleted, not redefined.** The outer route group currently
carries `can:access-admin`, and that group wraps every admin route including
Budget and Utilities. Redefining it to mean General would deny the member at the
door, before any area group ran. Task 4 replaces the outer middleware with
`can:access-panel`, and the 19 requests lose their `access-admin` call in Task 5.

Add the supporting method to `app/Models/User.php`:

```php
/**
 * Every area this user reaches. Empty means they do not belong in the panel.
 *
 * @return array<int, Area>
 */
public function areas(): array
{
    if ($this->isAdmin()) {
        return Area::cases();
    }

    return $this->role instanceof UserRole ? $this->role->areas() : [];
}
```

and simplify `canAccess()` to use it:

```php
public function canAccess(Area $area): bool
{
    return in_array($area, $this->areas(), true);
}
```

- [ ] **Step 4: Run the tests**

Run: `php artisan test --filter=AreaGateTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
composer lint && composer types:check
git add app/Providers/AppServiceProvider.php tests/Feature/Auth/AreaGateTest.php
git commit -m "feat: add the area gate, denying as not found" -- app/Providers/AppServiceProvider.php tests/Feature/Auth/AreaGateTest.php
```

---

### Task 4: Route groups per area

**Files:**
- Modify: `routes/admin.php`
- Test: `tests/Feature/Auth/AreaAccessMatrixTest.php`

**Interfaces:**
- Consumes: gates `access-panel` and `access-area` from Task 3.
- Produces: every admin route inside exactly one area group, except the six that belong to any signed-in user.

**The mapping.** Derived from the current file; every route is accounted for.

| Area | Route prefixes |
| --- | --- |
| Blog | `posts` |
| Budget | `incomes`, `expenses`, `debts` |
| Work | `clients`, `projects`, `repositories`, `invoices`, `coding-dashboard`, `tasks`, `waka-time-summaries` |
| Library | `publishers`, `writers`, `books` (including `books.isbn` and `books.isbn-relation`) |
| Utilities | `utility-accounts`, `utility-bills` |
| General | `settings`, `categories` |
| *(none)* | `dashboard`, `profile`, `search`, `notifications.*` (3 routes) |

`exports.*` stays outside the area groups and is checked per resource in Task 7,
because one route serves several resources.

- [ ] **Step 1: Write the failing matrix test**

Create `tests/Feature/Auth/AreaAccessMatrixTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Book;
use App\Models\Category;
use App\Models\Client;
use App\Models\Debt;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Invoice;
use App\Models\Post;
use App\Models\Project;
use App\Models\Publisher;
use App\Models\Repository;
use App\Models\Task;
use App\Models\User;
use App\Models\UtilityAccount;
use App\Models\UtilityBill;
use App\Models\WakaTimeSummary;
use App\Models\Writer;
use Illuminate\Support\Facades\Route;

/**
 * Every admin route, crossed with both roles.
 *
 * Built from the router rather than hand-listed, so a route added next year is
 * covered the day it appears and a route that forgets its area group fails the
 * moment it exists.
 *
 * @return array<string, array{string, string, array<int, string>}>
 */
function adminRoutes(): array
{
    $cases = [];

    foreach (Route::getRoutes() as $route) {
        $name = $route->getName();

        if (! is_string($name) || ! str_starts_with($name, 'admin.')) {
            continue;
        }

        // The signed download route cannot be reached without a signature, and
        // its guard is the signature, not an area.
        if ($name === 'admin.exports.download') {
            continue;
        }

        $method = in_array('GET', $route->methods(), true) ? 'GET' : $route->methods()[0];

        $cases[$method.' '.$name] = [$method, $name, $route->parameterNames()];
    }

    return $cases;
}

/**
 * A record for each route parameter, so the matrix does not collapse into
 * special cases for routes that bind a model.
 */
function parameterValue(string $parameter): mixed
{
    return match ($parameter) {
        'post' => Post::factory()->create()->getKey(),
        'category' => Category::factory()->create()->getKey(),
        'publisher' => Publisher::factory()->create()->getKey(),
        'writer' => Writer::factory()->create()->getKey(),
        'book' => Book::factory()->create()->getKey(),
        'utility_account' => UtilityAccount::factory()->create()->getKey(),
        'utility_bill', 'utilityBill' => UtilityBill::factory()->create()->getKey(),
        'client' => Client::factory()->create()->getKey(),
        'project' => Project::factory()->create()->getKey(),
        'repository' => Repository::factory()->create()->getKey(),
        'invoice' => Invoice::factory()->create()->getKey(),
        'task' => Task::factory()->create()->getKey(),
        'wakaTimeSummary' => WakaTimeSummary::factory()->create()->getKey(),
        'income' => Income::factory()->create()->getKey(),
        'expense' => Expense::factory()->create()->getKey(),
        'debt' => Debt::factory()->create()->getKey(),
        'notification' => 'not-a-real-notification-id',
        'resource' => 'books',
        default => '1',
    };
}

/** @return array<string, mixed> */
function routeParameters(array $names): array
{
    $parameters = [];

    foreach ($names as $name) {
        $parameters[$name] = parameterValue($name);
    }

    return $parameters;
}

/**
 * Which area each route belongs to, by name prefix. Anything unlisted is a
 * route every signed-in user may reach.
 */
function areaOf(string $routeName): ?string
{
    $map = [
        'admin.posts.' => 'blog',
        'admin.categories.' => 'general',
        'admin.settings' => 'general',
        'admin.publishers.' => 'library',
        'admin.writers.' => 'library',
        'admin.books.' => 'library',
        'admin.utility-accounts.' => 'utilities',
        'admin.utility-bills.' => 'utilities',
        'admin.clients.' => 'work',
        'admin.projects.' => 'work',
        'admin.repositories.' => 'work',
        'admin.invoices.' => 'work',
        'admin.coding-dashboard' => 'work',
        'admin.tasks.' => 'work',
        'admin.waka-time-summaries.' => 'work',
        'admin.incomes.' => 'budget',
        'admin.expenses.' => 'budget',
        'admin.debts.' => 'budget',
    ];

    foreach ($map as $prefix => $area) {
        if (str_starts_with($routeName, $prefix)) {
            return $area;
        }
    }

    return null;
}

beforeEach(function (): void {
    config(['app.admin_email' => 'owner@example.test']);
});

it('lets the admin reach every admin route', function (string $method, string $name, array $parameters): void {
    $this->actingAs(User::factory()->admin()->create(['email' => 'owner@example.test']));

    $response = $this->call($method, route($name, routeParameters($parameters)));

    expect($response->status())->not->toBe(404);
})->with(adminRoutes());

it('lets a member reach her areas and nothing else', function (string $method, string $name, array $parameters): void {
    $this->actingAs(User::factory()->member()->create(['email' => 'her@example.test']));

    $area = areaOf($name);
    $allowed = $area === null || in_array($area, ['budget', 'utilities', 'library'], true);

    $response = $this->call($method, route($name, routeParameters($parameters)));

    $allowed
        ? expect($response->status())->not->toBe(404)
        : expect($response->status())->toBe(404);
})->with(adminRoutes());
```

**Note on the assertion.** It checks "not 404" rather than "200", because a
write route without a valid payload legitimately returns 302 with validation
errors. What matters here is reachability, not success — the resource tests
already cover behaviour.

- [ ] **Step 2: Run it and watch the member half fail**

Run: `php artisan test --filter=AreaAccessMatrixTest`
Expected: the admin cases pass; the member cases FAIL for Blog, Work and General
routes, because nothing denies them yet.

- [ ] **Step 3: Replace the outer middleware**

In `routes/admin.php`, change the group declaration:

```php
Route::middleware(['auth', 'can:access-panel'])
```

- [ ] **Step 4: Wrap each area**

Inside the group, wrap the route blocks. Each wrapper looks like this; repeat it
per area with that area's routes moved inside, leaving `dashboard`, `profile`,
`search`, the three `notifications` routes and both `exports` routes at the top
level.

```php
Route::middleware('can:access-area,'.Area::Blog->value)->group(function (): void {
    Route::delete('posts/bulk', [PostController::class, 'bulkDestroy'])->name('posts.bulk-destroy');
    Route::patch('posts/bulk', [PostController::class, 'bulkUpdate'])->name('posts.bulk-update');
    Route::resource('posts', PostController::class)->except(['show']);
});
```

Add `use App\Enums\Area;` at the top of the file.

Order inside each group is unchanged — the bulk routes still come before their
resource route, for the same reason as before.

- [ ] **Step 5: Run the matrix**

Run: `php artisan test --filter=AreaAccessMatrixTest`
Expected: PASS, 272 cases — 137 admin routes less the signed download route, times two roles.

A member case that still reaches a Work route means that route was left outside
its group. A member case that 404s where it should not means an area group is
too greedy.

- [ ] **Step 6: Run everything**

Run: `php artisan test`
Expected: PASS. Existing resource tests act as a user whose email matches
`ADMIN_EMAIL`, so the lockout bypass keeps them reaching everything.

- [ ] **Step 7: Commit**

```bash
composer lint && composer types:check
git add routes/admin.php tests/Feature/Auth/AreaAccessMatrixTest.php
git commit -m "feat: group admin routes by area" -- routes/admin.php tests/Feature/Auth/AreaAccessMatrixTest.php
```

---

### Task 5: The base request

**Files:**
- Create: `app/Http/Requests/Admin/AdminRequest.php`
- Modify: all 18 files in `app/Http/Requests/Admin/`, `tests/Feature/ArchTest.php`
- Test: `tests/Feature/Auth/AdminRequestTest.php`

**Interfaces:**
- Consumes: `Area` from Task 1, gate `access-area` from Task 3.
- Produces: `AdminRequest` with `abstract protected function area(): Area;` and a final `authorize()`.

**Why this matters more than the route groups.** Today 18 requests each repeat
`return $this->user()?->can('access-admin') ?? false;`. A nineteenth can silently
omit it and nothing notices. Making `area()` abstract converts "remember to add
the check" into "the code does not compile".

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Auth/AdminRequestTest.php`:

```php
<?php

declare(strict_types=1);

use App\Http\Requests\Admin\AdminRequest;

/**
 * The guarantee: a new request in this namespace cannot exist without declaring
 * an area, because AdminRequest::area() is abstract. This test is what notices
 * if someone sidesteps the base class.
 */
it('routes every admin request through the base class', function (): void {
    $offenders = [];

    foreach (glob(app_path('Http/Requests/Admin/*.php')) ?: [] as $file) {
        $class = 'App\\Http\\Requests\\Admin\\'.basename($file, '.php');

        if ($class === AdminRequest::class) {
            continue;
        }

        if (! is_subclass_of($class, AdminRequest::class)) {
            $offenders[] = $class;
        }
    }

    expect($offenders)->toBe([], 'These bypass the area check: '.implode(', ', $offenders));
});

it('declares an area for every admin request', function (): void {
    foreach (glob(app_path('Http/Requests/Admin/*.php')) ?: [] as $file) {
        $class = 'App\\Http\\Requests\\Admin\\'.basename($file, '.php');

        if ($class === AdminRequest::class) {
            continue;
        }

        $method = new ReflectionMethod($class, 'area');

        expect($method->isAbstract())->toBeFalse("{$class} never implements area()");
    }
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --filter=AdminRequestTest`
Expected: FAIL — `AdminRequest` does not exist.

- [ ] **Step 3: Write the base**

Create `app/Http/Requests/Admin/AdminRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Area;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The inner fence.
 *
 * Route groups are the outer one; neither depends on the other being right.
 * area() is abstract on purpose: a new request cannot be written without saying
 * where it belongs, so the check stops being something to remember.
 */
abstract class AdminRequest extends FormRequest
{
    abstract protected function area(): Area;

    public function authorize(): bool
    {
        return $this->user()?->can('access-area', $this->area()) ?? false;
    }
}
```

- [ ] **Step 4: Convert the 18 requests**

For each file in `app/Http/Requests/Admin/`, replace
`extends FormRequest` with `extends AdminRequest`, delete its `authorize()`
method entirely, and add its area. For example, `ExpenseRequest`:

```php
use App\Enums\Area;

final class ExpenseRequest extends AdminRequest
{
    #[Override]
    protected function area(): Area
    {
        return Area::Budget;
    }

    // rules() unchanged
}
```

The areas, by file:

| Area | Requests |
| --- | --- |
| Blog | `PostRequest` |
| Budget | `IncomeRequest`, `ExpenseRequest`, `DebtRequest`, `PayDebtRequest` |
| Work | `ClientRequest`, `ProjectRequest`, `RepositoryRequest`, `InvoiceRequest`, `TaskRequest`, `MoveTaskRequest` |
| Library | `BookRequest`, `WriterRequest`, `PublisherRequest` |
| Utilities | `UtilityAccountRequest`, `UtilityBillRequest` |
| General | `CategoryRequest`, `SettingsRequest` |

That is all 18. Confirm with `ls app/Http/Requests/Admin/ | wc -l` that the
count is 18 before this task and 19 after, the nineteenth being `AdminRequest`
itself.

Each converted file also needs `use App\Enums\Area;` and `use Override;`.

There is no request for `utility-bills.pay`; it validates inline. Its only fence
is the route group, which is fine — the two fences are independent by design,
and a route with no request is not a route with no check.

- [ ] **Step 5: Allow the new abstract class in the architecture test**

`tests/Feature/ArchTest.php` asserts every class is final except five named
bases. Add `App\Http\Requests\Admin\AdminRequest::class` to both the
`every class is final` ignore list and the `base classes stay abstract`
expectation.

- [ ] **Step 6: Run the tests**

Run: `php artisan test`
Expected: PASS, including `ArchTest` and the full matrix.

If a resource test now fails with a 403, that request's area is wrong — a
Budget request declared as Work, for instance.

- [ ] **Step 7: Confirm the old gate is gone**

Run: `grep -rn "access-admin" app/ routes/`
Expected: no output. If anything remains, it is a call site that was missed and
is now checking a gate that no longer exists.

- [ ] **Step 8: Commit**

```bash
composer lint && composer types:check
git add app/Http/Requests/Admin routes/ tests/
git commit -m "feat: require every admin request to declare its area" -- app/Http/Requests/Admin routes/ tests/
```

---

### Task 6: The four leak surfaces

**Files:**
- Modify: `app/Support/Navigation.php`, `app/Support/GlobalSearch.php`,
  `app/Http/Controllers/Admin/SearchController.php`,
  `app/Http/Controllers/Admin/DashboardController.php`,
  `app/Http/Middleware/HandleInertiaRequests.php`,
  `app/Actions/Exports/ExportResource.php`,
  `app/Http/Controllers/Admin/ExportController.php`,
  `resources/js/Pages/Dashboard.vue`,
  `tests/Feature/Admin/GlobalSearchTest.php`
- Test: `tests/Feature/Auth/LeakSurfaceTest.php`

**Interfaces:**
- Consumes: `Area`, `User::canAccess()`.
- Produces: `Navigation::forUser(User $user): array`, `GlobalSearch::query(string $term, User $user, int $perResource = 5): array`, `ExportResource::EXPORTS` entries carrying an area.

**Why these four and no others.** A 404 on a route is not enough if the page
before it says the route exists. These are the places that enumerate resources
without going through the router: the navigation bar, the command palette, the
dashboard, and the export list. Each is closed here, and Task 7 adds a test
whose job is to notice a fifth.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Auth/LeakSurfaceTest.php`:

```php
<?php

declare(strict_types=1);

use App\Actions\Exports\ExportResource;
use App\Enums\Area;
use App\Models\Book;
use App\Models\Client;
use App\Models\Post;
use App\Models\User;
use App\Support\GlobalSearch;
use App\Support\Navigation;

beforeEach(function (): void {
    config(['app.admin_email' => 'owner@example.test']);

    $this->owner = User::factory()->admin()->create(['email' => 'owner@example.test']);
    $this->member = User::factory()->member()->create(['email' => 'her@example.test']);
});

it('shows the member only her clusters', function (): void {
    $labels = collect(Navigation::forUser($this->member))->pluck('label');

    expect($labels)->toEqualCanonicalizing(['Budget', 'Library', 'Utilities']);
});

it('shows the admin every cluster', function (): void {
    expect(Navigation::forUser($this->owner))->toHaveCount(count(Navigation::clusters()));
});

it('keeps every navigation item inside its own cluster area', function (): void {
    foreach (Navigation::forUser($this->member) as $cluster) {
        expect($this->member->canAccess($cluster['area']))->toBeTrue(
            "Cluster {$cluster['label']} leaked to a member",
        );
    }
});

it('does not share a hidden cluster with the browser', function (): void {
    $this->actingAs($this->member)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('navigation', fn (array $clusters): bool => collect($clusters)
                ->pluck('label')
                ->doesntContain('Work')));
});

it('hides search results from areas the member cannot reach', function (): void {
    Client::factory()->create(['name' => 'Zzleak Client']);
    Book::factory()->create(['title' => 'Zzleak Book']);

    $groups = collect(GlobalSearch::query('Zzleak', $this->member))->pluck('group');

    expect($groups)->toContain('Books')
        ->and($groups)->not->toContain('Clients');
});

it('still searches everything for the admin', function (): void {
    Client::factory()->create(['name' => 'Zzleak Client']);

    expect(collect(GlobalSearch::query('Zzleak', $this->owner))->pluck('group'))
        ->toContain('Clients');
});

it('returns nothing rather than everything when the term matches a hidden area only', function (): void {
    Post::factory()->create(['title' => 'Zzleak Post']);

    expect(GlobalSearch::query('Zzleak', $this->member))->toBe([]);
});

it('sends the member a dashboard without work or blog panels', function (): void {
    $this->actingAs($this->member)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('budget')
            ->has('library')
            ->missing('work')
            ->missing('recentPosts')
            ->missing('openTasks'));
});

it('sends the admin the whole dashboard', function (): void {
    $this->actingAs($this->owner)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('budget')
            ->has('work')
            ->has('library')
            ->has('recentPosts')
            ->has('openTasks'));
});

it('gives every export an area', function (string $resource): void {
    expect(ExportResource::areaFor($resource))->toBeInstanceOf(Area::class);
})->with(array_keys(ExportResource::EXPORTS));

it('lets the member export a resource inside her areas', function (): void {
    $this->actingAs($this->member)
        ->post(route('admin.exports.store', 'books'))
        ->assertRedirect();
});

it('lets the admin export everything', function (string $resource): void {
    $this->actingAs($this->owner)
        ->post(route('admin.exports.store', $resource))
        ->assertRedirect();
})->with(array_keys(ExportResource::EXPORTS));

it('refuses an export nobody has registered', function (): void {
    $this->actingAs($this->owner)
        ->post(route('admin.exports.store', 'invoices'))
        ->assertNotFound();
});
```

**Note on the export tests, and a deliberate divergence from the spec.** The
spec suggests proving the check by "temporarily registering an exporter in a
non-Library area". That is not possible: `ExportResource::EXPORTS` is a `const`,
so no test can add to it, and the class is `final`, so no test can subclass it
to lie about an area. Making it mutable to suit a test would weaken the thing
being tested.

The substitute is a chain that is just as hard to break. All three exports are
Library today, so the member-refusal case has no subject — but the moment a
Work export is added, `areaFor()` returns `null` for it and the first test above
fails. Fixing that failure means writing the area down, and once it is written
down the controller enforces it. The gate itself is already covered by
`AreaGateTest`, so nothing rests on an untested assumption; what these tests add
is the guarantee that an export cannot exist without an area.

Add the member-refusal case in the same commit as the first non-Library export.

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --filter=LeakSurfaceTest`
Expected: FAIL — `Navigation::forUser()` does not exist.

- [ ] **Step 3: Give each cluster an area**

In `app/Support/Navigation.php`, add `use App\Enums\Area;` and
`use App\Models\User;`, add an `area` key to every cluster, and update the
docblock:

```php
/**
 * @return array<int, array{label: string, icon: string, area: Area, items: array<int, array{label: string, route: string, icon: string}>}>
 */
public static function clusters(): array
{
    return [
        ['label' => 'Blog', 'icon' => 'pi pi-pencil', 'area' => Area::Blog, 'items' => [
            ['label' => 'Posts', 'route' => 'admin.posts.index', 'icon' => 'pi pi-file-edit'],
        ]],
        ['label' => 'Budget', 'icon' => 'pi pi-wallet', 'area' => Area::Budget, 'items' => [
            ['label' => 'Incomes', 'route' => 'admin.incomes.index', 'icon' => 'pi pi-plus-circle'],
            ['label' => 'Expenses', 'route' => 'admin.expenses.index', 'icon' => 'pi pi-minus-circle'],
            ['label' => 'Debts', 'route' => 'admin.debts.index', 'icon' => 'pi pi-exclamation-triangle'],
        ]],
        ['label' => 'Work', 'icon' => 'pi pi-briefcase', 'area' => Area::Work, 'items' => [
            ['label' => 'Coding analytics', 'route' => 'admin.coding-dashboard', 'icon' => 'pi pi-chart-bar'],
            ['label' => 'Task board', 'route' => 'admin.tasks.board', 'icon' => 'pi pi-th-large'],
            ['label' => 'Clients', 'route' => 'admin.clients.index', 'icon' => 'pi pi-users'],
            ['label' => 'Projects', 'route' => 'admin.projects.index', 'icon' => 'pi pi-folder-open'],
            ['label' => 'Repositories', 'route' => 'admin.repositories.index', 'icon' => 'pi pi-code'],
            ['label' => 'Invoices', 'route' => 'admin.invoices.index', 'icon' => 'pi pi-receipt'],
            ['label' => 'Daily summaries', 'route' => 'admin.waka-time-summaries.index', 'icon' => 'pi pi-clock'],
        ]],
        ['label' => 'Library', 'icon' => 'pi pi-book', 'area' => Area::Library, 'items' => [
            ['label' => 'Books', 'route' => 'admin.books.index', 'icon' => 'pi pi-book'],
            ['label' => 'Writers', 'route' => 'admin.writers.index', 'icon' => 'pi pi-user'],
            ['label' => 'Publishers', 'route' => 'admin.publishers.index', 'icon' => 'pi pi-building'],
        ]],
        ['label' => 'Utilities', 'icon' => 'pi pi-bolt', 'area' => Area::Utilities, 'items' => [
            ['label' => 'Bills', 'route' => 'admin.utility-bills.index', 'icon' => 'pi pi-receipt'],
            ['label' => 'Accounts', 'route' => 'admin.utility-accounts.index', 'icon' => 'pi pi-id-card'],
        ]],
        ['label' => 'General', 'icon' => 'pi pi-cog', 'area' => Area::General, 'items' => [
            ['label' => 'Categories', 'route' => 'admin.categories.index', 'icon' => 'pi pi-tags'],
            ['label' => 'Settings', 'route' => 'admin.settings', 'icon' => 'pi pi-cog'],
        ]],
    ];
}

/**
 * The clusters this user may actually open.
 *
 * The bar is the first thing that gives a resource away, so it is filtered at
 * the source rather than hidden in the component.
 *
 * @return array<int, array{label: string, icon: string, area: Area, items: array<int, array{label: string, route: string, icon: string}>}>
 */
public static function forUser(User $user): array
{
    return array_values(array_filter(
        self::clusters(),
        fn (array $cluster): bool => $user->canAccess($cluster['area']),
    ));
}
```

The enum reaches the browser as its string value, because backed enums are
`JsonSerializable`. Nothing in the Vue reads it; it is there so the filter and
the labels cannot drift apart.

- [ ] **Step 4: Share the filtered list**

In `app/Http/Middleware/HandleInertiaRequests.php`, replace the navigation line:

```php
'navigation' => fn (): array => $user === null ? [] : Navigation::forUser($user),
```

- [ ] **Step 5: Filter the command palette**

In `app/Support/GlobalSearch.php`, add `use App\Enums\Area;` and
`use App\Models\User;`, give each registry entry an area, and require a user:

```php
/**
 * Group label => [table factory, route name for a single record, area].
 *
 * @return array<string, array{table: callable(): ResourceTable, route: string, area: Area}>
 */
private static function registry(): array
{
    return [
        'Posts' => ['table' => fn (): ResourceTable => new PostTable, 'route' => 'admin.posts.edit', 'area' => Area::Blog],
        'Categories' => ['table' => fn (): ResourceTable => new CategoryTable, 'route' => 'admin.categories.edit', 'area' => Area::General],
        'Books' => ['table' => fn (): ResourceTable => new BookTable, 'route' => 'admin.books.edit', 'area' => Area::Library],
        'Writers' => ['table' => fn (): ResourceTable => new WriterTable, 'route' => 'admin.writers.edit', 'area' => Area::Library],
        'Publishers' => ['table' => fn (): ResourceTable => new PublisherTable, 'route' => 'admin.publishers.edit', 'area' => Area::Library],
        'Clients' => ['table' => fn (): ResourceTable => new ClientTable, 'route' => 'admin.clients.edit', 'area' => Area::Work],
        'Projects' => ['table' => fn (): ResourceTable => new ProjectTable, 'route' => 'admin.projects.edit', 'area' => Area::Work],
        'Repositories' => ['table' => fn (): ResourceTable => new RepositoryTable, 'route' => 'admin.repositories.show', 'area' => Area::Work],
        'Invoices' => ['table' => fn (): ResourceTable => new InvoiceTable, 'route' => 'admin.invoices.edit', 'area' => Area::Work],
        'Incomes' => ['table' => fn (): ResourceTable => new IncomeTable, 'route' => 'admin.incomes.show', 'area' => Area::Budget],
        'Expenses' => ['table' => fn (): ResourceTable => new ExpenseTable, 'route' => 'admin.expenses.edit', 'area' => Area::Budget],
        'Debts' => ['table' => fn (): ResourceTable => new DebtTable, 'route' => 'admin.debts.edit', 'area' => Area::Budget],
        'Daily summaries' => ['table' => fn (): ResourceTable => new WakaTimeSummaryTable, 'route' => 'admin.waka-time-summaries.show', 'area' => Area::Work],
    ];
}
```

and in `query()`, take the user as the second argument and skip what they cannot
reach:

```php
/**
 * @return array<int, array{label: string, group: string, url: string}>
 */
public static function query(string $term, User $user, int $perResource = self::PER_RESOURCE): array
{
    $term = trim($term);

    if (mb_strlen($term) < self::MINIMUM_TERM_LENGTH) {
        return [];
    }

    $results = [];

    foreach (self::registry() as $group => $entry) {
        if (! $user->canAccess($entry['area'])) {
            continue;
        }

        // body unchanged
    }

    return $results;
}
```

`$user` is required rather than nullable on purpose: a call site that forgets it
will not compile, instead of quietly searching everything.

- [ ] **Step 6: Update the one caller and the existing test**

`app/Http/Controllers/Admin/SearchController.php`:

```php
public function __invoke(Request $request): JsonResponse
{
    return response()->json([
        'results' => GlobalSearch::query($request->string('q')->toString(), $request->user()),
    ]);
}
```

The route is behind `auth`, so `$request->user()` is never null there; if
PHPStan disagrees, bind it first:

```php
$user = $request->user();

abort_if($user === null, 401);
```

In `tests/Feature/Admin/GlobalSearchTest.php`, the five `GlobalSearch::query(...)`
calls each need the acting user. Add to that file's `beforeEach`:

```php
$this->owner = User::factory()->admin()->create(['email' => 'admin@example.test']);
```

and pass `$this->owner` as the second argument at lines 50, 58, 75, 83 and 89.
Those tests assert cross-area results, so they must run as an admin.

- [ ] **Step 7: Trim the dashboard**

In `app/Http/Controllers/Admin/DashboardController.php`, build the props
conditionally:

```php
public function __invoke(Request $request): Response
{
    $user = $request->user();

    abort_if($user === null, 401);

    $props = [];

    if ($user->canAccess(Area::Budget)) {
        $props['budget'] = BudgetOverview::stats(...);
    }

    if ($user->canAccess(Area::Work)) {
        $props['work'] = WorkOverview::stats(...);
        $props['openTasks'] = fn (): array => $this->openTasks();
    }

    if ($user->canAccess(Area::Library)) {
        $props['library'] = LibraryOverview::stats(...);
    }

    if ($user->canAccess(Area::Blog)) {
        $props['recentPosts'] = fn (): array => $this->recentPosts();
    }

    return Inertia::render('Dashboard', $props);
}
```

Move the two inline closures into private methods with the same bodies they have
now, so the props array stays readable:

```php
/** @return array<int, array{id: int, title: string, slug: string, updatedAt: ?string}> */
private function recentPosts(): array
{
    return Post::query()
        ->latest()
        ->limit(5)
        ->get(['id', 'title', 'slug', 'updated_at'])
        ->map(fn (Post $post): array => [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'updatedAt' => $post->updated_at?->diffForHumans(),
        ])
        ->all();
}

/** @return array<int, array{id: int, title: string, status: string, repository: ?string}> */
private function openTasks(): array
{
    return Task::query()
        ->with('repository')
        ->whereIn('status', ['todo', 'in_progress'])
        ->orderBy('sort_order')
        ->limit(5)
        ->get()
        ->map(fn (Task $task): array => [
            'id' => $task->id,
            'title' => $task->title,
            'status' => $task->status,
            'repository' => $task->repository?->name,
        ])
        ->all();
}
```

Add `use App\Enums\Area;` and `use Illuminate\Http\Request;`.

- [ ] **Step 8: Let the page render with panels missing**

`resources/js/Pages/Dashboard.vue` defaults every prop to `[]`, so a missing
prop currently renders an empty section with a heading and a link to a route the
member cannot open. Skip those sections instead.

Replace the `SECTIONS` constant with a computed list:

```js
import { computed } from 'vue'

const props = defineProps({
  budget: { type: Array, default: () => [] },
  work: { type: Array, default: () => [] },
  library: { type: Array, default: () => [] },
  recentPosts: { type: Array, default: () => [] },
  openTasks: { type: Array, default: () => [] },
})

const ALL_SECTIONS = [
  ['budget', 'Budget', 'admin.expenses.index'],
  ['work', 'Work', 'admin.tasks.board'],
  ['library', 'Library', 'admin.books.index'],
]

const sections = computed(() => ALL_SECTIONS.filter(([key]) => props[key].length > 0))
```

In the template, iterate `sections` instead of `SECTIONS`, and guard the two
panels below it:

```vue
<section v-if="recentPosts.length" class="...">   <!-- recent posts panel -->
<section v-if="openTasks.length" class="...">     <!-- open tasks panel -->
```

Keep the existing "Nothing written yet." and "Nothing open." empty states —
they now mean "your blog is empty", not "you have no blog". A member never sees
either panel at all.

**Warning:** `v-if` on the panel and the empty state inside it read as
contradictory. They are not: the panel is absent when the *area* is absent, and
the empty state shows when the area is present but has no rows. Do not collapse
them into one condition.

- [ ] **Step 9: Give every export an area**

In `app/Actions/Exports/ExportResource.php`, replace the flat constant with one
that carries the area, and add a lookup:

```php
use App\Enums\Area;

/**
 * @var array<string, class-string<ResourceExport>>
 */
public const array EXPORTS = [
    'books' => BookExport::class,
    'publishers' => PublisherExport::class,
    'writers' => WriterExport::class,
];

/**
 * Which area each export belongs to.
 *
 * Separate from EXPORTS so the class-string map keeps its narrow type, and so
 * PHPStan flags a new export that this method does not answer for.
 */
public static function areaFor(string $resource): ?Area
{
    return match ($resource) {
        'books', 'publishers', 'writers' => Area::Library,
        default => null,
    };
}
```

- [ ] **Step 10: Check the area in the controller**

In `app/Http/Controllers/Admin/ExportController::store()`:

```php
public function store(Request $request, string $resource, ExportResource $exportResource): RedirectResponse
{
    $area = ExportResource::areaFor($resource);

    // One route serves several resources, so the route group cannot guard this;
    // the area is resolved per resource instead. Unknown and forbidden both 404,
    // which is also what the gate returns.
    abort_if($area === null, 404);
    abort_unless($request->user()?->can('access-area', $area) ?? false, 404);

    $path = $exportResource->handle($resource);

    // unchanged from here
}
```

The `array_key_exists` guard is gone: `areaFor()` returning null already covers
an unknown resource, and one guard is harder to half-remove than two.

- [ ] **Step 11: Run the leak tests**

Run: `php artisan test --filter=LeakSurfaceTest`
Expected: PASS.

- [ ] **Step 12: Run everything**

Run: `php artisan test`
Expected: PASS.

`ParityTest` at line 114 iterates `Navigation::clusters()` and needs no change —
it still sees all six. `AppServiceProviderTest` and the navigation shape tests
are unaffected because `clusters()` keeps its signature.

- [ ] **Step 13: Build the frontend and look at it**

Run: `npm run build`
Then sign in as each user and check the dashboard renders without an empty
section or a dead link.

- [ ] **Step 14: Commit**

```bash
composer lint && composer types:check
git add app/ resources/js/Pages/Dashboard.vue tests/
git commit -m "feat: hide areas a user cannot reach from navigation, search, dashboard and exports" -- app/ resources/js/Pages/Dashboard.vue tests/
```

---

### Task 7: Seed the member, and notice the fifth surface

**Files:**
- Modify: `database/seeders/DatabaseSeeder.php`, `.env.example`, `config/app.php`
- Test: `tests/Feature/Auth/LeakSurfaceTest.php` (append), `tests/Feature/DatabaseSeederTest.php`

**Interfaces:**
- Consumes: everything above.
- Produces: a seeded member account, and a test that fails when a new resource forgets its area.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Auth/LeakSurfaceTest.php`. The tests below use
`Category`, `Debt` and `UtilityBill`, so add those imports to the file's `use`
block alongside the ones already there.

```php
/**
 * The fifth surface, whatever it turns out to be.
 *
 * Every resource that appears in the navigation must also appear in the search
 * registry's area map, and every export must have an area. A new resource that
 * skips one of these is invisible to the matrix test, because the matrix only
 * knows about routes.
 */
it('gives every navigation item a route inside its cluster area', function (): void {
    $member = User::factory()->member()->create(['email' => 'her@example.test']);

    foreach (Navigation::clusters() as $cluster) {
        foreach ($cluster['items'] as $item) {
            $reachable = $member->canAccess($cluster['area']);

            $response = $this->actingAs($member)->get(route($item['route']));

            $reachable
                ? expect($response->status())->not->toBe(404, "{$item['label']} is listed but unreachable")
                : expect($response->status())->toBe(404, "{$item['label']} is hidden but still reachable");
        }
    }
});
```

Append these too — the spec calls for both, and neither is covered by the
matrix, which only knows whether a route answers.

```php
/**
 * Categories are General, but the books form needs them. She must be able to
 * pick and create one from inside Library without the Categories screen ever
 * being hers.
 */
it('lets the member use categories from inside the books form', function (): void {
    $category = Category::factory()->create(['name' => 'Zzshared']);
    $member = User::factory()->member()->create(['email' => 'her@example.test']);

    $this->actingAs($member)
        ->get(route('admin.books.create'))
        ->assertOk()
        ->assertSee('Zzshared');

    $this->actingAs($member)
        ->get(route('admin.categories.index'))
        ->assertNotFound();

    expect($category->exists)->toBeTrue();
});

/**
 * Reading is not the point of her account. These are the two writes she does
 * most, and they must work end to end.
 */
it('lets the member pay a utility bill', function (): void {
    $member = User::factory()->member()->create(['email' => 'her@example.test']);
    $bill = UtilityBill::factory()->unpaid()->create();

    $this->actingAs($member)
        ->post(route('admin.utility-bills.pay', $bill))
        ->assertRedirect();

    expect($bill->fresh()->paid_at)->not->toBeNull();
});

it('lets the member record a debt payment', function (): void {
    $member = User::factory()->member()->create(['email' => 'her@example.test']);
    $debt = Debt::factory()->create(['amount' => 1000, 'paid_amount' => 0]);

    $this->actingAs($member)
        ->post(route('admin.debts.pay', $debt), ['amount' => 250])
        ->assertRedirect();

    expect($debt->fresh()->paid_amount)->toEqual(250);
});

it('keeps the member out of every blog and work write route', function (string $routeName): void {
    $member = User::factory()->member()->create(['email' => 'her@example.test']);

    $this->actingAs($member)
        ->post(route($routeName))
        ->assertNotFound();
})->with([
    'admin.posts.store',
    'admin.clients.store',
    'admin.projects.store',
    'admin.invoices.store',
    'admin.tasks.store',
]);
```

**Before writing these, check the factory states and column names against the
models** — `UtilityBill::factory()->unpaid()` and `Debt`'s `paid_amount` are
what the utilities and budget tasks created; if a name differs, follow the model
rather than this plan, and fix the plan's line.

Create `tests/Feature/DatabaseSeederTest.php` if it does not exist, or append:

```php
it('seeds the member when an address is configured', function (): void {
    config(['app.admin_email' => 'owner@example.test', 'app.member_email' => 'her@example.test']);

    $this->seed(DatabaseSeeder::class);

    $member = User::query()->where('email', 'her@example.test')->sole();

    expect($member->role)->toBe(UserRole::Member)
        ->and($member->canAccess(Area::Budget))->toBeTrue()
        ->and($member->canAccess(Area::Work))->toBeFalse();
});

it('seeds only the owner when no member address is configured', function (): void {
    config(['app.admin_email' => 'owner@example.test', 'app.member_email' => null]);

    $this->seed(DatabaseSeeder::class);

    expect(User::query()->count())->toBe(1);
});
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php artisan test --filter="LeakSurfaceTest|DatabaseSeederTest"`
Expected: FAIL — `app.member_email` is not configured.

- [ ] **Step 3: Add the config key**

In `config/app.php`, beside `admin_email`:

```php
'member_email' => env('MEMBER_EMAIL'),
```

In `.env.example`, beside `ADMIN_EMAIL=`:

```dotenv
# Optional. A second account with access to Budget, Library and Utilities only.
MEMBER_EMAIL=
```

- [ ] **Step 4: Seed her**

In `database/seeders/DatabaseSeeder::run()`, after the admin user:

```php
User::factory()
    ->admin()
    ->create([
        'name' => config('app.admin_name'),
        'email' => config('app.admin_email'),
    ]);

$memberEmail = config('app.member_email');

if (is_string($memberEmail) && $memberEmail !== '') {
    User::factory()
        ->member()
        ->create([
            'name' => config('app.member_name', 'Member'),
            'email' => $memberEmail,
        ]);
}
```

Add `'member_name' => env('MEMBER_NAME', 'Member'),` to `config/app.php` and
`MEMBER_NAME=` to `.env.example` alongside it.

The admin now gets an explicit `admin()` state rather than relying on the
`ADMIN_EMAIL` bypass, so the seeded database is correct even if the environment
variable is later changed.

- [ ] **Step 5: Run the tests**

Run: `php artisan test --filter="LeakSurfaceTest|DatabaseSeederTest"`
Expected: PASS.

- [ ] **Step 6: Run everything, including the seeder end to end**

Run: `php artisan migrate:fresh --seed && php artisan test`
Expected: both succeed.

- [ ] **Step 7: Set your own environment**

Add `MEMBER_EMAIL` and `MEMBER_NAME` to your local `.env`. This is the only step
in the plan that touches an untracked file — do not commit `.env`.

- [ ] **Step 8: Commit**

```bash
composer lint && composer types:check
git add config/app.php .env.example database/seeders/DatabaseSeeder.php tests/
git commit -m "feat: seed the member account" -- config/app.php .env.example database/seeders/DatabaseSeeder.php tests/
```

---

### Task 8: Release

**Files:**
- Modify: `CHANGELOG.md`, `composer.json`, `package.json`

- [ ] **Step 1: Run the full check**

```bash
composer rector:fix
composer ci:check
echo "exit: $?"
```

Expected: exit 0. Rector is run in fix mode first, because a dry run that finds
changes fails the check and its output is easy to lose in a pipe.

- [ ] **Step 2: Confirm the counts**

Run: `php artisan test --compact | tail -3`
Expected: the suite total has grown by roughly 300 — the 272 matrix cases plus
the unit, gate, request and leak-surface tests.

- [ ] **Step 3: Write the changelog entry**

Add to `CHANGELOG.md` under a new `## 0.9.0` heading, dated today:

```markdown
### Added

- A `role` on each user, `admin` or `member`, mapped to six areas: Blog,
  Budget, Work, Library, Utilities and General.
- A `MEMBER_EMAIL` account seeded with access to Budget, Library and Utilities.
- `AdminRequest`, an abstract base that makes every admin request declare its
  area before it can be written.
- A matrix test covering every admin route against both roles.

### Changed

- Admin routes are grouped by area and denied with a 404 rather than a 403, so a
  hidden area is indistinguishable from one that does not exist.
- The navigation, the command palette, the dashboard and the exports each show
  only the areas the signed-in user can reach.

### Removed

- The `access-admin` gate, replaced by `access-panel` and `access-area`.
```

- [ ] **Step 4: Bump the version**

Set `"version": "0.9.0"` in both `composer.json` and `package.json`.

- [ ] **Step 5: Commit and tag**

```bash
git add CHANGELOG.md composer.json package.json
git commit -m "chore: release 0.9.0" -- CHANGELOG.md composer.json package.json
git tag v0.9.0
git push && git push --tags
```

- [ ] **Step 6: Watch CI**

Run: `gh run watch`
Expected: all six jobs green. The matrix test is the slowest addition; if the
Linux runner times out where macOS did not, check `tests/Feature/Auth/AreaAccessMatrixTest.php`
is not creating a model per case where a shared one would do.
