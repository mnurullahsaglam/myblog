# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.6.1] - 2026-09-20

### Fixed

- `PayDebtTest` compared the expense date against `now()` read a second time,
  so a run straddling midnight compared two different days. The clock is frozen
  for that assertion.

## [0.6.0] - 2026-09-20

### Fixed

- `Model::shouldBeStrict()` ran unconditionally, so a lazy load in production
  would have been a 500 for the visitor rather than a nudge for the developer.
  Now gated on the environment, matching the two hardening calls either side.
- `tasks` and `projects` carried id columns with neither an index nor a
  constraint. `tasks.status` is filtered in nine places and `sort_order`
  ordered in three, so those are indexed together; the three references now
  null out on delete instead of leaving rows pointing at nothing.

### Removed

- `panphp/pan`. Nothing ever called `Pan::`, the table held no rows, and the
  package registered a public `POST pan/events` endpoint for events that were
  never recorded. Its table is dropped; its create migration stays so existing
  histories still replay.
- `App\Services\ConvertZipHtmlToPdfService`, an empty class body with no
  references.
- The `invoices.invoice_pdf` column, never written or read. It belonged to an
  invoice PDF feature that was not built.

### Changed

- Inertia pages load lazily, so each is its own chunk. The initial bundle drops
  from 1,641 kB to 495 kB, and from 416 kB to 142 kB gzipped.
- `AppServiceProviderTest` asserts behaviour rather than grepping the
  provider's source for method names.

## [0.5.0] - 2026-09-20

### Added

- Bulk edit: set one field across a selection from the table toolbar.
  `App\Actions\Resources\BulkEditRecords` writes through each model inside a
  transaction, for the same reason bulk delete does.
- `ResourceForm::bulkEditableFields()` restricts bulk editing to choices,
  switches, dates and numbers - selects, relations, toggles, dates, numbers and
  money. Models are unguarded, so this list is the authorisation boundary: a
  title, a slug or an upload cannot be written in bulk, and neither can a field
  the form marks disabled because it is computed on save.
- `ResourceForm::bulkValueRules()` validates the submitted value against the
  field's own option list rather than the resource's FormRequest, which would
  demand every other field too. Numeric fields carry through the `min` and
  `max` the form declares, so a tax rate still cannot exceed 100 in bulk.
- A `*.bulk-update` route for each of the twelve resources that already had
  bulk delete.
- A guard that fails if any bulk write in `app/` goes through the query builder,
  which fires no model events. It demonstrates the trap on both `delete` and
  `update`, and reports the offending file and line.

## [0.4.0] - 2026-09-19

### Added

- `App\Contracts\{ConvertsCurrency,SyncsGitHubIssues,NotifiesAdmin}`, narrow
  interfaces over the three collaborators tests substitute. Consumers bind to
  these, which let the concrete services become final.
- An architecture rule requiring every class to be final, naming the five base
  classes that are abstract instead. PHP forbids `abstract final`, so those are
  asserted abstract, which carries the same guarantee.

### Changed

- Every class in `app/` is now final except those five base classes.
- Rules Rector could not apply while classes were open have now run:
  private methods on final classes, readonly classes, and empty observer
  methods removed.

### Fixed

- Deleting a post or a book left its `categoriables` pivot rows behind. The
  pivot cascades on `category_id`, but the polymorphic side cannot carry a
  foreign key, so the taxonomy is now detached on `deleting`.
- Bulk delete had the same gap for a second reason: a mass delete query fires
  no model events. It now deletes row by row inside a transaction.

## [0.3.0] - 2026-09-19

Tooling, static analysis and an action-class layer.

### Added

- Rector with the deadCode, codeQuality, codingStyle, typeDeclarations,
  privatization and earlyReturn sets, plus the Laravel and Pest sets. CI runs it
  as a dry run; a dirty result fails the build and a human applies the fix.
- ESLint 9 flat config and Prettier for the Vue frontend, with Tailwind class
  sorting.
- A Husky pre-commit hook running Pint, ESLint and Prettier on staged files.
  The slow gates stay in CI, because a hook people bypass checks nothing.
- Architecture tests: Pest's php, security and laravel presets, plus project
  rules covering action shape, controller boundaries, debug helpers and strict
  types.
- Type coverage enforced at 100%.
- Thirteen action classes under `app/Actions`, each with its own test file.
- Script harness on `composer.json` and `package.json`: `lint`, `lint:check`,
  `rector`, `rector:fix`, `types:check`, `type-coverage`, `mutate`, `test`,
  `ci:check`, `format`, `format:check`.
- Seven application environment variables now declared in `.env.example`.

### Changed

- PHP 8.5 is required, in `composer.json` and in CI.
- Upgraded to Pest 5 and PHPUnit 13, Laravel 13.32, Larastan 3.12, Boost 2.9,
  Rector 2.6, Pint 1.32, spatie/laravel-sluggable 4 and Symfony 8 components.
- PHPStan runs at `level: max` with bleedingEdge over `app/`, `bootstrap/`,
  `config/`, `database/` and `routes/`, up from level 10 over `app/` alone.
  There is no baseline and no `ignoreErrors`.
- Write-path logic moved out of controllers into actions.
  `AdminResourceController` and `TaskBoardController` both shrank accordingly.
- `RunResourceExport` moved from `app/Jobs` to `App\Actions\Exports\ExportResource`;
  it was always run inline and never dispatched.
- Three CI workflows replaced by one with six parallel jobs sharing a composite
  setup action.
- Inline body comments removed throughout; every docblock kept, because the
  array shapes in `app/Tables` and `app/Forms` are what PHPStan reads.
- Test suite grew from 634 to 745 tests.

### Fixed

- Six public book routes were bound to empty controller methods and returned
  blank 200 responses.
- `DatabaseSeeder` dereferenced `Project::first()` without checking for null.
- A failed relation sync during create left an orphaned record behind; create
  and update are now transactional.
- Bulk delete reported the number of ids submitted rather than the number of
  rows actually deleted.
- A failed receipt upload could store boolean `false` in `debts.receipt_path`.
- Factories seeded currencies with `array_rand`, which is not a secure source
  of randomness.
- Six factories and six models were missing their Eloquent generics.
- `AccentPicker.vue` had an unused binding and `FormField.vue` an untyped
  `modelValue` prop.

## [0.2.0] - 2026-09-19

### Changed

- Replaced the FilamentPHP admin panel with Inertia, Vue 3 and PrimeVue, in
  styled mode with a custom khaki token preset. Removed Filament, its four
  panel plugins, Laravel Pulse and Livewire. All thirteen resources ported.

## [0.1.0] - 2026-09-18

Initial tagged release of the Laravel blog and personal admin panel.
