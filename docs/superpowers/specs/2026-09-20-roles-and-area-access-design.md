# Roles and Area Access — Design

**Date:** 2026-09-20
**Status:** Approved for planning
**Baseline:** `v0.8.0`
**Part of:** a three-spec sequence. This is A. B is invites, C is column
visibility, per-user appearance and Pennant.

## Goal

A second person can sign in and reach exactly Utilities, Budget and Library —
fully, including paying bills and settling debts — while Blog, Work and Settings
stay invisible to her everywhere the application can leak them.

## Non-Goals

- **Invites and registration.** Her account is seeded here so the work is
  testable. Spec B replaces that with a single-use link.
- **Column-level visibility.** Hiding client names on incomes is Spec C; until
  then she sees the Client column on income rows.
- **Per-user appearance and Pennant.** Spec C.
- **Row ownership.** Household data is shared. No table gains a `user_id`, no
  query is scoped by user. This is a deliberate simplification, not an oversight.
- **A users management screen.** Two accounts, one role column.

---

## Current state

Verified on 2026-09-20 against the running application.

The entire authorisation boundary is one line:

```php
Gate::define('access-admin', fn (User $user): bool => $user->isAdmin());
```

where `isAdmin()` compares `$this->email` to `config('app.admin_email')`. It is
checked in **19 FormRequests**, each repeating the same line, and once as route
middleware on the whole `/admin` group.

`php artisan route:list` reports **137 admin routes**.

**No table has a `user_id`** except `passkeys` and `sessions`, which are
Laravel's own. Nothing is owned.

Registration is not in Fortify's feature list, so no second account can currently
come into existence.

---

## The model

### Areas

```php
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

These match the navigation clusters, which is the grouping the panel already
presents and the one both users already think in.

### Roles

```php
enum UserRole: string
{
    case Admin = 'admin';
    case Member = 'member';

    /** @return array<int, Area> */
    public function areas(): array
    {
        return match ($this) {
            self::Admin => Area::cases(),
            self::Member => [Area::Budget, Area::Utilities, Area::Library],
        };
    }
}
```

`users.role` is a plain `string` column cast to the enum — the same choice as
`utility_accounts.type`, and for the same reason: adding a role is one line in
the enum and no migration. It defaults to `member`, so a row created without a
role is the *less* privileged one.

The enum implements no presentation contracts, because no screen lists users
yet. `HasColor` and `HasLabel` exist and can be added the day one does.

### Lockout safety

```php
public function canAccess(Area $area): bool
{
    if ($this->isAdmin()) {
        return true;
    }

    return in_array($area, $this->role->areas(), true);
}
```

`isAdmin()` keeps its current meaning — the address in `ADMIN_EMAIL`. Whatever
the role column says, that address reaches everything. A bad migration, an empty
column or a fat-fingered seed cannot lock the owner out, and the guarantee costs
one condition.

---

## Enforcement

### One gate

```php
Gate::define('access-area', fn (User $user, Area $area): Response => $user->canAccess($area)
    ? Response::allow()
    : Response::denyAsNotFound());
```

`denyAsNotFound()` is Laravel's own mechanism, so a refusal becomes a genuine
404 rather than a faked exception. A forbidden URL and a nonexistent one are
indistinguishable, which is what was asked for.

**Accepted consequence:** she cannot tell a mistyped URL from one that is not
hers, so "page not found" may sometimes mean "not yours". Worth remembering when
she reports a broken link.

### Route groups

`routes/admin.php` gains a nested group per area inside the existing
`auth` group:

```php
Route::middleware('can:access-area,'.Area::Budget->value)->group(function (): void {
    // incomes, expenses, debts
});
```

Six routes stay outside any area group because they belong to whoever is signed
in: `admin.dashboard`, `admin.profile`, `admin.search` and the three
`admin.notifications.*` routes. Each filters its own contents instead.

### The base request

Today 19 FormRequests each repeat `return $this->user()?->can('access-admin')`.
A twentieth can silently omit it.

```php
abstract class AdminRequest extends FormRequest
{
    abstract protected function area(): Area;

    public function authorize(): bool
    {
        return $this->user()?->can('access-area', $this->area()) ?? false;
    }
}
```

Every existing admin request extends it and declares its area. `authorize()`
disappears from all 19. **A new request cannot be written without naming an
area**, because the method is abstract — the check stops being something to
remember and becomes something the language demands.

This is the single most valuable change in the spec. The route group is the
outer fence; this is the inner one, and neither depends on the other being right.

---

## The four leak surfaces

Each was confirmed by reading the code, not assumed.

### Navigation

`Navigation::clusters()` returns all six clusters to everyone, and
`HandleInertiaRequests.php:43` shares that list with every Inertia response — so
an unfiltered menu leaks the existence of Work and Blog on every page.

It gains a `forUser(User $user)` that drops clusters whose area the user cannot
reach, and the middleware calls that instead.

### Global search

`GlobalSearch::registry()` maps 13 group labels to a table and a route —
including Clients, Invoices, Posts, Projects and Repositories. Unfiltered, the
command palette would search your client names and invoice numbers from any
screen.

Each registry entry gains an `area`, and `query()` skips groups the user cannot
reach. The registry is keyed by label, so this is one key per entry.

### Dashboard

`DashboardController` returns five props: `budget`, `work`, `library`,
`recentPosts` and `openTasks`. Three of those are outside her areas —
`work`, `recentPosts` (Blog) and `openTasks` (Work).

Each prop becomes conditional on the viewing user's areas. A prop she cannot see
is **absent**, not empty: an empty array invites a component to render a heading
over nothing.

The Vue page renders what it is given, so a missing prop simply does not appear.

### Exports

`Route::post('exports/{resource}')` checks only that the resource has an
exporter. All three exportables — books, publishers, writers — happen to be
Library, so today it is *accidentally* safe. Adding an invoice export later would
hand it to her.

`ExportResource::EXPORTS` gains an area per entry and the controller checks it.
The accident becomes a rule.

---

## Categories

`BookForm` attaches categories, and `PostForm` uses the same shared taxonomy. She
needs to read categories to enter a book; the Categories *screen* is General and
stays with the owner.

So `Area::General` covers the screen, and the books form's category field is
readable by anyone who can reach Library. Concretely: the `categories` resource
routes sit in the General group, while `Field::multiRelationship('categories')`
resolves its options without an area check, because options are already scoped to
the form the user is permitted to open.

She can create a category inline from the books form. That category becomes
visible in your blog's taxonomy, which is what "shared" means and is accepted.

---

## Seeding her account

`DatabaseSeeder` creates two users: the admin from `ADMIN_EMAIL`, and a member.
The member's address comes from a new `MEMBER_EMAIL` in `.env.example` so nothing
depends on a value only the local `.env` carries — the trap that has broken CI
here before.

This is scaffolding. Spec B replaces it with an invite.

---

## Testing

The test is as much the deliverable as the code.

### The matrix

A dataset built from `Route::getRoutes()`, filtered to names starting `admin.`,
crossed with both roles. **137 routes × 2 roles.** For each: the member either
reaches it or gets 404, and the admin always reaches it.

Generated, never hand-listed. A route added next year is covered the day it
appears, and a route that forgets its area group fails the moment it exists.

Routes needing a bound model use a factory-made record; the dataset carries a
resolver per resource so the matrix does not collapse into special cases.

### Beyond the matrix

- **Every FormRequest declares an area.** Reflect over
  `App\Http\Requests\Admin`, assert each extends `AdminRequest`. This is what
  stops the twentieth request from being the one that forgets.
- **Navigation** returns only her clusters, and returns all six for the admin.
- **Global search** returns no Clients, Invoices, Posts, Projects or Repositories
  group for her, given matching records exist for each — a filter that passes
  because the data is absent proves nothing.
- **Dashboard** omits `work`, `recentPosts` and `openTasks` for her, and the
  props are absent rather than empty.
- **Exports** refuse a resource outside her areas, asserted by temporarily
  registering an exporter in a non-Library area rather than waiting for one.
- **Lockout:** a user whose email matches `ADMIN_EMAIL` reaches every area even
  with `role` set to member, and even with `role` empty.
- **Write actions:** she can pay a bill and record a debt payment; she cannot
  reach a single Work or Blog write route.

---

## Risks

| Risk | Mitigation |
| --- | --- |
| A route group is mis-scoped and exposes Work | The matrix covers all 137 routes; the base request refuses independently |
| Owner locked out mid-change | `ADMIN_EMAIL` bypasses the role column entirely |
| A future request forgets its area | `area()` is abstract; the code does not compile without it |
| 404 confuses her | Accepted deliberately; noted so a "broken link" report is read correctly |
| A future export in a new area leaks | Area recorded per exporter and checked, rather than relying on today's accident |
