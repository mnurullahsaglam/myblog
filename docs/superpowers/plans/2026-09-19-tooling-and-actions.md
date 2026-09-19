# Tooling, Static Analysis and Action Classes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bring tooling to the dentakay-veil standard — current dependencies, PHPStan at max over the whole application, Rector, a JavaScript lint chain, a pre-commit hook, one CI workflow — and move write-path logic out of controllers into action classes with extensive tests.

**Architecture:** Five sequential groups, each one commit pushed to `master` before the next starts. Dependencies first so every later gate runs on current versions; static analysis and Rector next so later code lands in the final style; the comment sweep third; action extraction fourth, test-first; automation last, because CI and the hook call scripts the earlier groups define.

**Tech Stack:** PHP 8.5, Laravel 13, Pest 5, PHPStan/Larastan at max, Rector 2, ESLint 9 flat config, Prettier, Husky + lint-staged, GitHub Actions.

**Spec:** `docs/superpowers/specs/2026-09-19-tooling-and-actions-design.md`

## Global Constraints

- **PHP 8.5 everywhere, no exceptions.** `composer.json` requires `"php": "^8.5"`. Every GitHub Actions job sets `php-version: '8.5'`. Rector targets `php85: true`. No file, workflow or config may name 8.4 after Task 1.
- **Work directly on `master`.** No feature branches.
- **Commit messages carry no AI attribution trailer.** No `Co-Authored-By`, no generation footer.
- **Conventional Commits.** `feat:`, `fix:`, `chore:`, `refactor:`, `test:`, `docs:`, `ci:`, `build:`.
- **PHPStan always runs with `--memory-limit=1G`.** The 128M default crashes Larastan on this codebase.
- **No PHPStan baseline and no `ignoreErrors`.** Errors are fixed in code. A genuinely unfixable one gets an inline `@phpstan-ignore` with a comment stating why.
- **Rector never rewrites in CI.** CI runs `--dry-run`; a human runs `composer rector:fix`.
- **`.env.example` is the CI contract.** Anything read through `config()` must exist there or CI fails where local passes.
- **Tests derive hosts.** Never hardcode `myblog.test`; `APP_URL` is `localhost` in CI.
- **Docblocks are load-bearing.** The comment sweep removes `//` and `/* */` from bodies only. Every `@param`, `@return`, `@var`, `@property`, `@throws`, array shape, `@phpstan-ignore` and `@pest-ignore-type` stays.
- **Test style:** name the behaviour (`it('refuses …')`), cover edges before the happy path, `dataset()` for combinatorial input, `Http::fake()` at the service boundary, never mock our own actions or models.

---

## File Structure

**Created:**

| Path | Responsibility |
| --- | --- |
| `rector.php` | Rector sets and paths |
| `eslint.config.js` | ESLint 9 flat config for `resources/js` |
| `.prettierrc` / `.prettierignore` | Prettier config and exclusions |
| `.husky/pre-commit` | Runs lint-staged |
| `.github/actions/setup/action.yml` | Composite: PHP 8.5, caches, `.env`, key |
| `.github/workflows/ci.yml` | One workflow, six parallel jobs |
| `CHANGELOG.md` | Keep a Changelog, hand-maintained |
| `app/Actions/Resources/{StoreRecord,UpdateRecord,DeleteRecord,BulkDeleteRecords}.php` | Generic CRUD over a `ResourceForm` |
| `app/Actions/Work/{CreateTask,UpdateTask,DeleteTask,MoveTask,SyncTaskToGitHub}.php` | Task board writes |
| `app/Actions/Budget/PayDebt.php` | Debt payment and expense creation |
| `app/Actions/Settings/SaveSettings.php` | Grouped setting persistence |
| `app/Actions/Exports/ExportResource.php` | CSV export (relocated from `app/Jobs`) |
| `app/Actions/Notifications/DismissNotification.php` | Delete one own notification |
| `tests/Feature/Actions/*Test.php` | One file per action |
| `tests/Feature/ArchTest.php` | Pest arch presets and project rules |

**Modified:** `composer.json`, `package.json`, `phpstan.neon`, `tests/Pest.php`, `routes/web.php`, `app/Http/Controllers/BookController.php`, `app/Http/Controllers/Admin/AdminResourceController.php`, `app/Http/Controllers/Admin/Work/TaskBoardController.php`, `app/Http/Controllers/Admin/Budget/DebtController.php`, `app/Http/Controllers/Admin/SettingsController.php`, `app/Http/Controllers/Admin/ExportController.php`, `app/Http/Controllers/Admin/NotificationController.php`.

**Deleted:** `.github/workflows/{code-style,static-analysis,tests}.yml`, `app/Jobs/RunResourceExport.php`.

---

# Group 1 — Dependency Upgrades

### Task 1: PHP 8.5 floor and minor/patch upgrades

**Files:**
- Modify: `composer.json`
- Modify: `.github/workflows/code-style.yml`, `.github/workflows/static-analysis.yml`, `.github/workflows/tests.yml`

- [ ] **Step 1: Raise the PHP floor**

In `composer.json`, change the `require` entry:

```json
"php": "^8.5",
```

- [ ] **Step 2: Pin every workflow to 8.5**

In all three workflow files, change:

```yaml
          php-version: '8.4'
```

to:

```yaml
          php-version: '8.5'
```

- [ ] **Step 3: Verify no 8.4 remains anywhere**

Run: `grep -rn "8\.4" composer.json .github/ --include="*.yml" --include="*.json"`
Expected: no output. If `composer.json` still shows `8.4` in a `platform` block, remove that block — there is no platform override today and none should be added.

- [ ] **Step 4: Upgrade the safe packages**

```bash
composer update \
  laravel/framework laravel/boost larastan/larastan rector/rector \
  laravel/pint laravel/pail laravel/sail laravel/tinker \
  inertiajs/inertia-laravel mockery/mockery nunomaduro/collision panphp/pan \
  --with-all-dependencies --no-interaction
```

- [ ] **Step 5: Confirm the versions landed**

Run: `composer show laravel/framework laravel/boost | grep -E "^(name|versions)"`
Expected: framework at 13.32 or later, boost at 2.9 or later.

- [ ] **Step 6: Run the suite**

Run: `php artisan test --compact`
Expected: PASS, the same count as before the upgrade.

If Laravel 13.32 changed behaviour a test depends on, fix the application code, not the test, unless the test itself encoded a Laravel implementation detail.

- [ ] **Step 7: Run static analysis**

Run: `vendor/bin/phpstan analyse --memory-limit=1G`
Expected: no errors. Larastan 3.12 may surface new ones; fix them.

- [ ] **Step 8: Commit**

```bash
git add composer.json composer.lock .github/workflows/
git commit -m "build: require PHP 8.5 and upgrade Laravel, Boost and tooling"
```

---

### Task 2: Pest 5

**Files:**
- Modify: `composer.json`
- Modify: `tests/Pest.php`

**Interfaces:**
- Produces: the `--type-coverage` and `--parallel` options relied on by Task 4's scripts and Task 16's CI jobs.

- [ ] **Step 1: Upgrade Pest and its plugins**

```bash
composer require --dev --no-interaction --with-all-dependencies \
  "pestphp/pest:^5.0" \
  "pestphp/pest-plugin-laravel:^5.0" \
  "pestphp/pest-plugin-browser:^5.0" \
  "pestphp/pest-plugin-type-coverage:^5.0" \
  "pestphp/pest-plugin-rector:^5.0"
```

- [ ] **Step 2: Run the full suite and read every failure**

Run: `php artisan test --compact`

Pest 5 is a major. Expected breakages and their fixes:

- A removed or renamed expectation — replace with the Pest 5 equivalent named in the failure message.
- Browser plugin API drift — the 27 browser checks are the acceptance gate; if a page-object method vanished, consult `vendor/pestphp/pest-plugin-browser` for the replacement rather than guessing.
- `pest()->extend(...)->in(...)` signature — confirm `tests/Pest.php` still binds `Tests\TestCase` and `RefreshDatabase` to `Feature` and `Browser`.

Do not proceed to Step 3 until the suite is green.

- [ ] **Step 3: Strip the `pest:install` scaffolding from `tests/Pest.php`**

Replace the whole file with:

```php
<?php

declare(strict_types=1);

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature', 'Browser');
```

This removes the three banner comments, the unused `toBeOne` expectation and the empty `something()` function, none of which is referenced anywhere.

- [ ] **Step 4: Prove nothing used the scaffolding**

Run: `grep -rn "toBeOne\|something()" tests/ app/`
Expected: no output.

- [ ] **Step 5: Run the suite again**

Run: `php artisan test --compact`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add composer.json composer.lock tests/Pest.php
git commit -m "build: upgrade to Pest 5 and drop the install scaffolding"
```

---

### Task 3: Remaining major upgrades

**Files:**
- Modify: `composer.json`

- [ ] **Step 1: Pin the current slugs before touching the package**

`spatie/laravel-sluggable` v4 may change slug generation, and every `Post`, `Book`, `Writer` and `Publisher` URL depends on it. Write a characterisation test first at `tests/Feature/SluggableUpgradeTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Post;

it('keeps generating the slug format the site already publishes', function (string $title, string $expected): void {
    $post = Post::factory()->create(['title' => $title]);

    expect($post->slug)->toBe($expected);
})->with([
    'plain words' => ['Hello World', 'hello-world'],
    'punctuation is dropped' => ['Hello, World!', 'hello-world'],
    'accents are folded' => ['Über Café', 'uber-cafe'],
    'numbers survive' => ['Rust in 2026', 'rust-in-2026'],
]);

it('appends a counter when a slug already exists', function (): void {
    Post::factory()->create(['title' => 'Duplicate Title']);
    $second = Post::factory()->create(['title' => 'Duplicate Title']);

    expect($second->slug)->not->toBe('duplicate-title')
        ->and($second->slug)->toStartWith('duplicate-title');
});
```

- [ ] **Step 2: Run it against the current v3 to record today's behaviour**

Run: `php artisan test --compact --filter=SluggableUpgrade`
Expected: PASS. If a dataset row fails, correct the *expectation* to whatever v3 actually produces — this test documents current behaviour, it does not prescribe new behaviour.

- [ ] **Step 3: Commit the characterisation test on its own**

```bash
git add tests/Feature/SluggableUpgradeTest.php
git commit -m "test: characterise slug generation before the sluggable upgrade"
```

- [ ] **Step 4: Upgrade**

```bash
composer require --no-interaction --with-all-dependencies \
  "spatie/laravel-sluggable:^4.0" \
  "symfony/css-selector:^8.0" \
  "symfony/dom-crawler:^8.0"
```

- [ ] **Step 5: Run the characterisation test**

Run: `php artisan test --compact --filter=SluggableUpgrade`
Expected: PASS unchanged. A failure here means published URLs would change — stop and report it rather than editing the expectations.

- [ ] **Step 6: Run everything**

Run: `php artisan test --compact` then `vendor/bin/phpstan analyse --memory-limit=1G`
Expected: both clean.

- [ ] **Step 7: Commit**

```bash
git add composer.json composer.lock
git commit -m "build: upgrade spatie/laravel-sluggable and symfony components"
```

- [ ] **Step 8: Push group 1**

```bash
git push origin master
```

Then confirm the three workflows go green on 8.5 before starting Group 2.

---

# Group 2 — Static Analysis and Style

### Task 4: Script harness

**Files:**
- Modify: `composer.json`
- Modify: `package.json`

**Interfaces:**
- Produces: `composer lint`, `lint:check`, `rector`, `rector:fix`, `types:check`, `type-coverage`, `test`, `ci:check`; `npm run lint`, `lint:check`, `format`, `format:check`. Tasks 15 and 16 call these by name.

- [ ] **Step 1: Add the composer scripts**

Add to the `scripts` object in `composer.json`, keeping the existing entries:

```json
"lint": "pint --parallel",
"lint:check": "pint --parallel --test",
"rector": "rector process --dry-run",
"rector:fix": "rector process",
"types:check": "phpstan analyse --memory-limit=1G",
"type-coverage": "pest --type-coverage --min=100",
"mutate": "pest --mutate --covered-only --min=80",
"test": [
    "@php artisan config:clear --ansi",
    "@php artisan test --parallel --compact"
],
"ci:check": [
    "@lint:check",
    "@types:check",
    "@rector",
    "@type-coverage",
    "@test"
]
```

- [ ] **Step 2: Add the npm scripts**

Add to `scripts` in `package.json`:

```json
"lint": "eslint resources/js --fix",
"lint:check": "eslint resources/js",
"format": "prettier --write resources/js resources/css",
"format:check": "prettier --check resources/js resources/css"
```

The eslint and prettier binaries arrive in Task 7; the scripts are declared here so the harness is one commit.

- [ ] **Step 3: Verify the composer scripts resolve**

Run: `composer run-script --list | grep -E "lint|rector|types|test|ci:check"`
Expected: every script above is listed.

`mutate` is deliberately absent from `ci:check`: mutation testing is slow and is run on demand.

- [ ] **Step 4: Check the existing gates still run through the new names**

Run: `composer lint:check && composer types:check`
Expected: both pass — these wrap the same commands CI already runs.

- [ ] **Step 5: Commit**

```bash
git add composer.json package.json
git commit -m "build: add lint, static analysis and test script harness"
```

---

### Task 5: PHPStan at max over the whole application

**Files:**
- Modify: `phpstan.neon`
- Modify: whichever application files the new errors point at

- [ ] **Step 1: Widen the configuration**

Replace `phpstan.neon` entirely:

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

- [ ] **Step 2: See the damage**

Run: `vendor/bin/phpstan analyse --memory-limit=1G --error-format=table 2>&1 | tail -40`
Expected: a list of errors. Four of those five paths have never been analysed and `bleedingEdge` adds rules beyond level 10, so errors here are normal.

- [ ] **Step 3: Fix the errors in the code**

Work file by file. Rules:

- Add the missing type, do not widen an existing one to `mixed`.
- Prefer an array-shape docblock over `array`.
- For a genuinely unfixable error, add an inline `@phpstan-ignore <identifier>` with a comment naming the reason. Do not create a baseline file and do not add `ignoreErrors`.
- If `config/*.php` produces errors about vendor array shapes, the fix belongs in a docblock in that config file, not in a global exclusion.

- [ ] **Step 4: Verify clean**

Run: `vendor/bin/phpstan analyse --memory-limit=1G`
Expected: `[OK] No errors`.

- [ ] **Step 5: Confirm nothing broke**

Run: `php artisan test --compact`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add phpstan.neon app/ bootstrap/ config/ database/ routes/
git commit -m "chore: raise PHPStan to max across app, config, database and routes"
```

---

### Task 6: Rector

**Files:**
- Create: `rector.php`
- Modify: `composer.json`
- Modify: whichever files Rector rewrites

- [ ] **Step 1: Install the Laravel rule set**

```bash
composer require --dev --no-interaction "driftingly/rector-laravel:^2.5"
```

- [ ] **Step 2: Create `rector.php`**

```php
<?php

declare(strict_types=1);

use Pest\Rector\Set\PestSetList;
use Rector\Config\RectorConfig;
use RectorLaravel\Set\LaravelSetList;

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

- [ ] **Step 3: Look before applying**

Run: `composer rector 2>&1 | tail -60`
Expected: a list of proposed changes. Read them. `typeDeclarations` can narrow an inferred signature in a way that breaks a subclass — `AdminResourceController` has six abstract methods with subclasses in eight directories, so scrutinise anything it proposes there.

- [ ] **Step 4: Apply one prepared set at a time**

Comment out all but `deadCode` in `withPreparedSets`, then:

```bash
composer rector:fix && php artisan test --compact
```

Re-enable the next set, repeat. Order: `deadCode`, `codeQuality`, `codingStyle`, `typeDeclarations`, `privatization`, `earlyReturn`. Running the suite between sets is what makes a bad rewrite attributable to one set instead of all six.

- [ ] **Step 5: Re-enable every set and confirm idempotence**

Run: `composer rector`
Expected: no proposed changes — a second pass must find nothing.

- [ ] **Step 6: Reformat and re-verify**

Run: `vendor/bin/pint --parallel && vendor/bin/phpstan analyse --memory-limit=1G && php artisan test --compact`
Expected: all three clean. Rector and Pint disagree about some formatting; Pint runs last and wins.

- [ ] **Step 7: Commit**

```bash
git add rector.php composer.json composer.lock app/ bootstrap/ config/ database/ routes/ tests/
git commit -m "refactor: add Rector and apply its prepared, Laravel and Pest sets"
```

---

### Task 7: ESLint and Prettier

**Files:**
- Create: `eslint.config.js`, `.prettierrc`, `.prettierignore`
- Modify: `package.json`
- Modify: files under `resources/js/` that the fixers rewrite

- [ ] **Step 1: Install**

```bash
npm install --save-dev \
  eslint @eslint/js eslint-plugin-vue globals \
  prettier eslint-config-prettier prettier-plugin-tailwindcss
```

- [ ] **Step 2: Create `eslint.config.js`**

```js
import js from '@eslint/js'
import pluginVue from 'eslint-plugin-vue'
import prettier from 'eslint-config-prettier'
import globals from 'globals'

export default [
    { ignores: ['public/build/**', 'vendor/**', 'node_modules/**', 'storage/**'] },
    js.configs.recommended,
    ...pluginVue.configs['flat/recommended'],
    {
        files: ['resources/js/**/*.{js,vue}'],
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: { ...globals.browser },
        },
        rules: {
            'vue/multi-word-component-names': 'off',
            'no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
        },
    },
    prettier,
]
```

`vue/multi-word-component-names` is off because the admin panel has single-word page components (`Index.vue`, `Edit.vue`) that Inertia resolves by path.

- [ ] **Step 3: Create `.prettierrc`**

```json
{
    "semi": false,
    "singleQuote": true,
    "tabWidth": 4,
    "printWidth": 120,
    "plugins": ["prettier-plugin-tailwindcss"]
}
```

- [ ] **Step 4: Create `.prettierignore`**

```
public/build
vendor
node_modules
storage
```

- [ ] **Step 5: See what fails before fixing**

Run: `npm run lint:check`
Expected: a list of violations. Read them for real bugs — an unused import is noise, but `no-undef` on a global the code actually depends on is a finding worth reporting.

- [ ] **Step 6: Fix**

Run: `npm run lint && npm run format`

- [ ] **Step 7: Verify both gates are clean**

Run: `npm run lint:check && npm run format:check`
Expected: both pass.

- [ ] **Step 8: Prove the frontend still builds and runs**

Run: `npm run build && php artisan test --compact`
Expected: build succeeds; the 27 browser checks pass with no console errors. Prettier reorders Tailwind classes, which is safe, but `eslint --fix` can change behaviour — the browser suite is what catches that.

- [ ] **Step 9: Commit and push group 2**

```bash
git add eslint.config.js .prettierrc .prettierignore package.json package-lock.json resources/
git commit -m "build: add ESLint and Prettier for the Vue frontend"
git push origin master
```

---

# Group 3 — Comment Sweep

### Task 8: Remove body comments, keep every docblock

**Files:**
- Modify: files under `app/`, `config/`, `database/`, `routes/`, `tests/`

- [ ] **Step 1: Inventory what is there**

Run: `grep -rn "^\s*//\|^\s*/\*[^*]" app/ config/ database/ routes/ tests/ --include="*.php" | wc -l`
Record the number; it is the budget for this task.

- [ ] **Step 2: Remove, file by file, by hand**

Remove:
- `//` and `/* */` comments inside method and function bodies
- commented-out code
- section-divider banners
- any comment that restates the line below it

Keep, without exception:
- every PHPDoc block: `@param`, `@return`, `@var`, `@property`, `@throws`, array shapes
- `@phpstan-ignore` and `@pest-ignore-type` annotations and their justifications
- comments in `config/*.php` that explain what an option means — removing those leaves a bare value with no meaning

This is not a `sed` job. A regex cannot tell a docblock from a banner, and `//` inside a string literal or a regex pattern is not a comment. Edit the files.

- [ ] **Step 3: Confirm docblocks survived**

Run: `grep -rc "@param\|@return\|@var\|@property" app/Tables app/Forms | grep ":0" || echo "all definition files still carry docblocks"`
Expected: the `echo` fires. PHPStan at max depends on the array shapes in `app/Tables` and `app/Forms`; a zero here means the sweep went too far.

- [ ] **Step 4: Verify the gates**

Run: `vendor/bin/phpstan analyse --memory-limit=1G && php artisan test --compact && vendor/bin/pint --parallel --test`
Expected: all clean. PHPStan failing here means a removed docblock was load-bearing — restore it.

- [ ] **Step 5: Commit and push group 3**

```bash
git add app/ config/ database/ routes/ tests/
git commit -m "style: remove inline body comments, keep all docblocks"
git push origin master
```

---

# Group 4 — Action Classes

Every task in this group is test-first: write the action's test, watch it fail, write the action, watch it pass, then move the controller onto it.

### Task 9: Delete the dead public book routes

**Files:**
- Modify: `app/Http/Controllers/BookController.php`
- Modify: `routes/web.php:13`
- Test: `tests/Feature/PublicBooksTest.php`

- [ ] **Step 1: Write the test that pins the one route that works**

Create `tests/Feature/PublicBooksTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Book;

it('lists published books', function (): void {
    $books = Book::factory()->count(3)->create();

    $this->get(route('books.index'))
        ->assertOk()
        ->assertSee($books->first()->title);
});

it('no longer exposes write routes for books', function (string $method, string $uri): void {
    $this->call($method, $uri)->assertMethodNotAllowed();
})->with([
    'store' => ['POST', '/books'],
    'update' => ['PUT', '/books/1'],
    'destroy' => ['DELETE', '/books/1'],
]);

it('no longer exposes a create form', function (): void {
    $this->get('/books/create')->assertNotFound();
});
```

- [ ] **Step 2: Run it and watch the write-route assertions fail**

Run: `php artisan test --compact --filter=PublicBooks`
Expected: FAIL — the routes currently exist and return 200 with an empty body, not 405.

- [ ] **Step 3: Narrow the route**

In `routes/web.php`, replace line 13:

```php
Route::resource('books', BookController::class);
```

with:

```php
Route::get('books', [BookController::class, 'index'])->name('books.index');
```

- [ ] **Step 4: Strip the empty methods**

`app/Http/Controllers/BookController.php` becomes:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Contracts\View\View;

final class BookController extends Controller
{
    public function index(): View
    {
        $books = Book::with('writer')->paginate(12);

        return view('pages.books.index', compact('books'));
    }
}
```

- [ ] **Step 5: Run the test**

Run: `php artisan test --compact --filter=PublicBooks`
Expected: PASS.

- [ ] **Step 6: Check nothing linked to the removed route names**

Run: `grep -rn "books.create\|books.store\|books.edit\|books.update\|books.destroy\|books.show" resources/ app/ tests/ routes/`
Expected: only `admin.books.*` matches. A bare `books.*` match is a broken `route()` call — fix it.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/BookController.php routes/web.php tests/Feature/PublicBooksTest.php
git commit -m "fix: remove the six empty public book routes"
```

---

### Task 10: Resource CRUD actions

**Files:**
- Create: `app/Actions/Resources/Concerns/SyncsRelations.php`
- Create: `app/Actions/Resources/StoreRecord.php`, `UpdateRecord.php`, `DeleteRecord.php`, `BulkDeleteRecords.php`
- Modify: `app/Http/Controllers/Admin/AdminResourceController.php`
- Test: `tests/Feature/Actions/Resources/StoreRecordTest.php`, `UpdateRecordTest.php`, `DeleteRecordTest.php`, `BulkDeleteRecordsTest.php`

**Interfaces:**
- Produces:
  - `StoreRecord::handle(string $modelClass, array{attributes: array<string, mixed>, relations: array<string, array<int, mixed>>} $partitioned): Model`
  - `UpdateRecord::handle(Model $record, array{attributes: …, relations: …} $partitioned): Model`
  - `DeleteRecord::handle(Model $record): void`
  - `BulkDeleteRecords::handle(string $modelClass, array<int, int> $ids): int`

- [ ] **Step 1: Write the tests**

Create `tests/Feature/Actions/Resources/StoreRecordTest.php`:

```php
<?php

declare(strict_types=1);

use App\Actions\Resources\StoreRecord;
use App\Models\Category;
use App\Models\Post;

it('creates a record from partitioned attributes', function (): void {
    $post = app(StoreRecord::class)->handle(Post::class, [
        'attributes' => ['title' => 'A New Post', 'body' => 'Body copy.'],
        'relations' => [],
    ]);

    expect($post)->toBeInstanceOf(Post::class)
        ->and($post->exists)->toBeTrue()
        ->and($post->title)->toBe('A New Post');
});

it('syncs many-to-many relations declared in the partition', function (): void {
    $categories = Category::factory()->count(2)->create();

    $post = app(StoreRecord::class)->handle(Post::class, [
        'attributes' => ['title' => 'Tagged', 'body' => 'Body copy.'],
        'relations' => ['categories' => $categories->modelKeys()],
    ]);

    expect($post->categories()->pluck('categories.id')->all())
        ->toEqualCanonicalizing($categories->modelKeys());
});

it('ignores a relation key the model does not define', function (): void {
    $post = app(StoreRecord::class)->handle(Post::class, [
        'attributes' => ['title' => 'Unknown Relation', 'body' => 'Body copy.'],
        'relations' => ['nonsense' => [1, 2, 3]],
    ]);

    expect($post->exists)->toBeTrue();
});

it('creates nothing when syncing a relation fails', function (): void {
    expect(fn () => app(StoreRecord::class)->handle(Post::class, [
        'attributes' => ['title' => 'Rolled Back', 'body' => 'Body copy.'],
        'relations' => ['categories' => [999_999]],
    ]))->toThrow(Throwable::class);

    expect(Post::where('title', 'Rolled Back')->exists())->toBeFalse();
});
```

That last test is the reason these actions wrap in a transaction: today `AdminResourceController::store` creates the record and *then* syncs, so a bad relation id leaves an orphan row behind.

Create `tests/Feature/Actions/Resources/BulkDeleteRecordsTest.php`:

```php
<?php

declare(strict_types=1);

use App\Actions\Resources\BulkDeleteRecords;
use App\Models\Post;

it('deletes every listed record and reports the count', function (): void {
    $posts = Post::factory()->count(3)->create();
    $survivor = Post::factory()->create();

    $deleted = app(BulkDeleteRecords::class)->handle(Post::class, $posts->modelKeys());

    expect($deleted)->toBe(3)
        ->and(Post::whereKey($survivor->getKey())->exists())->toBeTrue();
});

it('deletes nothing when given an empty list', function (): void {
    Post::factory()->count(2)->create();

    expect(app(BulkDeleteRecords::class)->handle(Post::class, []))->toBe(0)
        ->and(Post::count())->toBe(2);
});

it('ignores ids that do not exist', function (): void {
    $post = Post::factory()->create();

    $deleted = app(BulkDeleteRecords::class)->handle(Post::class, [$post->getKey(), 999_999]);

    expect($deleted)->toBe(1);
});
```

Write `UpdateRecordTest.php` and `DeleteRecordTest.php` in the same shape: update covers changing an attribute, replacing a relation set, clearing a relation set to empty, and rolling back on a bad relation id; delete covers removing the row and returning cleanly when the record was already deleted.

- [ ] **Step 2: Run the tests and watch them fail**

Run: `php artisan test --compact --filter="Actions/Resources"`
Expected: FAIL — `Class "App\Actions\Resources\StoreRecord" not found`.

- [ ] **Step 3: Write the shared relation sync**

Create `app/Actions/Resources/Concerns/SyncsRelations.php`:

```php
<?php

declare(strict_types=1);

namespace App\Actions\Resources\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait SyncsRelations
{
    /**
     * @param  array<string, array<int, mixed>>  $relations
     */
    private function syncRelations(Model $record, array $relations): void
    {
        foreach ($relations as $key => $ids) {
            if (! method_exists($record, $key)) {
                continue;
            }

            $relation = $record->{$key}();

            if ($relation instanceof BelongsToMany) {
                $relation->sync($ids);
            }
        }
    }
}
```

- [ ] **Step 4: Write the four actions**

`app/Actions/Resources/StoreRecord.php`:

```php
<?php

declare(strict_types=1);

namespace App\Actions\Resources;

use App\Actions\Resources\Concerns\SyncsRelations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Create a record and attach its many-to-many relations as one unit.
 *
 * The transaction matters: a rejected relation id would otherwise leave a
 * created row behind with none of the relations the form asked for.
 */
final class StoreRecord
{
    use SyncsRelations;

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array{attributes: array<string, mixed>, relations: array<string, array<int, mixed>>}  $partitioned
     */
    public function handle(string $modelClass, array $partitioned): Model
    {
        return DB::transaction(function () use ($modelClass, $partitioned): Model {
            $record = $modelClass::create($partitioned['attributes']);

            $this->syncRelations($record, $partitioned['relations']);

            return $record;
        });
    }
}
```

`app/Actions/Resources/UpdateRecord.php`:

```php
<?php

declare(strict_types=1);

namespace App\Actions\Resources;

use App\Actions\Resources\Concerns\SyncsRelations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Apply an edit and its relation changes as one unit, for the same reason
 * StoreRecord does.
 */
final class UpdateRecord
{
    use SyncsRelations;

    /**
     * @param  array{attributes: array<string, mixed>, relations: array<string, array<int, mixed>>}  $partitioned
     */
    public function handle(Model $record, array $partitioned): Model
    {
        return DB::transaction(function () use ($record, $partitioned): Model {
            $record->update($partitioned['attributes']);

            $this->syncRelations($record, $partitioned['relations']);

            return $record;
        });
    }
}
```

`app/Actions/Resources/DeleteRecord.php`:

```php
<?php

declare(strict_types=1);

namespace App\Actions\Resources;

use Illuminate\Database\Eloquent\Model;

final class DeleteRecord
{
    public function handle(Model $record): void
    {
        $record->delete();
    }
}
```

`app/Actions/Resources/BulkDeleteRecords.php`:

```php
<?php

declare(strict_types=1);

namespace App\Actions\Resources;

use Illuminate\Database\Eloquent\Model;

/**
 * Delete several records of one type, reporting how many rows actually went.
 *
 * The caller needs the real count rather than the requested count, because the
 * success message names a number and ids can disappear between render and submit.
 */
final class BulkDeleteRecords
{
    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<int, int>  $ids
     */
    public function handle(string $modelClass, array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        return $modelClass::query()->whereKey($ids)->delete();
    }
}
```

- [ ] **Step 5: Run the tests**

Run: `php artisan test --compact --filter="Actions/Resources"`
Expected: PASS.

- [ ] **Step 6: Move the controller onto the actions**

In `AdminResourceController`, inject the four actions and delete `syncRelations()`:

```php
public function __construct(
    protected readonly AdminNotifier $notifier,
    protected readonly StoreRecord $storeRecord,
    protected readonly UpdateRecord $updateRecord,
    protected readonly DeleteRecord $deleteRecord,
    protected readonly BulkDeleteRecords $bulkDeleteRecords,
) {}
```

`store()`:

```php
public function store(): RedirectResponse
{
    $this->storeRecord->handle($this->modelClass(), $this->form()->partition($this->validated()));

    $this->notifier->success(Str::ucfirst($this->label()).' created');

    return to_route($this->indexRoute());
}
```

`update()`:

```php
public function update(Request $request): RedirectResponse
{
    $this->updateRecord->handle(
        $this->resolveRecord($request),
        $this->form()->partition($this->validated()),
    );

    $this->notifier->success(Str::ucfirst($this->label()).' updated');

    return to_route($this->indexRoute());
}
```

`destroy()`:

```php
public function destroy(Request $request): RedirectResponse
{
    $this->deleteRecord->handle($this->resolveRecord($request));

    $this->notifier->success(Str::ucfirst($this->label()).' deleted');

    return to_route($this->indexRoute());
}
```

`bulkDestroy()` keeps its validation and message, delegating only the deletion:

```php
$count = $this->bulkDeleteRecords->handle($model, $ids);

$this->notifier->success($count.' '.Str::plural($this->label(), $count).' deleted');
```

Note the message now reports rows actually deleted, not ids submitted.

Every subclass constructor must be checked: `DebtController` and the other twelve inherit this constructor, so any that declares its own must forward the new arguments.

- [ ] **Step 7: Run everything**

Run: `php artisan test --compact`
Expected: PASS. All thirteen resources route through this controller, so a break here is a break everywhere.

- [ ] **Step 8: Commit**

```bash
git add app/Actions/Resources app/Http/Controllers/Admin/AdminResourceController.php tests/Feature/Actions/Resources
git commit -m "refactor: extract resource CRUD into action classes"
```

---

### Task 11: Task board actions

**Files:**
- Create: `app/Actions/Work/{CreateTask,UpdateTask,DeleteTask,MoveTask,SyncTaskToGitHub}.php`
- Modify: `app/Http/Controllers/Admin/Work/TaskBoardController.php`
- Test: `tests/Feature/Actions/Work/{CreateTask,UpdateTask,DeleteTask,MoveTask,SyncTaskToGitHub}Test.php`

**Interfaces:**
- Produces:
  - `CreateTask::handle(array<string, mixed> $attributes): Task`
  - `UpdateTask::handle(Task $task, array<string, mixed> $attributes): Task`
  - `DeleteTask::handle(Task $task): void`
  - `MoveTask::handle(Task $task, string $status, int $position): void`
  - `SyncTaskToGitHub::handle(Task $task): bool` — throws `AccessDeniedHttpException` when the task is not linked to an issue

- [ ] **Step 1: Write `MoveTaskTest.php` — the one with real ordering logic**

```php
<?php

declare(strict_types=1);

use App\Actions\Work\MoveTask;
use App\Models\Task;

function orderedTitles(string $status): array
{
    return Task::where('status', $status)->orderBy('sort_order')->pluck('title')->all();
}

it('places a task at the requested position and reindexes its new column', function (): void {
    Task::factory()->create(['status' => 'todo', 'title' => 'A', 'sort_order' => 1]);
    Task::factory()->create(['status' => 'todo', 'title' => 'B', 'sort_order' => 2]);
    $moving = Task::factory()->create(['status' => 'in_progress', 'title' => 'M', 'sort_order' => 1]);

    app(MoveTask::class)->handle($moving, 'todo', 1);

    expect(orderedTitles('todo'))->toBe(['A', 'M', 'B']);
});

it('appends when the position is past the end of the column', function (): void {
    Task::factory()->create(['status' => 'todo', 'title' => 'A', 'sort_order' => 1]);
    $moving = Task::factory()->create(['status' => 'in_progress', 'title' => 'M']);

    app(MoveTask::class)->handle($moving, 'todo', 99);

    expect(orderedTitles('todo'))->toBe(['A', 'M']);
});

it('places at the head when the position is zero', function (): void {
    Task::factory()->create(['status' => 'todo', 'title' => 'A', 'sort_order' => 1]);
    $moving = Task::factory()->create(['status' => 'in_progress', 'title' => 'M']);

    app(MoveTask::class)->handle($moving, 'todo', 0);

    expect(orderedTitles('todo'))->toBe(['M', 'A']);
});

it('reorders within a column without changing the status', function (): void {
    Task::factory()->create(['status' => 'todo', 'title' => 'A', 'sort_order' => 1]);
    $b = Task::factory()->create(['status' => 'todo', 'title' => 'B', 'sort_order' => 2]);

    app(MoveTask::class)->handle($b, 'todo', 0);

    expect(orderedTitles('todo'))->toBe(['B', 'A'])
        ->and($b->refresh()->status)->toBe('todo');
});

it('leaves sort_order contiguous from one with no gaps', function (): void {
    $tasks = Task::factory()->count(4)->sequence(
        ['sort_order' => 1], ['sort_order' => 5], ['sort_order' => 9], ['sort_order' => 12],
    )->create(['status' => 'todo']);

    app(MoveTask::class)->handle($tasks->last(), 'todo', 0);

    expect(Task::where('status', 'todo')->orderBy('sort_order')->pluck('sort_order')->all())
        ->toBe([1, 2, 3, 4]);
});

it('does not touch tasks in other columns', function (): void {
    $other = Task::factory()->create(['status' => 'completed', 'sort_order' => 7]);
    $moving = Task::factory()->create(['status' => 'in_progress']);

    app(MoveTask::class)->handle($moving, 'todo', 0);

    expect($other->refresh()->sort_order)->toBe(7);
});
```

The existing behaviour that must be preserved: the moved task is saved through `update()` so `TaskObserver` sees the status change and syncs GitHub, while siblings are reordered with `updateQuietly()` so pure reordering makes no API calls. Add a test asserting exactly that:

```php
it('reorders siblings quietly, so only the moved task reaches GitHub', function (): void {
    Http::fake();

    $sibling = Task::factory()->create([
        'status' => 'todo',
        'sort_order' => 1,
        'is_github_issue' => true,
        'github_issue_number' => '1',
    ]);
    $moving = Task::factory()->create([
        'status' => 'in_progress',
        'is_github_issue' => true,
        'github_issue_number' => '2',
    ]);

    $siblingTouchedAt = $sibling->updated_at;

    app(MoveTask::class)->handle($moving, 'todo', 0);

    expect($sibling->fresh()->updated_at->equalTo($siblingTouchedAt))->toBeTrue();

    Http::assertSentCount(1);
});

The sibling is reordered with `updateQuietly()`, so its `updated_at` must not move and
no second GitHub call may go out. `Http::assertSentCount(1)` is what proves the quiet
path stayed quiet — a naive rewrite that saved every row through `update()` would send
two and fail here.
```

- [ ] **Step 2: Write `SyncTaskToGitHubTest.php`**

```php
<?php

declare(strict_types=1);

use App\Actions\Work\SyncTaskToGitHub;
use App\Models\Task;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

it('refuses a task that is not linked to a GitHub issue', function (): void {
    $task = Task::factory()->create(['is_github_issue' => false]);

    expect(fn () => app(SyncTaskToGitHub::class)->handle($task))
        ->toThrow(AccessDeniedHttpException::class);
});

it('reports success when GitHub accepts the update', function (): void {
    Http::fake(['api.github.com/*' => Http::response(['number' => 12], 200)]);

    $task = Task::factory()->create(['is_github_issue' => true, 'github_issue_number' => '12']);

    expect(app(SyncTaskToGitHub::class)->handle($task))->toBeTrue();
});

it('reports failure when GitHub rejects the update', function (): void {
    Http::fake(['api.github.com/*' => Http::response(['message' => 'Not Found'], 404)]);

    $task = Task::factory()->create(['is_github_issue' => true, 'github_issue_number' => '12']);

    expect(app(SyncTaskToGitHub::class)->handle($task))->toBeFalse();
});

it('lets a transport failure surface to the caller', function (): void {
    Http::fake(fn () => throw new ConnectionException('network down'));

    $task = Task::factory()->create(['is_github_issue' => true, 'github_issue_number' => '12']);

    expect(fn () => app(SyncTaskToGitHub::class)->handle($task))->toThrow(Throwable::class);
});
```

The action throws; the controller catches and turns it into a notification. That split is deliberate — an action reports what happened, a controller decides how to say it.

- [ ] **Step 3: Run and watch them fail**

Run: `php artisan test --compact --filter="Actions/Work"`
Expected: FAIL — classes not found.

- [ ] **Step 4: Write the actions**

`app/Actions/Work/MoveTask.php` — lift the body of `TaskBoardController::move` verbatim, replacing `$data['status']` and `$data['position']` with the parameters:

```php
<?php

declare(strict_types=1);

namespace App\Actions\Work;

use App\Models\Task;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Move a task to a column and a position within it.
 *
 * The moved task is saved through update() so TaskObserver sees the status
 * change and syncs GitHub; its siblings are reordered with updateQuietly() so a
 * pure drag makes no API calls.
 */
final class MoveTask
{
    public function handle(Task $task, string $status, int $position): void
    {
        DB::transaction(function () use ($task, $status, $position): void {
            $task->updateQuietly(['sort_order' => null]);

            /** @var Collection<int, Task> $siblings */
            $siblings = Task::query()
                ->where('status', $status)
                ->whereKeyNot($task->getKey())
                ->orderBy('sort_order')
                ->get();

            $ordered = $siblings->values();
            $ordered->splice(min($position, $ordered->count()), 0, [$task]);

            foreach ($ordered as $index => $sibling) {
                if ($sibling->is($task)) {
                    $task->update(['status' => $status, 'sort_order' => $index + 1]);

                    continue;
                }

                $sibling->updateQuietly(['sort_order' => $index + 1]);
            }
        });
    }
}
```

`app/Actions/Work/SyncTaskToGitHub.php`:

```php
<?php

declare(strict_types=1);

namespace App\Actions\Work;

use App\Models\Task;
use App\Services\GitHubService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Push a task's current state to the GitHub issue it mirrors.
 *
 * Returns whether GitHub accepted the change and lets transport failures
 * propagate, so the caller can tell "rejected" from "never reached".
 */
final readonly class SyncTaskToGitHub
{
    public function __construct(private GitHubService $github) {}

    public function handle(Task $task): bool
    {
        if (! $task->is_github_issue) {
            throw new AccessDeniedHttpException('This task is not linked to a GitHub issue.');
        }

        return $this->github->updateIssue($task);
    }
}
```

`CreateTask`, `UpdateTask` and `DeleteTask` are three lines each:

```php
final class CreateTask
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes): Task
    {
        return Task::create($attributes);
    }
}
```

- [ ] **Step 5: Run the tests**

Run: `php artisan test --compact --filter="Actions/Work"`
Expected: PASS.

- [ ] **Step 6: Move the controller onto the actions**

`TaskBoardController` injects all five. `move()` becomes:

```php
public function move(MoveTaskRequest $request, Task $task): RedirectResponse
{
    /** @var array{status: string, position: int} $data */
    $data = $request->validated();

    $this->moveTask->handle($task, $data['status'], $data['position']);

    return back();
}
```

`syncToGitHub()` keeps the presentation decision:

```php
public function syncToGitHub(Task $task): RedirectResponse
{
    try {
        $this->syncTaskToGitHub->handle($task)
            ? $this->notifier->success('Synced to GitHub')
            : $this->notifier->danger('GitHub rejected the update');
    } catch (AccessDeniedHttpException $exception) {
        throw $exception;
    } catch (Throwable $exception) {
        $this->notifier->danger('Could not sync to GitHub', $exception->getMessage());
    }

    return to_route('admin.tasks.board');
}
```

The `AccessDeniedHttpException` is re-thrown rather than swallowed, preserving today's 403 for an unlinked task.

`index()` and `present()` stay exactly as they are.

- [ ] **Step 7: Run the suite**

Run: `php artisan test --compact`
Expected: PASS, including the existing task board feature and browser tests.

- [ ] **Step 8: Commit**

```bash
git add app/Actions/Work app/Http/Controllers/Admin/Work/TaskBoardController.php tests/Feature/Actions/Work
git commit -m "refactor: extract task board writes into action classes"
```

---

### Task 12: PayDebt

**Files:**
- Create: `app/Actions/Budget/PayDebt.php`
- Modify: `app/Http/Controllers/Admin/Budget/DebtController.php`
- Test: `tests/Feature/Actions/Budget/PayDebtTest.php`

**Interfaces:**
- Produces: `PayDebt::handle(Debt $debt, float $payment, string $description, ?string $receiptPath = null): float` — returns the remaining balance, rounded to two places.

- [ ] **Step 1: Write the test**

`tests/Feature/Actions/Budget/PayDebtTest.php`:

```php
<?php

declare(strict_types=1);

use App\Actions\Budget\PayDebt;
use App\Models\Debt;
use App\Models\Expense;

it('records a partial payment and reduces the balance', function (): void {
    $debt = Debt::factory()->create(['amount' => 1000, 'status' => 'unpaid']);

    $remaining = app(PayDebt::class)->handle($debt, 250.0, 'First instalment');

    expect($remaining)->toBe(750.0)
        ->and($debt->refresh()->amount)->toEqual(750)
        ->and($debt->status)->toBe('unpaid');
});

it('settles the debt when the payment clears the balance exactly', function (): void {
    $debt = Debt::factory()->create(['amount' => 500, 'status' => 'unpaid']);

    $remaining = app(PayDebt::class)->handle($debt, 500.0, 'Final payment');

    expect($remaining)->toBe(0.0)
        ->and($debt->refresh()->amount)->toEqual(0)
        ->and($debt->status)->toBe('paid');
});

it('settles rather than going negative when the payment overshoots', function (): void {
    $debt = Debt::factory()->create(['amount' => 100, 'status' => 'unpaid']);

    $remaining = app(PayDebt::class)->handle($debt, 150.0, 'Overpaid');

    expect($remaining)->toBeLessThanOrEqual(0.0)
        ->and($debt->refresh()->amount)->toEqual(0)
        ->and($debt->status)->toBe('paid');
});

it('creates one expense carrying the debt currency and today as the date', function (): void {
    $debt = Debt::factory()->create(['amount' => 300, 'currency' => 'USD']);

    app(PayDebt::class)->handle($debt, 100.0, 'Instalment');

    $expense = Expense::where('debt_id', $debt->id)->sole();

    expect((float) $expense->amount)->toBe(100.0)
        ->and($expense->currency)->toBe('USD')
        ->and($expense->description)->toBe('Instalment')
        ->and($expense->date->toDateString())->toBe(now()->toDateString());
});

it('stores the receipt path on the expense when one is given', function (): void {
    $debt = Debt::factory()->create(['amount' => 300]);

    app(PayDebt::class)->handle($debt, 100.0, 'With receipt', 'receipts/debt-payments/abc.pdf');

    expect(Expense::where('debt_id', $debt->id)->sole()->receipt_path)
        ->toBe('receipts/debt-payments/abc.pdf');
});

it('leaves the receipt path null when none is given', function (): void {
    $debt = Debt::factory()->create(['amount' => 300]);

    app(PayDebt::class)->handle($debt, 100.0, 'No receipt');

    expect(Expense::where('debt_id', $debt->id)->sole()->receipt_path)->toBeNull();
});

it('does not let the observer create a second expense for the settling payment', function (): void {
    $debt = Debt::factory()->create(['amount' => 200, 'status' => 'unpaid']);

    app(PayDebt::class)->handle($debt, 200.0, 'Settles it');

    expect(Expense::where('debt_id', $debt->id)->count())->toBe(1);
});

it('rounds to two decimal places', function (float $amount, float $payment, float $expected): void {
    $debt = Debt::factory()->create(['amount' => $amount]);

    expect(app(PayDebt::class)->handle($debt, $payment, 'Rounding'))->toBe($expected);
})->with([
    'third of a sum' => [100.00, 33.333, 66.67],
    'floating point drift' => [0.30, 0.10, 0.20],
    'repeating remainder' => [10.00, 3.33, 6.67],
]);

it('records nothing when the expense cannot be created', function (): void {
    $debt = Debt::factory()->create(['amount' => 500]);

    expect(fn () => app(PayDebt::class)->handle($debt, 100.0, str_repeat('x', 100_000)))
        ->toThrow(Throwable::class);

    expect($debt->refresh()->amount)->toEqual(500)
        ->and(Expense::where('debt_id', $debt->id)->count())->toBe(0);
});
```

The seventh test is the one that matters most: `DebtObserver` creates an expense when a debt flips to `paid`, so the action must create its expense *before* the status change or every settlement is double-counted. That ordering is currently a comment in the controller; here it becomes an assertion.

- [ ] **Step 2: Run and watch it fail**

Run: `php artisan test --compact --filter=PayDebt`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the action**

```php
<?php

declare(strict_types=1);

namespace App\Actions\Budget;

use App\Models\Debt;
use App\Models\Expense;
use Illuminate\Support\Facades\DB;

/**
 * Record a payment against a debt, in full or in part.
 *
 * The expense is created before the status flips because DebtObserver creates
 * one of its own when a debt becomes paid; writing ours first means the
 * observer finds it and does not double-count the settlement.
 *
 * @return float the remaining balance, rounded to two places
 */
final class PayDebt
{
    public function handle(Debt $debt, float $payment, string $description, ?string $receiptPath = null): float
    {
        $remaining = round((float) $debt->amount - $payment, 2);

        DB::transaction(function () use ($debt, $payment, $description, $receiptPath, $remaining): void {
            Expense::create([
                'debt_id' => $debt->id,
                'amount' => $payment,
                'currency' => $debt->currency->value,
                'description' => $description,
                'receipt_path' => $receiptPath,
                'date' => now()->toDateString(),
            ]);

            $debt->update($remaining <= 0
                ? ['amount' => 0, 'status' => 'paid']
                : ['amount' => $remaining]);
        });

        return $remaining;
    }
}
```

- [ ] **Step 4: Run the tests**

Run: `php artisan test --compact --filter=PayDebt`
Expected: PASS. If the rounding dataset fails, the expectation is wrong, not the code — `round()` gives `66.67` for `100 - 33.333`; correct the dataset to match real behaviour and note it.

- [ ] **Step 5: Move the controller onto it**

`DebtController::pay()` keeps file handling and messaging:

```php
public function pay(PayDebtRequest $request, Debt $debt, PayDebt $payDebt): RedirectResponse
{
    /** @var array{payment_amount: numeric-string|float|int, payment_description: string} $data */
    $data = $request->validated();

    $payment = (float) $data['payment_amount'];

    $receiptPath = $request->hasFile('receipt')
        ? $request->file('receipt')?->store('receipts/debt-payments', 'public')
        : null;

    $remaining = $payDebt->handle($debt, $payment, $data['payment_description'], $receiptPath);

    $symbol = $debt->currency->getSymbol();

    if ($remaining <= 0) {
        $this->notifier->success('Debt settled', "Nothing further owed to {$debt->creditor_name}.");
    } else {
        $this->notifier->success(
            'Partial payment recorded',
            'Paid '.$symbol.number_format($payment, 2).'. Remaining '.$symbol.number_format($remaining, 2).'.',
        );
    }

    return to_route('admin.debts.index');
}
```

`$debt->currency->getSymbol()` is read after the action runs, which is safe — the action changes `amount` and `status`, never `currency`.

- [ ] **Step 6: Run the suite**

Run: `php artisan test --compact`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Actions/Budget app/Http/Controllers/Admin/Budget/DebtController.php tests/Feature/Actions/Budget
git commit -m "refactor: extract debt payment into a PayDebt action"
```

---

### Task 13: Settings, export and notification actions

**Files:**
- Create: `app/Actions/Settings/SaveSettings.php`, `app/Actions/Exports/ExportResource.php`, `app/Actions/Notifications/DismissNotification.php`
- Delete: `app/Jobs/RunResourceExport.php`
- Modify: `app/Http/Controllers/Admin/SettingsController.php`, `ExportController.php`, `NotificationController.php`
- Test: `tests/Feature/Actions/Settings/SaveSettingsTest.php`, `tests/Feature/Actions/Exports/ExportResourceTest.php`, `tests/Feature/Actions/Notifications/DismissNotificationTest.php`

**Interfaces:**
- Produces:
  - `SaveSettings::handle(array<string, array<string, mixed>> $groups, array<string, UploadedFile> $files): void`
  - `ExportResource::handle(string $resource): string` — the stored path on the private disk; `ExportResource::EXPORTS` is the resource-to-exporter map
  - `DismissNotification::handle(User $user, string $notificationId): void`

- [ ] **Step 1: Note what `ExportResource` really is**

`app/Jobs/RunResourceExport` already has a `handle()` returning a path, and `ExportController::store` runs it inline with `new RunResourceExport($resource)` rather than dispatching. It is an action wearing a job's clothes. This task moves it to `App\Actions\Exports\ExportResource`, drops `ShouldQueue` and the `Queueable` trait, takes `$resource` as a `handle()` parameter instead of a constructor property, and deletes the job. Nothing dispatches it, so nothing breaks.

- [ ] **Step 2: Write the tests**

`tests/Feature/Actions/Exports/ExportResourceTest.php`:

```php
<?php

declare(strict_types=1);

use App\Actions\Exports\ExportResource;
use App\Models\Book;
use Illuminate\Support\Facades\Storage;

it('writes a CSV to the private disk and returns its path', function (): void {
    Storage::fake('local');
    Book::factory()->count(2)->create();

    $path = app(ExportResource::class)->handle('books');

    expect($path)->toStartWith('exports/')
        ->and(Storage::disk('local')->exists($path))->toBeTrue();
});

it('writes a header row even when there is nothing to export', function (): void {
    Storage::fake('local');

    $path = app(ExportResource::class)->handle('books');
    $lines = array_filter(explode("\n", (string) Storage::disk('local')->get($path)));

    expect($lines)->toHaveCount(1);
});

it('writes one row per record after the header', function (): void {
    Storage::fake('local');
    Book::factory()->count(3)->create();

    $path = app(ExportResource::class)->handle('books');
    $lines = array_filter(explode("\n", (string) Storage::disk('local')->get($path)));

    expect($lines)->toHaveCount(4);
});

it('rejects a resource it has no exporter for', function (): void {
    expect(fn () => app(ExportResource::class)->handle('nonsense'))
        ->toThrow(RuntimeException::class, 'No export is defined for [nonsense].');
})->with(['nonsense', '', 'BOOKS', 'books ']);

it('exports every declared resource', function (string $resource): void {
    Storage::fake('local');

    expect(app(ExportResource::class)->handle($resource))->toStartWith('exports/');
})->with(array_keys(App\Actions\Exports\ExportResource::EXPORTS));
```

`tests/Feature/Actions/Notifications/DismissNotificationTest.php`:

```php
<?php

declare(strict_types=1);

use App\Actions\Notifications\DismissNotification;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

it('deletes a notification belonging to the user', function (): void {
    $user = User::factory()->create();
    $user->notify(new App\Notifications\AdminAlert('Heads up', 'Body'));
    $id = (string) $user->notifications()->sole()->id;

    app(DismissNotification::class)->handle($user, $id);

    expect($user->notifications()->count())->toBe(0);
});

it('refuses to delete another user\'s notification', function (): void {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $owner->notify(new App\Notifications\AdminAlert('Private', 'Body'));
    $id = (string) $owner->notifications()->sole()->id;

    expect(fn () => app(DismissNotification::class)->handle($intruder, $id))
        ->toThrow(NotFoundHttpException::class);

    expect($owner->notifications()->count())->toBe(1);
});

it('refuses an id that does not exist', function (): void {
    $user = User::factory()->create();

    expect(fn () => app(DismissNotification::class)->handle($user, 'not-a-real-id'))
        ->toThrow(NotFoundHttpException::class);
});
```

`tests/Feature/Actions/Settings/SaveSettingsTest.php` covers: a scalar value is stored as a string; an array value is stored as JSON with type `json`; an upload field with no file leaves the stored path untouched; an upload field with a file stores it and records type `file`; a non-array group is skipped; and `meta.meta_keywords` survives a round trip through the edit screen as an array.

- [ ] **Step 3: Run and watch them fail**

Run: `php artisan test --compact --filter="Actions/(Settings|Exports|Notifications)"`
Expected: FAIL — classes not found.

- [ ] **Step 4: Write the actions**

`app/Actions/Exports/ExportResource.php` — the body of `RunResourceExport::handle()`, with `$this->resource` becoming the `$resource` parameter and the class no longer implementing `ShouldQueue`:

```php
<?php

declare(strict_types=1);

namespace App\Actions\Exports;

use App\Exports\BookExport;
use App\Exports\PublisherExport;
use App\Exports\ResourceExport;
use App\Exports\WriterExport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Build a resource's CSV on the private disk.
 *
 * Streams through the rows so memory stays flat however many there are.
 */
final class ExportResource
{
    /**
     * @var array<string, class-string<ResourceExport>>
     */
    public const EXPORTS = [
        'books' => BookExport::class,
        'publishers' => PublisherExport::class,
        'writers' => WriterExport::class,
    ];

    /**
     * @return string the stored path on the private disk
     */
    public function handle(string $resource): string
    {
        $exportClass = self::EXPORTS[$resource] ?? null;

        if ($exportClass === null) {
            throw new RuntimeException("No export is defined for [{$resource}].");
        }

        $export = new $exportClass;
        $path = $export->filename();

        $handle = fopen('php://temp/maxmemory:2097152', 'r+');

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

`app/Actions/Notifications/DismissNotification.php`:

```php
<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Models\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Delete one of a user's own notifications.
 *
 * Scoped through the relation rather than looked up by id, so one account
 * cannot dismiss another's — an unknown id and someone else's id are
 * indistinguishable from outside, which is the point.
 */
final class DismissNotification
{
    public function handle(User $user, string $notificationId): void
    {
        $record = $user->notifications()->whereKey($notificationId)->first();

        if ($record === null) {
            throw new NotFoundHttpException;
        }

        $record->delete();
    }
}
```

`app/Actions/Settings/SaveSettings.php` — the `update()` loop and `persist()` from `SettingsController`, taking files as an array so the action never touches a request object.

- [ ] **Step 5: Update the three controllers and delete the job**

`ExportController::store` calls `$this->exportResource->handle($resource)` and guards with `array_key_exists($resource, ExportResource::EXPORTS)`. Its `download()` method is untouched.

Run: `grep -rn "RunResourceExport" app/ tests/ routes/ config/`
Expected after the edit: no matches. Then `rm app/Jobs/RunResourceExport.php`.

`NotificationController::destroy` calls the action; `read()` and `readAll()` stay as they are.

`SettingsController::update` extracts the files and calls `SaveSettings`; `edit()` and the `GROUPS`/`UPLOADS` constants stay.

- [ ] **Step 6: Run everything**

Run: `php artisan test --compact && vendor/bin/phpstan analyse --memory-limit=1G`
Expected: both clean.

- [ ] **Step 7: Commit**

```bash
git add app/Actions app/Http/Controllers/Admin tests/Feature/Actions
git rm app/Jobs/RunResourceExport.php
git commit -m "refactor: extract settings, export and notification writes into actions"
```

---

### Task 14: Architecture tests

**Files:**
- Create: `tests/Feature/ArchTest.php`

- [ ] **Step 1: Write the presets first and see what they reject**

```php
<?php

declare(strict_types=1);

arch()->preset()->php();
arch()->preset()->security();
arch()->preset()->laravel();
```

Run: `php artisan test --compact --filter=Arch`

Read every failure before changing anything. The `security` preset bans `md5`, `sha1`, `uniqid` and friends; a hit there is a real finding worth reporting, not a rule to switch off.

- [ ] **Step 2: Fix what the presets legitimately catch, exclude only what Laravel requires**

Use `->ignoring(...)` for framework requirements — for example, Laravel's own `App\Actions\Fortify\*` classes cannot be `final` because Fortify resolves and extends them. Never ignore a rule to avoid fixing our own code.

- [ ] **Step 3: Add the project rules**

```php
arch('actions expose a single entry point')
    ->expect('App\Actions')
    ->toHaveMethod('handle')
    ->ignoring('App\Actions\Fortify')
    ->ignoring('App\Actions\Resources\Concerns');

arch('actions are final')
    ->expect('App\Actions')
    ->toBeFinal()
    ->ignoring('App\Actions\Fortify')
    ->ignoring('App\Actions\Resources\Concerns');

arch('controllers do not reach for the database directly')
    ->expect('App\Http\Controllers')
    ->not->toUse('Illuminate\Support\Facades\DB');

arch('models are not used inside Vue-facing support code')
    ->expect('App\Support\Theme')
    ->not->toUse('App\Models');

arch('no debug helpers ship')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'die', 'exit'])
    ->not->toBeUsed();

arch('everything declares strict types')
    ->expect('App')
    ->toUseStrictTypes();
```

The DB rule is the load-bearing one: it is what stops write logic drifting back into controllers after this refactor.

- [ ] **Step 4: Evaluate the strict preset**

Add `arch()->preset()->strict();`, run, and read the failures. Adopt it only if it does not demand `final` on classes Laravel needs to extend — `AdminResourceController` is abstract with thirteen subclasses, so it cannot be final. If the preset cannot be satisfied without weakening the design, leave it out and say so in the commit message.

- [ ] **Step 5: Run everything**

Run: `php artisan test --compact`
Expected: PASS.

- [ ] **Step 6: Commit and push group 4**

```bash
git add tests/Feature/ArchTest.php
git commit -m "test: add architecture tests for actions, controllers and strict types"
git push origin master
```

---

# Group 5 — Automation

### Task 15: Pre-commit hook

**Files:**
- Create: `.husky/pre-commit`
- Modify: `package.json`

- [ ] **Step 1: Install**

```bash
npm install --save-dev husky lint-staged
npx husky init
```

`husky init` writes `.husky/pre-commit` and adds a `prepare` script to `package.json`.

- [ ] **Step 2: Configure lint-staged**

Add to `package.json`:

```json
"lint-staged": {
    "*.php": "vendor/bin/pint",
    "resources/js/**/*.{js,vue}": [
        "eslint --fix",
        "prettier --write"
    ],
    "resources/css/**/*.css": "prettier --write"
}
```

- [ ] **Step 3: Write the hook**

`.husky/pre-commit`:

```sh
npx lint-staged
```

Fast gates only. PHPStan and the test suite stay in CI — a 600-plus-test suite on every commit is a hook people learn to bypass with `--no-verify`, and a bypassed hook checks nothing.

- [ ] **Step 4: Make it executable**

Run: `chmod +x .husky/pre-commit`

- [ ] **Step 5: Prove it runs and that it fixes**

```bash
printf '<?php\n$x   =   1;\n' > /tmp/hook-probe.php
cp /tmp/hook-probe.php app/hook-probe.php
git add app/hook-probe.php
git commit -m "test: hook probe"
```

Expected: lint-staged runs Pint and the committed file is reformatted. Then undo it:

```bash
git reset --hard HEAD~1
```

- [ ] **Step 6: Commit**

```bash
git add .husky package.json package-lock.json
git commit -m "build: run Pint, ESLint and Prettier on staged files before commit"
```

---

### Task 16: One CI workflow

**Files:**
- Create: `.github/actions/setup/action.yml`, `.github/workflows/ci.yml`
- Delete: `.github/workflows/code-style.yml`, `static-analysis.yml`, `tests.yml`

- [ ] **Step 1: Write the composite setup action**

`.github/actions/setup/action.yml`:

```yaml
name: Setup
description: PHP 8.5, Composer and npm caches, and a bootable Laravel environment

runs:
  using: composite
  steps:
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.5'
        extensions: dom, curl, libxml, mbstring, zip, pcntl, bcmath, intl, gd, pdo, sqlite3, pdo_sqlite
        coverage: none

    - name: Cache Composer dependencies
      uses: actions/cache@v5
      with:
        path: ~/.cache/composer/files
        key: composer-${{ hashFiles('composer.lock') }}
        restore-keys: composer-

    - name: Install PHP dependencies
      shell: bash
      run: composer install --no-interaction --prefer-dist --no-progress

    - name: Prepare environment
      shell: bash
      run: |
        cp .env.example .env
        php artisan key:generate
```

Every composite step needs `shell: bash`; omitting it is the usual reason a composite action fails to load.

- [ ] **Step 2: Write `ci.yml`**

```yaml
name: CI

on:
  push:
    branches: [master]
  pull_request:

concurrency:
  group: ci-${{ github.ref }}
  cancel-in-progress: true

jobs:
  pint:
    name: Pint
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v5
      - uses: ./.github/actions/setup
      - run: composer lint:check

  phpstan:
    name: Larastan (max)
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v5
      - uses: ./.github/actions/setup
      - run: vendor/bin/phpstan analyse --no-progress --memory-limit=1G

  rector:
    name: Rector (dry run)
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v5
      - uses: ./.github/actions/setup
      - run: composer rector

  type-coverage:
    name: Type coverage
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v5
      - uses: ./.github/actions/setup
      - run: vendor/bin/pest --type-coverage --min=100

  frontend:
    name: ESLint and Prettier
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v5
      - uses: actions/setup-node@v4
        with:
          node-version: '22'
          cache: npm
      - run: npm ci
      - run: npm run lint:check
      - run: npm run format:check

  pest:
    name: Pest (SQLite)
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v5
      - uses: ./.github/actions/setup
      - uses: actions/setup-node@v4
        with:
          node-version: '22'
          cache: npm
      - name: Build frontend assets
        run: |
          npm ci
          npm run build
      - name: Install Playwright browser
        run: npx playwright install --with-deps chromium
      - name: Run tests
        run: php artisan test
```

The `frontend` job deliberately skips the PHP setup — it needs neither Composer nor a database.

- [ ] **Step 3: Delete the three old workflows**

```bash
git rm .github/workflows/code-style.yml .github/workflows/static-analysis.yml .github/workflows/tests.yml
```

- [ ] **Step 4: Check the PHP version one more time**

Run: `grep -rn "php-version" .github/`
Expected: exactly one match, `'8.5'`, in the composite action.

- [ ] **Step 5: Check `.env.example` covers everything read through `config()`**

Run: `grep -rn "env(" config/ | grep -oP "env\('\K[A-Z_]+" | sort -u > /tmp/used.txt && grep -oP "^\K[A-Z_]+(?==)" .env.example | sort -u > /tmp/declared.txt && comm -23 /tmp/used.txt /tmp/declared.txt`

Anything printed is read by config but missing from `.env.example`. Laravel's own defaults are fine to leave out; `ADMIN_EMAIL` and `GITHUB_TOKEN` are not — both have already broken CI once. Add anything application-specific.

- [ ] **Step 6: Commit and push**

```bash
git add .github/
git commit -m "ci: replace three workflows with one, on PHP 8.5"
git push origin master
```

- [ ] **Step 7: Watch the run**

Run: `gh run watch`
Expected: six jobs, all green. Do not proceed to Task 17 until they are. A local pass is not proof — `core.ignorecase` and a richer local `.env` have both made this repo green on macOS and red on Ubuntu.

---

### Task 17: Changelog and release

**Files:**
- Create: `CHANGELOG.md`

- [ ] **Step 1: Write the changelog**

```markdown
# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.3.0] - 2026-09-19

### Added

- Rector with the prepared, Laravel and Pest rule sets, run as a dry run in CI.
- ESLint 9 and Prettier for the Vue frontend, with Tailwind class sorting.
- A Husky pre-commit hook running Pint, ESLint and Prettier on staged files.
- Architecture tests covering action shape, controller boundaries and strict types.
- Type coverage enforced at 100%.
- Thirteen action classes under `app/Actions`, each with its own test file.

### Changed

- PHP 8.5 is now required, in `composer.json` and in CI.
- Upgraded to Pest 5, Laravel 13.32, Larastan 3.12 and Boost 2.9.
- PHPStan runs at max over `app/`, `bootstrap/`, `config/`, `database/` and `routes/`.
- Write-path logic moved out of controllers into actions.
- Three CI workflows replaced by one with six parallel jobs.
- Inline body comments removed throughout; all docblocks kept.

### Fixed

- Six public book routes bound to empty controller methods returned blank responses.
- A failed relation sync during create no longer leaves an orphaned record behind.
- Bulk delete now reports the number of rows deleted rather than the number submitted.

## [0.2.0] - 2026-09-19

### Changed

- Replaced the FilamentPHP admin panel with Inertia, Vue 3 and PrimeVue; removed
  Filament, its four panel plugins, Laravel Pulse and Livewire.
```

Fill in the Fixed section from what Tasks 5 and 6 actually turned up — the three listed are known now; PHPStan at max and Rector will very likely find more, and a real bug found by a tool belongs in the changelog.

- [ ] **Step 2: Commit**

```bash
git add CHANGELOG.md
git commit -m "docs: add changelog through v0.3.0"
```

- [ ] **Step 3: Confirm the tree is clean and CI is green**

```bash
git status --short
gh run list --limit 1
```

Expected: no unexpected modified files, last run green. `posts/learning-rust.md` and `solo.yml` belong to the user — never stage them.

- [ ] **Step 4: Tag and push**

```bash
git tag -a v0.3.0 -m "Tooling, static analysis and action classes"
git push origin master --follow-tags
```

- [ ] **Step 5: Publish the release**

```bash
gh release create v0.3.0 --title "v0.3.0" --notes-file - <<'NOTES'
Tooling and architecture pass: PHP 8.5, Pest 5, PHPStan at max, Rector, ESLint
and Prettier, a pre-commit hook, one CI workflow, and thirteen action classes
with full test coverage.

See CHANGELOG.md for detail.
NOTES
```

- [ ] **Step 6: Verify**

Run: `gh release view v0.3.0`
Expected: the release exists and points at the `v0.3.0` tag.

---

## Verification Summary

After Task 17, all of the following must pass from a clean checkout:

```bash
composer install
npm ci
composer ci:check        # pint, phpstan, rector dry-run, type coverage, tests
npm run lint:check
npm run format:check
npm run build
```

And these must hold:

- `grep -rn "8\.4" composer.json .github/` returns nothing.
- `grep -rn "php-version" .github/` returns exactly one line, `'8.5'`.
- `vendor/bin/phpstan analyse --memory-limit=1G` reports no errors, with no baseline file and no `ignoreErrors` block in `phpstan.neon`.
- `composer rector` proposes no changes.
- `vendor/bin/pest --type-coverage --min=100` passes.
- Every class in `app/Actions` outside `Fortify` and `Concerns` is final and has a `handle()` method.
- Every action has a matching test file under `tests/Feature/Actions/`.
- `app/Jobs/RunResourceExport.php` no longer exists and nothing references it.
- `.github/workflows/` contains only `ci.yml`.
