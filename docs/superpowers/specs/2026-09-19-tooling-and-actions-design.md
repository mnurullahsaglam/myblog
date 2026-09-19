# Tooling, Static Analysis and Action Classes — Design

**Date:** 2026-09-19
**Status:** Approved for planning
**Baseline:** `v0.2.0` (`8593253`)

## Goal

Bring the repository's tooling to the standard of `~/Sites/dentakay-veil`: current dependencies,
PHPStan at max over the whole application, Rector, a full JavaScript lint chain, a pre-commit
hook, consolidated CI, and an extensive Nuno-style test suite. Move write-path logic out of
controllers into action classes.

## Non-Goals

- **Automated deploy.** There is no deployment target yet. Nothing in this spec deploys.
- **release-please.** Versions are bumped by hand: `CHANGELOG.md` plus an annotated git tag.
- **Restructuring read paths.** Index and show stay in controllers.
- **Rewriting `app/Services`.** The four service classes are API clients and a PDF converter.
  Actions orchestrate them; they do not absorb them.

---

## Current State

| Concern | Today | Target |
| --- | --- | --- |
| PHP runtime | 8.5.8 local, 8.4 in CI, `^8.4` in `composer.json` | 8.5 everywhere |
| Laravel | 13.3.0 | 13.32+ |
| Pest | 4.4.3 | 5.2+ |
| Larastan | 3.9.3 | 3.12+ |
| Boost | 2.4.8 | 2.9+ |
| PHPStan | level 10, `app/` only | level max, five paths, bleedingEdge |
| Rector | installed, no config | configured, dry-run in CI |
| ESLint / Prettier | absent | ESLint 9 flat config, Prettier |
| Pre-commit | absent | Husky + lint-staged |
| Workflows | three files, duplicated setup | one `ci.yml`, composite setup action |
| Arch tests | none | four Pest arch presets |
| Actions | `app/Actions/Fortify/` only | thirteen domain actions |

### Defects found while surveying

1. **`App\Http\Controllers\BookController` is a dead stub.** `create`, `store`, `show`, `edit`,
   `update` and `destroy` are all `public function …: void {}`, and `routes/web.php:13` binds
   `Route::resource('books', BookController::class)` to all seven verbs. Six public routes
   return an empty body.
2. **`tests/Pest.php` still carries scaffolding** — the `toBeOne` expectation and
   `function something()` from `pest:install`, neither referenced anywhere.
3. **Local/CI PHP drift.** Local 8.5.8, CI 8.4. Syntax accepted locally may fail in CI.

---

## 1. Dependency Upgrades

Run before anything else, because every later gate needs a green suite on current versions.

**Minor and patch (no code impact expected):**
`laravel/framework` 13.3 → 13.32, `laravel/boost` 2.4.8 → 2.9.1, `larastan/larastan` 3.9 → 3.12,
`rector/rector` 2.3 → 2.6, `laravel/pint` 1.29 → 1.32, `inertiajs/inertia-laravel` 3.3.0 → 3.3.4,
plus `laravel/pail`, `laravel/sail`, `laravel/tinker`, `mockery/mockery`,
`nunomaduro/collision`, `panphp/pan`.

**Major:**

- `pestphp/pest` ^4.0 → ^5.0, with `pest-plugin-laravel` ^5.0 and `pest-plugin-browser` ^5.0.
- `spatie/laravel-sluggable` ^3.8 → ^4.0. Read the upgrade notes; this package drives every
  slug on `Post`, `Book` and friends, so its tests must stay green.
- `symfony/css-selector` and `symfony/dom-crawler` ^7.3 → ^8.0, which Pest's browser plugin pulls.

**New dev dependencies:**

- `pestphp/pest-plugin-type-coverage` ^5.0 — reports untyped parameters and returns.
- `pestphp/pest-plugin-rector` ^5.0 — Pest-aware Rector rules.

Not adopted from veil: `pest-plugin-drift` (converts PHPUnit tests to Pest; there are none left
to convert) and `pest-plugin-stressless` (load testing; no load to test).

**PHP floor.** `composer.json` moves to `"php": "^8.5"` and every workflow pins `8.5`. This
closes the drift in defect 3 and lets Rector target the 8.5 syntax the runtime already supports.

---

## 2. Static Analysis and Style

### PHPStan

```neon
includes:
    - vendor/larastan/larastan/extension.neon
    - vendor/nesbot/carbon/extension.neon
    - vendor/pestphp/pest/extension.neon
    - vendor/phpstan/phpstan/conf/bleedingEdge.neon

parameters:
    paths:
        - app/
        - bootstrap/app.php
        - config/
        - database/
        - routes/
    level: max
```

Four of those five paths have never been analysed, and `bleedingEdge` adds rules beyond level 10.
Expect a fix pass. Errors are fixed in the code, not silenced: no `ignoreErrors` entries and no
baseline file. If a specific error proves genuinely unfixable, it gets an inline
`@phpstan-ignore` with a comment saying why — and that comment survives the comment sweep in
section 4, because it is load-bearing.

### Rector

`rector.php` at the project root:

```php
return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/bootstrap',
        __DIR__.'/config',
        __DIR__.'/database',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ])
    ->withPhpSets(php85: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        privatization: true,
        earlyReturn: true,
    )
    ->withSets([
        LaravelSetList::LARAVEL_CODE_QUALITY,
        LaravelSetList::LARAVEL_COLLECTION,
        LaravelSetList::LARAVEL_IF_HELPERS,
        PestSetList::CODING_STYLE,
    ])
    ->withImportNames(importShortClasses: false);
```

CI runs `--dry-run` only. Rector never rewrites code in CI; a dirty dry-run fails the build and a
human runs `composer rector:fix`.

`driftingly/rector-laravel` ^2.5 supplies `RectorLaravel\Set\LaravelSetList`; `PestSetList` comes from
`Pest\Rector\Set\PestSetList`, shipped by `pestphp/pest-plugin-rector`.

### ESLint and Prettier

ESLint 9 flat config in `eslint.config.js`: `@eslint/js` recommended, `eslint-plugin-vue`
`flat/recommended`, Prettier as the formatting authority via `eslint-config-prettier`. Scope is
`resources/js/`. myblog has no TypeScript, so there is no `tsc` step — this is the one place the
setup deliberately diverges from veil.

Prettier with `prettier-plugin-tailwindcss` so Tailwind v4 classes sort canonically. `.prettierignore`
excludes `public/build`, `vendor`, `node_modules` and `storage`.

Pint keeps its existing `pint.json`; only the `--parallel` flag is added via the scripts.

### Script harness

`composer.json`:

| Script | Command |
| --- | --- |
| `lint` | `pint --parallel` |
| `lint:check` | `pint --parallel --test` |
| `rector` | `rector process --dry-run` |
| `rector:fix` | `rector process` |
| `types:check` | `phpstan analyse --memory-limit=1G` |
| `type-coverage` | `pest --type-coverage --min=100` |
| `test` | `pest --parallel --compact` |
| `ci:check` | the five checks above, in order |

`package.json`: `lint`, `lint:check`, `format`, `format:check` alongside the existing `dev` and
`build`.

`--memory-limit=1G` is mandatory — the 128M default crashes Larastan on this codebase.

---

## 3. Action Classes

### Shape

`App\Actions\<Domain>\<VerbNoun>`, one public `handle()`, `declare(strict_types=1)`, a docblock
that explains *why* the action exists rather than restating the signature. Dependencies arrive
through constructor property promotion and are resolved from the container. Actions that touch
more than one table wrap in `DB::transaction()`.

### The thirteen

| Namespace | Action | Extracted from |
| --- | --- | --- |
| `Resources` | `StoreRecord` | `AdminResourceController::store` |
| `Resources` | `UpdateRecord` | `AdminResourceController::update` |
| `Resources` | `DeleteRecord` | `AdminResourceController::destroy` |
| `Resources` | `BulkDeleteRecords` | the `*.bulk-destroy` routes |
| `Work` | `CreateTask` | `TaskBoardController::store` |
| `Work` | `UpdateTask` | `TaskBoardController::update` |
| `Work` | `DeleteTask` | `TaskBoardController::destroy` |
| `Work` | `MoveTask` | `TaskBoardController::move` |
| `Work` | `SyncTaskToGitHub` | `TaskBoardController::syncToGitHub` |
| `Budget` | `PayDebt` | `DebtController::pay` |
| `Settings` | `SaveSettings` | `SettingsController::update` |
| `Exports` | `ExportResource` | `ExportController::store` |
| `Notifications` | `DismissNotification` | `NotificationController::destroy` |

`AdminResourceController` is 262 lines and `TaskBoardController` 207; both shrink to routing,
authorisation and a redirect.

The five existing `App\Actions\Fortify\*` classes are Laravel's own scaffolding with `create()`
and `update()` entry points rather than `handle()`. They stay as they are — Fortify calls them by
those names through its contracts.

### Dead route cleanup

`App\Http\Controllers\BookController` loses its six empty methods and `routes/web.php:13` becomes
a single `Route::get('books', …)->name('books.index')`. Any test asserting the removed routes is
removed with them.

---

## 4. Comments

Remove `//` and `/* */` comments from method bodies across `app/`, `config/`, `database/`,
`routes/` and `tests/`.

Keep, without exception:

- Every PHPDoc block: `@param`, `@return`, `@var`, `@property`, `@throws`, array shapes.
  PHPStan at max depends on the array-shape docblocks throughout `app/Tables` and `app/Forms`;
  deleting them breaks the build.
- `@phpstan-ignore` and `@pest-ignore-type` annotations and their justifications.
- The licence and attribution headers Laravel ships in `config/*.php`, where removing them would
  leave an option meaningless.

Remove: commented-out code, section-divider banners, and comments that restate the line below
them. `tests/Pest.php` loses the `pest:install` scaffolding — the banner comments, the unused
`toBeOne` expectation and the empty `something()` function.

---

## 5. Testing

Tests are written in the style Nuno Maduro uses: extensive, edge-case first, and meaningful —
each test names a behaviour, not a method.

### Principles

- **Name the behaviour.** `it('refuses to pay a debt larger than its remaining balance')`, not
  `test_pay`.
- **Cover the edges, not the happy path alone.** For every action: the success case, each
  validation failure, the authorisation failure, the boundary value (zero, negative, maximum),
  and the concurrent or duplicate call where it matters.
- **Datasets for combinatorial input.** Currency rounding, slug collisions and date boundaries
  belong in `dataset()`, not copy-pasted tests.
- **One assertion subject per test.** Several `expect()` calls about the same subject are fine;
  testing two behaviours in one test is not.
- **No mocking what we own.** Mock `GitHubService`, `WakaTimeService` and `ExchangeRateService`
  at the HTTP boundary with `Http::fake()`. Do not mock our own actions or models.

### Coverage targets

- Every one of the thirteen actions gets a dedicated test file under `tests/Feature/Actions/`.
- Type coverage enforced at 100% (`pest --type-coverage --min=100`).
- Mutation testing available as `composer mutate` (`pest --mutate --covered-only --min=80`),
  run on demand rather than in CI, because it is slow.

### Architecture tests

A new `tests/Feature/ArchTest.php` applying Pest's presets:

```php
arch()->preset()->php();
arch()->preset()->security();
arch()->preset()->laravel();

arch('actions expose a single entry point')
    ->expect('App\Actions')
    ->toHaveMethod('handle')
    ->ignoring('App\Actions\Fortify');

arch('controllers do not touch the database directly')
    ->expect('App\Http\Controllers')
    ->not->toUse(['Illuminate\Support\Facades\DB']);

arch('no debug helpers ship')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'die'])
    ->not->toBeUsed();
```

The `strict` preset is evaluated during implementation and adopted only if it does not demand
`final` on classes Laravel needs to extend.

---

## 6. Automation

### Pre-commit

Husky plus lint-staged. On staged files only:

- `*.php` → `pint`
- `resources/js/**/*.{js,vue}` → `eslint --fix` then `prettier --write`

PHPStan and the test suite stay out of the hook. A 634-test suite on every commit is a hook
people disable with `--no-verify`; CI is the right place for the slow gates.

### CI

The three workflows collapse into one `.github/workflows/ci.yml` with parallel jobs — `pint`,
`phpstan`, `rector`, `eslint`, `prettier`, `pest` — sharing a composite action at
`.github/actions/setup/action.yml` that installs PHP 8.5, restores the Composer and npm caches,
copies `.env.example` and generates a key.

Triggers stay `push` to `master` and `pull_request`, with the existing `concurrency` cancellation.

Three environment traps, all previously hit, stay guarded: anything read via `config()` must
exist in `.env.example`; assertions derive hosts rather than hardcoding `myblog.test`; the
browser job runs `npx playwright install --with-deps chromium`.

### Versioning

No release-please. Releases are cut by hand:

1. Update `CHANGELOG.md` in Keep a Changelog format under a new version heading.
2. Commit.
3. `git tag -a vX.Y.Z -m "…"` and push the tag.
4. `gh release create` with notes taken from the changelog entry.

Commit messages follow Conventional Commits (`feat:`, `fix:`, `chore:`, `refactor:`, `test:`)
so the changelog can be assembled from the log. Commits carry no AI attribution trailer.

---

## Order of Work

Each numbered group is one commit, pushed to `master` before the next begins.

1. **Dependency upgrades.** Minor and patch first, then Pest 5, then the remaining majors.
   Gate: `php artisan test` green.
2. **Static analysis and style.** PHPStan widening and its fix pass, `rector.php` and its first
   apply, ESLint, Prettier, the script harness.
   Gate: `composer ci:check` and `npm run lint:check` green.
3. **Comment sweep.** Mechanical, verified by the suite still passing and PHPStan staying clean.
4. **Action classes.** Tests first for each action, then the extraction, then the controller
   shrink. The dead `BookController` routes go here.
   Gate: full suite plus the new arch tests.
5. **Automation.** Husky, `ci.yml`, the composite setup action, `CHANGELOG.md`, tag `v0.3.0`.

Groups 2 and 3 produce large diffs but no behaviour change; group 4 is the only one that moves
logic, and it is test-first.

## Risks

| Risk | Mitigation |
| --- | --- |
| PHPStan max over four new paths yields a long error list | Budget a real fix pass; fix rather than baseline |
| Rector's `typeDeclarations` set changes inferred signatures | Apply set by set, run the suite between each |
| `spatie/laravel-sluggable` v4 changes slug generation | Slug tests must assert existing slugs are unmodified before upgrading |
| PHP floor moves to 8.5 | CI pins 8.5 in the same commit; local already runs 8.5.8 |
| Pest 5 browser plugin API drift | The 27 browser checks are the acceptance gate for the upgrade |
