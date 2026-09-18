# Replacing Filament with Inertia + Vue + PrimeVue

**Date:** 2026-09-18
**Status:** Approved design, pending implementation plan

## Goal

Remove FilamentPHP and rebuild the entire admin panel on Inertia + Vue 3 + PrimeVue,
preserving every current feature. Motivation is independence: full control over the
frontend, no panel framework dictating structure, no upgrade breakage of the kind
already hit during the Filament v5 migration.

This is a personal project still in development. Downtime during the rebuild is
acceptable, so the work happens on a single long-lived branch rather than
incrementally alongside the running panel.

## Scope

### In scope

The admin panel in its entirety: 13 Resources across 5 Clusters, ~40 List/Create/Edit/View
pages, 14 widgets, the Kanban board, command palette, global search, exports, database
notifications, the Settings page, login, and profile.

### Out of scope

The public-facing site. `routes/web.php` serves `/` and `/books` through
`BookController` and plain Blade views. These are untouched.

### Explicitly not preserved

Filament panel plugins cannot survive the removal of Filament — they hook into its panel
lifecycle. The *features* are rebuilt; the packages are not kept:

| Package | Feature | Replacement |
| --- | --- | --- |
| `relaticle/flowforge` | Kanban board | Custom Vue board, `vuedraggable` |
| `pxlrbt/filament-spotlight` | Cmd+K palette | Custom Vue overlay |
| `pxlrbt/filament-environment-indicator` | Env badge | Topbar strip, ~1h |
| `devonab/filament-easy-footer` | Footer | Layout footer, ~1h |

`laravel/pulse` is dropped entirely rather than ported. It is Livewire-based and
independent of Filament, but removing it lets Livewire leave the dependency tree
completely. The monitoring is not worth keeping Livewire for on a personal project.

## Dependency changes

**Removed**

```
filament/filament
relaticle/flowforge
pxlrbt/filament-spotlight
pxlrbt/filament-environment-indicator
devonab/filament-easy-footer
laravel/pulse
livewire/livewire            (transitive; nothing remaining requires it)
```

**Added**

```
composer: inertiajs/inertia-laravel, laravel/fortify, tightenco/ziggy
npm:      vue@3, @inertiajs/vue3, @vitejs/plugin-vue,
          primevue@4, @primeuix/themes, primeicons,
          tailwindcss-primeui, vuedraggable, chart.js
```

**Unchanged**

All 19 Eloquent models, Observers, Services, Enums, Traits, migrations and factories.
`spatie/laravel-sluggable`, `panphp/pan`, Tailwind v4. Business logic does not move —
only the presentation layer is replaced.

**Deleted directories**

```
app/Filament/                  (82 files)
app/Providers/Filament/
resources/css/filament/
resources/views/filament/
resources/views/vendor/pulse/
```

## Architecture

### Directory layout

```
app/Http/Controllers/Admin/{Blog,Budget,Work,Library,General}/*Controller.php
app/Http/Requests/Admin/          per-resource validation
app/Tables/                       ResourceTable base + Column/Filter classes
app/Tables/Definitions/           13 table definitions
app/Forms/                        ResourceForm base + Field classes
app/Forms/Definitions/            13 form definitions
app/Exports/                      3 exporters (Book, Publisher, Writer)

resources/js/
  app.js
  theme/preset.js                 PrimeVue token preset
  theme/ramps.js                  accent color ramps (shared with settings picker)
  Layouts/AdminLayout.vue         top nav, search, bell, avatar, footer, env strip
  Components/Table/ResourceTable.vue, Filters/*, Cells/*
  Components/Form/ResourceForm.vue, Fields/*
  Components/Spotlight.vue
  Components/NotificationsPanel.vue
  Pages/<Cluster>/<Resource>/{Index,Create,Edit,View}.vue
  Pages/Dashboard/Coding.vue
  Pages/Work/TasksBoard.vue
  Pages/Settings.vue
  Pages/Profile.vue
  Pages/Auth/Login.vue
```

### Routing and navigation

All admin routes live under an `/admin` prefix behind `auth` middleware, grouped per
cluster to mirror the current navigation. Navigation is **horizontal top nav with cluster
dropdowns**, matching the existing panel's `->topNavigation()` configuration, with content
capped at 1280px.

During the rebuild the new panel is mounted at `/app` so the Filament panel keeps working
at `/admin`. The final phase moves it to `/admin`.

### Authentication

Laravel Fortify provides backend auth: session login, password reset, 2FA, recovery codes.
Vue views are written by hand (`Pages/Auth/Login.vue`, `Pages/Profile.vue`). The current
custom Filament `Login` page's logic ports across.

## The ResourceTable contract

This is the load-bearing abstraction. Filament's productivity comes from declaring a
resource once and getting a full CRUD UI. Without an equivalent, 13 resources × 4 pages
becomes unmanageable duplication. The contract reproduces that economy without the
framework.

### PHP side

A `ResourceTable` base class declares model, eager loads, default sort, columns, filters,
and searchable fields. Example, based on the current `ExpenseResource` — the most complex
table in the app:

```php
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
            Column::badge('expenseCategory.name')->label('Category')->sortable()
                ->color(fn (Expense $r) => $r->expenseCategory?->color ? 'primary' : 'gray'),
            Column::text('description')->limit(50)->tooltip(),
            Column::image('receipt_path')->label('Receipt')->circular()->size(40),
            Column::badge('debt.creditor_name')->label('Debt To')->color('warning')->default('N/A'),
            Column::date('date')->sortable(),
            Column::datetime('created_at')->sortable()->toggleable(hiddenByDefault: true),
        ];
    }

    protected function filters(): array
    {
        return [
            Filter::relationship('expense_category_id', 'expenseCategory', 'name')->label('Category')->multiple(),
            Filter::enum('currency', Currencies::class)->multiple(),
            Filter::relationship('debt_id', 'debt', 'creditor_name')->label('Debt Payment')->multiple(),
            Filter::dateRange('date'),
            Filter::boolean('receipt_path')->label('Receipt')
                ->trueLabel('Has receipt')->falseLabel('No receipt'),
        ];
    }

    protected function searchable(): array
    {
        return ['description', 'expenseCategory.name'];
    }
}
```

### Column types

`text`, `money`, `date`, `datetime`, `badge`, `image`, `boolean`, `enum`.
Modifiers: `label`, `sortable`, `toggleable(hiddenByDefault:)`, `limit`, `tooltip`,
`default`, `color`, `align`, `circular`, `size`.

### Filter types

`relationship` (belongsTo select, multiple, preloaded), `enum`, `dateRange`, `boolean`,
`select`.

### Inertia props

Two props, emitted by the controller:

```
schema:  columns[] — key, label, type, sortable, toggleable, align, meta
         filters[] — key, type, label, options[]
rows:    LengthAwarePaginator; each row is a map of column key to rendered cell
```

**Cell presentation resolves on the server.** Color closures such as
`fn (Expense $r) => ...` are PHP and cannot cross to Vue. Each cell ships as a small
object carrying both display and raw values:

```json
{ "amount":   { "display": "₺1.250,00", "raw": 1250 },
  "currency": { "display": "TRY", "variant": "danger" } }
```

The Vue layer therefore needs no model knowledge whatsoever. Adding a resource never
requires touching Vue.

### Vue side

One component renders every resource:

```vue
<ResourceTable
  :schema="schema" :rows="rows" :filters="filters"
  resource="expenses"
  :row-actions="['view', 'edit', 'delete']"
  :bulk-actions="['delete']" />
```

Built on PrimeVue `DataTable` in `lazy` mode. Sort, page, filter and search changes issue
`router.reload({ only: ['rows'] })` with query parameters. **Table state lives in the
URL**, so the back button, bookmarking and link sharing all work — an improvement over the
current Livewire-held state. Column visibility toggles persist to `localStorage`.

### ResourceForm

Mirrors the table contract. Field types: `text`, `textarea`, `markdown`, `select`,
`relationship`, `date`, `file`, `tags`, `hidden`. Validation is **not** derived from the
schema; each resource has a real `FormRequest` under `app/Http/Requests/Admin/`. Reactive
behavior such as the Post title → slug sync is implemented as small Vue-side field
behaviors rather than server round-trips.

### Cost shape

Roughly 600 lines of framework written once, then 40–80 lines per resource definition.
This ratio is what makes the project feasible at all.

## Feature rebuilds

### Kanban board

```
GET   /admin/tasks/board        → Pages/Work/TasksBoard.vue, tasks grouped by status
PATCH /admin/tasks/{task}/move  → { status, sort_order }
```

`vuedraggable` across three columns (To Do, In Progress, Completed). Reorder applies
optimistically in local state, then PATCHes; failure rolls back. The server reindexes
`sort_order` within the target column inside a transaction.

Card actions port one-to-one from the current board: view detail drawer, edit dialog
(reusing `ResourceForm`), and GitHub sync behind a confirmation dialog calling the existing
`GitHubService` unchanged.

### Spotlight

Cmd+K overlay built on PrimeVue `Dialog`. The navigation target registry is generated from
the same array that drives the top navigation, so nav and palette cannot drift apart.
Fuzzy matching runs client-side; navigating costs no network request.

### Global search

Shares the Cmd+K surface as a second results section beneath navigation targets. Each
`ResourceTable` definition already declares `searchable()`, so that metadata is reused
directly. `GET /admin/search?q=` fans out across the 13 definitions with `LIMIT 5` each and
returns `{ label, resource, url }`. Input is debounced 250ms.

### Exports

Three exporters exist today (Book, Publisher, Writer). `app/Filament/Imports/` is empty —
there are no importers to replace.

`app/Exports/{Book,Publisher,Writer}Export.php` each declare columns and a query.
`POST /admin/{resource}/export` dispatches a queued job that writes CSV via `fputcsv` over
a `lazy()` chunked query, stores it, and notifies on completion. Download uses a signed
URL. No new dependency.

### Notifications

The panel currently uses `->databaseNotifications()`. Laravel's `notifications` table stays
as-is. `NotificationsPanel.vue` (PrimeVue `OverlayPanel`) reads unread notifications from a
shared Inertia prop and marks them read via PATCH.

Transient feedback — save confirmations, errors — is separate: a PrimeVue `Toast` fed by
Inertia's shared `flash` data.

### Environment indicator and footer

A colored strip across the top of the layout when `config('app.env') !== 'production'`, and
a footer showing app version and a GitHub link. Roughly one hour combined.

### Charts

Filament's `ChartWidget` is Chart.js underneath, and PrimeVue's `Chart` component is also
Chart.js. The `InteractsWithWakaTimeData` concern — which holds the actual aggregation
logic — ports unchanged. Only the widget wrapper is rewritten.

## Design system

### Direction

Quiet and precise; a developer tool rather than enterprise SaaS. Dark mode primary, light
mode secondary. Neutral grays throughout with a single accent used sparingly for primary
actions and active states. No gradients, no card drop shadows — 1px borders instead.
Generous whitespace around dense data. Monospace for numbers, IDs and dates.

### Design tooling

Mockups are produced in Google Stitch **before any Vue exists**, for four screens only —
everything else is a variation of these:

1. Data table index (top nav, filter bar, dense table)
2. Record edit form
3. Metrics dashboard (chart grid)
4. Kanban board

Stitch output is treated as **visual direction, never code**. Its tables will render looser
and prettier than PrimeVue's `DataTable` natively is; that screen is a palette-and-hierarchy
reference, not a layout to match pixel-for-pixel. The form and board screens translate far
more faithfully.

The chosen direction is then converted into `resources/js/theme/preset.js` (a PrimeVue
`definePreset` over Aura) plus `docs/superpowers/design-system.md` pinning down type scale, spacing
rhythm, table row density, badge color semantics per enum, empty states and loading states.
The preset is the only design artifact that enters the repo.

#### Stitch prompt

```
A personal admin dashboard for a developer's self-hosted life-management app.
Web, desktop-first, 1440px. Dark mode primary, light mode secondary.

Content areas: blog posts, personal finance (income/expenses/debts),
freelance work (clients/projects/invoices/repositories), a book library,
and coding-time analytics.

Navigation: horizontal top bar, not a sidebar. Logo left, five section
dropdowns (Blog, Budget, Work, Library, General), right side has global
search, notifications bell, avatar menu. Max content width 1280px, centered.

Aesthetic: quiet and precise. Developer tool, not enterprise SaaS.
Warm khaki/olive as the single accent color (#C9BE6E), used sparingly —
only for primary actions and active states. Everything else neutral grays.
Primary buttons use dark text on the khaki fill, never white.
No gradients, no drop shadows on cards, use 1px borders instead.
Generous whitespace around dense data. Monospace for all numbers, IDs and dates.

Generate these four screens:
1. "Expenses" list — dense data table, 8 columns including a money column,
   colored category badges, small circular receipt thumbnails, a row of
   filter chips above the table showing active filters, pagination below.
2. "Edit Post" form — single column, max 720px wide, title field,
   read-only slug field, large markdown editor with a preview toggle,
   metadata shown as quiet muted text, save bar pinned to the bottom.
3. "Coding Dashboard" — a date-range selector at top, four small stat tiles
   in a row, then a 2-column grid of chart cards (a line chart for the
   daily trend, two donut charts for languages and editors, a bar chart
   for weekday distribution).
4. "Task Board" — three kanban columns (To Do, In Progress, Completed),
   each with a count badge, draggable cards showing a title, a repository
   name, and GitHub issue labels as small pills.
```

### Theming

PrimeVue runs in **styled mode with a custom token preset**, not unstyled mode. A custom
preset yields a distinctly personal look without hand-styling thirty components, which is
what makes 13 resources shippable in reasonable time.

**Default accent: khaki.** Ramp anchored so khaki reads at 400 on dark surfaces and 500 on
light:

```
50  #FBFAF0    400 #C9BE6E    800 #5A522A
100 #F5F1D8    500 #B3A651    900 #4A4426
200 #EAE3B0    600 #96893F    950 #2A2613
300 #DCD183    700 #756A32
```

Khaki is a mid-luminance hue, which forces one constraint: **khaki fills take dark text,
not white.** `#B3A651` with white text is roughly 2.3:1 and fails contrast; with `#2A2613`
it is roughly 8:1. The preset therefore sets `primary.contrastColor` per color scheme
rather than defaulting to white. Amber, the current accent, has the same property.

### User-selectable appearance

Stored in the existing `settings` table — no new table, and `Setting::get()` already
caches:

```
group='appearance'  name='accent'        value='khaki'
group='appearance'  name='color_scheme'  value='dark' | 'light' | 'system'
```

Default color scheme is `system`, following the OS.

- `HandleInertiaRequests` shares the `appearance` group as a global Inertia prop.
- The Settings page gains an **Appearance** tab: a swatch grid rather than a select.
  Clicking a swatch previews instantly via
  `updatePreset({ semantic: { primary: RAMPS[name] } })`; the choice persists on save.
- The topbar carries a quick dark/light toggle that writes through to the same setting.
- **No flash of wrong theme on first paint:** the root Blade layout inlines
  `<style>:root{--p-primary-50:…;--p-primary-950:…}</style>` server-side from the stored
  setting. Correct color renders before Vue boots; JavaScript only handles live switching.
- Ramps live in a single `resources/js/theme/ramps.js`, shared by both the preset and the
  swatch picker, so adding an accent is one entry.

**Shipping accents:** khaki (default), amber, orange, rose, emerald, sky, indigo, violet,
zinc. Each verified for contrast in both schemes.

## Build sequence

### Phase 0 — Safety net

The test suite is currently two stub files with no real coverage. Before deleting anything,
write tests for the code that *survives* the rewrite, so they retain value afterwards:
`TaskObserver`, `GitHubService`, the WakaTime services and `InteractsWithWakaTimeData`
aggregation, `Setting` caching behavior, sluggable behavior, and model casts, relations and
accessors.

Deliberately **not** Filament tests — those would be deleted along with Filament.

### Phase 1 — Foundation

Install Inertia, Vue, PrimeVue and Fortify. Build `AdminLayout.vue` (top nav, cluster
dropdowns, search, notifications bell, avatar menu). Wire the theme preset, ramps and
server-inlined CSS variables. Ship login and profile. Filament continues running at
`/admin`; the new panel mounts at `/app`. Nothing is deleted.

### Phase 2 — The engine

Build `ResourceTable` and `ResourceForm` on both the PHP and Vue sides, proven end to end
against **Posts** — the smallest resource, three columns, no filters.

This phase concentrates the project's risk. If the contract is wrong, it surfaces against
80 lines of definition rather than 1000.

### Phase 3 — Bulk port

Ordered easiest to hardest so the engine hardens gradually:

```
Categories, Publishers, Writers, Books     (General + Library)
Clients, Projects, Repositories            (Work)
Income, Debts                              (Budget)
Expenses                                   (stress test: money, dynamic badges, date range)
Invoices, WakaTimeSummaries                (custom view pages)
```

Each resource ships with Pest feature tests covering index (sort, filter, search,
paginate), create, update, delete and validation, asserting on Inertia props via
`assertInertia()`.

### Phase 4 — Specials

Kanban board, spotlight, global search, exports, notifications bell, environment indicator,
footer, and the Settings page including the Appearance tab.

### Phase 5 — Dashboards

Coding Dashboard with its nine WakaTime charts, Books overview, Income overview, exchange
rate widget.

### Phase 6 — Demolition

`composer remove` the six packages. Delete `app/Filament/`, `app/Providers/Filament/`, the
Filament views and CSS. Drop the Pulse tables. Move `/app` to `/admin`.

Verification: a Pest 4 browser smoke test across all 13 index pages plus the board and
dashboard, asserting no JavaScript console errors. Larastan must remain clean at level 10 —
that was hard-won, from 386 errors to zero.

### Estimate

Five to seven weeks of focused evening work. Phases 0–2 account for roughly 30% of the time
and roughly 70% of the risk.

## Risks

**The engine contract turns out wrong.** Discovering this at resource nine would force
rework across eight already-ported resources. Mitigated by proving the contract on Posts in
Phase 2, then deliberately scheduling Expenses — by far the most demanding table — before
the tail end of the port.

**No test coverage during the port.** With two stub tests today, regressions would surface
only through manual use. Mitigated by Phase 0 and by writing per-resource tests during the
port rather than after it.

**Scope creep into the public site.** The blog frontend at `/` and `/books` is out of scope
and stays as-is.

## Decisions log

| Decision | Choice | Reason |
| --- | --- | --- |
| Migration strategy | Big bang on a branch | Personal project still in development; downtime acceptable |
| Component library | PrimeVue v4 | DataTable replaces most Filament table work |
| Styling mode | Styled + custom token preset | Distinct look without hand-styling 30 components |
| Data layer | Server-driven shared contract | Makes 13 resources affordable |
| Auth | Laravel Fortify | 2FA and password reset without hand-rolling |
| Pulse | Dropped | Removes Livewire from the dependency tree entirely |
| Design tooling | Stitch for direction, spec for implementation | Mockups inform the preset; they are not the deliverable |
| Accent | Khaki default, user-selectable | Stored in existing `settings` table |
| Color scheme | DB setting, `system` default | Consistent across devices, no flash |
