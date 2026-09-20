# Per-User Visibility — Design

**Date:** 2026-09-20
**Status:** Approved, not yet implemented
**Follows:** `2026-09-20-roles-and-area-access-design.md` (Spec A, v0.9.0) and
`2026-09-20-invites-and-registration-design.md` (Spec B, v0.10.0)

---

## Goal

Three things the household needs once two people share one panel:

1. She can see what the money was, without seeing whose work it came from.
2. She can choose her own accent and colour scheme without changing yours.
3. You can hide a half-built screen from her while you work on it.

## Non-Goals

- **Row ownership.** Nothing gains a `user_id`. Household data stays shared;
  this spec changes what is *shown*, never who owns what.
- **A permissions screen.** Abilities are declared in code, like areas.
- **Hiding amounts.** She sees every income's amount. That is the point of
  giving her Budget.
- **Impersonation.** The view-as toggle renders the panel under her role; it
  never authenticates as her and never writes as her.

---

## Current state

| Thing | Where it stands |
| --- | --- |
| Access | `Area` per screen, `UserRole` to areas, one gate (Spec A) |
| Appearance | Global. `Setting` rows in the `appearance` group, edited on Settings |
| Settings screen | `Area::General` — **owner-only** |
| Profile screen | Outside every area — reachable by anyone in the panel |
| Feature flags | None. Pennant is not installed |
| Client data in Budget | `incomes.client_id` and `incomes.invoice_id` only |

`Expense` and `Debt` never reference a client, so incomes are the whole surface.

---

## Part 1 — Field visibility

### The capability

An `Ability` enum, mirroring `Area`:

```php
enum Ability: string
{
    case SeeClientIdentity = 'see-client-identity';
}
```

`UserRole::abilities()` returns every case for `Admin` and none for `Member`,
and `User::abilities()` short-circuits on `isAdmin()` exactly as `areas()` does —
so `ADMIN_EMAIL` keeps its lockout bypass without a second implementation.

One gate, `has-ability`, wraps it. Refusal is a plain `false`; unlike an area,
an ability never produces a response of its own.

### Two chokepoints, not five

The first draft of this design listed five places to enforce visibility. Reading
the contracts showed that to be wrong, and the real answer is better:

- `ResourceTable::schema()`, `rows()`, `applySort()` and `applyFilters()` all
  iterate `$this->columns()` and `$this->filters()`.
- `ResourceForm::schema()`, `values()`, `bulkEditableFields()`,
  `bulkValueRules()` and `relationKeys()` all iterate `$this->fields()`.

So filtering **`columns()`, `filters()` and `fields()`** — three methods on two
base classes — closes the schema, the serialised cells, sorting, filtering, the
create form, the edit form, the show page and bulk editing together.

That matters beyond tidiness. A column dropped only in Vue still rides along in
the Inertia payload and is visible in devtools; a filter dropped only from the
UI can still be applied by hand-editing the query string, and a filter over
`client_id` would let her binary-search which incomes belong to whom. Removing
the definition removes all of it, because the definition is what every other
method reads.

### Declaring it

```php
Column::text('client.title')->hiddenWithout(Ability::SeeClientIdentity)
Field::relationship('client_id', 'client', 'title')->hiddenWithout(Ability::SeeClientIdentity)
Filter::relationship('client_id', 'client', 'title')->hiddenWithout(Ability::SeeClientIdentity)
```

Named for *why* it is hidden rather than *who* is excluded, so a second member
later needs no change to any field.

### Two rules that are not covered by the chokepoints

- **`searchable()` is a separate list.** A searchable path that reaches a hidden
  relation would leak through the search box and the command palette. No current
  table does this — `IncomeTable` searches `source`, `description` and
  `incomeCategory.name` — and the rule is stated so the next one does not.
- **The FormRequest must refuse the key.** The chokepoints stop the field being
  offered; they do not stop a hand-made POST. `IncomeRequest` drops `client_id`
  and `invoice_id` from its rules when the user lacks the ability, so an
  injected value is never validated and never written.

### What she sees

Nothing in place of the client: no column, no filter, no field. Invoice numbers
**stay visible**, as chosen.

**Accepted residual risk:** an invoice number beside an amount can often identify
the client to someone who knows the business. This is recorded rather than
mitigated, deliberately.

---

## Part 2 — Incomes that have a client

An income with `client_id` set is read-only for anyone without
`SeeClientIdentity`.

The row stays in her table with its amount, date, category and description, so
**her totals agree with yours**. Hiding the rows would make the two views of the
household's money disagree, which is a worse problem than the one being solved.

- No edit or delete action is offered on that row.
- `edit`, `update` and `destroy` return **404** for that record, matching Spec
  A's rule that a thing she may not have is indistinguishable from a thing that
  does not exist.
- Bulk update and bulk delete skip those records rather than failing the whole
  operation, and report how many were skipped.

`AdminResourceController` gains an overridable per-record check. `IncomeController`
is the only resource that overrides it today.

She can still create an income — her form has no client field, so what she
creates has no client, and remains hers to edit.

---

## Part 3 — Per-user appearance

### Storage

A `preferences` JSON column on `users`, cast to an array. Not a `user_id` on
`settings`: the `Setting` API is a global key-value store with a shared cache
key per `group.name`, and threading a user through it would either break that
cache or need a second one.

### Resolution

`Appearance` gains a per-user read that falls back to the global setting, which
in turn falls back to the built-in default. Used in both places appearance is
consumed:

- the Inertia share, for the running application, and
- the Blade view composer that writes the accent CSS variables into the shell
  **before** Inertia boots, so the first paint is already her colour.

The global `Setting` rows stay exactly as they are. They are what the public
site and every signed-out page use, and what a user without a preference gets.

### Where she sets it

**Profile**, not Settings. Settings is `Area::General` and owner-only, so
leaving appearance there would give her a preference she cannot reach. Settings
keeps the global default; Profile gains her personal override and a way to clear
it back to the default.

---

## Part 4 — Feature flags

`laravel/pennant`, database driver. One new dependency, and the only one in this
spec.

**The rule: a flag is always on for an admin, and off for a member unless
explicitly enabled.** A flag means "not ready for her yet", so it cannot
accidentally hide something from you.

Flags gate navigation items and routes **inside an area she already has**. They
never decide whether an area exists. Keeping the two apart means the Spec A
access matrix keeps answering one question — does this route belong to an area
she has — and a second, smaller test answers the other.

A flagged-off route returns 404, for the same reason everything else does.

---

## Part 5 — View-as

Owner-only, read-only, with a banner that cannot be dismissed while it is on.

### The architectural consequence

For the preview to be honest, `areas()`, `abilities()` and flag resolution must
all be read through **one resolver** that view-as can substitute. A preview that
swapped only the navigation would show a screen as hidden while its route stayed
open, which is worse than having no preview: it would produce confident, wrong
answers about whether something is exposed.

So this part is not additive. It requires that the three access questions stop
being asked directly of `$user` and start being asked of a single object that
knows the effective role. That object is the deliverable; the toggle is a thin
thing on top of it.

### Rules

- Available only to a user who is an admin **for real** — never from inside an
  active preview, so it cannot be used to climb.
- Any write while active is refused, including bulk operations and exports.
- A member can never activate it, and the control is absent from her panel.
- Ends on sign-out as well as by the banner's exit control.

---

## Testing

**Visibility, the part that matters:**

- For each of the table and form contracts, a hidden field is absent from the
  schema *and* from the serialised payload — asserted against the JSON, not the
  rendered page, because the payload is where a leak would live.
- Sorting by a hidden column via a hand-typed query string is ignored, not
  honoured.
- Filtering by a hidden filter via a hand-typed query string is ignored, and the
  row count is unchanged — the assertion that catches a filter which silently
  works.
- Bulk editing a hidden field is refused, and the records are untouched.
- A POST carrying `client_id` from a member neither validates nor writes.
- The admin sees all of it, in every one of those places.
- Every ability has at least one case in the matrix, generated from
  `Ability::cases()` so a new ability cannot be added without covering it.

**Read-only incomes:**

- She may edit an income with no client, and may not edit one with a client.
- Her list shows both, and the totals match the admin's.
- Bulk operations skip the protected rows and say how many.

**Appearance:**

- Two users hold different accents at once, and neither reads the other's.
- A user with no preference gets the global setting; changing the global setting
  does not change a user who has one.
- The Blade shell carries her accent on first paint, before Inertia boots.

**Flags:**

- A flag off hides the navigation item and 404s the route, for a member only.
- The same flag never hides anything from an admin, asserted for every flag from
  `Feature::all()` rather than a hand-written list.

**View-as:**

- While active, the owner sees exactly what the member's own session sees —
  asserted by comparing the two payloads, which is the only assertion that
  proves the preview is honest.
- Every write verb is refused while active.
- A member cannot activate it, and cannot activate it from within a preview.

---

## Risks

| Risk | Mitigation |
| --- | --- |
| A hidden column still ships in the Inertia payload | Enforcement removes the definition, which is what serialisation reads |
| A hidden filter is applied by hand-editing the URL | `applyFilters()` iterates the filtered list, so an unknown key has nothing to apply |
| A new table or form forgets to hide a client field | The ability matrix test walks every table and form definition |
| Her totals disagree with the owner's | Protected rows stay visible; only the actions are removed |
| View-as gives a false sense of safety | One resolver answers all three access questions, and the test compares payloads rather than screens |
| A flag hides something from the owner | Flags resolve true for admins unconditionally |
| Invoice numbers identify the client anyway | Accepted, recorded, not mitigated |
