# Utility Bills — Design

**Date:** 2026-09-20
**Status:** Approved for planning
**Baseline:** `v0.7.1` plus the migration squash

## Goal

Record household utility bills — elektrik, doğalgaz, su, internet, telefon —
with their meter readings where they have one, their due date, and the itemised
breakdown the bill prints. Paying one records an expense, so the budget totals
stay honest without entering the amount twice.

## Non-Goals

- **Modelling Turkish tax rules.** No VAT rate column, no levy columns, no
  computation. See "Why no tax rules" below.
- **Provider integrations.** Bills are entered by hand. No scraping, no e-fatura.
- **Payment.** The panel records that a bill was paid; it does not pay it.
- **Consumption forecasting or tariff comparison.** Later, if ever.

## Naming

`invoices` already exists and means **client invoicing** — outgoing work bills
with `client_id` and `invoice_number`. These are unrelated, so the new tables are
`utility_accounts`, `utility_bills` and `utility_bill_lines`.

---

## Why no tax rules

A Turkish electricity bill itemises enerji bedeli, dağıtım bedeli, enerji fonu,
elektrik tüketim vergisi and KDV. A phone bill itemises hizmet bedeli, ÖİV and
KDV. Water bills add atıksu bedeli. The set of levies differs per utility, and
both the rates and the levies themselves change — KDV's standard rate moved in
2023, and levies on electricity have been added and removed over the years.

Encoding any of that would mean a schema that is wrong the moment the rules move,
and historical bills that silently recompute to figures that never appeared on
the paper.

So the application stores **what the bill prints**: a label and an amount per
line, plus the stated total. That is correct under any rate, needs no migration
when a levy appears, and keeps a 2024 bill showing 2024's numbers forever.

The line sum is compared against the stated total and **warns** when they differ
by more than 0.01, rather than blocking. Real bills round to the kuruş, and a
bill that disagrees with itself is still the bill you have to pay.

---

## The type is a string, not a database enum

`expenses.currency` is declared `$table->enum('currency', array_column(Currencies::cases(), 'value'))`.
That means adding a currency needs a migration. The utility type deliberately
does **not** follow that pattern.

`utility_accounts.type` is a plain `string`, cast on the model to
`App\Enums\UtilityType`. Adding a type is one line in the enum and nothing else —
no migration, no schema change, no deploy-ordering problem.

```php
enum UtilityType: string implements HasColor, HasLabel
{
    case Electricity = 'electricity';
    case NaturalGas = 'natural_gas';
    case Water = 'water';
    case Internet = 'internet';
    case Phone = 'phone';

    public function getLabel(): string
    {
        return match ($this) {
            self::Electricity => 'Elektrik',
            self::NaturalGas => 'Doğalgaz',
            self::Water => 'Su',
            self::Internet => 'İnternet',
            self::Phone => 'Telefon',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Electricity => 'warning',
            self::NaturalGas => 'danger',
            self::Water => 'info',
            self::Internet => 'primary',
            self::Phone => 'secondary',
        };
    }

    /** Whether this utility is metered, which decides if the reading fields show. */
    public function hasMeter(): bool
    {
        return in_array($this, [self::Electricity, self::NaturalGas, self::Water], true);
    }

    /** kWh, m³ or null. Labels the reading fields and the consumption figure. */
    public function unit(): ?string
    {
        return match ($this) {
            self::Electricity => 'kWh',
            self::NaturalGas, self::Water => 'm³',
            self::Internet, self::Phone => null,
        };
    }
}
```

`App\Support\Contracts\HasColor` and `HasLabel` already exist, and
`app/Tables/Column.php:402` already renders a badge colour from `HasColor`. The
enum implements them and the table picks the colour up with no new machinery.

Values stay English so the codebase stays one language; labels are Turkish
because that is what the bills say.

---

## Schema

### `utility_accounts`

The subscription: one row per meter, line or connection. This is what makes
"telefon can be many" work, and equally two flats' electricity.

| Column | Notes |
| --- | --- |
| `type` | `string`, cast to `UtilityType`. Indexed. |
| `provider` | Free text: "Enerjisa", "Turkcell", "İGDAŞ". |
| `subscriber_no` | Nullable. Abone or tesisat numarası. |
| `label` | What you call it: "Ev elektrik", "İş telefonu". |
| `is_active` | Cancelled lines stop appearing in the new-bill picker. |

### `utility_bills`

| Column | Notes |
| --- | --- |
| `utility_account_id` | Cascades on delete: a bill without its account is meaningless. |
| `bill_number` | Nullable. Fatura no. |
| `period_start`, `period_end` | Nullable dates. Fatura dönemi. |
| `issued_at` | Nullable date. |
| `due_date` | Required. Son ödeme tarihi — the field the panel exists to surface. |
| `meter_start`, `meter_end` | `decimal(12,3)` nullable. Doğalgaz m³ is fractional. |
| `total_amount` | `decimal(15,2)`. The figure the bill states. |
| `currency` | Follows the existing enum pattern, default TRY. |
| `paid_at` | Nullable timestamp. Null means outstanding. |
| `expense_id` | Nullable, `nullOnDelete`. Links to the Expense created on payment. |
| `document_path` | Nullable. A scan or PDF on the public disk. |

Indexed on `due_date` (the overdue and due-soon filters) and on
`(utility_account_id, period_start)`.

**Consumption is not stored.** It is `meter_end - meter_start`, exposed as an
accessor returning null when either reading is absent. A stored copy can drift
from the readings it claims to summarise.

### `utility_bill_lines`

`utility_bill_id` cascading, `label`, `amount` `decimal(15,2)`, `sort_order`.
Negative amounts are allowed: bills carry discounts and previous-balance credits.

---

## Field::repeater()

The form contract has no repeater. `ResourceForm.vue` has no slots either, so the
line editor cannot be layered on from the page — the same constraint that made
`Field::isbn()` its own type.

```php
Field::repeater('lines', [
    Field::text('label')->required(),
    Field::money('amount')->required(),
])->label('Breakdown')->columnSpan(2)
```

`Field` gains a nested `fields` array, emitted in `schema()`. `FormField.vue`
renders a row per entry with add and remove controls, reusing `FormField` itself
for each sub-field so every existing type works inside a repeater for free.

It is built as a general field type rather than a bills-only widget, because the
next resource that needs repeated rows should not repeat this work.

**Validation.** `UtilityBillRequest` validates `lines` as an array and
`lines.*.label` and `lines.*.amount` individually, so an error lands on the row
that caused it.

---

## Actions

| Action | Job |
| --- | --- |
| `App\Actions\Utilities\SaveBillLines` | Replaces a bill's lines in one transaction. Delete-and-recreate, not diffing: the lines have no identity worth preserving and diffing would be more code for no gain. |
| `App\Actions\Utilities\PayBill` | Sets `paid_at`, creates the Expense, links `expense_id`. One transaction. Refuses a bill that is already paid, so a double click cannot double-count. |

`PayBill` mirrors `PayDebt`: the Expense is created first, then the bill is
marked paid. `DebtObserver` showed why order matters when an observer also
creates expenses — here nothing observes utility bills, but the same ordering
costs nothing and keeps the two actions readable side by side.

The Expense carries the account's label and the bill period as its description,
the bill's total as its amount, and the bill's currency.

---

## Resources

Two, both on the existing `AdminResourceController`:

- **Utility accounts** — table shows type as a coloured badge, provider, label,
  active. Form is five fields.
- **Utility bills** — table shows account, period, due date, total, and a paid or
  overdue badge. Filters: by account, by type, unpaid, overdue, due within seven
  days. Tiles: outstanding total, overdue count, spend this month.

A `Pay` row action opens a confirm and calls `PayBill`.

**The meter fields are always shown.** The form contract has no conditional
visibility — `Field::hidden()` is a hidden input type, not a rule — and adding
one would be a second new capability on top of the repeater. It would not help
on the create form anyway, where the account is not chosen until the user picks
it client side.

Instead the readings carry help text naming the unit, and `UtilityBillRequest`
enforces the rule server side, where the account is known: readings are
permitted only when the account's type `hasMeter()`, and are rejected with a
clear message otherwise. The constraint is real without new UI machinery.

---

## Testing

- **`UtilityType`**: label and colour for every case, `hasMeter()` and `unit()`
  per case, and a test asserting every case is covered by both matches so adding
  a case without a label fails rather than returning a blank badge.
- **`SaveBillLines`**: replaces rather than appends, clears when given none,
  keeps order, rolls back the whole set when one row is rejected.
- **`PayBill`**: creates exactly one Expense with the right amount and currency,
  links it, sets `paid_at`, refuses an already-paid bill, and leaves nothing
  behind when the Expense cannot be created.
- **Consumption accessor**: both readings present, either absent, and a reading
  that went backwards (meter replaced) returning a negative rather than throwing.
- **Line total warning**: equal sums pass silently, a one kuruş difference
  passes, a larger difference warns without blocking the save.
- **Repeater**: schema emits nested fields; validation errors land on the right
  row index; a bill saves with zero lines.
- **Endpoint tests** for both resources, plus one browser test that the bill form
  renders with its repeater and no JavaScript errors.

## Risks

| Risk | Mitigation |
| --- | --- |
| The repeater is the largest new piece and touches a shared component | Built as a general field type with its own tests, not wired into `FormField.vue` behaviour that already works |
| Deleting an account deletes its bills | Intended and stated in the UI copy; the confirm names how many bills will go |
| A bill's lines disagree with its total | Warned, never blocked — the bill is the source of truth, not the application |
| Type list grows | That is the design: one line in the enum, no migration |
