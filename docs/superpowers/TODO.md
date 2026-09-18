# Filament → Inertia migration · progress

45 tasks, 6 phases. Detail lives in
[the plan](plans/2026-09-18-filament-to-inertia-vue.md); design rules in
[the design system](design-system.md).

**Now:** Phase 3, Task 22. **Released:** `v0.1.0` (pre-release).

---

## Phase 0 — Decouple and build the safety net ✅

- [x] 1 · Replace Filament enum contracts with application-owned ones
- [x] 2 · Application-owned notification channel (`AdminNotifier`, `AdminAlert`)
- [x] 3 · Strip Filament macros and the Pulse gate from `AppServiceProvider`
- [x] 4 · Decouple `User`; gate admin access on `access-admin`
- [x] 5 · Factories for the eleven models that had none
- [x] 6 · Cover `TaskObserver` sort order and GitHub sync
- [x] 7 · Cover `DebtObserver`, `Setting` caching and sluggable behaviour
- [x] 8 · Move WakaTime aggregation out of `app/Filament/`
- [x] 8b · Categories became a shared taxonomy (`categoriables` pivot)

## Phase 1 — Foundation ✅

- [x] 9 · Install Inertia, Vue, PrimeVue, Fortify, Ziggy
- [x] 10 · Terminal Horizon theme, accent ramps, design system doc
- [x] 11 · Share appearance, auth, navigation and flash through Inertia
- [x] 12 · `/app` route group behind `auth` + `can:access-admin`
- [x] 13 · `AdminLayout`: prompt-glyph mark, cluster nav, env strip, toasts
- [x] 14 · Fortify login, two-factor challenge, password confirmation
- [x] 15 · Profile: password change, two-factor toggle, passkey management

## Phase 2 — The engine ✅

The risk-carrying block, now closed. A resource is a table definition, a form
definition, a FormRequest and a five-line controller.

- [x] 16 · `Column` value object — text, money, date, badge, image, boolean, count
- [x] 17 · `Filter` value object — relationship, enum, select, dateRange, boolean, custom, displayOnly
- [x] 18 · `ResourceTable` base — schema, rows, sort, search, filter, paginate
- [x] 19 · `ResourceTable.vue` + `TableCell` + `FilterBar` + `StatTile`
- [x] 20 · `ResourceForm` contract, incl. the many-to-many field categories need
- [x] 21 · Prove the engine end to end on **Posts**

## Phase 3 — Bulk port ⬅ current

Easiest first, so the engine hardens before it meets the hard tables.

- [x] 22a · `AdminResourceController` *(landed with Posts)*
- [ ] 22 · **Categories** (now a real standalone resource)
- [ ] 23 · Publishers · [ ] 24 · Writers · [ ] 25 · Books
- [ ] 26 · Clients · [ ] 27 · Projects
- [ ] 28 · Repositories *(+ `commits_count` column)*
- [ ] 29 · Incomes · [ ] 30 · Expenses *(+ `is_recurring`, `is_tax_deductible`)*
- [ ] 31 · **Debts** — hardest table: currency conversion driven by a filter,
      computed due-date status, pay-debt action with partial payments
- [ ] 32 · Invoices · [ ] 33 · WakaTime summaries (read-only)

## Phase 4 — The specials

- [ ] 34 · Kanban board, replacing flowforge *(assignee dropped — single user)*
- [ ] 35 · Cmd+K command palette
- [ ] 36 · Global search across all thirteen resources
- [ ] 37 · CSV exports for Books, Publishers, Writers
- [ ] 38 · Settings page with the live accent picker
- [ ] 39 · Notification bell

## Phase 5 — Dashboards

- [ ] 40 · Coding dashboard *(replace the rainbow `palette()` with the accent ramp)*
- [ ] 41 · Overview widgets and the main dashboard

## Phase 6 — Demolition

- [ ] 42 · Parity test — fails if any Filament feature lacks a replacement
- [ ] 43 · Remove Filament, its four plugins, Pulse and Livewire
- [ ] 44 · Move the panel from `/app` to `/admin`
- [ ] 45 · Browser smoke test across every page; final verification

---

## Waiting on you

- [ ] `herd secure myblog`, then set `APP_URL=https://myblog.test` — passkeys need
      a secure context or `navigator.credentials` is undefined
- [ ] Register a passkey at `/app/profile` and confirm Touch ID prompts

## Open questions

- Nothing blocking. Categories, dead enums, kanban assignee and the Stitch
  feature scope are all decided and recorded in the plan.

## Deliberately not built

Import QIF, audit queue, VAT/tax recoverable, account numbers, a separate vendor
field, expense clearing status, ledger checksum, sprint velocity, cycle time,
milestones, PR and deploy status on cards, per-card checklists and comment
counts, telemetry accuracy, daemon host readouts. All would need columns or
integrations that do not exist, and would mean fabricated numbers on screen.
