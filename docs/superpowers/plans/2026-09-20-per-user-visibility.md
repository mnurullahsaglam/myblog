# Per-User Visibility Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** She sees what the money was without seeing whose work it came from, chooses her own colours, and never sees a screen you are still building — and you can check all of that without signing in as her.

**Architecture:** One request-scoped `AccessProfile` answers every "may this person see it" question — areas, abilities and feature flags. Field visibility is enforced by filtering three methods (`columns()`, `filters()`, `fields()`), which every other method on the table and form contracts already reads. View-as substitutes the profile; it is built first because retrofitting it under finished security code would mean rewriting that code.

**Tech Stack:** PHP 8.5, Laravel 13, Pennant, Pest 5, Inertia + Vue 3, PrimeVue.

**Spec:** `docs/superpowers/specs/2026-09-20-per-user-visibility-design.md`

## Global Constraints

- **Work directly on `master`.** No feature branches. Commit messages carry no AI attribution trailer.
- **Conventional Commits:** `feat:`, `fix:`, `chore:`, `refactor:`, `test:`, `docs:`.
- **One migration file per table.** Fold the `preferences` column into `create_users_table`.
- **Every class is `final`** except the six abstract bases listed in `tests/Feature/ArchTest.php`.
- **PHPStan `level: max`.** No baseline, no `ignoreErrors`, no casting `mixed`.
- **Type coverage stays at 100%.**
- **Hiding happens server-side.** A field removed only in Vue still ships in the Inertia payload. Assert against the payload, never the rendered page.
- **Refusal is 404**, consistent with Spec A.
- **`ADMIN_EMAIL` always wins.** It bypasses role, abilities and flags alike.
- **Every commit is green.** If a change cannot stand alone, land it with the change that completes it.
- **Gates before every push:** `composer ci:check`, `npm run lint:check`, `npm run format:check`. Check the exit status, not the tail of a pipe.

---

## File Structure

**Created:**

| Path | Responsibility |
| --- | --- |
| `app/Enums/Ability.php` | Named capabilities, mirroring `Area` |
| `app/Support/Access/AccessProfile.php` | The one resolver: areas, abilities, flags, preview state |
| `app/Providers/AccessServiceProvider.php` | Binds the profile per request |
| `app/Http/Controllers/Admin/PreviewController.php` | Start and stop view-as |
| `app/Http/Middleware/RefuseWritesWhilePreviewing.php` | A preview never writes |
| `app/Support/Features.php` | Flag names in one place |
| `tests/Unit/AbilityTest.php` | The enum and the role mapping |
| `tests/Feature/Auth/AccessProfileTest.php` | The resolver |
| `tests/Feature/Auth/FieldVisibilityTest.php` | The security core: payloads, sorting, filtering, bulk |
| `tests/Feature/Admin/Budget/ProtectedIncomeTest.php` | Read-only incomes |
| `tests/Feature/Admin/AppearanceTest.php` | Per-user appearance |
| `tests/Feature/Auth/FeatureFlagTest.php` | Flags |
| `tests/Feature/Auth/PreviewTest.php` | View-as, by payload comparison |

**Modified:**

| Path | Change |
| --- | --- |
| `app/Enums/UserRole.php` | `abilities()` beside `areas()` |
| `app/Models/User.php` | `abilities()`, `preferences` cast |
| `app/Tables/Column.php`, `app/Tables/Filter.php`, `app/Forms/Field.php` | `hiddenWithout()` |
| `app/Tables/ResourceTable.php`, `app/Forms/ResourceForm.php` | Filter at the chokepoints |
| `app/Tables/Definitions/IncomeTable.php`, `app/Forms/Definitions/IncomeForm.php` | Hide the client |
| `app/Http/Requests/Admin/IncomeRequest.php` | Refuse the key |
| `app/Http/Controllers/Admin/AdminResourceController.php` | Per-record guard |
| `app/Http/Controllers/Admin/Budget/IncomeController.php` | The one override |
| `app/Providers/AppServiceProvider.php` | Gates delegate to the profile |
| `app/Http/Middleware/EnsureAreaAccess.php` | Asks the profile |
| `app/Support/Navigation.php` | Flags, and the profile |
| `app/Support/Theme/Appearance.php` | Per-user resolution |
| `app/Http/Controllers/Admin/ProfileController.php` | Her appearance controls |
| `database/migrations/0001_01_01_000000_create_users_table.php` | `preferences` |
| `routes/admin.php` | Preview routes, flag-gated groups |

---

### Task 1: The ability, and the one resolver

**Files:**
- Create: `app/Enums/Ability.php`, `app/Support/Access/AccessProfile.php`, `app/Providers/AccessServiceProvider.php`
- Modify: `app/Enums/UserRole.php`, `app/Models/User.php`, `bootstrap/providers.php`
- Test: `tests/Unit/AbilityTest.php`, `tests/Feature/Auth/AccessProfileTest.php`

**Interfaces:**
- Consumes: `Area`, `UserRole` from Spec A.
- Produces: `Ability` enum; `UserRole::abilities(): array<int, Ability>`; `User::abilities(): array<int, Ability>`; `AccessProfile` with `areas()`, `allows(Ability)`, `feature(string)`, `isPreviewing()`, and the static builders `forUser()` and `preview()`.

**Why the resolver comes first.** View-as must substitute every access answer at once. If abilities and flags are built asking `$user` directly, the toggle would have to rewrite them later — and a half-substituted preview reports a screen as hidden while its route stays open, which is worse than no preview.

- [ ] **Step 1: Write the failing enum test**

Create `tests/Unit/AbilityTest.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\Ability;
use App\Enums\UserRole;

it('gives an admin every ability', function (): void {
    expect(UserRole::Admin->abilities())->toEqualCanonicalizing(Ability::cases());
});

it('gives a member none', function (): void {
    expect(UserRole::Member->abilities())->toBe([]);
});

/**
 * Abilities are named for why something is hidden, not for who is excluded, so
 * a value that names a role would be a design error rather than a typo.
 */
it('names abilities after what they permit', function (Ability $ability): void {
    expect($ability->value)->not->toContain('member')
        ->and($ability->value)->not->toContain('admin')
        ->and($ability->value)->toMatch('/^[a-z][a-z-]+$/');
})->with(fn (): array => array_map(fn (Ability $a): array => [$a], Ability::cases()));
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --filter=AbilityTest`
Expected: FAIL — `Class "App\Enums\Ability" not found`.

- [ ] **Step 3: Write the enum and the role mapping**

Create `app/Enums/Ability.php`:

```php
<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A named capability, mirroring Area.
 *
 * Named for what it permits rather than who is excluded, so adding a second
 * member later needs no change to any field that references one.
 */
enum Ability: string
{
    case SeeClientIdentity = 'see-client-identity';
}
```

In `app/Enums/UserRole.php`, beside `areas()`:

```php
/**
 * @return array<int, Ability>
 */
public function abilities(): array
{
    return match ($this) {
        self::Admin => Ability::cases(),
        self::Member => [],
    };
}
```

In `app/Models/User.php`, beside `areas()`:

```php
/**
 * @return array<int, Ability>
 */
public function abilities(): array
{
    if ($this->isAdmin()) {
        return Ability::cases();
    }

    $stored = $this->getAttributes()['role'] ?? null;

    return (is_string($stored) ? UserRole::tryFrom($stored) : null)?->abilities() ?? [];
}
```

The raw read matches `areas()` and for the same reason: the cast throws on a
value the enum does not know, and an unreadable role must mean no access rather
than a 500 on every page.

- [ ] **Step 4: Write the failing resolver test**

Create `tests/Feature/Auth/AccessProfileTest.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\Ability;
use App\Enums\Area;
use App\Enums\UserRole;
use App\Models\User;
use App\Support\Access\AccessProfile;

beforeEach(function (): void {
    config(['app.admin_email' => 'owner@example.test']);
});

it('answers for an admin', function (): void {
    $profile = AccessProfile::forUser(User::factory()->admin()->create(['email' => 'other@example.test']));

    expect($profile->areas())->toEqualCanonicalizing(Area::cases())
        ->and($profile->allows(Ability::SeeClientIdentity))->toBeTrue()
        ->and($profile->isPreviewing())->toBeFalse();
});

it('answers for a member', function (): void {
    $profile = AccessProfile::forUser(User::factory()->member()->create(['email' => 'her@example.test']));

    expect($profile->areas())->toEqualCanonicalizing([Area::Budget, Area::Utilities, Area::Library])
        ->and($profile->allows(Ability::SeeClientIdentity))->toBeFalse();
});

it('answers nothing for a guest', function (): void {
    $profile = AccessProfile::forUser(null);

    expect($profile->areas())->toBe([])
        ->and($profile->allows(Ability::SeeClientIdentity))->toBeFalse();
});

/**
 * The lockout guarantee, carried into abilities: the configured owner is an
 * admin whatever the column says.
 */
it('keeps the configured owner privileged whatever the column says', function (): void {
    $owner = User::factory()->member()->create(['email' => 'owner@example.test']);

    expect(AccessProfile::forUser($owner)->allows(Ability::SeeClientIdentity))->toBeTrue();
});

it('answers as the previewed role, and says it is previewing', function (): void {
    $owner = User::factory()->admin()->create(['email' => 'owner@example.test']);

    $profile = AccessProfile::preview($owner, UserRole::Member);

    expect($profile->areas())->toEqualCanonicalizing([Area::Budget, Area::Utilities, Area::Library])
        ->and($profile->allows(Ability::SeeClientIdentity))->toBeFalse()
        ->and($profile->isPreviewing())->toBeTrue();
});

/**
 * A preview must not be able to preview an admin: that would be a way to climb
 * out of a restricted session rather than a way to look into one.
 */
it('refuses to preview a role that is not narrower', function (): void {
    $owner = User::factory()->admin()->create(['email' => 'owner@example.test']);

    expect(fn (): AccessProfile => AccessProfile::preview($owner, UserRole::Admin))
        ->toThrow(InvalidArgumentException::class);
});

it('is bound once per request', function (): void {
    $owner = User::factory()->admin()->create(['email' => 'owner@example.test']);

    $this->actingAs($owner)->get(route('admin.dashboard'));

    expect(app(AccessProfile::class))->toBe(app(AccessProfile::class));
});
```

- [ ] **Step 5: Write the resolver**

Create `app/Support/Access/AccessProfile.php`:

```php
<?php

declare(strict_types=1);

namespace App\Support\Access;

use App\Enums\Ability;
use App\Enums\Area;
use App\Enums\UserRole;
use App\Models\User;
use InvalidArgumentException;

/**
 * Everything the panel is allowed to show this request, in one place.
 *
 * Areas, abilities and feature flags are asked of this object rather than of the
 * user, so that view-as can substitute all three at once. A preview that
 * swapped only some of them would report a screen as hidden while its route
 * stayed open, which is worse than having no preview at all.
 */
final class AccessProfile
{
    /**
     * @param  array<int, Area>  $areas
     * @param  array<int, Ability>  $abilities
     */
    private function __construct(
        private readonly ?User $user,
        private readonly array $areas,
        private readonly array $abilities,
        private readonly bool $previewing,
    ) {}

    public static function forUser(?User $user): self
    {
        if (! $user instanceof User) {
            return new self(null, [], [], false);
        }

        return new self($user, $user->areas(), $user->abilities(), false);
    }

    /**
     * Render as a narrower role would see it.
     *
     * The role must grant strictly less than the viewer already has, so this can
     * only ever be used to look down.
     */
    public static function preview(User $user, UserRole $role): self
    {
        $areas = $role->areas();

        if (count($areas) >= count($user->areas())) {
            throw new InvalidArgumentException('A preview may only narrow what is visible.');
        }

        return new self($user, $areas, $role->abilities(), true);
    }

    /**
     * @return array<int, Area>
     */
    public function areas(): array
    {
        return $this->areas;
    }

    public function canAccess(Area $area): bool
    {
        return in_array($area, $this->areas, true);
    }

    public function allows(Ability $ability): bool
    {
        return in_array($ability, $this->abilities, true);
    }

    public function isPreviewing(): bool
    {
        return $this->previewing;
    }

    public function user(): ?User
    {
        return $this->user;
    }
}
```

`feature()` is added in Task 6, when there are flags for it to answer about.
That task also adds two constructor parameters — a `privileged` bool and a
`flags` map — and updates both `forUser()` and `preview()` to pass them. Expect
this constructor to change; it is not final as written.

- [ ] **Step 6: Bind it per request**

```bash
php artisan make:provider AccessServiceProvider --no-interaction
```

```php
public function register(): void
{
    // Scoped, not singleton: one profile per request, rebuilt for the next.
    $this->app->scoped(AccessProfile::class, function (Application $app): AccessProfile {
        $request = $app->make('request');
        $user = $request->user();

        $previewRole = $request->hasSession()
            ? UserRole::tryFrom((string) $request->session()->get('preview_role'))
            : null;

        if ($user instanceof User && $previewRole instanceof UserRole) {
            try {
                return AccessProfile::preview($user, $previewRole);
            } catch (InvalidArgumentException) {
                // A session carrying a role that is no longer narrower is
                // ignored rather than trusted.
                return AccessProfile::forUser($user);
            }
        }

        return AccessProfile::forUser($user);
    });
}
```

Register it in `bootstrap/providers.php` beside the existing providers.

- [ ] **Step 7: Run the tests**

Run: `php artisan test --filter="AbilityTest|AccessProfileTest"`
Expected: PASS.

- [ ] **Step 8: Run everything**

Run: `php artisan test`
Expected: PASS. Nothing consumes the profile yet, so nothing else changes.

- [ ] **Step 9: Commit**

```bash
composer lint && composer types:check
git add app/ bootstrap/providers.php tests/
git commit -m "feat: add abilities and the access profile" -- app/ bootstrap/providers.php tests/
```

---

### Task 2: Route every access question through the profile

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`, `app/Http/Middleware/EnsureAreaAccess.php`, `app/Support/Navigation.php`, `app/Http/Middleware/HandleInertiaRequests.php`, `app/Http/Controllers/Admin/DashboardController.php`, `app/Support/GlobalSearch.php`
- Test: `tests/Feature/Auth/AccessProfileTest.php` (append)

**Interfaces:**
- Consumes: `AccessProfile`.
- Produces: `Navigation::forProfile(AccessProfile)`, `GlobalSearch::query(string, AccessProfile, int)`.

**Why now.** Spec A's four leak surfaces each ask the user directly. Leaving
them that way would mean view-as shows the full navigation while the routes are
restricted. Moving them is mechanical and must happen before anything new is
built on the old shape.

- [ ] **Step 1: Point the gates at the profile**

In `app/Providers/AppServiceProvider.php`:

```php
Gate::define('access-area', function (User $user, Area|string $area): Response {
    $area = $area instanceof Area ? $area : Area::tryFrom($area);

    return $area instanceof Area && app(AccessProfile::class)->canAccess($area)
        ? Response::allow()
        : Response::denyAsNotFound();
});

Gate::define('access-panel', fn (User $user): bool => app(AccessProfile::class)->areas() !== []);

Gate::define('has-ability', fn (User $user, Ability $ability): bool => app(AccessProfile::class)->allows($ability));
```

**Warning.** The gate closures still take `$user` because Laravel requires it,
but they no longer read it — the profile is built from the *request's* user. That
means `Gate::forUser($someoneElse)->allows('access-area', ...)` no longer answers
about `$someoneElse`. Two existing tests use that form; convert them to
`actingAs` plus a real request, or to `AccessProfile::forUser($other)` directly.
Check `tests/Feature/Auth/AreaGateTest.php` first and fix it in this step, not
later.

- [ ] **Step 2: Point the middleware at the profile**

In `app/Http/Middleware/EnsureAreaAccess.php`, replace the user check:

```php
abort_unless(app(AccessProfile::class)->canAccess($resolved), 404);
```

The `$request->user()` null check goes away: a guest has an empty profile, so the
same line covers it.

- [ ] **Step 3: Point navigation at the profile**

In `app/Support/Navigation.php`, replace `forUser(User $user)` with:

```php
public static function forProfile(AccessProfile $profile): array
{
    return array_values(array_filter(
        self::clusters(),
        fn (array $cluster): bool => $profile->canAccess($cluster['area']),
    ));
}
```

and in `HandleInertiaRequests`:

```php
'navigation' => fn (): array => Navigation::forProfile(app(AccessProfile::class)),
```

- [ ] **Step 4: Point search and the dashboard at the profile**

`GlobalSearch::query(string $term, AccessProfile $profile, int $perResource = self::PER_RESOURCE)`,
with the loop asking `$profile->canAccess($entry['area'])`.

`SearchController` passes `app(AccessProfile::class)`; the `abort_if` on a null
user stays, because the route is still behind `auth`.

`DashboardController` asks the profile rather than the user for each of its four
conditions.

- [ ] **Step 5: Update the callers in tests**

`tests/Feature/Admin/GlobalSearchTest.php` and `tests/Feature/Auth/LeakSurfaceTest.php`
pass a user to `GlobalSearch::query`. Replace with
`AccessProfile::forUser($user)`.

`LeakSurfaceTest` also calls `Navigation::forUser($user)`; replace with
`Navigation::forProfile(AccessProfile::forUser($user))`.

- [ ] **Step 6: Append the substitution test**

In `tests/Feature/Auth/AccessProfileTest.php`:

```php
/**
 * The point of the refactor: swapping the profile changes every answer at once.
 */
it('changes navigation, search and the dashboard together', function (): void {
    $owner = User::factory()->admin()->create(['email' => 'owner@example.test']);
    $narrow = AccessProfile::preview($owner, UserRole::Member);

    app()->instance(AccessProfile::class, $narrow);

    expect(collect(Navigation::forProfile($narrow))->pluck('label')->all())
        ->not->toContain('Work');

    $this->actingAs($owner)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page->missing('work'));
});
```

- [ ] **Step 7: Run everything**

Run: `php artisan test`
Expected: PASS, including the 136-case access matrix and `LeakSurfaceTest`.

If the matrix fails, the middleware and the gate disagree — both must read the
profile, or an area group and a request will answer differently.

- [ ] **Step 8: Commit**

```bash
composer lint && composer types:check
git add app/ tests/
git commit -m "refactor: ask the access profile rather than the user" -- app/ tests/
```

---

### Task 3: `hiddenWithout()`, and the three chokepoints

**Files:**
- Modify: `app/Tables/Column.php`, `app/Tables/Filter.php`, `app/Forms/Field.php`, `app/Tables/ResourceTable.php`, `app/Forms/ResourceForm.php`
- Test: `tests/Feature/Auth/FieldVisibilityTest.php`

**Interfaces:**
- Consumes: `Ability`, `AccessProfile`.
- Produces: `Column::hiddenWithout(Ability)`, `Filter::hiddenWithout(Ability)`, `Field::hiddenWithout(Ability)`, and `visible()` on each; filtering inside `ResourceTable::columns()`/`filters()` and `ResourceForm::fields()`.

**Why three methods closes everything.** `ResourceTable::schema()`, `rows()`,
`applySort()` and `applyFilters()` all iterate `$this->columns()` or
`$this->filters()`. `ResourceForm::schema()`, `values()`, `bulkEditableFields()`,
`bulkValueRules()` and `relationKeys()` all iterate `$this->fields()`. Filtering
the three definition methods therefore closes the schema, the serialised cells,
sorting, filtering, create, edit, show and bulk edit at once — and nothing else
has to remember.

**The subtlety.** The filtering must wrap the *subclass* methods without the
subclasses knowing. `columns()` is `abstract protected` and every definition
implements it, so the base class cannot simply filter its own method. Rename the
abstract to `definedColumns()` — no. That renames 16 files.

Instead, keep `columns()` abstract and add a final `visibleColumns()` used by
everything in the base class:

```php
/**
 * @return array<int, Column>
 */
final protected function visibleColumns(): array
{
    $profile = app(AccessProfile::class);

    return array_values(array_filter(
        $this->columns(),
        fn (Column $column): bool => $column->visibleTo($profile),
    ));
}
```

and replace every internal `$this->columns()` with `$this->visibleColumns()`.
Same for `visibleFilters()` and `visibleFields()`. The definitions keep their
current shape; only the base classes change.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Auth/FieldVisibilityTest.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\Ability;
use App\Forms\Definitions\IncomeForm;
use App\Models\Client;
use App\Models\Income;
use App\Models\User;
use App\Support\Access\AccessProfile;
use App\Tables\Definitions\IncomeTable;
use Illuminate\Http\Request;

beforeEach(function (): void {
    config(['app.admin_email' => 'owner@example.test']);
    $this->owner = User::factory()->admin()->create(['email' => 'owner@example.test']);
    $this->member = User::factory()->member()->create(['email' => 'her@example.test']);
});

function asProfile(User $user): void
{
    app()->instance(AccessProfile::class, AccessProfile::forUser($user));
}

it('keeps a hidden column out of the schema', function (): void {
    asProfile($this->member);

    $keys = array_column((new IncomeTable)->schema()['columns'], 'key');

    expect($keys)->not->toContain('client.title');
});

it('keeps it in the schema for the admin', function (): void {
    asProfile($this->owner);

    expect(array_column((new IncomeTable)->schema()['columns'], 'key'))->toContain('client.title');
});

/**
 * The assertion that matters. A column removed only in Vue still travels in the
 * Inertia payload, where anyone can read it from devtools.
 */
it('never serialises the hidden value into the payload', function (): void {
    $client = Client::factory()->create(['title' => 'Zzsecret Client']);
    Income::factory()->create(['client_id' => $client->id]);

    $response = $this->actingAs($this->member)->get(route('admin.incomes.index'));

    expect($response->getContent())->not->toContain('Zzsecret Client');
});

it('does serialise it for the admin', function (): void {
    $client = Client::factory()->create(['title' => 'Zzsecret Client']);
    Income::factory()->create(['client_id' => $client->id]);

    expect($this->actingAs($this->owner)->get(route('admin.incomes.index'))->getContent())
        ->toContain('Zzsecret Client');
});

it('keeps a hidden filter out of the schema', function (): void {
    asProfile($this->member);

    expect(array_column((new IncomeTable)->schema()['filters'], 'key'))->not->toContain('client_id');
});

/**
 * A filter she cannot see but can still apply would let her binary-search which
 * incomes belong to a client, which is the thing being prevented.
 */
it('ignores a hidden filter applied by hand', function (): void {
    $client = Client::factory()->create();
    Income::factory()->count(2)->create(['client_id' => $client->id]);
    Income::factory()->count(3)->create(['client_id' => null]);

    asProfile($this->member);

    $filtered = (new IncomeTable)->rows(Request::create('/', 'GET', ['filter' => ['client_id' => [$client->id]]]));

    expect($filtered->total())->toBe(5, 'the hidden filter narrowed the result');
});

it('honours the filter for the admin', function (): void {
    $client = Client::factory()->create();
    Income::factory()->count(2)->create(['client_id' => $client->id]);
    Income::factory()->count(3)->create(['client_id' => null]);

    asProfile($this->owner);

    expect((new IncomeTable)->rows(Request::create('/', 'GET', ['filter' => ['client_id' => [$client->id]]]))->total())
        ->toBe(2);
});

it('ignores sorting by a hidden column applied by hand', function (): void {
    Income::factory()->count(3)->create();

    asProfile($this->member);

    $rows = (new IncomeTable)->rows(Request::create('/', 'GET', ['sort' => 'client.title']));

    expect($rows->total())->toBe(3);
});

it('keeps a hidden field out of the form schema and values', function (): void {
    asProfile($this->member);

    $income = Income::factory()->create();
    $form = new IncomeForm;

    expect(array_column($form->schema()['fields'], 'key'))->not->toContain('client_id')
        ->and($form->values($income))->not->toHaveKey('client_id');
});

it('keeps a hidden field out of the bulk editable whitelist', function (): void {
    asProfile($this->member);

    expect((new IncomeForm)->bulkEditableFields())->not->toContain('client_id');
});

it('prohibits a bulk value for a hidden field', function (): void {
    asProfile($this->member);

    expect((new IncomeForm)->bulkValueRules('client_id')['value'])->toContain('prohibited');
});

it('refuses a bulk edit of the hidden field over HTTP', function (): void {
    $incomes = Income::factory()->count(2)->create(['client_id' => null]);
    $client = Client::factory()->create();

    $this->actingAs($this->member)
        ->from(route('admin.incomes.index'))
        ->patch(route('admin.incomes.bulk-update'), [
            'ids' => $incomes->modelKeys(),
            'field' => 'client_id',
            'value' => $client->id,
        ])
        ->assertSessionHasErrors('field');

    expect(Income::whereKey($incomes->modelKeys())->pluck('client_id')->all())->each->toBeNull();
});

it('covers every ability', function (Ability $ability): void {
    expect(AccessProfile::forUser($this->owner)->allows($ability))->toBeTrue()
        ->and(AccessProfile::forUser($this->member)->allows($ability))->toBeFalse();
})->with(fn (): array => array_map(fn (Ability $a): array => [$a], Ability::cases()));
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --filter=FieldVisibilityTest`
Expected: FAIL — `hiddenWithout()` does not exist and the client is visible to
everyone.

- [ ] **Step 3: Add the declaration to all three classes**

The same pair of members goes on `Column`, `Filter` and `Field`. On `Column`:

```php
private ?Ability $requires = null;

/**
 * Hidden from anyone without this ability — everywhere, not only in the table
 * header. The value never reaches the browser.
 */
public function hiddenWithout(Ability $ability): self
{
    $this->requires = $ability;

    return $this;
}

public function visibleTo(AccessProfile $profile): bool
{
    return ! $this->requires instanceof Ability || $profile->allows($this->requires);
}
```

`Filter` and `Field` take the identical pair. They are separate classes with no
shared base, so this is three copies of six lines; a trait would be a seventh
file for less than it saves.

- [ ] **Step 4: Filter at the chokepoints**

In `ResourceTable`, add `visibleColumns()` and `visibleFilters()` as shown above,
then replace **every** internal use:

- `schema()` — both `columns()` and `filters()`
- `rows()` — the `$columns` local
- `applyFilters()` — the loop
- `isSortable()` — the `array_any`
- `search()` and any other method that reads `columns()`

Run `grep -n 'this->columns()\|this->filters()' app/Tables/ResourceTable.php`
afterwards; the only remaining hits should be inside `visibleColumns()` and
`visibleFilters()` themselves.

In `ResourceForm`, add `visibleFields()` and replace every `$this->fields()` in
`schema()`, `values()`, `placeholders()`, `bulkEditableFields()`,
`bulkValueRules()` and `relationKeys()`. Same grep check.

**`bulkValueRules()` needs one extra line.** It returns
`['value' => ['prohibited']]` when it falls through without matching a key, which
is exactly right for a hidden field — but only if the loop is over
`visibleFields()`. Confirm that is what the fall-through does rather than
assuming.

- [ ] **Step 5: Hide the client**

In `app/Tables/Definitions/IncomeTable.php`:

```php
Column::text('client.title')->label('Client')->default('—')
    ->toggleable(hiddenByDefault: true)
    ->hiddenWithout(Ability::SeeClientIdentity),
```

and the filter:

```php
Filter::relationship('client_id', 'client', 'title')->label('Client')->multiple()
    ->hiddenWithout(Ability::SeeClientIdentity),
```

In `app/Forms/Definitions/IncomeForm.php`:

```php
Field::relationship('client_id', 'client', 'title')->label('Client')->searchable()
    ->hiddenWithout(Ability::SeeClientIdentity),
```

`invoice_id` stays visible, as decided. Note in the file why, so the next reader
does not "fix" it:

```php
// Invoice numbers stay visible deliberately. Hiding them was considered and
// declined; see the spec's accepted-risk table.
```

- [ ] **Step 6: Run the tests**

Run: `php artisan test --filter=FieldVisibilityTest`
Expected: PASS.

- [ ] **Step 7: Run everything**

Run: `php artisan test`
Expected: PASS. Existing resource tests act as an admin, so they still see every
column.

- [ ] **Step 8: Commit**

```bash
composer lint && composer types:check
git add app/ tests/
git commit -m "feat: hide fields from anyone without the ability they require" -- app/ tests/
```

---

### Task 4: Incomes that have a client

**Files:**
- Modify: `app/Http/Controllers/Admin/AdminResourceController.php`, `app/Http/Controllers/Admin/Budget/IncomeController.php`, `app/Http/Requests/Admin/IncomeRequest.php`, `app/Tables/ResourceTable.php`, `resources/js/Components/Table/ResourceTable.vue`
- Test: `tests/Feature/Admin/Budget/ProtectedIncomeTest.php`

**Interfaces:**
- Produces: `AdminResourceController::isRecordEditable(Model): bool` (default `true`), and an `editable` flag on every serialised row.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/Budget/ProtectedIncomeTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\Income;
use App\Models\User;

beforeEach(function (): void {
    config(['app.admin_email' => 'owner@example.test']);
    $this->owner = User::factory()->admin()->create(['email' => 'owner@example.test']);
    $this->member = User::factory()->member()->create(['email' => 'her@example.test']);
    $this->client = Client::factory()->create();
});

it('lets her edit an income with no client', function (): void {
    $income = Income::factory()->create(['client_id' => null, 'description' => 'Before']);

    $this->actingAs($this->member)->get(route('admin.incomes.edit', $income))->assertOk();

    $this->actingAs($this->member)
        ->put(route('admin.incomes.update', $income), [
            'amount' => 100,
            'currency' => 'TRY',
            'date' => now()->toDateString(),
            'description' => 'After',
        ])
        ->assertRedirect();

    expect($income->fresh()->description)->toBe('After');
});

it('refuses to let her edit an income with a client', function (string $verb): void {
    $income = Income::factory()->create(['client_id' => $this->client->id, 'description' => 'Before']);

    $response = match ($verb) {
        'edit' => $this->actingAs($this->member)->get(route('admin.incomes.edit', $income)),
        'update' => $this->actingAs($this->member)->put(route('admin.incomes.update', $income), [
            'amount' => 999, 'currency' => 'TRY', 'date' => now()->toDateString(), 'description' => 'After',
        ]),
        'destroy' => $this->actingAs($this->member)->delete(route('admin.incomes.destroy', $income)),
    };

    $response->assertNotFound();

    expect($income->fresh())->not->toBeNull()
        ->and($income->fresh()->description)->toBe('Before');
})->with(['edit', 'update', 'destroy']);

it('lets the owner edit it', function (): void {
    $income = Income::factory()->create(['client_id' => $this->client->id]);

    $this->actingAs($this->owner)->get(route('admin.incomes.edit', $income))->assertOk();
});

/**
 * Her totals must agree with the owner's, so the row stays — only the actions
 * are removed. Hiding the row would make the household's two views of its own
 * money disagree.
 */
it('still shows her the row and its amount', function (): void {
    Income::factory()->create(['client_id' => $this->client->id, 'amount' => 4321]);

    $this->actingAs($this->member)
        ->get(route('admin.incomes.index'))
        ->assertOk()
        ->assertSee('4,321', false);
});

it('marks the row as not editable for her and editable for him', function (): void {
    $income = Income::factory()->create(['client_id' => $this->client->id]);

    $hers = $this->actingAs($this->member)->getJson(route('admin.incomes.index', ['_data' => 1]));

    expect($hers->getContent())->toContain('"editable":false');

    $his = $this->actingAs($this->owner)->getJson(route('admin.incomes.index', ['_data' => 1]));

    expect($his->getContent())->not->toContain('"editable":false');
});

it('skips protected rows in a bulk delete and says how many', function (): void {
    $protected = Income::factory()->count(2)->create(['client_id' => $this->client->id]);
    $free = Income::factory()->count(3)->create(['client_id' => null]);

    $this->actingAs($this->member)
        ->delete(route('admin.incomes.bulk-destroy'), [
            'ids' => array_merge($protected->modelKeys(), $free->modelKeys()),
        ])
        ->assertRedirect();

    expect(Income::whereKey($protected->modelKeys())->count())->toBe(2)
        ->and(Income::whereKey($free->modelKeys())->count())->toBe(0);
});

it('skips protected rows in a bulk edit', function (): void {
    $protected = Income::factory()->create(['client_id' => $this->client->id, 'amount' => 10]);
    $free = Income::factory()->create(['client_id' => null, 'amount' => 10]);

    $this->actingAs($this->member)
        ->patch(route('admin.incomes.bulk-update'), [
            'ids' => [$protected->id, $free->id],
            'field' => 'amount',
            'value' => 250,
        ]);

    expect((float) $protected->fresh()->amount)->toBe(10.0)
        ->and((float) $free->fresh()->amount)->toBe(250.0);
});
```

**Correction to the two payload cases above.** Inertia returns JSON only when
the `X-Inertia` header is present; a `_data` query parameter does nothing. Write
that case as:

```php
it('marks the row as not editable for her and editable for him', function (): void {
    Income::factory()->create(['client_id' => $this->client->id]);

    $this->actingAs($this->member)
        ->get(route('admin.incomes.index'))
        ->assertInertia(fn ($page) => $page->where(
            'rows.data.0.editable',
            false,
        ));

    $this->actingAs($this->owner)
        ->get(route('admin.incomes.index'))
        ->assertInertia(fn ($page) => $page->where('rows.data.0.editable', true));
});
```

`rows` is a lazy prop, so confirm it is evaluated on a plain visit; if it is
not, add `->get(route('admin.incomes.index'), ['X-Inertia' => 'true'])`.

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --filter=ProtectedIncomeTest`
Expected: FAIL — she can edit everything.

- [ ] **Step 3: Add the guard to the base controller**

In `AdminResourceController`:

```php
/**
 * Whether this record may be written by the current request.
 *
 * Default true. A resource overrides it when some of its rows are readable but
 * not writable — which is not the same as a hidden field, because the row still
 * has to appear and still has to count towards totals.
 */
protected function isRecordEditable(Model $record): bool
{
    return true;
}
```

and call it from `edit()`, `update()` and `destroy()`, immediately after
`resolveRecord()`:

```php
$record = $this->resolveRecord($request);

abort_unless($this->isRecordEditable($record), 404);
```

`update()` and `destroy()` currently inline `$this->resolveRecord($request)` into
the call; split it into a local first.

For the bulk methods, filter the validated ids before handing them on:

```php
$ids = array_values(array_filter(
    $validated['ids'],
    fn (int $id): bool => $this->isRecordEditable($model::query()->findOrFail($id)),
));
```

and report the count actually acted on, which the existing notifier line already
does because it reports the action's return value.

- [ ] **Step 4: Override it for incomes**

In `IncomeController`:

```php
/**
 * An income that names a client is readable but not writable by anyone who may
 * not see which client it is — otherwise a save from a form that omits the
 * field would quietly drop the association.
 */
#[Override]
protected function isRecordEditable(Model $record): bool
{
    if (app(AccessProfile::class)->allows(Ability::SeeClientIdentity)) {
        return true;
    }

    return $record->getAttribute('client_id') === null;
}
```

- [ ] **Step 5: Mark the rows**

In `ResourceTable::rows()`, add the flag to each row:

```php
->through(fn (Model $record): array => [
    'id' => $record->getKey(),
    'editable' => $this->isRowEditable($record),
    'cells' => $this->resolveCells($columns, $record),
]);
```

with a protected `isRowEditable(Model $record): bool` returning `true` by
default and overridden in `IncomeTable`:

```php
#[Override]
protected function isRowEditable(Model $record): bool
{
    return app(AccessProfile::class)->allows(Ability::SeeClientIdentity)
        || $record->getAttribute('client_id') === null;
}
```

The duplication with the controller is deliberate and worth keeping: the table
decides what to *draw*, the controller decides what to *allow*, and a UI bug in
one must not become a security hole in the other.

- [ ] **Step 6: Hide the buttons**

In `resources/js/Components/Table/ResourceTable.vue`, the row action cell
already checks `rowActions.includes(...)`. Add the per-row condition:

```vue
v-if="rowActions.includes('edit') && data.editable !== false"
```

and the same for the delete action. Rows from tables that do not set the flag
have `editable === undefined`, so `!== false` leaves them untouched.

- [ ] **Step 7: Refuse the key in the request**

In `IncomeRequest::rules()`, drop the protected keys when the ability is absent:

```php
$rules = [ /* the existing rules, without client_id */ ];

if (app(AccessProfile::class)->allows(Ability::SeeClientIdentity)) {
    $rules['client_id'] = ['nullable', 'integer', 'exists:clients,id'];
}

return $rules;
```

A member's POST carrying `client_id` then has no rule, so `validated()` never
returns it and nothing writes it. Add a test case to
`ProtectedIncomeTest` asserting exactly that on a *new* income:

```php
it('ignores a client id posted by her when creating', function (): void {
    $this->actingAs($this->member)
        ->post(route('admin.incomes.store'), [
            'amount' => 100,
            'currency' => 'TRY',
            'date' => now()->toDateString(),
            'description' => 'Mine',
            'client_id' => $this->client->id,
        ])
        ->assertRedirect();

    expect(Income::query()->where('description', 'Mine')->sole()->client_id)->toBeNull();
});
```

- [ ] **Step 8: Run the tests, build, run everything**

```bash
php artisan test --filter=ProtectedIncomeTest
npm run build
php artisan test
```

Expected: PASS throughout.

- [ ] **Step 9: Commit**

```bash
composer lint && composer types:check
npm run lint:check && npm run format:check
git add app/ resources/js/Components/Table/ResourceTable.vue tests/
git commit -m "feat: make incomes with a client read-only without the ability" -- app/ resources/js/Components/Table/ResourceTable.vue tests/
```

---

### Task 5: Per-user appearance

**Files:**
- Modify: `database/migrations/0001_01_01_000000_create_users_table.php`, `app/Models/User.php`, `app/Support/Theme/Appearance.php`, `app/Providers/AppServiceProvider.php`, `app/Http/Middleware/HandleInertiaRequests.php`, `app/Http/Controllers/Admin/ProfileController.php`, `routes/admin.php`, `resources/js/pages/Profile.vue`
- Create: `app/Http/Requests/PreferencesRequest.php`
- Test: `tests/Feature/Admin/AppearanceTest.php`

**Interfaces:**
- Produces: `users.preferences` (JSON, cast to array), `Appearance::forUser(?User): array{accent: string, colorScheme: string}`, route `admin.preferences.update`.

**Why Profile and not Settings.** Settings is `Area::General` and owner-only.
Leaving appearance there would give her a preference she cannot reach. Settings
keeps the *global* default, which is what the public site and every signed-out
page use.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/AppearanceTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Models\User;
use App\Support\Theme\Appearance;

beforeEach(function (): void {
    config(['app.admin_email' => 'owner@example.test']);
    $this->owner = User::factory()->admin()->create(['email' => 'owner@example.test']);
    $this->member = User::factory()->member()->create(['email' => 'her@example.test']);
});

it('falls back to the global setting when a user has no preference', function (): void {
    Setting::set('appearance', 'accent', 'amber');

    expect(Appearance::forUser($this->member)['accent'])->toBe('amber');
});

it('prefers the user over the global setting', function (): void {
    Setting::set('appearance', 'accent', 'amber');
    $this->member->update(['preferences' => ['accent' => 'rose']]);

    expect(Appearance::forUser($this->member->fresh())['accent'])->toBe('rose');
});

/**
 * The whole point: two people, two accents, at the same time.
 */
it('keeps two users on different accents', function (): void {
    $this->owner->update(['preferences' => ['accent' => 'emerald']]);
    $this->member->update(['preferences' => ['accent' => 'rose']]);

    expect(Appearance::forUser($this->owner->fresh())['accent'])->toBe('emerald')
        ->and(Appearance::forUser($this->member->fresh())['accent'])->toBe('rose');
});

it('does not change a user who has a preference when the global changes', function (): void {
    $this->member->update(['preferences' => ['accent' => 'rose']]);
    Setting::set('appearance', 'accent', 'amber');

    expect(Appearance::forUser($this->member->fresh())['accent'])->toBe('rose');
});

it('ignores an accent the application does not have', function (): void {
    $this->member->update(['preferences' => ['accent' => 'ultraviolet']]);

    expect(Appearance::forUser($this->member->fresh())['accent'])
        ->toBe(Appearance::forUser(null)['accent']);
});

it('ignores a colour scheme the application does not have', function (): void {
    $this->member->update(['preferences' => ['color_scheme' => 'sepia']]);

    expect(Appearance::forUser($this->member->fresh())['colorScheme'])->toBe('system');
});

/**
 * The accent CSS is written into the Blade shell before Inertia boots, so her
 * first paint is already her colour rather than the owner's.
 */
it('carries her accent in the first paint', function (): void {
    $this->member->update(['preferences' => ['accent' => 'rose']]);
    Setting::set('appearance', 'accent', 'emerald');

    $html = $this->actingAs($this->member->fresh())->get(route('admin.dashboard'))->getContent();

    expect($html)->toContain('--p-primary-500')
        ->and($html)->not->toContain('emerald');
});

it('lets her save her own appearance from her profile', function (): void {
    $this->actingAs($this->member)
        ->put(route('admin.preferences.update'), ['accent' => 'rose', 'color_scheme' => 'dark'])
        ->assertRedirect();

    expect($this->member->fresh()->preferences)
        ->toMatchArray(['accent' => 'rose', 'color_scheme' => 'dark']);
});

it('refuses an accent that does not exist', function (): void {
    $this->actingAs($this->member)
        ->from(route('admin.profile'))
        ->put(route('admin.preferences.update'), ['accent' => 'ultraviolet'])
        ->assertSessionHasErrors('accent');
});

it('lets her clear her preference back to the default', function (): void {
    $this->member->update(['preferences' => ['accent' => 'rose']]);
    Setting::set('appearance', 'accent', 'amber');

    $this->actingAs($this->member->fresh())
        ->put(route('admin.preferences.update'), ['accent' => null, 'color_scheme' => null]);

    expect(Appearance::forUser($this->member->fresh())['accent'])->toBe('amber');
});

it('does not let her change the global setting', function (): void {
    $this->actingAs($this->member)
        ->put(route('admin.preferences.update'), ['accent' => 'rose']);

    expect(Setting::get('appearance', 'accent', 'unset'))->toBe('unset');
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --filter=AppearanceTest`
Expected: FAIL — there is no `preferences` column.

- [ ] **Step 3: Add the column**

In `database/migrations/0001_01_01_000000_create_users_table.php`, after `role`:

```php
// Per-user appearance. A JSON column rather than rows in `settings`, because
// Setting is a global key-value store with one cache key per group.name and
// threading a user through it would either break that cache or need a second.
$table->json('preferences')->nullable();
```

In `app/Models/User.php`, add to `casts()`:

```php
'preferences' => 'array',
```

and to the class docblock:

```php
 * @property array<string, mixed>|null $preferences
```

- [ ] **Step 4: Resolve per user**

In `app/Support/Theme/Appearance.php`, add:

```php
/**
 * This user's appearance, falling back to the global setting and then to the
 * built-in default.
 *
 * @return array{accent: string, colorScheme: string}
 */
public static function forUser(?User $user): array
{
    $preferences = $user?->preferences ?? [];

    return [
        'accent' => self::resolveAccent(is_array($preferences) ? ($preferences['accent'] ?? null) : null),
        'colorScheme' => self::resolveScheme(is_array($preferences) ? ($preferences['color_scheme'] ?? null) : null),
    ];
}

private static function resolveAccent(mixed $preferred): string
{
    if (is_string($preferred) && AccentRamps::has($preferred)) {
        return $preferred;
    }

    return self::accent();
}

private static function resolveScheme(mixed $preferred): string
{
    if (is_string($preferred) && in_array($preferred, self::SCHEMES, true)) {
        return $preferred;
    }

    return self::colorScheme();
}
```

`accent()` and `colorScheme()` keep their current bodies and stay the global
answer. `toArray()` keeps working for anything that has no user.

- [ ] **Step 5: Use it in both consumers**

In `AppServiceProvider`'s view composer:

```php
View::composer('app', function (\Illuminate\View\View $view): void {
    $appearance = Appearance::forUser(auth()->user());

    $view->with([
        'accentCss' => AccentRamps::cssVariables($appearance['accent']),
        'colorScheme' => $appearance['colorScheme'],
    ]);
});
```

In `HandleInertiaRequests`:

```php
'appearance' => fn (): array => Appearance::forUser($user),
```

- [ ] **Step 6: Add the route and the request**

Create `app/Http/Requests/PreferencesRequest.php` — a plain `FormRequest`, not an
`AdminRequest`, because Profile belongs to no area:

```php
final class PreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'accent' => ['nullable', Rule::in(AccentRamps::names())],
            'color_scheme' => ['nullable', Rule::in(Appearance::SCHEMES)],
        ];
    }
}
```

In `routes/admin.php`, beside `profile`, outside every area group:

```php
Route::put('preferences', [ProfileController::class, 'updatePreferences'])->name('preferences.update');
```

In `ProfileController`:

```php
public function updatePreferences(PreferencesRequest $request): RedirectResponse
{
    /** @var array{accent: string|null, color_scheme: string|null} $data */
    $data = $request->validated();

    $user = $request->user();

    abort_if($user === null, 401);

    // Nulls are stored rather than dropped, so "clear it" is expressible and
    // resolves back to the global default on the next read.
    $user->update(['preferences' => array_merge($user->preferences ?? [], $data)]);

    $this->notifier->success('Appearance saved');

    return to_route('admin.profile');
}
```

`ProfileController` gains a `NotifiesAdmin` constructor dependency; it has none
today.

`__invoke()` also needs to pass the current values and the accent list to the
page, mirroring what `SettingsController` already sends.

- [ ] **Step 7: Add the controls to the page**

In `resources/js/pages/Profile.vue`, add an Appearance card above or below the
passkeys card, with an accent picker and a colour-scheme select posting to
`route('admin.preferences.update')`. Copy the control markup from
`resources/js/pages/Settings.vue` rather than inventing a second style for the
same two inputs.

- [ ] **Step 8: Run the tests and look at it**

```bash
php artisan migrate:fresh --seed --no-interaction
php artisan test --filter=AppearanceTest
npm run build
```

Sign in as each user and confirm the two see different accents.

**If `it carries her accent in the first paint` fails**, the composer is running
before the session is resolved. Move the composer registration into a
`View::composer` inside a `booted()` callback, or read the user from
`$view->getFactory()->getShared()`. Fix it rather than weakening the test — this
assertion is the only one that proves the first paint is not the owner's colour.

- [ ] **Step 9: Run everything and commit**

```bash
php artisan test
composer lint && composer types:check
npm run lint:check && npm run format:check
git add app/ database/migrations resources/js/pages/Profile.vue routes/admin.php tests/
git commit -m "feat: give each user their own appearance" -- app/ database/migrations resources/js/pages/Profile.vue routes/admin.php tests/
```

---

### Task 6: Feature flags

**Files:**
- Create: `app/Support/Features.php`
- Modify: `composer.json`, `app/Support/Access/AccessProfile.php`, `app/Providers/AccessServiceProvider.php`, `app/Support/Navigation.php`, `routes/admin.php`
- Test: `tests/Feature/Auth/FeatureFlagTest.php`

**Interfaces:**
- Consumes: `AccessProfile`.
- Produces: `AccessProfile::feature(string): bool`, `Features::ALL`, middleware alias `feature`.

**The rule, restated because everything depends on it:** a flag is **always on
for an admin** and off for a member unless explicitly enabled. A flag means "not
ready for her yet", so it can never hide something from you.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Auth/FeatureFlagTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Access\AccessProfile;
use App\Support\Features;
use Laravel\Pennant\Feature;

beforeEach(function (): void {
    config(['app.admin_email' => 'owner@example.test']);
    $this->owner = User::factory()->admin()->create(['email' => 'owner@example.test']);
    $this->member = User::factory()->member()->create(['email' => 'her@example.test']);
});

it('is on for an admin whatever the store says', function (string $flag): void {
    Feature::for($this->owner)->deactivate($flag);

    expect(AccessProfile::forUser($this->owner)->feature($flag))->toBeTrue();
})->with(fn (): array => array_map(fn (string $f): array => [$f], Features::ALL));

it('is off for a member until it is turned on', function (string $flag): void {
    expect(AccessProfile::forUser($this->member)->feature($flag))->toBeFalse();

    Feature::for($this->member)->activate($flag);

    expect(AccessProfile::forUser($this->member->fresh())->feature($flag))->toBeTrue();
})->with(fn (): array => array_map(fn (string $f): array => [$f], Features::ALL));

it('is off for a guest', function (): void {
    expect(AccessProfile::forUser(null)->feature(Features::ALL[0]))->toBeFalse();
});

it('answers false for a flag nobody declared', function (): void {
    expect(AccessProfile::forUser($this->owner)->feature('not-a-real-flag'))->toBeFalse();
});
```

**If `Features::ALL` is empty** there is nothing to gate yet and every dataset is
empty, which Pest reports as an error rather than a pass. Declare one real flag
in Step 3 — the plan does not ship an empty list.

- [ ] **Step 2: Add Pennant**

```bash
composer require laravel/pennant
php artisan vendor:publish --provider="Laravel\Pennant\PennantServiceProvider"
php artisan migrate --no-interaction
```

This is the only dependency this plan adds.

- [ ] **Step 3: Declare the flags**

Create `app/Support/Features.php`:

```php
<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Flags hide a screen that is not finished yet.
 *
 * A flag is always on for an admin, so it can only ever hide something from a
 * member. That is what makes it safe to leave one in place for a while.
 */
final class Features
{
    /**
     * Whatever is currently half-built. Remove a name once the screen is ready
     * rather than leaving a permanently-on flag behind.
     *
     * @var array<int, string>
     */
    public const array ALL = [
        'utilities-meter-chart',
    ];
}
```

`utilities-meter-chart` is a placeholder for a screen you are actually building.
If nothing is in progress when this runs, pick the next thing you intend to
build and name it — an empty list makes the tests vacuous.

- [ ] **Step 4: Teach the profile about flags**

In `AccessProfile`, add the flag set to the constructor and:

```php
/**
 * An admin sees every flag. A member sees a flag only once it is turned on for
 * her, and a flag nobody declared is always off.
 */
public function feature(string $flag): bool
{
    if (! in_array($flag, Features::ALL, true)) {
        return false;
    }

    return $this->privileged || $this->flags[$flag] ?? false;
}
```

`forUser()` sets `privileged` from `$user->isAdmin() || $user->role === UserRole::Admin`
— the same test `abilities()` already makes — and resolves the member's flags
from Pennant once, so a page that checks ten flags makes one round trip.

`preview()` sets `privileged` to **false** and resolves the flags of the
previewed role, so a preview shows you what she sees rather than what you see.

- [ ] **Step 5: Gate navigation and routes**

In `Navigation`, items gain an optional flag:

```php
['label' => 'Meter chart', 'route' => 'admin.utility-bills.chart', 'icon' => 'pi pi-chart-line', 'feature' => 'utilities-meter-chart'],
```

and `forProfile()` filters items as well as clusters:

```php
$items = array_values(array_filter(
    $cluster['items'],
    fn (array $item): bool => ! isset($item['feature']) || $profile->feature($item['feature']),
));
```

A cluster whose items are all hidden is dropped too, so she never sees an empty
menu.

Add a `feature` middleware alias mirroring `EnsureAreaAccess`:

```php
final class EnsureFeatureEnabled
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $flag): Response
    {
        abort_unless(app(AccessProfile::class)->feature($flag), 404);

        return $next($request);
    }
}
```

registered in `bootstrap/app.php` beside `area`, and used as
`->middleware('feature:utilities-meter-chart')`.

- [ ] **Step 6: Extend the access matrix**

`tests/Feature/Auth/AreaAccessMatrixTest.php` compares the member's status
against the owner's. A flag-gated route inside an area she has would now answer 404
for her and 200 for him, which the matrix reads as a leak.

Add the flagged route names to a small exclusion list in that file, with a
comment saying they are covered by `FeatureFlagTest` instead. Keep the list
short and keep it in one place; if it grows past a handful, the flag rule is
being used for something it was not meant for.

- [ ] **Step 7: Run everything and commit**

```bash
php artisan test
composer lint && composer types:check
git add app/ bootstrap/ composer.json composer.lock config/ database/migrations routes/ tests/
git commit -m "feat: hide unfinished screens behind feature flags" -- app/ bootstrap/ composer.json composer.lock config/ database/migrations routes/ tests/
```

---

### Task 7: View-as

**Files:**
- Create: `app/Http/Controllers/Admin/PreviewController.php`, `app/Http/Middleware/RefuseWritesWhilePreviewing.php`
- Modify: `routes/admin.php`, `bootstrap/app.php`, `app/Http/Middleware/HandleInertiaRequests.php`, `resources/js/Layouts/AdminLayout.vue`
- Test: `tests/Feature/Auth/PreviewTest.php`

**Interfaces:**
- Consumes: `AccessProfile::preview()` from Task 1.
- Produces: routes `admin.preview.store` and `admin.preview.destroy`, and a `preview` prop shared with every page.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Auth/PreviewTest.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Income;
use App\Models\User;

beforeEach(function (): void {
    config(['app.admin_email' => 'owner@example.test']);
    $this->owner = User::factory()->admin()->create(['email' => 'owner@example.test']);
    $this->member = User::factory()->member()->create(['email' => 'her@example.test']);
});

it('starts and stops', function (): void {
    $this->actingAs($this->owner)
        ->post(route('admin.preview.store'), ['role' => UserRole::Member->value])
        ->assertRedirect();

    $this->actingAs($this->owner)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page->where('preview.active', true));

    $this->actingAs($this->owner)->delete(route('admin.preview.destroy'));

    $this->actingAs($this->owner)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page->where('preview.active', false));
});

/**
 * The assertion that makes the preview worth having. Comparing payloads, not
 * screens, is what proves the preview is honest rather than decorative.
 */
it('shows the owner exactly what her own session shows', function (string $routeName): void {
    $hers = $this->actingAs($this->member)->get(route($routeName))->getContent();

    $this->actingAs($this->owner)->post(route('admin.preview.store'), ['role' => UserRole::Member->value]);
    $his = $this->actingAs($this->owner)->get(route($routeName))->getContent();

    $strip = fn (string $html): string => preg_replace('/"(auth|preview|notifications)":\{.*?\}\}/', '', $html) ?? $html;

    expect($strip($his))->toBe($strip($hers));
})->with(['admin.dashboard', 'admin.incomes.index']);

it('hides the same routes from him as from her', function (): void {
    $this->actingAs($this->owner)->post(route('admin.preview.store'), ['role' => UserRole::Member->value]);

    $this->actingAs($this->owner)->get(route('admin.posts.index'))->assertNotFound();
    $this->actingAs($this->owner)->get(route('admin.clients.index'))->assertNotFound();
});

it('refuses every write while active', function (): void {
    $income = Income::factory()->create(['client_id' => null]);

    $this->actingAs($this->owner)->post(route('admin.preview.store'), ['role' => UserRole::Member->value]);

    $this->actingAs($this->owner)
        ->put(route('admin.incomes.update', $income), [
            'amount' => 999, 'currency' => 'TRY', 'date' => now()->toDateString(), 'description' => 'Changed',
        ])
        ->assertForbidden();

    expect($income->fresh()->description)->not->toBe('Changed');
});

it('still allows leaving the preview', function (): void {
    $this->actingAs($this->owner)->post(route('admin.preview.store'), ['role' => UserRole::Member->value]);

    $this->actingAs($this->owner)->delete(route('admin.preview.destroy'))->assertRedirect();
});

it('refuses to start a preview for a member', function (): void {
    $this->actingAs($this->member)
        ->post(route('admin.preview.store'), ['role' => UserRole::Member->value])
        ->assertNotFound();
});

/**
 * A preview must not be a way to climb: starting one from inside another, or
 * previewing a role no narrower than the current one, both have to fail.
 */
it('refuses to preview a role that is not narrower', function (): void {
    $this->actingAs($this->owner)
        ->post(route('admin.preview.store'), ['role' => UserRole::Admin->value])
        ->assertForbidden();
});

it('ends on sign out', function (): void {
    $this->actingAs($this->owner)->post(route('admin.preview.store'), ['role' => UserRole::Member->value]);

    $this->actingAs($this->owner)->post(route('logout'));

    $this->actingAs($this->owner->fresh())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page->where('preview.active', false));
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --filter=PreviewTest`
Expected: FAIL — the routes do not exist.

- [ ] **Step 3: Write the controller**

```php
final class PreviewController extends Controller
{
    /**
     * Only a real admin may start a preview — never someone already inside one,
     * which would otherwise be a way to climb rather than to look down.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user?->isAdmin() || $user?->role === UserRole::Admin, 404);
        abort_if(app(AccessProfile::class)->isPreviewing(), 403);

        $role = UserRole::tryFrom($request->string('role')->toString());

        abort_if(! $role instanceof UserRole, 422);
        abort_if(count($role->areas()) >= count($user->areas()), 403);

        $request->session()->put('preview_role', $role->value);

        return back();
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget('preview_role');

        return back();
    }
}
```

The `404` for a member matches everything else: the control does not exist for
her, so neither does the route.

- [ ] **Step 4: Refuse writes**

```php
final class RefuseWritesWhilePreviewing
{
    private const array SAFE = ['GET', 'HEAD', 'OPTIONS'];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $previewing = app(AccessProfile::class)->isPreviewing();
        $leaving = $request->routeIs('admin.preview.destroy');

        abort_if(
            $previewing && ! $leaving && ! in_array($request->method(), self::SAFE, true),
            403,
            'This is a preview. Leave it before making changes.',
        );

        return $next($request);
    }
}
```

Appended to the `web` group in `bootstrap/app.php`, after
`HandleInertiaRequests`. It must run for every route, not only admin ones, so a
preview cannot write through the public site either.

- [ ] **Step 5: Add the routes**

In `routes/admin.php`, at the top level beside `profile`:

```php
Route::post('preview', [PreviewController::class, 'store'])->name('preview.store');
Route::delete('preview', [PreviewController::class, 'destroy'])->name('preview.destroy');
```

These sit outside every area group; the controller does its own checking.

Add both names to the access matrix's `areaOf()` map as shared routes, or the
matrix will expect a member to reach `preview.store` and get the same answer as
the owner — she gets 404 and he gets 302, which is correct and would otherwise
read as a failure. The cleanest fix is to list them alongside the export routes
as "guarded by the controller, not by an area".

- [ ] **Step 6: Share the state and show a banner**

In `HandleInertiaRequests`:

```php
'preview' => fn (): array => [
    'active' => app(AccessProfile::class)->isPreviewing(),
    'role' => $request->session()->get('preview_role'),
],
```

In `AdminLayout.vue`, a fixed bar at the top of the viewport when
`$page.props.preview.active`, naming the role and offering a single control that
`DELETE`s `route('admin.preview.destroy')`. It must not be dismissible: a
preview you have forgotten about is a preview that will confuse you.

Add the entry point beside the profile link, visible only when
`$page.props.auth.user` is an admin and no preview is active.

- [ ] **Step 7: Run everything**

```bash
php artisan test
npm run build
```

Expected: PASS.

**If `it shows the owner exactly what her own session shows` fails on a
difference you believe is harmless**, do not widen the regular expression until
the test passes. Read what differs first — the whole value of that assertion is
that it fails on anything the two sessions do not share.

- [ ] **Step 8: Commit**

```bash
composer lint && composer types:check
npm run lint:check && npm run format:check
git add app/ bootstrap/app.php routes/admin.php resources/js tests/
git commit -m "feat: preview the panel as a narrower role" -- app/ bootstrap/app.php routes/admin.php resources/js tests/
```

---

### Task 8: Release

**Files:**
- Modify: `CHANGELOG.md`

**Note:** neither `composer.json` nor `package.json` carries a `version` field.
Releases are a CHANGELOG entry and a git tag. Do not add one.

- [ ] **Step 1: Run rector, then the full check**

```bash
composer rector:fix
composer ci:check
echo "exit: $?"
```

Expected: exit 0. Rector runs in fix mode first, because a dry run that finds
changes fails the check and its output is easy to lose in a pipe.

- [ ] **Step 2: Frontend gates**

```bash
npm run lint:check && npm run format:check && npm run build
```

- [ ] **Step 3: Write the changelog entry**

Add above the previous version:

```markdown
## [0.11.0] - <today>

### Added

- Abilities: named capabilities beside areas, with `see-client-identity` the
  first. A field declares `hiddenWithout()` and disappears from the schema, the
  payload, sorting, filtering, the forms and bulk editing together.
- Incomes that name a client are read-only for anyone without that ability. The
  row and its amount stay visible, so both people's totals agree.
- Per-user appearance. Each user keeps their own accent and colour scheme in
  `users.preferences`, set from Profile; Settings keeps the global default that
  the public site uses.
- Feature flags, through Pennant. A flag is always on for an admin and off for a
  member until enabled, so it can only hide something that is not finished.
- View-as: render the panel as a narrower role, read-only, with a banner.

### Changed

- Areas, abilities and flags are all answered by one request-scoped
  `AccessProfile` rather than asked of the user directly.

### Security

- Hidden fields are removed server-side, so the value never reaches the browser.
- A hidden filter or sort applied by hand-editing the query string is ignored.
- A preview refuses every write, cannot be started by a member, and cannot widen
  what the viewer already has.
```

- [ ] **Step 4: Commit, tag, push**

```bash
git add CHANGELOG.md
git commit -m "chore: release 0.11.0" -- CHANGELOG.md
git tag v0.11.0
git push && git push --tags
```

- [ ] **Step 5: Check CI properly**

```bash
gh run list --limit 1
gh run view <id> --json conclusion,status
```

Read the `conclusion` field. `gh run watch --exit-status` has returned 0 for a
failed run in this repository; do not trust it.

- [ ] **Step 6: Look at it as she sees it**

Start a preview from your own account and walk the panel: no client column on
incomes, no edit button on an income that has one, and your own accent replaced
by hers. This is the step that proves the feature does what it was built for,
and no test can do it.
