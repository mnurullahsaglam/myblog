# Invites and Registration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A second person joins the panel by opening a link sent to her email, choosing her own password, and landing in exactly the areas the invite named — without the owner ever typing her password or editing a seeder.

**Architecture:** An `invites` table holding a hashed token, a role and three timestamps from which status is derived. One action creates an invite and mails it; another accepts it under a row lock and creates the user. Two public routes outside `auth` handle the link. Fortify's password reset is switched on in the same change, because an emailed invite is only safe to depend on if she can recover her own account.

**Tech Stack:** PHP 8.5, Laravel 13, Fortify, Pest 5, Inertia + Vue 3, PrimeVue, Resend (production) and Mailtrap (local).

**Spec:** `docs/superpowers/specs/2026-09-20-invites-and-registration-design.md`

## Global Constraints

- **Work directly on `master`.** No feature branches. Commit messages carry no AI attribution trailer.
- **Conventional Commits:** `feat:`, `fix:`, `chore:`, `refactor:`, `test:`, `docs:`.
- **One migration file per table.** Do not add `add_x_to_y` migrations.
- **Every class is `final`** except the six documented abstract bases in `tests/Feature/ArchTest.php`.
- **PHPStan `level: max`, `--memory-limit=1G`.** No baseline, no `ignoreErrors`, no casting `mixed`.
- **Type coverage stays at 100%.**
- **Refusal is 404.** An expired, revoked, accepted, tampered or unknown token are indistinguishable.
- **The token's plaintext is never stored.** Only `hash('sha256', $token)` reaches the database.
- **Tests never depend on the local `.env`;** set values with `config([...])` inside the test. `phpunit.xml:32` already pins `MAIL_MAILER=array`.
- **`MEMBER_EMAIL` seeding stays.** Invites do not replace it; `migrate:fresh --seed` must keep producing a working member account.
- **Every commit is green.** If a change cannot stand alone, land it with the change that completes it rather than committing a red tree.
- **Gates before every push:** `composer ci:check`, `npm run lint:check`, `npm run format:check`. Check the exit status, not the tail of the output — a pipe into `grep` or `tail` discards it.

---

## File Structure

**Created:**

| Path | Responsibility |
| --- | --- |
| `app/Enums/InviteStatus.php` | Pending, accepted, revoked, expired — with label and colour |
| `database/migrations/..._create_invites_table.php` | The one invites table |
| `app/Models/Invite.php` | Casts, the derived status, usability |
| `database/factories/InviteFactory.php` | With `accepted()`, `revoked()` and `expired()` states |
| `app/Actions/People/CreateInvite.php` | Issue a token, supersede any live invite, send the mail |
| `app/Actions/People/AcceptInvite.php` | Lock, re-check, create the user, sign in |
| `app/Mail/InviteMail.php` | The one message this application sends |
| `resources/views/mail/invite.blade.php` | Its body |
| `app/Http/Controllers/InviteController.php` | The two public routes |
| `app/Http/Requests/AcceptInviteRequest.php` | Name and password rules |
| `resources/js/pages/Auth/AcceptInvite.vue` | The form she sees |
| `resources/js/pages/Auth/ForgotPassword.vue` | Request a reset link |
| `resources/js/pages/Auth/ResetPassword.vue` | Set a new password |
| `app/Http/Controllers/Admin/General/PeopleController.php` | The panel screen |
| `app/Http/Requests/Admin/InviteRequest.php` | Email and role rules, declaring `Area::General` |
| `app/Tables/Definitions/InviteTable.php` | The invites table |
| `app/Tables/Definitions/UserTable.php` | The users table |
| `resources/js/pages/General/People/Index.vue` | Both tables and the invite action |
| `tests/Feature/Auth/InviteTest.php` | The link: every way a token can be wrong |
| `tests/Feature/Auth/PasswordResetTest.php` | The reset pages and enumeration safety |
| `tests/Feature/Admin/General/PeopleTest.php` | The panel screen |
| `tests/Feature/Mail/InviteMailTest.php` | What is sent, and what is not |

**Modified:**

| Path | Change |
| --- | --- |
| `composer.json` | `resend/resend-php` |
| `.env.example` | Mailtrap locally, Resend in production |
| `config/fortify.php` | `Features::resetPasswords()`, and the comment above it rewritten |
| `app/Providers/FortifyServiceProvider.php` | Two view callbacks, one rate limiter |
| `app/Actions/Fortify/CreateNewUser.php` | Accept and set `role` |
| `routes/web.php` | The two public invite routes |
| `routes/admin.php` | People, inside the General group |
| `app/Support/Navigation.php` | A People item in the General cluster |

---

### Task 1: Mail, for real

**Files:**
- Modify: `composer.json`, `.env.example`
- Test: `tests/Feature/Mail/InviteMailTest.php` (created here, one case)

**Interfaces:**
- Produces: a working `resend` transport, and documented local settings.

**Why first:** every later task that sends anything depends on this resolving,
and it is the one step that touches a dependency.

- [ ] **Step 1: Prove the transport is missing**

Run:

```bash
php artisan tinker --execute 'try { app("mail.manager")->mailer("resend"); echo "ok"; } catch (Throwable $e) { echo get_class($e).": ".$e->getMessage(); }'
```

Expected: `Error: Class "Resend" not found`. The transport ships with the
framework; the SDK it calls does not.

- [ ] **Step 2: Add the package**

```bash
composer require resend/resend-php
```

This is the only dependency this plan adds, and it was approved explicitly.

- [ ] **Step 3: Prove it resolves**

Run the same tinker line as Step 1.
Expected: `ok`.

- [ ] **Step 4: Document both environments**

In `.env.example`, replace the existing mail block:

```dotenv
# Local. Mailtrap catches everything; nothing reaches a real inbox.
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"

# Production. Set MAIL_MAILER=resend and fill this in; the rest above is ignored.
RESEND_API_KEY=
```

Keep `MAIL_FROM_ADDRESS` and `MAIL_FROM_NAME` where they are if they already
carry values you want; only the mailer, host, port and key lines change.

- [ ] **Step 5: Set your own local environment**

Put your Mailtrap username and password in `.env`. This is the only step in the
plan that touches an untracked file — do not commit `.env`.

- [ ] **Step 6: Write a test that pins the test mailer**

Create `tests/Feature/Mail/InviteMailTest.php`:

```php
<?php

declare(strict_types=1);

/**
 * The suite must never reach a real mail server, whatever a developer's .env
 * says. phpunit.xml pins this; the test is what notices if that is edited.
 */
it('sends mail nowhere during tests', function (): void {
    expect(config('mail.default'))->toBe('array');
});
```

- [ ] **Step 7: Run it**

Run: `php artisan test --filter=InviteMailTest`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
composer lint && composer types:check
git add composer.json composer.lock .env.example tests/Feature/Mail/InviteMailTest.php
git commit -m "chore: add the resend mail transport" -- composer.json composer.lock .env.example tests/Feature/Mail/InviteMailTest.php
```

---

### Task 2: The invite itself

**Files:**
- Create: `app/Enums/InviteStatus.php`, `database/migrations/<timestamp>_create_invites_table.php`, `app/Models/Invite.php`, `database/factories/InviteFactory.php`
- Test: `tests/Unit/InviteStatusTest.php`, `tests/Feature/Models/InviteTest.php`

**Interfaces:**
- Consumes: `UserRole` from Spec A.
- Produces: `Invite::$status` (derived, an `InviteStatus`), `Invite::isUsable(): bool`, `Invite::scopeUsable()`, factory states `accepted()`, `revoked()`, `expired()`.

- [ ] **Step 1: Write the failing enum test**

Create `tests/Unit/InviteStatusTest.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\InviteStatus;
use App\Support\Contracts\HasColor;
use App\Support\Contracts\HasLabel;

it('has the four states an invite can be in', function (): void {
    expect(array_column(InviteStatus::cases(), 'value'))
        ->toEqualCanonicalizing(['pending', 'accepted', 'revoked', 'expired']);
});

it('presents every case', function (InviteStatus $status): void {
    expect($status)->toBeInstanceOf(HasLabel::class)
        ->and($status)->toBeInstanceOf(HasColor::class)
        ->and($status->getLabel())->not->toBe('')
        ->and($status->getColor())->not->toBe('');
})->with(fn (): array => array_map(fn (InviteStatus $s): array => [$s], InviteStatus::cases()));

it('colours only pending as something a reader should act on', function (): void {
    expect(InviteStatus::Pending->getColor())->toBe('warning')
        ->and(InviteStatus::Accepted->getColor())->toBe('success')
        ->and(InviteStatus::Revoked->getColor())->toBe('gray')
        ->and(InviteStatus::Expired->getColor())->toBe('danger');
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --filter=InviteStatusTest`
Expected: FAIL — `Class "App\Enums\InviteStatus" not found`.

- [ ] **Step 3: Write the enum**

Create `app/Enums/InviteStatus.php`:

```php
<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Contracts\HasColor;
use App\Support\Contracts\HasLabel;

/**
 * What an invite currently is.
 *
 * Derived from three timestamps and never stored: a status column would be a
 * second source of truth that the passage of time can falsify.
 */
enum InviteStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Revoked = 'revoked';
    case Expired = 'expired';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Accepted => 'Accepted',
            self::Revoked => 'Revoked',
            self::Expired => 'Expired',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Accepted => 'success',
            self::Revoked => 'gray',
            self::Expired => 'danger',
        };
    }
}
```

- [ ] **Step 4: Run the enum test**

Run: `php artisan test --filter=InviteStatusTest`
Expected: PASS.

- [ ] **Step 5: Write the failing model test**

Create `tests/Feature/Models/InviteTest.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\InviteStatus;
use App\Enums\UserRole;
use App\Models\Invite;

it('is pending when nothing has happened to it', function (): void {
    expect(Invite::factory()->create()->status)->toBe(InviteStatus::Pending);
});

it('is accepted once accepted', function (): void {
    expect(Invite::factory()->accepted()->create()->status)->toBe(InviteStatus::Accepted);
});

it('is revoked once revoked', function (): void {
    expect(Invite::factory()->revoked()->create()->status)->toBe(InviteStatus::Revoked);
});

it('is expired once its moment has passed', function (): void {
    expect(Invite::factory()->expired()->create()->status)->toBe(InviteStatus::Expired);
});

/**
 * Acceptance outranks expiry. An invite that was accepted and then sat around
 * until its window closed is still accepted — reporting it as expired would
 * suggest it can be reissued, and it cannot.
 */
it('reports an accepted invite as accepted even after its window closes', function (): void {
    $invite = Invite::factory()->accepted()->expired()->create();

    expect($invite->status)->toBe(InviteStatus::Accepted);
});

it('reports a revoked invite as revoked even after its window closes', function (): void {
    expect(Invite::factory()->revoked()->expired()->create()->status)->toBe(InviteStatus::Revoked);
});

it('is usable only while pending', function (): void {
    expect(Invite::factory()->create()->isUsable())->toBeTrue()
        ->and(Invite::factory()->accepted()->create()->isUsable())->toBeFalse()
        ->and(Invite::factory()->revoked()->create()->isUsable())->toBeFalse()
        ->and(Invite::factory()->expired()->create()->isUsable())->toBeFalse();
});

it('finds only usable invites through the scope', function (): void {
    $pending = Invite::factory()->create();
    Invite::factory()->accepted()->create();
    Invite::factory()->revoked()->create();
    Invite::factory()->expired()->create();

    expect(Invite::query()->usable()->pluck('id')->all())->toBe([$pending->id]);
});

it('casts the role to the enum', function (): void {
    $invite = Invite::factory()->create(['role' => UserRole::Admin->value]);

    expect($invite->role)->toBe(UserRole::Admin);
});

it('never stores the plaintext token', function (): void {
    $invite = Invite::factory()->create();

    expect($invite->token_hash)->toHaveLength(64)
        ->and($invite->getAttributes())->not->toHaveKey('token');
});
```

- [ ] **Step 6: Run it and watch it fail**

Run: `php artisan test --filter="Models/InviteTest"`
Expected: FAIL — no `invites` table.

- [ ] **Step 7: Write the migration**

```bash
php artisan make:migration create_invites_table --no-interaction
```

Fill it in:

```php
Schema::create('invites', function (Blueprint $table): void {
    $table->id();
    $table->string('email');
    // A plain string cast to App\Enums\UserRole, matching users.role: adding a
    // role stays a one-line change with no migration.
    $table->string('role')->default('member');
    // Only the hash. The plaintext lives in the URL, the mail and the panel's
    // copy field, so a leaked database yields no working links.
    $table->string('token_hash', 64)->unique();
    $table->timestamp('expires_at');
    $table->timestamp('accepted_at')->nullable();
    $table->timestamp('revoked_at')->nullable();
    $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();

    $table->index('email');
});
```

**There is deliberately no partial unique index on `email`.** SQLite supports
one and MySQL does not; development runs MySQL and CI runs SQLite, so an index
present on one and absent on the other would mean the rule is tested against a
database nobody uses. Task 3 enforces it in `CreateInvite` instead.

- [ ] **Step 8: Write the model**

Create `app/Models/Invite.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InviteStatus;
use App\Enums\UserRole;
use Database\Factories\InviteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A pending invitation to join the panel.
 *
 * @property UserRole $role
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $revoked_at
 * @property-read InviteStatus $status
 */
final class Invite extends Model
{
    /** @use HasFactory<InviteFactory> */
    use HasFactory;

    /**
     * Usable means: issued, not spent, not withdrawn, not stale. Every refusal
     * in this feature reduces to the negation of this one method.
     */
    public function isUsable(): bool
    {
        return $this->accepted_at === null
            && $this->revoked_at === null
            && $this->expires_at->isFuture();
    }

    /**
     * Acceptance and revocation outrank expiry: an invite that was spent and
     * then sat past its window is spent, not stale.
     */
    public function getStatusAttribute(): InviteStatus
    {
        return match (true) {
            $this->accepted_at !== null => InviteStatus::Accepted,
            $this->revoked_at !== null => InviteStatus::Revoked,
            $this->expires_at->isPast() => InviteStatus::Expired,
            default => InviteStatus::Pending,
        };
    }

    /**
     * @param  Builder<Invite>  $query
     * @return Builder<Invite>
     */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now());
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
```

**Note:** `status` is an accessor, so it is available as `$invite->status` but
is not an attribute — `getAttributes()` will not contain it, and neither will
a `select` that names columns.

- [ ] **Step 9: Write the factory**

```bash
php artisan make:factory InviteFactory --model=Invite --no-interaction
```

```php
public function definition(): array
{
    return [
        'email' => fake()->unique()->safeEmail(),
        'role' => UserRole::Member->value,
        'token_hash' => hash('sha256', Str::random(64)),
        'expires_at' => now()->addHours(48),
        'accepted_at' => null,
        'revoked_at' => null,
        'invited_by' => null,
    ];
}

public function accepted(): static
{
    return $this->state(fn (array $attributes): array => ['accepted_at' => now()]);
}

public function revoked(): static
{
    return $this->state(fn (array $attributes): array => ['revoked_at' => now()]);
}

/** Past its window, whatever else is true of it. */
public function expired(): static
{
    return $this->state(fn (array $attributes): array => ['expires_at' => now()->subMinute()]);
}
```

- [ ] **Step 10: Run the model tests**

Run: `php artisan migrate --no-interaction && php artisan test --filter="Models/InviteTest"`
Expected: PASS, 10 tests.

- [ ] **Step 11: Run the whole suite**

Run: `php artisan test`
Expected: PASS. `FactoriesTest` walks every factory, so `InviteFactory` is
covered the moment it exists.

- [ ] **Step 12: Commit**

```bash
composer lint && composer types:check && composer type-coverage
git add app/Enums/InviteStatus.php app/Models/Invite.php database/migrations database/factories/InviteFactory.php tests/
git commit -m "feat: add the invite model" -- app/Enums/InviteStatus.php app/Models/Invite.php database/migrations database/factories/InviteFactory.php tests/
```

---

### Task 3: Creating and sending an invite

**Files:**
- Create: `app/Actions/People/CreateInvite.php`, `app/Mail/InviteMail.php`, `resources/views/mail/invite.blade.php`
- Modify: `tests/Feature/Mail/InviteMailTest.php`
- Test: `tests/Feature/Actions/CreateInviteTest.php`

**Interfaces:**
- Consumes: `Invite`, `UserRole`.
- Produces: `CreateInvite::handle(string $email, UserRole $role, ?User $invitedBy = null): array{invite: Invite, token: string}`.

**Why the action returns the token.** It exists exactly once, in memory, at
creation. The caller needs it for the panel's copy field and the mail needs it
for the link; after this method returns, nothing can recover it.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Actions/CreateInviteTest.php`:

```php
<?php

declare(strict_types=1);

use App\Actions\People\CreateInvite;
use App\Enums\UserRole;
use App\Mail\InviteMail;
use App\Models\Invite;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    Mail::fake();
    config(['app.admin_email' => 'owner@example.test']);
    $this->owner = User::factory()->admin()->create(['email' => 'owner@example.test']);
});

it('issues an invite with a token that is not what it stores', function (): void {
    ['invite' => $invite, 'token' => $token] = app(CreateInvite::class)
        ->handle('her@example.test', UserRole::Member, $this->owner);

    expect($token)->toHaveLength(64)
        ->and($invite->token_hash)->toBe(hash('sha256', $token))
        ->and($invite->token_hash)->not->toBe($token);
});

it('sets the window to 48 hours', function (): void {
    $this->freezeTime();

    ['invite' => $invite] = app(CreateInvite::class)
        ->handle('her@example.test', UserRole::Member, $this->owner);

    expect($invite->expires_at->equalTo(now()->addHours(48)))->toBeTrue();
});

it('records who sent it and what it grants', function (): void {
    ['invite' => $invite] = app(CreateInvite::class)
        ->handle('her@example.test', UserRole::Admin, $this->owner);

    expect($invite->role)->toBe(UserRole::Admin)
        ->and($invite->invited_by)->toBe($this->owner->id);
});

it('sends exactly one mail, to the invited address', function (): void {
    app(CreateInvite::class)->handle('her@example.test', UserRole::Member, $this->owner);

    Mail::assertSentCount(1);
    Mail::assertSent(InviteMail::class, fn (InviteMail $mail): bool => $mail->hasTo('her@example.test'));
});

/**
 * The mail must carry the usable secret and the database must not. If these two
 * ever hold the same string, the hashing has been undone by a refactor.
 */
it('puts the plaintext token in the mail and the hash in the database', function (): void {
    ['invite' => $invite, 'token' => $token] = app(CreateInvite::class)
        ->handle('her@example.test', UserRole::Member, $this->owner);

    Mail::assertSent(InviteMail::class, function (InviteMail $mail) use ($token, $invite): bool {
        return str_contains($mail->url, $token)
            && ! str_contains($mail->url, $invite->token_hash);
    });
});

/**
 * Re-inviting is a normal thing to do when the first link went stale. It must
 * not error, and the old link must stop working.
 */
it('supersedes a live invite rather than refusing', function (): void {
    ['invite' => $first] = app(CreateInvite::class)
        ->handle('her@example.test', UserRole::Member, $this->owner);

    ['invite' => $second] = app(CreateInvite::class)
        ->handle('her@example.test', UserRole::Member, $this->owner);

    expect($first->fresh()->isUsable())->toBeFalse()
        ->and($second->isUsable())->toBeTrue()
        ->and(Invite::query()->usable()->count())->toBe(1);
});

it('leaves an accepted invite alone when issuing a new one', function (): void {
    $accepted = Invite::factory()->accepted()->create(['email' => 'her@example.test']);

    app(CreateInvite::class)->handle('her@example.test', UserRole::Member, $this->owner);

    expect($accepted->fresh()->revoked_at)->toBeNull();
});

it('refuses an address that already has an account', function (): void {
    User::factory()->create(['email' => 'taken@example.test']);

    expect(fn (): array => app(CreateInvite::class)
        ->handle('taken@example.test', UserRole::Member, $this->owner))
        ->toThrow(ValidationException::class);

    expect(Invite::query()->count())->toBe(0);
    Mail::assertNothingSent();
});

it('matches an existing account case-insensitively', function (): void {
    User::factory()->create(['email' => 'taken@example.test']);

    expect(fn (): array => app(CreateInvite::class)
        ->handle('TAKEN@example.test', UserRole::Member, $this->owner))
        ->toThrow(ValidationException::class);
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --filter=CreateInviteTest`
Expected: FAIL — `Target class [App\Actions\People\CreateInvite] does not exist.`

- [ ] **Step 3: Write the mailable**

```bash
php artisan make:mail InviteMail --no-interaction
```

Replace its body:

```php
<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Override;

/**
 * The only message this application sends.
 *
 * The plaintext token is passed in rather than read from the invite, because
 * the invite does not have it — it stores a hash.
 */
final class InviteMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $url,
        public readonly string $invitedByName,
    ) {}

    #[Override]
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'You have been invited to '.config('app.name'));
    }

    #[Override]
    public function content(): Content
    {
        return new Content(markdown: 'mail.invite');
    }
}
```

- [ ] **Step 4: Write the mail body**

Create `resources/views/mail/invite.blade.php`:

```blade
<x-mail::message>
# You have been invited

{{ $invitedByName }} has invited you to {{ config('app.name') }}.

The link below works once and expires in 48 hours.

<x-mail::button :url="$url">
Accept the invitation
</x-mail::button>

If you were not expecting this, ignore it — nothing happens until the link is opened.
</x-mail::message>
```

If `x-mail::message` is not available, publish the vendor views first with
`php artisan vendor:publish --tag=laravel-mail`.

- [ ] **Step 5: Write the action**

Create `app/Actions/People/CreateInvite.php`:

```php
<?php

declare(strict_types=1);

namespace App\Actions\People;

use App\Enums\UserRole;
use App\Mail\InviteMail;
use App\Models\Invite;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Issue an invitation and send it.
 *
 * At most one invite per address may be usable at a time. That rule lives here
 * rather than in a database index because the index that would express it is a
 * partial unique index, which SQLite supports and MySQL does not — and this
 * application develops on MySQL while its CI runs SQLite.
 */
final class CreateInvite
{
    /**
     * @return array{invite: Invite, token: string}
     *
     * @throws ValidationException
     */
    public function handle(string $email, UserRole $role, ?User $invitedBy = null): array
    {
        $email = mb_strtolower(trim($email));

        if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages([
                'email' => 'That address already has an account.',
            ]);
        }

        // Exists only here and in what this method returns. After that, the row
        // holds a hash and the plaintext is unrecoverable.
        $token = Str::random(64);

        $invite = DB::transaction(function () use ($email, $role, $invitedBy, $token): Invite {
            Invite::query()
                ->where('email', $email)
                ->usable()
                ->update(['revoked_at' => now()]);

            return Invite::create([
                'email' => $email,
                'role' => $role->value,
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addHours(48),
                'invited_by' => $invitedBy?->getKey(),
            ]);
        });

        Mail::to($email)->send(new InviteMail(
            url: route('invite.show', $token),
            invitedByName: $invitedBy?->name ?? config('app.name'),
        ));

        return ['invite' => $invite, 'token' => $token];
    }
}
```

**The mail is sent outside the transaction on purpose.** A transport failure
must not roll back an invite whose link the panel is about to display — the
copyable link is the fallback for exactly that case.

**On the bulk update.** `tests/Feature/Actions/Resources/BulkWriteSafetyTest.php`
demonstrates that a query-builder write fires no model events, which is why
`BulkDeleteRecords` loops. It is a demonstration, not a scanner — it does not
fail on new bulk writes elsewhere. `Invite` has no events and no pivot to
orphan, so the single `update()` here is correct and a loop would only be
slower.

- [ ] **Step 6: Add the route name the action needs**

`route('invite.show', $token)` does not exist yet — Task 4 defines it. To keep
this task's tests runnable, add the two routes to `routes/web.php` now, pointing
at a controller Task 4 fills in:

```php
Route::get('invite/{token}', [InviteController::class, 'show'])->name('invite.show');
Route::post('invite/{token}', [InviteController::class, 'store'])->name('invite.store');
```

and create `app/Http/Controllers/InviteController.php` with both methods
throwing `abort(404)` for now. Task 4 replaces the bodies and adds the
throttle.

- [ ] **Step 7: Run the tests**

Run: `php artisan test --filter=CreateInviteTest`
Expected: PASS, 8 tests.

- [ ] **Step 8: Run the whole suite**

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 9: Commit**

```bash
composer lint && composer types:check
git add app/Actions/People app/Mail resources/views/mail app/Http/Controllers/InviteController.php routes/web.php tests/
git commit -m "feat: issue and send invitations" -- app/Actions/People app/Mail resources/views/mail app/Http/Controllers/InviteController.php routes/web.php tests/
```

---

### Task 4: Accepting an invite

**Files:**
- Create: `app/Actions/People/AcceptInvite.php`, `app/Http/Requests/AcceptInviteRequest.php`, `resources/js/pages/Auth/AcceptInvite.vue`
- Modify: `app/Http/Controllers/InviteController.php`, `routes/web.php`, `app/Providers/FortifyServiceProvider.php`
- Test: `tests/Feature/Auth/InviteTest.php`

**Interfaces:**
- Consumes: `Invite`, `CreateInvite`.
- Produces: `AcceptInvite::handle(Invite $invite, string $name, string $password): User`, routes `invite.show` and `invite.store`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Auth/InviteTest.php`:

```php
<?php

declare(strict_types=1);

use App\Actions\People\CreateInvite;
use App\Enums\Area;
use App\Enums\UserRole;
use App\Models\Invite;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    Mail::fake();
    config(['app.admin_email' => 'owner@example.test']);
    $this->owner = User::factory()->admin()->create(['email' => 'owner@example.test']);
});

/** @return array{Invite, string} */
function issueInvite(UserRole $role = UserRole::Member, string $email = 'her@example.test'): array
{
    $result = app(CreateInvite::class)->handle($email, $role, test()->owner);

    return [$result['invite'], $result['token']];
}

it('shows the form for a usable token', function (): void {
    [, $token] = issueInvite();

    $this->get(route('invite.show', $token))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Auth/AcceptInvite')
            ->where('email', 'her@example.test'));
});

it('creates the account and signs her in', function (): void {
    [$invite, $token] = issueInvite();

    $this->post(route('invite.store', $token), [
        'name' => 'Her Name',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ])->assertRedirect(route('admin.profile'));

    $user = User::query()->where('email', 'her@example.test')->sole();

    expect($user->name)->toBe('Her Name')
        ->and($user->role)->toBe(UserRole::Member)
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($invite->fresh()->accepted_at)->not->toBeNull()
        ->and(auth()->id())->toBe($user->id);
});

it('regenerates the session so the pre-login id cannot be reused', function (): void {
    [, $token] = issueInvite();

    $this->get(route('invite.show', $token));
    $before = session()->getId();

    $this->post(route('invite.store', $token), [
        'name' => 'Her Name',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ]);

    expect(session()->getId())->not->toBe($before);
});

it('gives her exactly the areas the invite named', function (): void {
    [, $token] = issueInvite();

    $this->post(route('invite.store', $token), [
        'name' => 'Her Name',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ]);

    $user = User::query()->where('email', 'her@example.test')->sole();

    foreach (Area::cases() as $area) {
        expect($user->canAccess($area))->toBe(
            in_array($area, UserRole::Member->areas(), true),
            "{$area->value} did not match the invited role",
        );
    }
});

it('creates an admin when the invite says so', function (): void {
    [, $token] = issueInvite(UserRole::Admin, 'them@example.test');

    $this->post(route('invite.store', $token), [
        'name' => 'Them',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ]);

    expect(User::query()->where('email', 'them@example.test')->sole()->role)
        ->toBe(UserRole::Admin);
});

it('ignores an email submitted with the form', function (): void {
    [, $token] = issueInvite();

    $this->post(route('invite.store', $token), [
        'name' => 'Her Name',
        'email' => 'someone-else@example.test',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ]);

    expect(User::query()->where('email', 'someone-else@example.test')->exists())->toBeFalse()
        ->and(User::query()->where('email', 'her@example.test')->exists())->toBeTrue();
});

it('refuses every kind of unusable token', function (string $case): void {
    [$invite, $token] = issueInvite();

    $token = match ($case) {
        'expired' => tap($token, fn () => $invite->update(['expires_at' => now()->subMinute()])),
        'revoked' => tap($token, fn () => $invite->update(['revoked_at' => now()])),
        'accepted' => tap($token, fn () => $invite->update(['accepted_at' => now()])),
        'tampered' => mb_substr($token, 0, 63).($token[63] === 'a' ? 'b' : 'a'),
        'unknown' => str_repeat('z', 64),
    };

    $this->get(route('invite.show', $token))->assertNotFound();

    $this->post(route('invite.store', $token), [
        'name' => 'Her Name',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ])->assertNotFound();

    expect(User::query()->where('email', 'her@example.test')->exists())->toBeFalse();
})->with(['expired', 'revoked', 'accepted', 'tampered', 'unknown']);

it('cannot be spent twice', function (): void {
    [, $token] = issueInvite();

    $payload = [
        'name' => 'Her Name',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ];

    $this->post(route('invite.store', $token), $payload);

    auth()->logout();

    $this->post(route('invite.store', $token), $payload)->assertNotFound();

    expect(User::query()->where('email', 'her@example.test')->count())->toBe(1);
});

/**
 * The concurrency case, as close as a single test process can get to it.
 *
 * Two simultaneous accepts are prevented by the row lock inside AcceptInvite,
 * and a lock cannot be observed from here. What can be observed is the re-check
 * the lock exists to protect: an invite that was spent after this request
 * loaded it must not produce a second account.
 */
it('refuses an invite that was spent between loading and accepting', function (): void {
    [$invite, ] = issueInvite();

    $loaded = App\Models\Invite::query()->findOrFail($invite->getKey());

    App\Models\Invite::query()->whereKey($invite->getKey())->update(['accepted_at' => now()]);

    expect(fn (): App\Models\User => app(App\Actions\People\AcceptInvite::class)
        ->handle($loaded, 'Her Name', 'correct-horse-battery-staple'))
        ->toThrow(RuntimeException::class);

    expect(User::query()->where('email', 'her@example.test')->exists())->toBeFalse();
});

it('refuses a token whose address gained an account in the meantime', function (): void {
    [, $token] = issueInvite();

    User::factory()->create(['email' => 'her@example.test']);

    $this->post(route('invite.store', $token), [
        'name' => 'Her Name',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ])->assertNotFound();

    expect(User::query()->where('email', 'her@example.test')->count())->toBe(1);
});

it('sends a signed-in visitor to the dashboard instead of the form', function (): void {
    [, $token] = issueInvite();

    $this->actingAs($this->owner)
        ->get(route('invite.show', $token))
        ->assertRedirect(route('admin.dashboard'));
});

it('requires a name and a confirmed password', function (array $payload, string $field): void {
    [, $token] = issueInvite();

    $this->from(route('invite.show', $token))
        ->post(route('invite.store', $token), $payload)
        ->assertSessionHasErrors($field);

    expect(User::query()->where('email', 'her@example.test')->exists())->toBeFalse();
})->with([
    'no name' => [['password' => 'correct-horse-battery-staple', 'password_confirmation' => 'correct-horse-battery-staple'], 'name'],
    'no password' => [['name' => 'Her'], 'password'],
    'unconfirmed' => [['name' => 'Her', 'password' => 'correct-horse-battery-staple', 'password_confirmation' => 'different'], 'password'],
    'too short' => [['name' => 'Her', 'password' => 'short', 'password_confirmation' => 'short'], 'password'],
]);

it('throttles attempts', function (): void {
    [, $token] = issueInvite();

    foreach (range(1, 6) as $ignored) {
        $this->get(route('invite.show', $token));
    }

    $this->get(route('invite.show', $token))->assertStatus(429);
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --filter="Auth/InviteTest"`
Expected: FAIL — every case 404s, because the controller still aborts.

- [ ] **Step 3: Write the request**

Create `app/Http/Requests/AcceptInviteRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\Fortify\PasswordValidationRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Not an AdminRequest: this is the one form in the application filled in by
 * somebody who does not have an account yet.
 */
final class AcceptInviteRequest extends FormRequest
{
    use PasswordValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'password' => $this->passwordRules(),
        ];
    }
}
```

`email` is absent from the rules on purpose. The invite decides the address, and
a submitted one is never read.

- [ ] **Step 4: Write the action**

Create `app/Actions/People/AcceptInvite.php`:

```php
<?php

declare(strict_types=1);

namespace App\Actions\People;

use App\Models\Invite;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

final class AcceptInvite
{
    /**
     * Spend an invite and create the account it was for.
     *
     * The invite is re-read under a row lock rather than trusted as passed: two
     * requests arriving together must produce one user, and the lock is what
     * makes that true rather than merely likely. The unique index on
     * users.email is the backstop if the lock is ever lost to a refactor.
     */
    public function handle(Invite $invite, string $name, string $password): User
    {
        return DB::transaction(function () use ($invite, $name, $password): User {
            $locked = Invite::query()->lockForUpdate()->find($invite->getKey());

            throw_if(! $locked instanceof Invite || ! $locked->isUsable(), RuntimeException::class, 'This invitation is no longer usable.');

            throw_if(
                User::query()->whereRaw('LOWER(email) = ?', [$locked->email])->exists(),
                RuntimeException::class,
                'That address already has an account.',
            );

            $user = User::create([
                'name' => $name,
                'email' => $locked->email,
                'password' => Hash::make($password),
                'role' => $locked->role->value,
                // Opening a link sent to an address proves control of it at
                // least as well as a second mail would.
                'email_verified_at' => now(),
            ]);

            $locked->update(['accepted_at' => now()]);

            return $user;
        });
    }
}
```

- [ ] **Step 5: Write the controller**

Replace `app/Http/Controllers/InviteController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\People\AcceptInvite;
use App\Contracts\NotifiesAdmin;
use App\Http\Requests\AcceptInviteRequest;
use App\Models\Invite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * The one public flow in this application that creates an account.
 *
 * Every refusal is a 404: expired, revoked, already spent, tampered with and
 * never issued are indistinguishable from outside, exactly as a hidden area is.
 */
final class InviteController extends Controller
{
    public function __construct(private readonly NotifiesAdmin $notifier) {}

    public function show(Request $request, string $token): Response|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect()->route('admin.dashboard');
        }

        $invite = $this->usableInvite($token);

        return Inertia::render('Auth/AcceptInvite', [
            'email' => $invite->email,
            'token' => $token,
        ]);
    }

    public function store(AcceptInviteRequest $request, string $token, AcceptInvite $acceptInvite): RedirectResponse
    {
        $invite = $this->usableInvite($token);

        /** @var array{name: string, password: string} $data */
        $data = $request->validated();

        try {
            $user = $acceptInvite->handle($invite, $data['name'], $data['password']);
        } catch (RuntimeException) {
            abort(404);
        }

        auth()->login($user);
        $request->session()->regenerate();

        $this->notifier->success('Welcome', 'Add a passkey so you can sign in without a password.');

        return redirect()->route('admin.profile');
    }

    private function usableInvite(string $token): Invite
    {
        $invite = Invite::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        abort_if(! $invite instanceof Invite || ! $invite->isUsable(), 404);

        return $invite;
    }
}
```

**On `hash_equals`:** the lookup is by the hash of the supplied token, so the
database compares two 64-character hex strings and the secret is never compared
against a stored plaintext. There is no plaintext to compare against, which is
the stronger form of the same protection.

- [ ] **Step 6: Throttle the routes**

In `routes/web.php`, replace the two routes added in Task 3:

```php
Route::middleware('throttle:invite')->group(function (): void {
    Route::get('invite/{token}', [InviteController::class, 'show'])->name('invite.show');
    Route::post('invite/{token}', [InviteController::class, 'store'])->name('invite.store');
});
```

and in `FortifyServiceProvider::boot()`, beside the two limiters already there:

```php
RateLimiter::for('invite', fn (Request $request): Limit => Limit::perMinute(6)->by($request->ip() ?? 'unknown'));
```

- [ ] **Step 7: Write the page**

Create `resources/js/pages/Auth/AcceptInvite.vue`, modelled on
`resources/js/pages/Auth/Login.vue` — same `Mark`, same card, same PrimeVue
imports:

```vue
<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Password from 'primevue/password'
import Mark from '@/Components/Brand/Mark.vue'

const props = defineProps({
  email: { type: String, required: true },
  token: { type: String, required: true },
})

const form = useForm({
  name: '',
  password: '',
  password_confirmation: '',
})

function submit() {
  form.post(route('invite.store', props.token), {
    onFinish: () => form.reset('password', 'password_confirmation'),
  })
}
</script>

<template>
  <Head title="Accept your invitation" />

  <div
    class="bg-surface-50 text-surface-900 dark:text-surface-100 flex min-h-screen items-center justify-center px-4 dark:bg-[#0D0E11]"
  >
    <div class="w-full max-w-sm">
      <div class="mb-8 flex items-center gap-2.5">
        <Mark :size="30" />
        <span class="font-mono text-sm font-semibold tracking-tight">OP//SHELL</span>
      </div>

      <form
        class="border-surface-200 bg-surface-0 flex flex-col gap-4 rounded-lg border p-6 dark:border-[#272B35] dark:bg-[#15171C]"
        @submit.prevent="submit"
      >
        <Message severity="info" class="mb-0">Setting up the account for {{ email }}</Message>

        <div class="flex flex-col gap-1.5">
          <label for="name" class="text-surface-500 font-mono text-[11px] font-semibold tracking-[0.06em] uppercase"
            >Name</label
          >
          <InputText id="name" v-model="form.name" autocomplete="name" autofocus />
          <small v-if="form.errors.name" class="text-red-500">{{ form.errors.name }}</small>
        </div>

        <div class="flex flex-col gap-1.5">
          <label for="password" class="text-surface-500 font-mono text-[11px] font-semibold tracking-[0.06em] uppercase"
            >Password</label
          >
          <Password id="password" v-model="form.password" toggle-mask :feedback="false" fluid />
          <small v-if="form.errors.password" class="text-red-500">{{ form.errors.password }}</small>
        </div>

        <div class="flex flex-col gap-1.5">
          <label
            for="password_confirmation"
            class="text-surface-500 font-mono text-[11px] font-semibold tracking-[0.06em] uppercase"
            >Confirm password</label
          >
          <Password
            id="password_confirmation"
            v-model="form.password_confirmation"
            toggle-mask
            :feedback="false"
            fluid
          />
        </div>

        <Button type="submit" label="Create my account" :loading="form.processing" />
      </form>
    </div>
  </div>
</template>
```

Check `Login.vue` for the exact class names and PrimeVue props in use and match
them; the markup above is the shape, not a licence to diverge from the
surrounding style.

- [ ] **Step 8: Run the tests**

Run: `php artisan test --filter="Auth/InviteTest"`
Expected: PASS.

The throttle case is the one most likely to fail first. `RefreshDatabase` does
not clear the rate limiter, so if an earlier test in the file has already spent
attempts, add `RateLimiter::clear()` in `beforeEach` — or assert the throttle in
a test file of its own.

- [ ] **Step 9: Build and look at it**

Run: `npm run build`
Then issue an invite in tinker and open the link in a private window.

- [ ] **Step 10: Run everything**

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 11: Commit**

```bash
composer lint && composer types:check
npm run lint:check && npm run format:check
git add app/ routes/web.php resources/js/pages/Auth/AcceptInvite.vue tests/
git commit -m "feat: accept an invitation and create the account" -- app/ routes/web.php resources/js/pages/Auth/AcceptInvite.vue tests/
```

---

### Task 5: Password reset

**Files:**
- Create: `resources/js/pages/Auth/ForgotPassword.vue`, `resources/js/pages/Auth/ResetPassword.vue`
- Modify: `config/fortify.php`, `app/Providers/FortifyServiceProvider.php`
- Test: `tests/Feature/Auth/PasswordResetTest.php`

**Interfaces:**
- Produces: routes `password.request`, `password.email`, `password.reset`, `password.update`.

**Why this is in the same release.** An emailed invite is only safe to depend on
if she can recover her own account. Without it, a forgotten password means the
owner editing the database by hand — the dependence this whole spec exists to
remove.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Auth/PasswordResetTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

beforeEach(function (): void {
    Notification::fake();
});

it('serves the forgot password page', function (): void {
    $this->get(route('password.request'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/ForgotPassword'));
});

it('serves the reset page with the token', function (): void {
    $this->get(route('password.reset', ['token' => 'a-token']).'?email=her@example.test')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/ResetPassword'));
});

it('sends a reset link to a known address', function (): void {
    $user = User::factory()->create(['email' => 'her@example.test']);

    $this->post(route('password.email'), ['email' => 'her@example.test']);

    Notification::assertSentTo($user, ResetPassword::class);
});

/**
 * The response must not reveal whether an address has an account. A different
 * status, a different redirect or a different session message would turn this
 * form into a way to enumerate the household.
 */
it('answers an unknown address exactly as it answers a known one', function (): void {
    User::factory()->create(['email' => 'known@example.test']);

    $known = $this->post(route('password.email'), ['email' => 'known@example.test']);
    $unknown = $this->post(route('password.email'), ['email' => 'nobody@example.test']);

    expect($unknown->status())->toBe($known->status())
        ->and($unknown->headers->get('Location'))->toBe($known->headers->get('Location'));

    Notification::assertCount(1);
});

it('resets the password and lets her sign in with it', function (): void {
    $user = User::factory()->create(['email' => 'her@example.test']);
    $token = Password::createToken($user);

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => 'her@example.test',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ])->assertRedirect(route('login'));

    auth()->logout();

    $this->post(route('login'), [
        'email' => 'her@example.test',
        'password' => 'correct-horse-battery-staple',
    ])->assertRedirect(route('admin.dashboard'));
});

it('refuses a tampered reset token', function (): void {
    User::factory()->create(['email' => 'her@example.test']);

    $this->from(route('password.reset', ['token' => 'nope']))
        ->post(route('password.update'), [
            'token' => 'nope',
            'email' => 'her@example.test',
            'password' => 'correct-horse-battery-staple',
            'password_confirmation' => 'correct-horse-battery-staple',
        ])
        ->assertSessionHasErrors('email');
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --filter=PasswordResetTest`
Expected: FAIL — `Route [password.request] not defined.`

- [ ] **Step 3: Enable the feature and rewrite the comment**

In `config/fortify.php`, the comment at lines 167–168 currently reads:

```php
// Registration and password reset are deliberately off: this is a
// single-user panel and the only account is provisioned by hand.
```

That stops being true here. Replace it and add the feature:

```php
'features' => [
    // Registration stays off: accounts come from invitations, which are
    // created inside the panel by someone who already has access.
    // Password reset is on, because an invited user must be able to recover
    // her own account without the owner touching the database.
    Features::resetPasswords(),
    Features::updateProfileInformation(),
    Features::updatePasswords(),
    // ... twoFactorAuthentication and passkeys unchanged
],
```

- [ ] **Step 4: Wire the two views**

In `app/Providers/FortifyServiceProvider::boot()`, beside the three
`Fortify::` view callbacks already there:

```php
Fortify::requestPasswordResetLinkView(fn (): InertiaResponse => Inertia::render('Auth/ForgotPassword', [
    'status' => session('status'),
]));

Fortify::resetPasswordView(fn (Request $request): InertiaResponse => Inertia::render('Auth/ResetPassword', [
    'token' => $request->route('token'),
    'email' => $request->string('email')->toString(),
]));
```

- [ ] **Step 5: Write the two pages**

Both follow `Auth/Login.vue`'s structure exactly — same wrapper, same `Mark`,
same card classes.

`resources/js/pages/Auth/ForgotPassword.vue`: one email field, posting to
`route('password.email')`, showing `status` in a `Message` when present, and a
link back to `route('login')`.

`resources/js/pages/Auth/ResetPassword.vue`: hidden `token`, a read-only email
field prefilled from the prop, `password` and `password_confirmation`, posting
to `route('password.update')`.

Add a "Forgot your password?" link to `Auth/Login.vue` pointing at
`route('password.request')`. Without it the feature exists but nobody can find
it.

- [ ] **Step 6: Run the tests**

Run: `php artisan test --filter=PasswordResetTest`
Expected: PASS.

- [ ] **Step 7: Run everything**

Run: `php artisan test`
Expected: PASS. The route count grows by four, and the Spec A access matrix
ignores them because they are not `admin.*`.

- [ ] **Step 8: Build and commit**

```bash
npm run build
composer lint && composer types:check
npm run lint:check && npm run format:check
git add config/fortify.php app/Providers/FortifyServiceProvider.php resources/js/pages/Auth tests/
git commit -m "feat: enable password reset" -- config/fortify.php app/Providers/FortifyServiceProvider.php resources/js/pages/Auth tests/
```

---

### Task 6: The People screen

**Files:**
- Create: `app/Tables/Definitions/InviteTable.php`, `app/Tables/Definitions/UserTable.php`, `app/Http/Controllers/Admin/General/PeopleController.php`, `app/Http/Requests/Admin/InviteRequest.php`, `resources/js/pages/General/People/Index.vue`
- Modify: `routes/admin.php`, `app/Support/Navigation.php`
- Test: `tests/Feature/Admin/General/PeopleTest.php`

**Interfaces:**
- Consumes: `CreateInvite`, `Invite`, `InviteTable`, `UserTable`.
- Produces: routes `admin.people.index`, `admin.people.store`, `admin.people.revoke`, `admin.people.resend`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/General/PeopleTest.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Invite;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    Mail::fake();
    config(['app.admin_email' => 'owner@example.test']);
    $this->owner = User::factory()->admin()->create(['email' => 'owner@example.test']);
    $this->member = User::factory()->member()->create(['email' => 'her@example.test']);
});

it('serves the page to the owner', function (): void {
    $this->actingAs($this->owner)
        ->get(route('admin.people.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('General/People/Index')
            ->has('users')
            ->has('invites'));
});

it('hides the page from a member entirely', function (): void {
    $this->actingAs($this->member)
        ->get(route('admin.people.index'))
        ->assertNotFound();
});

it('creates an invite and shows the link once', function (): void {
    $this->actingAs($this->owner)
        ->post(route('admin.people.store'), [
            'email' => 'new@example.test',
            'role' => UserRole::Member->value,
        ])
        ->assertRedirect(route('admin.people.index'));

    $invite = Invite::query()->where('email', 'new@example.test')->sole();

    expect($invite->role)->toBe(UserRole::Member)
        ->and(session('flash.invite_url'))->toContain('/invite/');

    Mail::assertSentCount(1);
});

it('refuses an invite from a member', function (): void {
    $this->actingAs($this->member)
        ->post(route('admin.people.store'), [
            'email' => 'new@example.test',
            'role' => UserRole::Member->value,
        ])
        ->assertNotFound();

    expect(Invite::query()->count())->toBe(0);
});

it('validates the invite form', function (array $payload, string $field): void {
    $this->actingAs($this->owner)
        ->from(route('admin.people.index'))
        ->post(route('admin.people.store'), $payload)
        ->assertSessionHasErrors($field);
})->with([
    'no email' => [['role' => 'member'], 'email'],
    'not an email' => [['email' => 'nope', 'role' => 'member'], 'email'],
    'unknown role' => [['email' => 'a@example.test', 'role' => 'wizard'], 'role'],
    'existing account' => [['email' => 'her@example.test', 'role' => 'member'], 'email'],
]);

it('revokes an invite', function (): void {
    $invite = Invite::factory()->create();

    $this->actingAs($this->owner)
        ->delete(route('admin.people.revoke', $invite))
        ->assertRedirect(route('admin.people.index'));

    expect($invite->fresh()->revoked_at)->not->toBeNull();
    Mail::assertNothingSent();
});

it('refuses to revoke from a member', function (): void {
    $invite = Invite::factory()->create();

    $this->actingAs($this->member)
        ->delete(route('admin.people.revoke', $invite))
        ->assertNotFound();

    expect($invite->fresh()->revoked_at)->toBeNull();
});

/**
 * Reissue, not resend.
 *
 * CreateInvite is the only place a token is generated and the plaintext is gone
 * the moment it returns, so nothing can re-send the original link — it no
 * longer exists anywhere but in the recipient's inbox. The button therefore
 * issues a new invite and supersedes the old one, and says so.
 */
it('reissues rather than resending, because the token cannot be recovered', function (): void {
    $invite = Invite::factory()->create(['email' => 'new@example.test']);
    $hash = $invite->token_hash;

    $this->actingAs($this->owner)
        ->post(route('admin.people.resend', $invite))
        ->assertRedirect(route('admin.people.index'));

    expect($invite->fresh()->revoked_at)->not->toBeNull('the old invite is superseded');

    $fresh = Invite::query()->where('email', 'new@example.test')->usable()->sole();

    expect($fresh->token_hash)->not->toBe($hash);
    Mail::assertSentCount(1);
});

it('refuses to reissue an invite that is no longer usable', function (): void {
    $invite = Invite::factory()->revoked()->create();

    $this->actingAs($this->owner)
        ->post(route('admin.people.resend', $invite))
        ->assertNotFound();

    Mail::assertNothingSent();
});
```

**The button is labelled Reissue, not Resend,** and its confirmation says the
previous link stops working. Naming it Resend would promise something the
storage model makes impossible.

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --filter=PeopleTest`
Expected: FAIL — `Route [admin.people.index] not defined.`

- [ ] **Step 3: Write the request**

Create `app/Http/Requests/Admin/InviteRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Area;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Validation\Rule;
use Override;

final class InviteRequest extends AdminRequest
{
    #[Override]
    protected function area(): Area
    {
        return Area::General;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'role' => ['required', Rule::enum(UserRole::class)],
        ];
    }
}
```

- [ ] **Step 4: Write the two tables**

`app/Tables/Definitions/UserTable.php`:

```php
final class UserTable extends ResourceTable
{
    #[Override]
    protected string $model = User::class;

    /**
     * @return array<int, Column>
     */
    protected function columns(): array
    {
        return [
            Column::text('name')->sortable(),
            Column::text('email')->sortable(),
            Column::badge('role')->state(fn (User $record): string => $record->role->name),
            Column::datetime('created_at')->label('Joined')->sortable(),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function searchable(): array
    {
        return ['name', 'email'];
    }
}
```

`app/Tables/Definitions/InviteTable.php`:

```php
final class InviteTable extends ResourceTable
{
    #[Override]
    protected string $model = Invite::class;

    #[Override]
    protected array $with = ['invitedBy'];

    /**
     * @return array<int, Column>
     */
    protected function columns(): array
    {
        return [
            Column::text('email')->sortable(),
            Column::badge('role')->state(fn (Invite $record): string => $record->role->name),
            // The badge reads its colour from HasColor on InviteStatus.
            Column::badge('status')->state(fn (Invite $record): string => $record->status->getLabel())
                ->color(fn (Invite $record): string => $record->status->getColor()),
            Column::datetime('expires_at')->label('Expires')->sortable(),
            Column::text('invitedBy.name')->label('Sent by')->default('—'),
            Column::datetime('created_at')->label('Sent')->sortable()->toggleable(hiddenByDefault: true),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function searchable(): array
    {
        return ['email'];
    }
}
```

**`status` is an accessor, not a column.** Sorting or filtering by it would
generate SQL against a column that does not exist, so it is neither
`->sortable()` nor offered as a filter.

`Column::color()` takes `string|Closure`, and `Column::resolveVariant()` at
`app/Tables/Column.php:390` calls the closure with the **record** and keeps the
result only if it is a string. The closure above matches that exactly. The
automatic path — a raw value that implements `HasColor` colours itself — does
not apply here, because `state()` has already turned the status into a label
string by the time the badge is resolved.

- [ ] **Step 5: Write the controller**

Create `app/Http/Controllers/Admin/General/PeopleController.php` with four
methods, following `WakaTimeSummaryController`'s shape for `index` (schema plus
a lazy `rows` closure per table) and the resource controllers' shape for the
writes. `store` calls `CreateInvite` and flashes the URL:

```php
public function store(InviteRequest $request, CreateInvite $createInvite): RedirectResponse
{
    /** @var array{email: string, role: string} $data */
    $data = $request->validated();

    ['token' => $token] = $createInvite->handle(
        $data['email'],
        UserRole::from($data['role']),
        $request->user(),
    );

    $this->notifier->success('Invitation sent', 'The link is on screen until you leave this page.');

    // The only moment this URL can be shown. It is flashed rather than
    // returned as a prop so a refresh does not put it back on screen.
    return redirect()
        ->route('admin.people.index')
        ->with('flash.invite_url', route('invite.show', $token));
}

public function revoke(Invite $invite): RedirectResponse
{
    abort_unless($invite->isUsable(), 404);

    $invite->update(['revoked_at' => now()]);

    $this->notifier->success('Invitation revoked', 'That link no longer works.');

    return redirect()->route('admin.people.index');
}

public function resend(Invite $invite, CreateInvite $createInvite, Request $request): RedirectResponse
{
    abort_unless($invite->isUsable(), 404);

    ['token' => $token] = $createInvite->handle($invite->email, $invite->role, $request->user());

    $this->notifier->success('Invitation reissued', 'The previous link has stopped working.');

    return redirect()
        ->route('admin.people.index')
        ->with('flash.invite_url', route('invite.show', $token));
}
```

`CreateInvite` already supersedes the live invite, so `resend` needs no extra
revocation of its own.

- [ ] **Step 6: Add the routes**

In `routes/admin.php`, inside the `Area::General` group, after the settings
routes:

```php
Route::get('people', [PeopleController::class, 'index'])->name('people.index');
Route::post('people', [PeopleController::class, 'store'])->name('people.store');
Route::post('people/{invite}/resend', [PeopleController::class, 'resend'])->name('people.resend');
Route::delete('people/{invite}', [PeopleController::class, 'revoke'])->name('people.revoke');
```

The Spec A access matrix is generated from the router, so these are covered the
moment they exist. Its `parameterValue()` resolver needs an `'invite'` arm —
add `'invite' => Invite::factory()->create(),` to
`tests/Feature/Auth/AreaAccessMatrixTest.php`, or every People case will bind
to id `1` and 404 for reasons unrelated to areas.

- [ ] **Step 7: Add the navigation item**

In `app/Support/Navigation.php`, in the General cluster:

```php
['label' => 'People', 'route' => 'admin.people.index', 'icon' => 'pi pi-users'],
```

`LeakSurfaceTest` walks every navigation item and opens it as the member, so
this is covered without a new test.

- [ ] **Step 8: Write the page**

Create `resources/js/pages/General/People/Index.vue`: two `ResourceTable`
instances stacked, an "Invite someone" button opening a PrimeVue `Dialog` with
an email field and a role `Select`, and — when `flash.invite_url` is present —
a `Message` holding the URL with a copy button.

Find the copy-to-clipboard helper the panel already uses; if none exists,
`navigator.clipboard.writeText` behind a small `Button` is enough.

- [ ] **Step 9: Run the tests**

Run: `php artisan test --filter=PeopleTest`
Expected: PASS.

- [ ] **Step 10: Run everything**

Run: `php artisan test`
Expected: PASS, including the access matrix and `LeakSurfaceTest`.

- [ ] **Step 11: Build and commit**

```bash
npm run build
composer lint && composer types:check
npm run lint:check && npm run format:check
git add app/ routes/admin.php resources/js/pages/General tests/
git commit -m "feat: add the people screen" -- app/ routes/admin.php resources/js/pages/General tests/
```

---

### Task 7: Close the loaded gun

**Files:**
- Modify: `app/Actions/Fortify/CreateNewUser.php`
- Test: `tests/Feature/Actions/CreateNewUserTest.php`

**Interfaces:**
- Produces: `CreateNewUser` that sets `role`.

**Why bother when registration is off.** `CreateNewUser` is Fortify's
registration action. It currently builds a user without a role, which the
column's default quietly rescues. Whoever enables `Features::registration()`
next inherits a function that silently ignores access control. Two lines now.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Actions/CreateNewUserTest.php`:

```php
<?php

declare(strict_types=1);

use App\Actions\Fortify\CreateNewUser;
use App\Enums\UserRole;
use Illuminate\Validation\ValidationException;

it('creates a member by default', function (): void {
    $user = app(CreateNewUser::class)->create([
        'name' => 'Someone',
        'email' => 'someone@example.test',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ]);

    expect($user->role)->toBe(UserRole::Member);
});

it('creates the role it is given', function (): void {
    $user = app(CreateNewUser::class)->create([
        'name' => 'Someone',
        'email' => 'someone@example.test',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
        'role' => UserRole::Admin->value,
    ]);

    expect($user->role)->toBe(UserRole::Admin);
});

/**
 * The role is the access decision. An unrecognised value must stop the
 * creation, not fall back to something convenient.
 */
it('refuses a role the enum does not know', function (): void {
    expect(fn () => app(CreateNewUser::class)->create([
        'name' => 'Someone',
        'email' => 'someone@example.test',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
        'role' => 'wizard',
    ]))->toThrow(ValidationException::class);
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --filter=CreateNewUserTest`
Expected: FAIL — the second case returns a member.

- [ ] **Step 3: Wire the role in**

In `app/Actions/Fortify/CreateNewUser.php`, add to the validator:

```php
'role' => ['sometimes', Rule::enum(UserRole::class)],
```

and to the created attributes:

```php
// Defaulted here as well as in the column, so this action states the access
// decision rather than depending on a schema default to rescue it.
'role' => $input['role'] ?? UserRole::Member->value,
```

Import `App\Enums\UserRole`. The `@param array<string, string>` docblock still
holds.

- [ ] **Step 4: Run the tests**

Run: `php artisan test --filter=CreateNewUserTest`
Expected: PASS.

- [ ] **Step 5: Run everything**

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
composer lint && composer types:check
git add app/Actions/Fortify/CreateNewUser.php tests/Feature/Actions/CreateNewUserTest.php
git commit -m "fix: make the registration action set a role" -- app/Actions/Fortify/CreateNewUser.php tests/Feature/Actions/CreateNewUserTest.php
```

---

### Task 8: Release

**Files:**
- Modify: `CHANGELOG.md`

**Note:** neither `composer.json` nor `package.json` carries a `version` field
in this repository. Releases are a CHANGELOG entry and a git tag. Do not add
one.

- [ ] **Step 1: Run rector, then the full check**

```bash
composer rector:fix
composer ci:check
echo "exit: $?"
```

Expected: exit 0. Rector runs in fix mode first because a dry run that finds
changes fails the check, and its output is easy to lose in a pipe.

- [ ] **Step 2: Check the frontend gates**

```bash
npm run lint:check && npm run format:check && npm run build
```

Expected: all three clean.

- [ ] **Step 3: Write the changelog entry**

Add to `CHANGELOG.md` above `## [0.9.0]`:

```markdown
## [0.10.0] - <today>

### Added

- Invitations. Someone with access to General invites an address and a role; the
  invitee opens a link, chooses her own password, and lands in exactly the areas
  the invite named.
- A People screen in General listing users and invitations, with invite, reissue
  and revoke. The link is shown in the panel as well as emailed, so a delivery
  failure is recoverable.
- Password reset, with forgot-password and reset-password pages. An invited user
  can recover her own account without the owner touching the database.
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
- Password reset answers an unknown address exactly as it answers a known one.
```

- [ ] **Step 4: Commit and tag**

```bash
git add CHANGELOG.md
git commit -m "chore: release 0.10.0" -- CHANGELOG.md
git tag v0.10.0
git push && git push --tags
```

- [ ] **Step 5: Watch CI**

Run: `gh run watch`
Expected: all six jobs green.

- [ ] **Step 6: Send yourself one**

With Mailtrap configured in `.env`, invite a spare address from the panel and
open the link. This is the only step that proves the mail transport works, and
no test can do it.
