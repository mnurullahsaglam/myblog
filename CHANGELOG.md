# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.10.0] - 2026-09-20

### Added

- Invitations. Someone with access to General invites an address and a role; the
  invitee opens a link, chooses her own password, and lands in exactly the areas
  the invite named.
- A People screen in General listing accounts and invitations, with invite,
  reissue and revoke. The link is shown in the panel as well as emailed, so a
  delivery failure is recoverable.
- Password reset, with forgot-password and reset-password pages, and a link to
  it from the sign-in form. An invited user can recover her own account without
  the owner touching the database.
- Real mail: the Resend transport for production, Mailtrap locally. Tests keep
  using the array mailer.

### Changed

- `CreateNewUser` sets a role instead of ignoring the column.
- `config/fortify.php` no longer describes this as a single-user panel.

### Security

- Invitation tokens are stored only as a SHA-256 hash; the plaintext exists in
  the link and nowhere else.
- An expired, revoked, spent, tampered or unknown token all answer 404.
- Accepting an invitation takes a row lock, so two simultaneous accepts create
  one account.
- The invite link is rate limited to six attempts a minute per address.
- Password reset answers an unknown address exactly as it answers a known one.

## [0.9.0] - 2026-09-20

### Added

- A `role` on each user, `admin` or `member`, mapped to six areas: Blog, Budget,
  Work, Library, Utilities and General.
- A `MEMBER_EMAIL` account seeded with access to Budget, Library and Utilities,
  so a second person in the household can keep the bills and the budget without
  seeing client work or the blog.
- `AdminRequest`, an abstract base that makes every admin request declare its
  area before it can be written. `area()` is abstract, so a new request that
  forgets the check does not compile.
- An `area` route middleware, and a generated test crossing all 137 admin routes
  with both roles.

### Changed

- Admin routes are grouped by area and refuse with a 404 rather than a 403, so a
  hidden area is indistinguishable from one that does not exist.
- The navigation, the command palette, the dashboard and the exports each show
  only the areas the signed-in user can reach. Dashboard panels are omitted
  rather than emptied, because an empty panel still names its area.
- `GlobalSearch::query()` now requires the asking user, so a call site cannot
  quietly search everything.
- The seeded owner carries the admin role in the column rather than relying on
  the `ADMIN_EMAIL` bypass.

### Removed

- The `access-admin` gate, replaced by `access-panel` for the door and
  `access-area` for each area behind it.

## [0.8.0] - 2026-09-20

### Added

- Utility bills: elektrik, doğalgaz, su, internet and telefon, each belonging to
  a subscription account so a household can hold several phone lines or two
  flats' electricity.
- Meter readings for the utilities that have one, with consumption computed from
  the two readings rather than stored.
- An itemised breakdown per bill, entered exactly as the bill prints it.
- Paying a bill records an Expense, so the budget totals need no second entry.
- `Field::repeater()`, a general repeating-group field type for the form
  contract, reusing FormField for each sub-field so every existing type works
  inside it.
- Migrations squashed to one file per table, and `DatabaseSeeder` now covers
  settings, categories, posts and utilities so `migrate:fresh --seed` produces
  a working panel.

### Fixed

- `AdminResourceController` built the route parameter from the resource name
  without converting hyphens, so every edit, update and delete on a hyphenated
  resource returned 404. Latent until now, because the only other hyphenated
  resource is read-only.
- Two tests pinned the navigation to an exact cluster list and an exact cluster
  count, which forbade ever adding one. Both now assert coverage and structure.

### Notes

- No Turkish tax rule is encoded anywhere. There is no VAT rate column and
  nothing is computed: rates and levies change, and a stored rule would silently
  recompute old bills to figures that never appeared on the paper.
- `utility_accounts.type` is a plain string column cast to `App\Enums\UtilityType`,
  not a database enum like `expenses.currency`. Adding a type is one line in the
  enum and needs no migration.

## [0.7.1] - 2026-09-20

### Fixed

- Three tests looped over a collection and asserted only inside the loop, so
  each would have passed while checking nothing had its collection ever been
  empty: the bulk update relation check, the navigation route check and the
  accent ramp comparison against the JavaScript. Each now proves the collection
  is populated first.

### Notes

- The intermittent single-test failure seen three times during development was
  not reproduced and remains unidentified. Roughly 120 full-suite runs,
  including 100 in randomised order, were clean. All three sightings occurred
  in a shell invocation where a file-modifying tool had just run, which points
  at the environment rather than a defect, but that is not proven.

## [0.7.0] - 2026-09-20

### Added

- ISBN lookup on the book form. Paste an ISBN, press Fetch, and the title,
  publisher, year, place, page count and cover fill in from Open Library.
  Nothing is written until you save, and fields you have already typed into
  are left alone.
- A unique `books.isbn` column, so a lookup can say the book is already in the
  library instead of letting a duplicate be created.
- `App\Support\Isbn`, which validates the check digit before any request is
  made, so a typo fails instantly rather than after a round trip.

### Notes

- Open Library's `/api/books?jscmd=data` endpoint returns 404 and is not used.
  The edition record supplies the facts and `search.json` supplies only the
  author name, because its publisher and year are aggregates across every
  edition of a work.
- Cover requests send `?default=false`. Without it a missing cover returns
  HTTP 200 carrying a blank placeholder image.
- Writer and publisher matching lowercases both sides rather than relying on
  the database collation: MySQL compares case insensitively by default and
  SQLite does not, and the suite runs on SQLite.
- ISBN-13 uses a mod-10 checksum, which cannot detect a transposition of two
  digits differing by 5. `IsbnTest` pins that limitation rather than implying
  the validation is stronger than the format allows.

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
