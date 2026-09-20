# Utility Bills Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Record household utility bills — elektrik, doğalgaz, su, internet, telefon — with meter readings where they exist, a due date, and the itemised breakdown the bill prints, where paying one records an expense.

**Architecture:** Three tables. An account is a subscription (one meter, one phone line); a bill belongs to an account; lines belong to a bill. The utility type is a plain string column cast to a PHP enum, so adding a type needs no migration. No tax rule is encoded anywhere — the bill's own line labels and amounts are stored verbatim. A new general `Field::repeater()` type carries the line editor.

**Tech Stack:** PHP 8.5, Laravel 13, Pest 5, Vue 3 + PrimeVue, MySQL.

**Spec:** `docs/superpowers/specs/2026-09-20-utility-bills-design.md`

## Global Constraints

- **Work directly on `master`.** No feature branches. Commit messages carry no AI attribution trailer.
- **Conventional Commits:** `feat:`, `fix:`, `chore:`, `refactor:`, `test:`, `docs:`.
- **One migration file per table.** The repository was just squashed to that rule; do not add `add_x_to_y` migrations.
- **Every class is `final`** except the five documented abstract bases. `tests/Feature/ArchTest.php` enforces it.
- **Every action is `App\Actions\<Domain>\<VerbNoun>`, final, one `handle()`.**
- **PHPStan `level: max`, `--memory-limit=1G`.** No baseline, no `ignoreErrors`, no casting `mixed`.
- **Type coverage stays at 100%.**
- **Never bulk write through the query builder.** `tests/Feature/Actions/Resources/BulkWriteSafetyTest.php` fails the build if you do.
- **Case-insensitive matching lowercases both sides explicitly.** MySQL and SQLite disagree on collation and the suite runs on SQLite.
- **Tests never depend on the local `.env`;** set values with `config([...])` inside the test.
- **Gates before every push:** `composer ci:check`, `npm run lint:check`, `npm run format:check`.
- **No tax rates, no levy columns, no computed VAT.** Store the labels and amounts the bill prints.

---

## File Structure

**Created:**

| Path | Responsibility |
| --- | --- |
| `app/Enums/UtilityType.php` | The type list, its Turkish labels, colours, meter flag and unit |
| `database/migrations/*_create_utility_accounts_table.php` | Subscriptions |
| `database/migrations/*_create_utility_bills_table.php` | Bills |
| `database/migrations/*_create_utility_bill_lines_table.php` | Breakdown rows |
| `app/Models/UtilityAccount.php`, `UtilityBill.php`, `UtilityBillLine.php` | Models |
| `database/factories/UtilityAccountFactory.php`, `UtilityBillFactory.php`, `UtilityBillLineFactory.php` | Factories |
| `app/Actions/Utilities/SaveBillLines.php` | Replace a bill's lines atomically |
| `app/Actions/Utilities/PayBill.php` | Mark paid and create the Expense |
| `app/Forms/Definitions/UtilityAccountForm.php`, `UtilityBillForm.php` | Forms |
| `app/Tables/Definitions/UtilityAccountTable.php`, `UtilityBillTable.php` | Tables |
| `app/Http/Requests/Admin/UtilityAccountRequest.php`, `UtilityBillRequest.php` | Validation |
| `app/Http/Controllers/Admin/Utilities/UtilityAccountController.php`, `UtilityBillController.php` | Resources |
| `resources/js/pages/Utilities/Accounts/{Index,Create,Edit}.vue`, `Bills/{Index,Create,Edit}.vue` | Pages |

**Modified:** `app/Forms/Field.php`, `resources/js/Components/Form/FormField.vue`, `app/Http/Controllers/Admin/AdminResourceController.php`, `routes/admin.php`, `app/Support/Navigation.php`, `database/seeders/DatabaseSeeder.php`, `tests/Browser/AdminSmokeTest.php`, `CHANGELOG.md`.

---

### Task 1: The `UtilityType` enum

**Files:**
- Create: `app/Enums/UtilityType.php`
- Test: `tests/Unit/UtilityTypeTest.php`

**Interfaces:**
- Produces: `UtilityType` implementing `HasColor` and `HasLabel`, plus `hasMeter(): bool` and `unit(): ?string`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/UtilityTypeTest.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\UtilityType;
use App\Support\Contracts\HasColor;
use App\Support\Contracts\HasLabel;

it('labels every type in Turkish', function (UtilityType $type, string $label): void {
    expect($type->getLabel())->toBe($label);
})->with([
    'electricity' => [UtilityType::Electricity, 'Elektrik'],
    'natural gas' => [UtilityType::NaturalGas, 'Doğalgaz'],
    'water' => [UtilityType::Water, 'Su'],
    'internet' => [UtilityType::Internet, 'İnternet'],
    'phone' => [UtilityType::Phone, 'Telefon'],
]);

it('knows which utilities have a meter', function (UtilityType $type, bool $metered): void {
    expect($type->hasMeter())->toBe($metered);
})->with([
    'electricity is metered' => [UtilityType::Electricity, true],
    'gas is metered' => [UtilityType::NaturalGas, true],
    'water is metered' => [UtilityType::Water, true],
    'internet is not' => [UtilityType::Internet, false],
    'phone is not' => [UtilityType::Phone, false],
]);

it('names the unit a meter counts in', function (UtilityType $type, ?string $unit): void {
    expect($type->unit())->toBe($unit);
})->with([
    'electricity in kWh' => [UtilityType::Electricity, 'kWh'],
    'gas in cubic metres' => [UtilityType::NaturalGas, 'm³'],
    'water in cubic metres' => [UtilityType::Water, 'm³'],
    'internet has none' => [UtilityType::Internet, null],
    'phone has none' => [UtilityType::Phone, null],
]);

it('implements the contracts the table reads badges from', function (): void {
    expect(UtilityType::Electricity)->toBeInstanceOf(HasColor::class)
        ->and(UtilityType::Electricity)->toBeInstanceOf(HasLabel::class);
});

/**
 * The point of these three: adding a case without extending every match arm
 * should fail here rather than render a blank badge or an unlabelled option.
 */
it('gives every case a non-empty label', function (): void {
    foreach (UtilityType::cases() as $type) {
        expect($type->getLabel())->not->toBeEmpty();
    }

    expect(UtilityType::cases())->not->toBeEmpty();
});

it('gives every case a semantic colour token', function (): void {
    $tokens = ['primary', 'secondary', 'success', 'warning', 'danger', 'info', 'gray'];

    foreach (UtilityType::cases() as $type) {
        expect($tokens)->toContain($type->getColor());
    }
});

it('gives every metered case a unit and every unmetered case none', function (): void {
    foreach (UtilityType::cases() as $type) {
        $type->hasMeter()
            ? expect($type->unit())->not->toBeNull()
            : expect($type->unit())->toBeNull();
    }
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --filter=UtilityTypeTest`
Expected: FAIL with `Class "App\Enums\UtilityType" not found`.

- [ ] **Step 3: Write the enum**

Create `app/Enums/UtilityType.php`:

```php
<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Contracts\HasColor;
use App\Support\Contracts\HasLabel;

/**
 * The kinds of utility bill the panel records.
 *
 * This enum is the only place the list lives. utility_accounts.type is a plain
 * string column, deliberately not a database enum like expenses.currency, so
 * adding a case here needs no migration.
 */
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

    /**
     * Whether this utility is billed against a meter reading (sayaç).
     */
    public function hasMeter(): bool
    {
        return in_array($this, [self::Electricity, self::NaturalGas, self::Water], true);
    }

    /**
     * What the meter counts, or null when there is no meter.
     */
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

- [ ] **Step 4: Run the tests**

Run: `php artisan test --filter=UtilityTypeTest`
Expected: PASS.

- [ ] **Step 5: Verify the gates**

Run: `composer lint && composer types:check && composer type-coverage`

- [ ] **Step 6: Commit**

```bash
git add app/Enums/UtilityType.php tests/Unit/UtilityTypeTest.php
git commit -m "feat: add the utility type enum with Turkish labels" -- app/Enums/UtilityType.php tests/Unit/UtilityTypeTest.php
```

---

### Task 2: Tables, models and factories

**Files:**
- Create: three migrations, three models, three factories
- Test: `tests/Feature/Models/UtilityBillTest.php`

**Interfaces:**
- Produces: `UtilityAccount` (hasMany `bills`), `UtilityBill` (belongsTo `account`, hasMany `lines`, belongsTo `expense`, `consumption` accessor, `isPaid()`, `lineTotal()`), `UtilityBillLine` (belongsTo `bill`).

- [ ] **Step 1: Create the three migrations**

```bash
php artisan make:migration create_utility_accounts_table --no-interaction
php artisan make:migration create_utility_bills_table --no-interaction
php artisan make:migration create_utility_bill_lines_table --no-interaction
```

Accounts:

```php
Schema::create('utility_accounts', function (Blueprint $table): void {
    $table->id();

    // A plain string, not an enum column: App\Enums\UtilityType is the only
    // list, so adding a type never needs a migration.
    $table->string('type')->index();
    $table->string('provider');
    $table->string('subscriber_no')->nullable();
    $table->string('label');
    $table->boolean('is_active')->default(true);

    $table->timestamps();
});
```

Bills:

```php
Schema::create('utility_bills', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('utility_account_id')->constrained()->cascadeOnDelete();
    $table->foreignId('expense_id')->nullable()->constrained()->nullOnDelete();

    $table->string('bill_number')->nullable();
    $table->date('period_start')->nullable();
    $table->date('period_end')->nullable();
    $table->date('issued_at')->nullable();
    $table->date('due_date');

    $table->decimal('meter_start', 12, 3)->nullable();
    $table->decimal('meter_end', 12, 3)->nullable();

    $table->decimal('total_amount', 15, 2);
    $table->enum('currency', array_column(Currencies::cases(), 'value'))->default('TRY');
    $table->timestamp('paid_at')->nullable();
    $table->string('document_path')->nullable();

    $table->timestamps();

    $table->index('due_date');
    $table->index(['utility_account_id', 'period_start']);
});
```

Lines:

```php
Schema::create('utility_bill_lines', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('utility_bill_id')->constrained()->cascadeOnDelete();

    $table->string('label');
    // Signed: bills carry discounts and previous-balance credits.
    $table->decimal('amount', 15, 2);
    $table->unsignedInteger('sort_order')->default(0);

    $table->timestamps();
});
```

Each migration imports `use App\Enums\Currencies;` where it references it, and `down()` is `Schema::dropIfExists(...)` for its own table.

- [ ] **Step 2: Run the migrations**

Run: `php artisan migrate --no-interaction`
Expected: three DONE lines. Then `php artisan migrate:rollback --step=3 --no-interaction` and `php artisan migrate --no-interaction` to prove the round trip.

- [ ] **Step 3: Write the failing model test**

Create `tests/Feature/Models/UtilityBillTest.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\UtilityType;
use App\Models\UtilityAccount;
use App\Models\UtilityBill;
use App\Models\UtilityBillLine;

it('casts the account type to the enum', function (): void {
    $account = UtilityAccount::factory()->create(['type' => 'electricity']);

    expect($account->type)->toBe(UtilityType::Electricity);
});

it('computes consumption from the two readings', function (): void {
    $bill = UtilityBill::factory()->create(['meter_start' => 1000.5, 'meter_end' => 1250.25]);

    expect($bill->consumption)->toBe(249.75);
});

it('has no consumption when either reading is missing', function (?float $start, ?float $end): void {
    $bill = UtilityBill::factory()->create(['meter_start' => $start, 'meter_end' => $end]);

    expect($bill->consumption)->toBeNull();
})->with([
    'no start' => [null, 1250.0],
    'no end' => [1000.0, null],
    'neither' => [null, null],
]);

it('reports a negative consumption rather than throwing when a meter was replaced', function (): void {
    $bill = UtilityBill::factory()->create(['meter_start' => 9000.0, 'meter_end' => 12.0]);

    expect($bill->consumption)->toBeLessThan(0.0);
});

it('knows whether it is paid', function (): void {
    expect(UtilityBill::factory()->create(['paid_at' => null])->isPaid())->toBeFalse()
        ->and(UtilityBill::factory()->create(['paid_at' => now()])->isPaid())->toBeTrue();
});

it('sums its lines', function (): void {
    $bill = UtilityBill::factory()->create();
    UtilityBillLine::factory()->for($bill, 'bill')->create(['amount' => 10.00]);
    UtilityBillLine::factory()->for($bill, 'bill')->create(['amount' => 2.50]);
    UtilityBillLine::factory()->for($bill, 'bill')->create(['amount' => -1.00]);

    expect($bill->refresh()->lineTotal())->toBe(11.50);
});

it('sums to zero when it has no lines', function (): void {
    expect(UtilityBill::factory()->create()->lineTotal())->toBe(0.0);
});

it('belongs to an account which has many bills', function (): void {
    $account = UtilityAccount::factory()->create();
    UtilityBill::factory()->count(3)->for($account, 'account')->create();

    expect($account->bills)->toHaveCount(3)
        ->and($account->bills->first()->account->id)->toBe($account->id);
});

it('deletes its lines when the bill goes', function (): void {
    $bill = UtilityBill::factory()->create();
    UtilityBillLine::factory()->count(2)->for($bill, 'bill')->create();

    $bill->delete();

    expect(UtilityBillLine::count())->toBe(0);
});

it('deletes its bills when the account goes', function (): void {
    $account = UtilityAccount::factory()->create();
    UtilityBill::factory()->count(2)->for($account, 'account')->create();

    $account->delete();

    expect(UtilityBill::count())->toBe(0);
});
```

- [ ] **Step 4: Run it and watch it fail**

Run: `php artisan test --filter=UtilityBillTest`
Expected: FAIL — models not found.

- [ ] **Step 5: Write the three models**

`app/Models/UtilityAccount.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UtilityType;
use Database\Factories\UtilityAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One subscription: a meter, a phone line, a connection.
 *
 * @property UtilityType $type
 */
final class UtilityAccount extends Model
{
    /** @use HasFactory<UtilityAccountFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['type' => UtilityType::class, 'is_active' => 'boolean'];
    }

    /**
     * @return HasMany<UtilityBill, $this>
     */
    public function bills(): HasMany
    {
        return $this->hasMany(UtilityBill::class);
    }
}
```

`app/Models/UtilityBill.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currencies;
use Database\Factories\UtilityBillFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property Currencies $currency
 * @property float|null $consumption
 */
final class UtilityBill extends Model
{
    /** @use HasFactory<UtilityBillFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'currency' => Currencies::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'issued_at' => 'date',
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'meter_start' => 'float',
            'meter_end' => 'float',
            'total_amount' => 'float',
        ];
    }

    /**
     * @return BelongsTo<UtilityAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(UtilityAccount::class, 'utility_account_id');
    }

    /**
     * @return HasMany<UtilityBillLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(UtilityBillLine::class)->orderBy('sort_order');
    }

    /**
     * @return BelongsTo<Expense, $this>
     */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    /**
     * Never stored: a saved copy drifts from the readings it claims to summarise.
     * Negative when a meter was replaced mid-period, which is information, not an error.
     *
     * @return Attribute<float|null, never>
     */
    protected function consumption(): Attribute
    {
        return Attribute::get(fn (): ?float => $this->meter_start === null || $this->meter_end === null
            ? null
            : round($this->meter_end - $this->meter_start, 3));
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }

    /**
     * What the breakdown adds up to, which may differ from the stated total by
     * rounding. The form warns on a real difference; nothing blocks on it.
     */
    public function lineTotal(): float
    {
        return round((float) $this->lines->sum('amount'), 2);
    }
}
```

`app/Models/UtilityBillLine.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UtilityBillLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class UtilityBillLine extends Model
{
    /** @use HasFactory<UtilityBillLineFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount' => 'float'];
    }

    /**
     * @return BelongsTo<UtilityBill, $this>
     */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(UtilityBill::class, 'utility_bill_id');
    }
}
```

- [ ] **Step 6: Write the three factories**

`database/factories/UtilityAccountFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UtilityType;
use App\Models\UtilityAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UtilityAccount>
 */
final class UtilityAccountFactory extends Factory
{
    protected $model = UtilityAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = UtilityType::cases();
        $type = $types[random_int(0, count($types) - 1)];

        return [
            'type' => $type->value,
            'provider' => fake()->company(),
            'subscriber_no' => (string) fake()->numerify('##########'),
            'label' => $type->getLabel().' — '.fake()->word(),
            'is_active' => true,
        ];
    }

    public function metered(): static
    {
        return $this->state(fn (array $attributes): array => ['type' => UtilityType::Electricity->value]);
    }

    public function unmetered(): static
    {
        return $this->state(fn (array $attributes): array => ['type' => UtilityType::Internet->value]);
    }
}
```

`database/factories/UtilityBillFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\UtilityAccount;
use App\Models\UtilityBill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UtilityBill>
 */
final class UtilityBillFactory extends Factory
{
    protected $model = UtilityBill::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'utility_account_id' => UtilityAccount::factory(),
            'expense_id' => null,
            'bill_number' => (string) fake()->numerify('FTR-#######'),
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'issued_at' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'meter_start' => null,
            'meter_end' => null,
            'total_amount' => 250.00,
            'currency' => 'TRY',
            'paid_at' => null,
            'document_path' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => ['paid_at' => now()]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes): array => [
            'due_date' => now()->subDays(3)->toDateString(),
            'paid_at' => null,
        ]);
    }
}
```

`database/factories/UtilityBillLineFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\UtilityBill;
use App\Models\UtilityBillLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UtilityBillLine>
 */
final class UtilityBillLineFactory extends Factory
{
    protected $model = UtilityBillLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'utility_bill_id' => UtilityBill::factory(),
            'label' => fake()->randomElement(['Enerji bedeli', 'Dağıtım bedeli', 'KDV', 'ÖİV', 'Atıksu bedeli']),
            'amount' => 10.00,
            'sort_order' => 0,
        ];
    }
}
```

- [ ] **Step 7: Run the tests**

Run: `php artisan test --filter=UtilityBillTest`
Expected: PASS, 12 tests.

If `consumption` returns a string rather than a float, the `meter_start` and
`meter_end` casts are missing — MySQL returns decimals as strings.

- [ ] **Step 8: Verify the gates and commit**

```bash
composer lint && composer types:check && composer type-coverage
git add app/Models database/migrations database/factories tests/Feature/Models/UtilityBillTest.php
git commit -m "feat: add utility account, bill and line models" -- app/Models database/migrations database/factories tests/Feature/Models/UtilityBillTest.php
```

---

### Task 3: `SaveBillLines`

**Files:**
- Create: `app/Actions/Utilities/SaveBillLines.php`
- Test: `tests/Feature/Actions/Utilities/SaveBillLinesTest.php`

**Interfaces:**
- Consumes: `UtilityBill`, `UtilityBillLine` from Task 2.
- Produces: `SaveBillLines::handle(UtilityBill $bill, array $lines): int` where `$lines` is `array<int, array{label: string, amount: float|int|string}>` and the return is the number of lines stored.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Actions/Utilities/SaveBillLinesTest.php`:

```php
<?php

declare(strict_types=1);

use App\Actions\Utilities\SaveBillLines;
use App\Models\UtilityBill;
use App\Models\UtilityBillLine;
use Illuminate\Database\QueryException;

it('stores the lines in the order given', function (): void {
    $bill = UtilityBill::factory()->create();

    $stored = resolve(SaveBillLines::class)->handle($bill, [
        ['label' => 'Enerji bedeli', 'amount' => 180.40],
        ['label' => 'Dağıtım bedeli', 'amount' => 42.10],
        ['label' => 'KDV', 'amount' => 44.50],
    ]);

    expect($stored)->toBe(3)
        ->and($bill->refresh()->lines->pluck('label')->all())
        ->toBe(['Enerji bedeli', 'Dağıtım bedeli', 'KDV']);
});

it('numbers the rows so the order survives a reload', function (): void {
    $bill = UtilityBill::factory()->create();

    resolve(SaveBillLines::class)->handle($bill, [
        ['label' => 'First', 'amount' => 1],
        ['label' => 'Second', 'amount' => 2],
    ]);

    expect($bill->refresh()->lines->pluck('sort_order')->all())->toBe([0, 1]);
});

it('replaces the previous lines rather than appending to them', function (): void {
    $bill = UtilityBill::factory()->create();
    UtilityBillLine::factory()->count(3)->for($bill, 'bill')->create(['label' => 'Old']);

    resolve(SaveBillLines::class)->handle($bill, [['label' => 'New', 'amount' => 5]]);

    expect($bill->refresh()->lines->pluck('label')->all())->toBe(['New'])
        ->and(UtilityBillLine::count())->toBe(1);
});

it('clears the lines when given none', function (): void {
    $bill = UtilityBill::factory()->create();
    UtilityBillLine::factory()->count(2)->for($bill, 'bill')->create();

    expect(resolve(SaveBillLines::class)->handle($bill, []))->toBe(0)
        ->and($bill->refresh()->lines)->toBeEmpty();
});

it('keeps negative amounts, because bills carry discounts', function (): void {
    $bill = UtilityBill::factory()->create();

    resolve(SaveBillLines::class)->handle($bill, [
        ['label' => 'Hizmet bedeli', 'amount' => 100],
        ['label' => 'İndirim', 'amount' => -15.50],
    ]);

    expect($bill->refresh()->lineTotal())->toBe(84.50);
});

it('leaves another bill untouched', function (): void {
    $mine = UtilityBill::factory()->create();
    $theirs = UtilityBill::factory()->create();
    UtilityBillLine::factory()->count(2)->for($theirs, 'bill')->create();

    resolve(SaveBillLines::class)->handle($mine, [['label' => 'Only mine', 'amount' => 1]]);

    expect($theirs->refresh()->lines)->toHaveCount(2);
});

it('keeps the previous lines when one new row is rejected', function (): void {
    $bill = UtilityBill::factory()->create();
    UtilityBillLine::factory()->for($bill, 'bill')->create(['label' => 'Original']);

    expect(fn () => resolve(SaveBillLines::class)->handle($bill, [
        ['label' => 'Fine', 'amount' => 1],
        ['label' => null, 'amount' => 2],
    ]))->toThrow(QueryException::class);

    expect($bill->refresh()->lines->pluck('label')->all())->toBe(['Original']);
});

it('accepts amounts arriving as strings from the form', function (): void {
    $bill = UtilityBill::factory()->create();

    resolve(SaveBillLines::class)->handle($bill, [['label' => 'KDV', 'amount' => '44.50']]);

    expect($bill->refresh()->lineTotal())->toBe(44.50);
});
```

The rollback test is the one that matters: a half-saved breakdown is worse than
the old one, because you would not know which rows were yours.

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --filter=SaveBillLines`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the action**

Create `app/Actions/Utilities/SaveBillLines.php`:

```php
<?php

declare(strict_types=1);

namespace App\Actions\Utilities;

use App\Models\UtilityBill;
use Illuminate\Support\Facades\DB;

/**
 * Replace a bill's breakdown with the rows the form submitted.
 *
 * Delete and recreate rather than diffing: the rows have no identity worth
 * preserving, and diffing would be more code for the same result. One
 * transaction, because a half-written breakdown is worse than the old one.
 */
final class SaveBillLines
{
    /**
     * @param  array<int, array{label: string|null, amount: float|int|string|null}>  $lines
     * @return int the number of lines stored
     */
    public function handle(UtilityBill $bill, array $lines): int
    {
        return DB::transaction(function () use ($bill, $lines): int {
            $bill->lines()->delete();

            foreach (array_values($lines) as $index => $line) {
                $bill->lines()->create([
                    'label' => $line['label'] ?? null,
                    'amount' => $line['amount'] ?? null,
                    'sort_order' => $index,
                ]);
            }

            return count($lines);
        });
    }
}
```

`$bill->lines()->delete()` is a relation delete, which is a query-level write.
That is fine and does not trip `BulkWriteSafetyTest`: the guard matches
`::query()`, `whereIn(`, `whereKey(` and `::where(` chains, and nothing observes
`UtilityBillLine`. If you ever add an observer to it, switch this to a loop.

- [ ] **Step 4: Run the tests**

Run: `php artisan test --filter=SaveBillLines`
Expected: PASS, 8 tests.

- [ ] **Step 5: Confirm the bulk write guard still passes**

Run: `php artisan test --filter="no bulk write in app"`
Expected: PASS. If it fails, it has matched the relation delete — rewrite the
delete as `$bill->lines->each->delete()` and re-run.

- [ ] **Step 6: Commit**

```bash
git add app/Actions/Utilities/SaveBillLines.php tests/Feature/Actions/Utilities/SaveBillLinesTest.php
git commit -m "feat: replace a utility bill's breakdown atomically" -- app/Actions/Utilities/SaveBillLines.php tests/Feature/Actions/Utilities/SaveBillLinesTest.php
```

---

### Task 4: `PayBill`

**Files:**
- Create: `app/Actions/Utilities/PayBill.php`
- Test: `tests/Feature/Actions/Utilities/PayBillTest.php`

**Interfaces:**
- Consumes: `UtilityBill` from Task 2, `Expense` and `ExpenseCategory` from the existing budget.
- Produces: `PayBill::handle(UtilityBill $bill): Expense`, throwing `RuntimeException` when the bill is already paid.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Actions/Utilities/PayBillTest.php`:

```php
<?php

declare(strict_types=1);

use App\Actions\Utilities\PayBill;
use App\Models\Expense;
use App\Models\UtilityAccount;
use App\Models\UtilityBill;

it('creates one expense for the bill total', function (): void {
    $bill = UtilityBill::factory()->create(['total_amount' => 412.75, 'currency' => 'TRY']);

    $expense = resolve(PayBill::class)->handle($bill);

    expect(Expense::count())->toBe(1)
        ->and((float) $expense->amount)->toBe(412.75)
        ->and($expense->currency->value)->toBe('TRY');
});

it('marks the bill paid and links the expense', function (): void {
    $bill = UtilityBill::factory()->create();

    $expense = resolve(PayBill::class)->handle($bill);

    expect($bill->refresh()->isPaid())->toBeTrue()
        ->and($bill->expense_id)->toBe($expense->id);
});

it('describes the expense with the account label and the period', function (): void {
    $account = UtilityAccount::factory()->create(['label' => 'Ev elektrik']);
    $bill = UtilityBill::factory()->for($account, 'account')->create([
        'period_start' => '2026-08-01',
        'period_end' => '2026-08-31',
    ]);

    $expense = resolve(PayBill::class)->handle($bill);

    expect($expense->description)->toContain('Ev elektrik')
        ->and($expense->description)->toContain('2026-08-01')
        ->and($expense->description)->toContain('2026-08-31');
});

it('still describes a bill that carries no period', function (): void {
    $account = UtilityAccount::factory()->create(['label' => 'İş telefonu']);
    $bill = UtilityBill::factory()->for($account, 'account')->create([
        'period_start' => null,
        'period_end' => null,
    ]);

    expect(resolve(PayBill::class)->handle($bill)->description)->toContain('İş telefonu');
});

it('marks the expense recurring, because a utility bill always is', function (): void {
    $expense = resolve(PayBill::class)->handle(UtilityBill::factory()->create());

    expect($expense->is_recurring)->toBeTrue();
});

it('dates the expense today', function (): void {
    $this->freezeTime();

    $expense = resolve(PayBill::class)->handle(UtilityBill::factory()->create());

    expect($expense->date->toDateString())->toBe(now()->toDateString());
});

it('refuses a bill that is already paid', function (): void {
    $bill = UtilityBill::factory()->paid()->create();

    expect(fn () => resolve(PayBill::class)->handle($bill))
        ->toThrow(RuntimeException::class, 'already paid');

    expect(Expense::count())->toBe(0);
});

it('does not double count when called twice', function (): void {
    $bill = UtilityBill::factory()->create();

    resolve(PayBill::class)->handle($bill);

    expect(fn () => resolve(PayBill::class)->handle($bill->refresh()))->toThrow(RuntimeException::class);

    expect(Expense::count())->toBe(1);
});

it('leaves nothing behind when the transaction fails', function (): void {
    $bill = UtilityBill::factory()->create(['total_amount' => 100.0]);

    DB::table('expenses')->truncate();

    // Force a failure inside the transaction by removing the table the action writes to.
    Schema::rename('expenses', 'expenses_hidden');

    try {
        expect(fn () => resolve(PayBill::class)->handle($bill))->toThrow(Throwable::class);
    } finally {
        Schema::rename('expenses_hidden', 'expenses');
    }

    expect($bill->refresh()->isPaid())->toBeFalse()
        ->and($bill->expense_id)->toBeNull();
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --filter=PayBillTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the action**

Create `app/Actions/Utilities/PayBill.php`:

```php
<?php

declare(strict_types=1);

namespace App\Actions\Utilities;

use App\Models\Expense;
use App\Models\UtilityBill;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Record that a utility bill was paid.
 *
 * The expense is created first and the bill marked paid second, mirroring
 * PayDebt. Refusing an already-paid bill is what stops a double click
 * double-counting the month's spending.
 */
final class PayBill
{
    public function handle(UtilityBill $bill): Expense
    {
        if ($bill->isPaid()) {
            throw new RuntimeException('This bill is already paid.');
        }

        return DB::transaction(function () use ($bill): Expense {
            $expense = Expense::create([
                'amount' => $bill->total_amount,
                'currency' => $bill->currency->value,
                'description' => $this->describe($bill),
                'is_recurring' => true,
                'date' => now()->toDateString(),
            ]);

            $bill->update(['paid_at' => now(), 'expense_id' => $expense->id]);

            return $expense;
        });
    }

    private function describe(UtilityBill $bill): string
    {
        $label = (string) $bill->account->getAttribute('label');

        if ($bill->period_start === null || $bill->period_end === null) {
            return trim($label.' bill') ?: 'Utility bill';
        }

        return trim(sprintf(
            '%s %s — %s',
            $label,
            $bill->period_start->toDateString(),
            $bill->period_end->toDateString(),
        ));
    }
}
```

- [ ] **Step 4: Run the tests**

Run: `php artisan test --filter=PayBillTest`
Expected: PASS, 9 tests.

- [ ] **Step 5: Run everything and commit**

```bash
php artisan test
composer lint && composer types:check
git add app/Actions/Utilities/PayBill.php tests/Feature/Actions/Utilities/PayBillTest.php
git commit -m "feat: record a utility bill payment as an expense" -- app/Actions/Utilities/PayBill.php tests/Feature/Actions/Utilities/PayBillTest.php
```

---

### Task 5: `Field::repeater()` and its renderer

**Files:**
- Modify: `app/Forms/Field.php`, `resources/js/Components/Form/FormField.vue`
- Test: `tests/Feature/Forms/ResourceFormTest.php`

**Interfaces:**
- Produces: `Field::repeater(string $key, array $fields): self`, emitting `type => 'repeater'` and a `fields` key in `schema()` holding each sub-field's own schema array.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Forms/ResourceFormTest.php`:

```php
it('declares a repeater carrying its sub-fields', function (): void {
    $schema = App\Forms\Field::repeater('lines', [
        App\Forms\Field::text('label')->required(),
        App\Forms\Field::money('amount')->required(),
    ])->label('Breakdown')->schema();

    expect($schema['type'])->toBe('repeater')
        ->and($schema['key'])->toBe('lines')
        ->and($schema['label'])->toBe('Breakdown')
        ->and(array_column($schema['fields'], 'key'))->toBe(['label', 'amount'])
        ->and(array_column($schema['fields'], 'type'))->toBe(['text', 'money']);
});

it('gives a repeater an empty field list when it has no sub-fields', function (): void {
    expect(App\Forms\Field::repeater('lines', [])->schema()['fields'])->toBe([]);
});

it('leaves every other field type without a fields key', function (): void {
    expect(App\Forms\Field::text('name')->schema())->not->toHaveKey('fields');
});
```

The third assertion matters: adding `fields` to every field would change the
payload of all thirteen existing resources for no reason.

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --filter=ResourceFormTest`
Expected: FAIL — `Field::repeater()` does not exist.

- [ ] **Step 3: Add the factory method and the schema key**

In `app/Forms/Field.php`, add a property beside the existing private state:

```php
/** @var array<int, self> */
private array $fields = [];
```

Add the factory directly after `public static function isbn()`:

```php
/**
 * A repeating group of sub-fields, edited as rows.
 *
 * Its own type rather than a bespoke widget because ResourceForm.vue has no
 * slots. The renderer reuses FormField for each sub-field, so every existing
 * type works inside a repeater without further work.
 *
 * @param  array<int, self>  $fields
 */
public static function repeater(string $key, array $fields): self
{
    $field = new self($key, 'repeater');
    $field->fields = $fields;

    return $field;
}
```

In `schema()`, add the key only for repeaters, leaving every other field's
payload untouched:

```php
$schema = [
    'key' => $this->key,
    'type' => $this->type,
    'label' => $this->label ?? $this->humanisedLabel(),
    'required' => $this->required,
    'disabled' => $this->disabled,
    'help' => $this->help,
    'placeholder' => $this->placeholder,
    'default' => $this->default,
    'options' => $this->resolveOptions(),
    'meta' => $this->meta,
    'columnSpan' => $this->columnSpan,
];

if ($this->type === 'repeater') {
    $schema['fields'] = array_map(fn (self $field): array => $field->schema(), $this->fields);
}

return $schema;
```

Update the method's return docblock to `array<string, mixed>`, because the shape
is no longer fixed.

- [ ] **Step 4: Run the tests**

Run: `php artisan test --filter=ResourceFormTest`
Expected: PASS.

- [ ] **Step 5: Render it**

In `resources/js/Components/Form/FormField.vue`, add to the script block:

```js
function repeaterRows() {
  return Array.isArray(props.modelValue) ? props.modelValue : []
}

function blankRow() {
  return Object.fromEntries(props.field.fields.map((sub) => [sub.key, null]))
}

function addRow() {
  update([...repeaterRows(), blankRow()])
}

function removeRow(index) {
  update(repeaterRows().filter((_, i) => i !== index))
}

function updateRow(index, key, value) {
  update(repeaterRows().map((row, i) => (i === index ? { ...row, [key]: value } : row)))
}
```

And in the template, immediately before the final fallback `<InputText v-else …>`:

```html
<div v-else-if="field.type === 'repeater'" class="flex flex-col gap-3">
  <div
    v-for="(row, index) in repeaterRows()"
    :key="index"
    class="border-surface-200 dark:border-[#272B35] flex items-end gap-2 rounded border p-3"
  >
    <div class="grid flex-1 gap-3" :style="{ gridTemplateColumns: `repeat(${field.fields.length}, minmax(0, 1fr))` }">
      <FormField
        v-for="sub in field.fields"
        :key="sub.key"
        :field="sub"
        :model-value="row[sub.key]"
        :error="error?.[index]?.[sub.key] ?? null"
        @update:model-value="(value) => updateRow(index, sub.key, value)"
      />
    </div>

    <Button
      icon="pi pi-trash"
      severity="danger"
      text
      rounded
      size="small"
      :aria-label="`Remove row ${index + 1}`"
      @click="removeRow(index)"
    />
  </div>

  <div>
    <Button label="Add row" icon="pi pi-plus" severity="secondary" outlined size="small" @click="addRow" />
  </div>
</div>
```

`FormField` referencing itself works because a `<script setup>` component can
recurse by its own filename. No import is needed.

- [ ] **Step 6: Check the frontend gates**

Run: `npm run lint:check && npm run format:check && npm run build`
Expected: all clean.

If ESLint reports `vue/no-mutating-props`, you have written into `props.modelValue`
rather than emitting a new array — the repeater must always emit a fresh array.

- [ ] **Step 7: Commit**

```bash
git add app/Forms/Field.php resources/js/Components/Form/FormField.vue tests/Feature/Forms/ResourceFormTest.php
git commit -m "feat: add a repeater field type to the form contract" -- app/Forms/Field.php resources/js/Components/Form/FormField.vue tests/Feature/Forms/ResourceFormTest.php
```

---

### Task 6: The accounts resource

**Files:**
- Create: `app/Forms/Definitions/UtilityAccountForm.php`, `app/Tables/Definitions/UtilityAccountTable.php`, `app/Http/Requests/Admin/UtilityAccountRequest.php`, `app/Http/Controllers/Admin/Utilities/UtilityAccountController.php`, `resources/js/pages/Utilities/Accounts/{Index,Create,Edit}.vue`
- Modify: `routes/admin.php`, `app/Support/Navigation.php`
- Test: `tests/Feature/Admin/Utilities/UtilityAccountResourceTest.php`

**Interfaces:**
- Consumes: `UtilityType` (Task 1), `UtilityAccount` (Task 2).
- Produces: routes `admin.utility-accounts.*`.

- [ ] **Step 1: Write the form definition**

```php
<?php

declare(strict_types=1);

namespace App\Forms\Definitions;

use App\Enums\UtilityType;
use App\Forms\Field;
use App\Forms\ResourceForm;
use Override;

final class UtilityAccountForm extends ResourceForm
{
    #[Override]
    protected int $columns = 2;

    /**
     * @return array<int, Field>
     */
    protected function fields(): array
    {
        return [
            Field::enum('type', UtilityType::class)->required(),
            Field::text('provider')->required()->help('Enerjisa, İGDAŞ, Turkcell.'),
            Field::text('label')->required()->help('What you call it: Ev elektrik, İş telefonu.'),
            Field::text('subscriber_no')->label('Subscriber no')->help('Abone or tesisat numarası.'),
            Field::toggle('is_active')->label('Active')->default(true),
            Field::placeholder('created_at')->label('Created'),
        ];
    }
}
```

- [ ] **Step 2: Write the table definition**

```php
<?php

declare(strict_types=1);

namespace App\Tables\Definitions;

use App\Enums\UtilityType;
use App\Models\UtilityAccount;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\ResourceTable;
use Override;

final class UtilityAccountTable extends ResourceTable
{
    #[Override]
    protected string $model = UtilityAccount::class;

    // Column::count reads the _count column, so the query has to produce it.
    #[Override]
    protected array $withCount = ['bills'];

    /**
     * @return array<int, Column>
     */
    protected function columns(): array
    {
        return [
            Column::badge('type')->state(fn (UtilityAccount $record): string => $record->type->getLabel()),
            Column::text('label')->sortable(),
            Column::text('provider')->sortable(),
            Column::text('subscriber_no')->label('Subscriber no')->default('—'),
            Column::count('bills_count')->label('Bills'),
            Column::boolean('is_active')->label('Active'),
            Column::datetime('created_at')->label('Created')->sortable()->toggleable(hiddenByDefault: true),
        ];
    }

    /**
     * @return array<int, Filter>
     */
    protected function filters(): array
    {
        return [
            Filter::enum('type', UtilityType::class)->multiple(),
            Filter::select('is_active', [1 => 'Active', 0 => 'Inactive'])->label('Status'),
        ];
    }
}
```

`Column::badge('type')` picks its colour up from `HasColor` automatically —
`app/Tables/Column.php:402` already does that for any enum value.

- [ ] **Step 3: Write the request**

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\UtilityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UtilityAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::enum(UtilityType::class)],
            'provider' => ['required', 'string', 'max:255'],
            'label' => ['required', 'string', 'max:255'],
            'subscriber_no' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }
}
```

- [ ] **Step 4: Write the controller**

Copy the shape of `app/Http/Controllers/Admin/Budget/ExpenseController.php`:
`table()`, `form()`, `modelClass()`, `resourceName()` returning
`'utility-accounts'`, `pagePath()` returning `'Utilities/Accounts'`, and
`requestClass()`. No `uploads()`, no `indexTiles()`.

- [ ] **Step 5: Register the routes**

In `routes/admin.php`, beside the other resources:

```php
Route::delete('utility-accounts/bulk', [UtilityAccountController::class, 'bulkDestroy'])->name('utility-accounts.bulk-destroy');
Route::patch('utility-accounts/bulk', [UtilityAccountController::class, 'bulkUpdate'])->name('utility-accounts.bulk-update');
Route::resource('utility-accounts', UtilityAccountController::class)->except(['show']);
```

The two bulk routes come first, for the same reason every other resource does:
`utility-accounts/bulk` must not be captured by `utility-accounts/{utility_account}`.

- [ ] **Step 6: Add the three Vue pages**

Copy `resources/js/pages/Budget/Expenses/{Index,Create,Edit}.vue` verbatim into
`resources/js/pages/Utilities/Accounts/`, replacing every `admin.expenses.`
route name with `admin.utility-accounts.` and the page titles with
"Utility accounts", "New account" and "Edit account".

- [ ] **Step 7: Add it to the navigation**

In `app/Support/Navigation.php`, add a Utilities cluster containing the accounts
item, pointing at `admin.utility-accounts.index`. `ParityTest` asserts every
navigation item points at a real route, so this must come after Step 5.

- [ ] **Step 8: Write the resource test**

Create `tests/Feature/Admin/Utilities/UtilityAccountResourceTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UtilityAccount;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));
});

it('lists accounts', function (): void {
    UtilityAccount::factory()->count(3)->create();

    $this->get(route('admin.utility-accounts.index'))->assertOk();
});

it('creates an account', function (): void {
    $this->post(route('admin.utility-accounts.store'), [
        'type' => 'electricity',
        'provider' => 'Enerjisa',
        'label' => 'Ev elektrik',
        'subscriber_no' => '1234567890',
        'is_active' => true,
    ])->assertRedirect(route('admin.utility-accounts.index'));

    expect(UtilityAccount::where('label', 'Ev elektrik')->exists())->toBeTrue();
});

it('refuses a type outside the enum', function (string $type): void {
    $this->from(route('admin.utility-accounts.index'))
        ->post(route('admin.utility-accounts.store'), [
            'type' => $type,
            'provider' => 'Enerjisa',
            'label' => 'Ev elektrik',
        ])
        ->assertSessionHasErrors('type');
})->with(['gasoline', 'ELECTRICITY', '', 'App\Models\User']);

it('requires a provider and a label', function (): void {
    $this->from(route('admin.utility-accounts.index'))
        ->post(route('admin.utility-accounts.store'), ['type' => 'water'])
        ->assertSessionHasErrors(['provider', 'label']);
});

it('updates an account', function (): void {
    $account = UtilityAccount::factory()->create(['label' => 'Before']);

    $this->put(route('admin.utility-accounts.update', $account), [
        'type' => $account->type->value,
        'provider' => $account->provider,
        'label' => 'After',
        'is_active' => true,
    ])->assertRedirect(route('admin.utility-accounts.index'));

    expect($account->refresh()->label)->toBe('After');
});

it('deletes an account', function (): void {
    $account = UtilityAccount::factory()->create();

    $this->delete(route('admin.utility-accounts.destroy', $account))
        ->assertRedirect(route('admin.utility-accounts.index'));

    expect(UtilityAccount::count())->toBe(0);
});

it('turns a guest away', function (): void {
    auth()->logout();

    $this->get(route('admin.utility-accounts.index'))->assertRedirect(route('login'));
});
```

- [ ] **Step 9: Run everything and commit**

```bash
php artisan test
composer lint && composer types:check && composer type-coverage
npm run lint:check && npm run format:check && npm run build
git add app/ routes/admin.php resources/js/pages/Utilities tests/
git commit -m "feat: add the utility accounts resource" -- app/ routes/admin.php resources/js/pages/Utilities tests/
```

---

### Task 7: The bills resource

**Files:**
- Create: `app/Forms/Definitions/UtilityBillForm.php`, `app/Tables/Definitions/UtilityBillTable.php`, `app/Http/Requests/Admin/UtilityBillRequest.php`, `app/Http/Controllers/Admin/Utilities/UtilityBillController.php`, `resources/js/pages/Utilities/Bills/{Index,Create,Edit}.vue`
- Modify: `routes/admin.php`, `app/Support/Navigation.php`
- Test: `tests/Feature/Admin/Utilities/UtilityBillResourceTest.php`

**Interfaces:**
- Consumes: `Field::repeater()` (Task 5), `SaveBillLines` (Task 3), `PayBill` (Task 4), `UtilityBill` (Task 2).
- Produces: routes `admin.utility-bills.*` plus `admin.utility-bills.pay`.

**The one place this resource differs from the other thirteen.** `lines` is not a
column and not a relation-sync, so `ResourceForm::partition()` would put it in
`attributes` and mass assignment would fail on a column that does not exist. The
controller therefore pulls `lines` out of the validated data before partitioning,
saves the record, then hands the rows to `SaveBillLines`.

- [ ] **Step 1: Write the form definition**

```php
<?php

declare(strict_types=1);

namespace App\Forms\Definitions;

use App\Enums\Currencies;
use App\Forms\Field;
use App\Forms\ResourceForm;
use Override;

final class UtilityBillForm extends ResourceForm
{
    #[Override]
    protected int $columns = 2;

    /**
     * @return array<int, Field>
     */
    protected function fields(): array
    {
        return [
            Field::relationship('utility_account_id', 'account', 'label')->label('Account')->required()->searchable(),
            Field::text('bill_number')->label('Bill no')->help('Fatura numarası.'),
            Field::date('period_start')->label('Period from'),
            Field::date('period_end')->label('Period to'),
            Field::date('issued_at')->label('Issued'),
            Field::date('due_date')->label('Due date')->required()->help('Son ödeme tarihi.'),

            // Always shown: the form contract has no conditional visibility, and
            // on create the account is not chosen server side yet. The request
            // rejects readings on an unmetered account.
            Field::number('meter_start')->label('Meter start')->step(0.001)
                ->help('Only for metered utilities: elektrik, doğalgaz, su.'),
            Field::number('meter_end')->label('Meter end')->step(0.001),

            Field::money('total_amount')->label('Total')->required()->min(0)->step(0.01),
            Field::enum('currency', Currencies::class)->required()->default(Currencies::TRY->value),

            Field::repeater('lines', [
                Field::text('label')->required(),
                Field::money('amount')->required()->step(0.01),
            ])->label('Breakdown')->help('Enter the lines exactly as the bill prints them.')->columnSpan(2),

            Field::image('document_path')->label('Bill document')->directory('utility-bills')
                ->accept(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])->columnSpan(2),
            Field::placeholder('created_at')->label('Created'),
        ];
    }

    /**
     * The breakdown rows, which are saved separately from the record's columns.
     *
     * @param  array<string, mixed>  $values
     * @return array<int, array{label: string|null, amount: float|int|string|null}>
     */
    public function pullLines(array &$values): array
    {
        $lines = $values['lines'] ?? [];
        unset($values['lines']);

        return is_array($lines) ? array_values(array_filter($lines, is_array(...))) : [];
    }
}
```

- [ ] **Step 2: Write the table definition**

```php
<?php

declare(strict_types=1);

namespace App\Tables\Definitions;

use App\Enums\UtilityType;
use App\Models\UtilityBill;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\ResourceTable;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Override;

final class UtilityBillTable extends ResourceTable
{
    #[Override]
    protected string $model = UtilityBill::class;

    // Every row reads account.type and account.label. Model::shouldBeStrict()
    // turns a lazy load into an exception outside production, so this is not
    // an optimisation, it is what stops the page throwing.
    #[Override]
    protected array $with = ['account'];

    #[Override]
    protected string $defaultSort = 'due_date';

    /**
     * @return array<int, Column>
     */
    protected function columns(): array
    {
        return [
            Column::badge('account.type')->label('Type')
                ->state(fn (UtilityBill $record): string => $record->account->type->getLabel())
                ->color(fn (UtilityBill $record): string => $record->account->type->getColor()),
            Column::text('account.label')->label('Account'),
            Column::date('period_start')->label('From')->default('—'),
            Column::date('period_end')->label('To')->default('—'),
            Column::date('due_date')->label('Due')->sortable(),
            Column::badge('paid_at')->label('Status')
                ->state(fn (UtilityBill $record): string => match (true) {
                    $record->isPaid() => 'Paid',
                    $record->due_date->isPast() => 'Overdue',
                    default => 'Outstanding',
                })
                ->color(fn (UtilityBill $record): string => match (true) {
                    $record->isPaid() => 'success',
                    $record->due_date->isPast() => 'danger',
                    default => 'warning',
                }),
            Column::money('total_amount', currencyFrom: 'currency')->label('Total')->sortable(),
            Column::datetime('created_at')->label('Created')->sortable()->toggleable(hiddenByDefault: true),
        ];
    }

    /**
     * @return array<int, Filter>
     */
    protected function filters(): array
    {
        return [
            Filter::relationship('utility_account_id', 'account', 'label')->label('Account')->multiple(),
            Filter::custom('unpaid', 'Unpaid', fn (Builder $query): Builder => $query->whereNull('paid_at')),
            Filter::custom('overdue', 'Overdue', fn (Builder $query): Builder => $query
                ->whereNull('paid_at')
                ->whereDate('due_date', '<', now())),
            Filter::custom('due_soon', 'Due within 7 days', fn (Builder $query): Builder => $query
                ->whereNull('paid_at')
                ->whereBetween('due_date', [now()->toDateString(), now()->addDays(7)->toDateString()])),
            Filter::dateRange('due_date')->label('Due'),
        ];
    }

    /**
     * @return array<int, array{label: string, value: string, caption: string|null, icon: string|null}>
     */
    public function tiles(Request $request): array
    {
        $query = $this->filteredQuery($request);

        $outstanding = (float) (clone $query)->whereNull('paid_at')->sum('total_amount');
        $overdue = (clone $query)->whereNull('paid_at')->whereDate('due_date', '<', now())->count();
        $thisMonth = (float) (clone $query)
            ->whereNotNull('paid_at')
            ->whereBetween('due_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->sum('total_amount');

        return [
            [
                'label' => 'Outstanding',
                'value' => number_format($outstanding, 2),
                'caption' => 'unpaid bills',
                'icon' => 'pi pi-wallet',
            ],
            [
                'label' => 'Overdue',
                'value' => number_format($overdue),
                'caption' => $overdue === 0 ? 'nothing late' : 'past the due date',
                'icon' => 'pi pi-exclamation-triangle',
            ],
            [
                'label' => 'Paid this month',
                'value' => number_format($thisMonth, 2),
                'caption' => now()->format('F'),
                'icon' => 'pi pi-check-circle',
            ],
        ];
    }
}
```

- [ ] **Step 3: Write the request**

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\UtilityAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UtilityBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'utility_account_id' => ['required', 'integer', 'exists:utility_accounts,id'],
            'bill_number' => ['nullable', 'string', 'max:255'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'issued_at' => ['nullable', 'date'],
            'due_date' => ['required', 'date'],
            'meter_start' => ['nullable', 'numeric'],
            'meter_end' => ['nullable', 'numeric'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string'],
            'document_path' => ['nullable', 'file', 'max:5120'],
            'lines' => ['nullable', 'array'],
            'lines.*.label' => ['required', 'string', 'max:255'],
            'lines.*.amount' => ['required', 'numeric'],
        ];
    }

    /**
     * The form has no conditional visibility, so the rule that readings belong
     * only to metered utilities is enforced here, where the account is known.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $account = UtilityAccount::find($this->input('utility_account_id'));

            if (! $account instanceof UtilityAccount || $account->type->hasMeter()) {
                return;
            }

            foreach (['meter_start', 'meter_end'] as $field) {
                if ($this->input($field) !== null) {
                    $validator->errors()->add($field, $account->type->getLabel().' has no meter.');
                }
            }
        });
    }
}
```

- [ ] **Step 4: Write the controller**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Utilities;

use App\Actions\Utilities\PayBill;
use App\Actions\Utilities\SaveBillLines;
use App\Forms\Definitions\UtilityBillForm;
use App\Http\Controllers\Admin\AdminResourceController;
use App\Http\Requests\Admin\UtilityBillRequest;
use App\Models\UtilityBill;
use App\Tables\Definitions\UtilityBillTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class UtilityBillController extends AdminResourceController
{
    protected function table(): UtilityBillTable
    {
        return new UtilityBillTable;
    }

    protected function form(): UtilityBillForm
    {
        return new UtilityBillForm;
    }

    protected function modelClass(): string
    {
        return UtilityBill::class;
    }

    protected function resourceName(): string
    {
        return 'utility-bills';
    }

    protected function pagePath(): string
    {
        return 'Utilities/Bills';
    }

    protected function requestClass(): string
    {
        return UtilityBillRequest::class;
    }

    /**
     * @return array<string, string>
     */
    protected function uploads(): array
    {
        return ['document_path' => 'utility-bills'];
    }

    /**
     * Overridden because the breakdown is not a column and not a relation sync,
     * so it has to leave the data before partition() mass assigns it.
     */
    public function store(): RedirectResponse
    {
        $form = $this->form();
        $data = $this->validated();
        $lines = $form->pullLines($data);

        $bill = $this->storeRecord->handle($this->modelClass(), $form->partition($data));

        resolve(SaveBillLines::class)->handle($bill, $lines);

        $this->notifier->success('Bill created');

        return to_route($this->indexRoute());
    }

    public function update(Request $request): RedirectResponse
    {
        $form = $this->form();
        $data = $this->validated();
        $lines = $form->pullLines($data);

        $bill = $this->updateRecord->handle($this->resolveRecord($request), $form->partition($data));

        resolve(SaveBillLines::class)->handle($bill, $lines);

        $this->notifier->success('Bill updated');

        return to_route($this->indexRoute());
    }

    public function pay(UtilityBill $utilityBill, PayBill $payBill): RedirectResponse
    {
        try {
            $payBill->handle($utilityBill);
            $this->notifier->success('Bill paid', 'Recorded as an expense.');
        } catch (RuntimeException $exception) {
            $this->notifier->danger('Could not pay', $exception->getMessage());
        }

        return to_route($this->indexRoute());
    }
}
```

`$this->storeRecord`, `$this->updateRecord`, `$this->notifier` and
`resolveRecord()` are all `protected` on `AdminResourceController`, so no
constructor change is needed. `SaveBillLines` is resolved inside the method
because adding a required parameter to an overridden method is a signature
violation in PHP.

- [ ] **Step 5: Register the routes**

```php
Route::delete('utility-bills/bulk', [UtilityBillController::class, 'bulkDestroy'])->name('utility-bills.bulk-destroy');
Route::patch('utility-bills/bulk', [UtilityBillController::class, 'bulkUpdate'])->name('utility-bills.bulk-update');
Route::post('utility-bills/{utilityBill}/pay', [UtilityBillController::class, 'pay'])->name('utility-bills.pay');
Route::resource('utility-bills', UtilityBillController::class)->except(['show']);
```

- [ ] **Step 6: Add the pages and the navigation entry**

Copy `resources/js/pages/Budget/Debts/{Index,Create,Edit}.vue` into
`resources/js/pages/Utilities/Bills/`, swap the route names to
`admin.utility-bills.`, retitle to "Utility bills", "New bill" and "Edit bill",
and replace the Debts pay dialog with a plain confirm calling
`router.post(route('admin.utility-bills.pay', row.id))`.

Add the bills item to the Utilities cluster in `app/Support/Navigation.php`.

- [ ] **Step 7: Write the resource test**

Create `tests/Feature/Admin/Utilities/UtilityBillResourceTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Expense;
use App\Models\User;
use App\Models\UtilityAccount;
use App\Models\UtilityBill;
use App\Models\UtilityBillLine;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));
});

it('creates a bill with its breakdown', function (): void {
    $account = UtilityAccount::factory()->metered()->create();

    $this->post(route('admin.utility-bills.store'), [
        'utility_account_id' => $account->id,
        'due_date' => now()->addDays(10)->toDateString(),
        'total_amount' => 266.00,
        'currency' => 'TRY',
        'lines' => [
            ['label' => 'Enerji bedeli', 'amount' => 180.40],
            ['label' => 'Dağıtım bedeli', 'amount' => 41.10],
            ['label' => 'KDV', 'amount' => 44.50],
        ],
    ])->assertRedirect(route('admin.utility-bills.index'));

    $bill = UtilityBill::sole();

    expect($bill->lines)->toHaveCount(3)
        ->and($bill->lines->pluck('label')->all())->toBe(['Enerji bedeli', 'Dağıtım bedeli', 'KDV'])
        ->and($bill->lineTotal())->toBe(266.00);
});

it('creates a bill with no breakdown at all', function (): void {
    $account = UtilityAccount::factory()->create();

    $this->post(route('admin.utility-bills.store'), [
        'utility_account_id' => $account->id,
        'due_date' => now()->addDays(10)->toDateString(),
        'total_amount' => 99.00,
        'currency' => 'TRY',
    ])->assertRedirect(route('admin.utility-bills.index'));

    expect(UtilityBill::sole()->lines)->toBeEmpty();
});

it('replaces the breakdown on update rather than appending', function (): void {
    $bill = UtilityBill::factory()->create();
    UtilityBillLine::factory()->count(2)->for($bill, 'bill')->create(['label' => 'Old']);

    $this->put(route('admin.utility-bills.update', $bill), [
        'utility_account_id' => $bill->utility_account_id,
        'due_date' => $bill->due_date->toDateString(),
        'total_amount' => $bill->total_amount,
        'currency' => $bill->currency->value,
        'lines' => [['label' => 'New', 'amount' => 5]],
    ])->assertRedirect(route('admin.utility-bills.index'));

    expect($bill->refresh()->lines->pluck('label')->all())->toBe(['New']);
});

it('rejects a line with no label', function (): void {
    $account = UtilityAccount::factory()->create();

    $this->from(route('admin.utility-bills.index'))
        ->post(route('admin.utility-bills.store'), [
            'utility_account_id' => $account->id,
            'due_date' => now()->toDateString(),
            'total_amount' => 10,
            'currency' => 'TRY',
            'lines' => [['label' => '', 'amount' => 5]],
        ])
        ->assertSessionHasErrors('lines.0.label');
});

it('refuses meter readings on a utility with no meter', function (string $field): void {
    $account = UtilityAccount::factory()->unmetered()->create();

    $this->from(route('admin.utility-bills.index'))
        ->post(route('admin.utility-bills.store'), [
            'utility_account_id' => $account->id,
            'due_date' => now()->toDateString(),
            'total_amount' => 10,
            'currency' => 'TRY',
            $field => 1234,
        ])
        ->assertSessionHasErrors($field);
})->with(['meter_start', 'meter_end']);

it('accepts meter readings on a metered utility', function (): void {
    $account = UtilityAccount::factory()->metered()->create();

    $this->post(route('admin.utility-bills.store'), [
        'utility_account_id' => $account->id,
        'due_date' => now()->toDateString(),
        'total_amount' => 10,
        'currency' => 'TRY',
        'meter_start' => 1000,
        'meter_end' => 1250,
    ])->assertRedirect(route('admin.utility-bills.index'));

    expect(UtilityBill::sole()->consumption)->toBe(250.0);
});

it('refuses a period that ends before it starts', function (): void {
    $account = UtilityAccount::factory()->create();

    $this->from(route('admin.utility-bills.index'))
        ->post(route('admin.utility-bills.store'), [
            'utility_account_id' => $account->id,
            'period_start' => '2026-08-31',
            'period_end' => '2026-08-01',
            'due_date' => now()->toDateString(),
            'total_amount' => 10,
            'currency' => 'TRY',
        ])
        ->assertSessionHasErrors('period_end');
});

it('pays a bill and records the expense', function (): void {
    $bill = UtilityBill::factory()->create(['total_amount' => 300.00]);

    $this->post(route('admin.utility-bills.pay', $bill))
        ->assertRedirect(route('admin.utility-bills.index'));

    expect($bill->refresh()->isPaid())->toBeTrue()
        ->and(Expense::count())->toBe(1)
        ->and((float) Expense::sole()->amount)->toBe(300.00);
});

it('reports rather than throws when the bill is already paid', function (): void {
    $bill = UtilityBill::factory()->paid()->create();

    $this->post(route('admin.utility-bills.pay', $bill))->assertRedirect();

    expect(Expense::count())->toBe(0)
        ->and(session('flash.notification'))->toHaveKey('variant', 'danger');
});

it('turns a guest away', function (): void {
    auth()->logout();

    $this->get(route('admin.utility-bills.index'))->assertRedirect(route('login'));
});
```

- [ ] **Step 8: Run everything and commit**

```bash
php artisan test
composer lint && composer types:check && composer type-coverage
npm run lint:check && npm run format:check && npm run build
git add app/ routes/admin.php resources/js/pages/Utilities tests/
git commit -m "feat: add the utility bills resource" -- app/ routes/admin.php resources/js/pages/Utilities tests/
```

---

### Task 8: Seed data, browser check and release

**Files:**
- Modify: `database/seeders/DatabaseSeeder.php`, `tests/Browser/AdminSmokeTest.php`, `CHANGELOG.md`

- [ ] **Step 1: Seed one account and one bill per metered and unmetered type**

In `DatabaseSeeder`, add a `createUtilities()` call beside `createSettings()` and
`createContent()`:

```php
private function createUtilities(): void
{
    $accounts = [
        ['type' => 'electricity', 'provider' => 'Enerjisa', 'label' => 'Ev elektrik', 'subscriber_no' => '4001234567'],
        ['type' => 'natural_gas', 'provider' => 'İGDAŞ', 'label' => 'Ev doğalgaz', 'subscriber_no' => '9007654321'],
        ['type' => 'water', 'provider' => 'İSKİ', 'label' => 'Ev su', 'subscriber_no' => '5551112222'],
        ['type' => 'internet', 'provider' => 'Türk Telekom', 'label' => 'Ev internet', 'subscriber_no' => null],
        ['type' => 'phone', 'provider' => 'Turkcell', 'label' => 'İş telefonu', 'subscriber_no' => null],
    ];

    foreach ($accounts as $accountData) {
        $account = UtilityAccount::create($accountData + ['is_active' => true]);

        $bill = $account->bills()->create([
            'bill_number' => 'FTR-'.random_int(1000000, 9999999),
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'issued_at' => now()->toDateString(),
            'due_date' => now()->addDays(12)->toDateString(),
            'meter_start' => $account->type->hasMeter() ? 1000 : null,
            'meter_end' => $account->type->hasMeter() ? 1240 : null,
            'total_amount' => 266.00,
            'currency' => 'TRY',
        ]);

        $bill->lines()->createMany([
            ['label' => 'Hizmet bedeli', 'amount' => 221.50, 'sort_order' => 0],
            ['label' => 'KDV', 'amount' => 44.50, 'sort_order' => 1],
        ]);
    }
}
```

Import `App\Models\UtilityAccount` at the top.

- [ ] **Step 2: Prove a fresh database is usable**

```bash
php artisan migrate:fresh --seed --no-interaction
php artisan tinker --execute 'echo UtilityAccount::count()." accounts, ".UtilityBill::count()." bills, ".UtilityBillLine::count()." lines\n";'
```

Expected: `5 accounts, 5 bills, 10 lines`.

- [ ] **Step 3: Add the browser check**

Append to `tests/Browser/AdminSmokeTest.php`:

```php
it('renders the utility bill form with its repeater and no javascript errors', function (): void {
    App\Models\UtilityAccount::factory()->create(['label' => 'Ev elektrik']);

    visit(route('admin.utility-bills.create'))
        ->assertNoJavaScriptErrors()
        ->assertSee('Breakdown')
        ->assertSee('Add row')
        ->click('button:has-text("Add row")')
        ->assertNoJavaScriptErrors();
});
```

Also add `admin.utility-accounts.index` and `admin.utility-bills.index` to the
existing dataset in `it('loads every admin index without javascript errors')`.

- [ ] **Step 4: Run every gate from a clean state**

```bash
composer ci:check
npm run lint:check && npm run format:check && npm run build
```

- [ ] **Step 5: Run against the CI environment**

```bash
cp .env .env.bak && cp .env.example .env && php artisan key:generate --quiet
php artisan test
cp .env.bak .env && rm .env.bak
```

Expected: identical results. A difference means a test reads a value only your
local `.env` carries; set it with `config([...])` inside the test instead.

- [ ] **Step 6: Write the changelog entry**

Add above the newest released version in `CHANGELOG.md`:

```markdown
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
  contract, reusing FormField for each sub-field.

### Notes

- No Turkish tax rule is encoded anywhere. There is no VAT rate column and
  nothing is computed: rates and levies change, and a stored rule would silently
  recompute old bills to figures that never appeared on the paper.
- `utility_accounts.type` is a plain string column cast to `App\Enums\UtilityType`,
  not a database enum like `expenses.currency`. Adding a type is one line in the
  enum and needs no migration.
```

- [ ] **Step 7: Commit, tag and release**

```bash
git add database/seeders/DatabaseSeeder.php tests/Browser/AdminSmokeTest.php CHANGELOG.md
git commit -m "feat: seed utility accounts and bills" -- database/seeders/DatabaseSeeder.php tests/Browser/AdminSmokeTest.php CHANGELOG.md
git push origin master
git tag -a v0.8.0 -m "Utility bills"
git push origin v0.8.0
```

- [ ] **Step 8: Confirm CI is green before publishing the release**

```bash
gh run list --limit 1 --workflow=ci.yml
```

Expected: all six jobs succeed.

---

## Verification Summary

After Task 8, all of the following must hold:

```bash
composer ci:check
npm run lint:check && npm run format:check && npm run build
```

- `php artisan route:list --name=utility` shows both bulk routes and the pay
  route **above** their resource routes.
- `php artisan migrate:fresh --seed` yields 5 accounts, 5 bills and 10 lines.
- Adding a case to `UtilityType` requires no migration, and
  `tests/Unit/UtilityTypeTest.php` fails if the new case lacks a label, a colour
  or a unit decision.
- `UtilityBillRequest` rejects meter readings on an unmetered account.
- `PayBill` refuses an already-paid bill, and `Expense::count()` stays at one
  after two attempts.
- `SaveBillLines` leaves the previous breakdown intact when a new row is rejected.
- No column anywhere stores a VAT rate or a computed tax.
- Every new class under `app/` is `final`; `tests/Feature/ArchTest.php` passes.
- `tests/Feature/Actions/Resources/BulkWriteSafetyTest.php` still passes.
