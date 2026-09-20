# Mobile API Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A versioned, token-authenticated API exposing everything the panel exposes, enforcing the same access rules — proven at a boundary where none of them currently apply.

**Architecture:** `AccessProfile` becomes guard-aware first, because nothing else works until it is. Sanctum issues one token per device, honouring the two-factor already enabled. The API reuses the panel's own middleware, FormRequests and query filters rather than reimplementing them, so the two clients cannot drift. API Resources are a third serialisation path that Spec C's field hiding does not reach, so they consult the profile and a test searches whole payloads rather than named fields.

**Tech Stack:** PHP 8.5, Laravel 13, Sanctum, Fortify, Pest 5, PostgreSQL 18.

**Spec:** `docs/superpowers/specs/2026-09-20-mobile-api-design.md`

## Global Constraints

- **Work directly on `master`.** No feature branches. Commit messages carry no AI attribution trailer.
- **No new inline comments.** PHPDoc blocks only — PHPStan at max needs them for array shapes and generics.
- **One migration file per table.**
- **Every class is `final`** except documented abstract bases, which must be added to `tests/Feature/ArchTest.php`.
- **PHPStan `level: max`.** No baseline, no `ignoreErrors`, no casting `mixed`.
- **Type coverage stays at 100%.**
- **The panel's behaviour does not change.** Every existing test passes untouched, except where a test asserted the absence of something this adds.
- **Refusal is 404**, consistent with Specs A and C.
- **Nothing here needs a deployed server.** If a step cannot be verified locally against Postgres, it is in the wrong plan.
- **Every commit is green.**
- **Gates before every push:** `composer ci:check`, `npm run lint:check`, `npm run format:check`. Check the exit status, not the tail of a pipe.

---

## File Structure

**Created:**

| Path | Responsibility |
| --- | --- |
| `routes/api.php` | The versioned surface, grouped by area |
| `app/Http/Controllers/Api/V1/TokenController.php` | Issue and revoke device tokens |
| `app/Http/Requests/Api/V1/TokenRequest.php` | Credentials and an optional code |
| `app/Http/Controllers/Api/V1/ApiResourceController.php` | Abstract base mirroring `AdminResourceController` |
| `app/Http/Resources/Api/V1/*Resource.php` | One per model |
| `app/Http/Middleware/EnforceIdempotency.php` | Replay a stored response for a repeated key |
| `database/migrations/..._create_idempotency_keys_table.php` | Key, user, endpoint, response, expiry |
| `app/Models/IdempotencyKey.php` | |
| `app/Console/Commands/PruneIdempotencyKeys.php` | Daily cleanup |
| `tests/Feature/Api/TokenIssueTest.php` | 2FA, throttle, revocation |
| `tests/Feature/Api/ApiAccessMatrixTest.php` | Every route, both roles, generated |
| `tests/Feature/Api/SerialisationLeakTest.php` | Whole-payload search |
| `tests/Feature/Api/IdempotencyTest.php` | |
| `tests/Feature/Auth/GuardParityTest.php` | One profile, two guards |

**Modified:**

| Path | Change |
| --- | --- |
| `composer.json` | `laravel/sanctum` |
| `bootstrap/app.php` | Register `routes/api.php`, alias the idempotency middleware |
| `app/Providers/AccessServiceProvider.php` | Guard-aware resolution |
| `app/Models/User.php` | `HasApiTokens` |
| `app/Providers/FortifyServiceProvider.php` | A limiter for the token endpoint |
| `app/Http/Controllers/Admin/General/PeopleController.php` | Device list and revoke |
| `resources/js/pages/General/People/Index.vue` | Devices table |
| `routes/admin.php` | Device revoke route |
| `tests/Feature/ArchTest.php` | `ApiResourceController` as an abstract base |

---

### Task 1: The access profile learns about tokens

**Files:**
- Modify: `composer.json`, `app/Providers/AccessServiceProvider.php`, `app/Models/User.php`, `bootstrap/app.php`
- Test: `tests/Feature/Auth/GuardParityTest.php`

**Interfaces:**
- Produces: an `AccessProfile` that resolves correctly under session *and* token authentication; `User` with `HasApiTokens`.

**Why first.** `AccessServiceProvider` reads `$app->make('auth')->guard()->user()` — the
default guard, which is `web`. A Sanctum request authenticates on the `sanctum`
guard, so that returns null, the profile is empty, and every area 404s, every
ability denies and every field hides. It fails closed, which is the right
direction, but it means no API route can work until this is fixed.

- [ ] **Step 1: Add Sanctum**

```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate --no-interaction
```

This is the only dependency this plan adds, and it was chosen explicitly.

- [ ] **Step 2: Register the API routes file**

In `bootstrap/app.php`:

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
    then: function (): void {
        Route::middleware('web')->group(base_path('routes/admin.php'));
    },
)
```

Create `routes/api.php` with nothing but a comment-free opening for now:

```php
<?php

declare(strict_types=1);
```

- [ ] **Step 3: Give the user tokens**

In `app/Models/User.php`, add the trait:

```php
use Laravel\Sanctum\HasApiTokens;
```

and `use HasApiTokens;` beside the existing traits.

- [ ] **Step 4: Write the failing test**

Create `tests/Feature/Auth/GuardParityTest.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\Ability;
use App\Enums\Area;
use App\Models\User;
use App\Support\Access\AccessProfile;

beforeEach(function (): void {
    config(['app.admin_email' => 'owner@example.test']);
});

/**
 * The assertion this whole plan rests on. The same person must get the same
 * answers whether they arrive with a session cookie or a device token; the day
 * those two diverge is the day one of them is wrong and nothing says so.
 */
it('resolves the same profile for a session and a token', function (string $email, string $state): void {
    $user = User::factory()->{$state}()->create(['email' => $email]);

    $viaSession = $this->actingAs($user)->getJson('/api/v1/profile-probe')->json();

    $token = $user->createToken('probe')->plainTextToken;

    $viaToken = $this->withToken($token)->getJson('/api/v1/profile-probe')->json();

    expect($viaToken)->toBe($viaSession);
})->with([
    'admin' => ['owner@example.test', 'admin'],
    'member' => ['her@example.test', 'member'],
]);

it('gives a token-authenticated member her areas and no more', function (): void {
    $member = User::factory()->member()->create(['email' => 'her@example.test']);

    $response = $this->withToken($member->createToken('probe')->plainTextToken)
        ->getJson('/api/v1/profile-probe');

    expect($response->json('areas'))->toEqualCanonicalizing(
        array_map(fn (Area $area): string => $area->value, [Area::Budget, Area::Utilities, Area::Library]),
    )->and($response->json('abilities'))->toBe([]);
});

it('gives a token-authenticated admin everything', function (): void {
    $owner = User::factory()->admin()->create(['email' => 'owner@example.test']);

    $response = $this->withToken($owner->createToken('probe')->plainTextToken)
        ->getJson('/api/v1/profile-probe');

    expect($response->json('abilities'))->toContain(Ability::SeeClientIdentity->value);
});

it('gives an unauthenticated request nothing', function (): void {
    $this->getJson('/api/v1/profile-probe')->assertUnauthorized();
});
```

**The probe route is temporary scaffolding.** Add it to `routes/api.php` for
this task and **delete it in Task 4**, when real routes exist to assert against.
A permanent endpoint that reports a user's own permissions is a gift to anyone
enumerating the application.

```php
Route::middleware('auth:sanctum')->prefix('v1')->group(function (): void {
    Route::get('profile-probe', function (): array {
        $profile = app(AccessProfile::class);

        return [
            'areas' => array_map(fn (Area $area): string => $area->value, $profile->areas()),
            'abilities' => array_values(array_filter(
                array_map(fn (Ability $a): ?string => $profile->allows($a) ? $a->value : null, Ability::cases()),
            )),
        ];
    })->name('api.v1.profile-probe');
});
```

- [ ] **Step 5: Run it and watch it fail**

Run: `php artisan test --filter=GuardParityTest`
Expected: the token cases FAIL with empty areas and abilities, because the
profile resolved from the `web` guard and found nobody.

- [ ] **Step 6: Make the resolution guard-aware**

In `app/Providers/AccessServiceProvider.php`:

```php
$user = $request->user() ?? $app->make('auth')->guard()->user();
```

with the reasoning in the class docblock:

```php
/**
 * Request::user() consults the resolver the authenticating middleware installed,
 * so it answers for a session cookie and a Sanctum token alike. The default
 * guard stays as a fallback because actingAs() sets the guard without setting a
 * request resolver, and that is how most of this application's tests
 * authenticate.
 */
```

- [ ] **Step 7: Run the tests**

Run: `php artisan test --filter=GuardParityTest`
Expected: PASS.

- [ ] **Step 8: Run everything**

Run: `php artisan test`
Expected: PASS. The fallback is what keeps the existing suite working; if many
tests fail here, the fallback was dropped rather than added.

- [ ] **Step 9: Commit**

```bash
composer lint && composer types:check
git add composer.json composer.lock config/ database/migrations bootstrap/app.php routes/api.php app/ tests/
git commit -m "feat: make the access profile answer for token requests" -- composer.json composer.lock config/ database/migrations bootstrap/app.php routes/api.php app/ tests/
```

---

### Task 2: Issuing a device token

**Files:**
- Create: `app/Http/Controllers/Api/V1/TokenController.php`, `app/Http/Requests/Api/V1/TokenRequest.php`
- Modify: `routes/api.php`, `app/Providers/FortifyServiceProvider.php`
- Test: `tests/Feature/Api/TokenIssueTest.php`

**Interfaces:**
- Produces: `POST /api/v1/tokens`, `DELETE /api/v1/tokens/current`.

**The rule:** two-factor is enabled on this application, so an endpoint that
trades a password for a long-lived token without it would remove the second
factor from the most easily stolen device in the house.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Api/TokenIssueTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

beforeEach(function (): void {
    config(['app.admin_email' => 'owner@example.test']);
    RateLimiter::clear('api-token|her@example.test|127.0.0.1');

    $this->member = User::factory()->member()->create([
        'email' => 'her@example.test',
        'password' => Hash::make('correct-horse-battery-staple'),
    ]);
});

it('issues a token for correct credentials', function (): void {
    $response = $this->postJson(route('api.v1.tokens.store'), [
        'email' => 'her@example.test',
        'password' => 'correct-horse-battery-staple',
        'device_name' => "Her iPhone",
    ])->assertOk();

    expect($response->json('token'))->toBeString()->not->toBeEmpty()
        ->and($this->member->fresh()->tokens()->count())->toBe(1)
        ->and($this->member->fresh()->tokens()->sole()->name)->toBe('Her iPhone');
});

it('refuses a wrong password and issues nothing', function (): void {
    $this->postJson(route('api.v1.tokens.store'), [
        'email' => 'her@example.test',
        'password' => 'wrong',
        'device_name' => 'Her iPhone',
    ])->assertStatus(422);

    expect($this->member->fresh()->tokens()->count())->toBe(0);
});

it('refuses an unknown address exactly as it refuses a wrong password', function (): void {
    $known = $this->postJson(route('api.v1.tokens.store'), [
        'email' => 'her@example.test', 'password' => 'wrong', 'device_name' => 'x',
    ]);

    $unknown = $this->postJson(route('api.v1.tokens.store'), [
        'email' => 'nobody@example.test', 'password' => 'wrong', 'device_name' => 'x',
    ]);

    expect($unknown->status())->toBe($known->status())
        ->and($unknown->json('message'))->toBe($known->json('message'));
});

it('requires a device name', function (): void {
    $this->postJson(route('api.v1.tokens.store'), [
        'email' => 'her@example.test',
        'password' => 'correct-horse-battery-staple',
    ])->assertStatus(422);
});

/**
 * The point of the endpoint's design. Without this a stolen password is a
 * permanent token and the second factor may as well not be switched on.
 */
it('will not issue a token to a two-factor account without a code', function (): void {
    enableTwoFactor($this->member);

    $this->postJson(route('api.v1.tokens.store'), [
        'email' => 'her@example.test',
        'password' => 'correct-horse-battery-staple',
        'device_name' => 'Her iPhone',
    ])
        ->assertStatus(423)
        ->assertJson(['two_factor' => true]);

    expect($this->member->fresh()->tokens()->count())->toBe(0);
});

it('issues a token when the code is right', function (): void {
    enableTwoFactor($this->member);

    $code = app(TwoFactorAuthenticationProvider::class)
        ->getCurrentOtp(decrypt($this->member->fresh()->two_factor_secret));

    $this->postJson(route('api.v1.tokens.store'), [
        'email' => 'her@example.test',
        'password' => 'correct-horse-battery-staple',
        'device_name' => 'Her iPhone',
        'code' => $code,
    ])->assertOk();

    expect($this->member->fresh()->tokens()->count())->toBe(1);
});

it('refuses a wrong code', function (): void {
    enableTwoFactor($this->member);

    $this->postJson(route('api.v1.tokens.store'), [
        'email' => 'her@example.test',
        'password' => 'correct-horse-battery-staple',
        'device_name' => 'Her iPhone',
        'code' => '000000',
    ])->assertStatus(423);

    expect($this->member->fresh()->tokens()->count())->toBe(0);
});

/**
 * A phone being set up is exactly when the authenticator app might be on the
 * device being replaced.
 */
it('accepts a recovery code once and not twice', function (): void {
    enableTwoFactor($this->member);

    $recovery = $this->member->fresh()->recoveryCodes()[0];

    $payload = [
        'email' => 'her@example.test',
        'password' => 'correct-horse-battery-staple',
        'device_name' => 'Her iPhone',
        'code' => $recovery,
    ];

    $this->postJson(route('api.v1.tokens.store'), $payload)->assertOk();
    $this->postJson(route('api.v1.tokens.store'), $payload)->assertStatus(423);

    expect($this->member->fresh()->tokens()->count())->toBe(1);
});

it('throttles attempts', function (): void {
    foreach (range(1, 5) as $ignored) {
        $this->postJson(route('api.v1.tokens.store'), [
            'email' => 'her@example.test', 'password' => 'wrong', 'device_name' => 'x',
        ]);
    }

    $this->postJson(route('api.v1.tokens.store'), [
        'email' => 'her@example.test', 'password' => 'wrong', 'device_name' => 'x',
    ])->assertStatus(429);
});

it('lets a device revoke its own token', function (): void {
    $token = $this->member->createToken('Her iPhone')->plainTextToken;

    $this->withToken($token)->deleteJson(route('api.v1.tokens.destroy'))->assertNoContent();

    expect($this->member->fresh()->tokens()->count())->toBe(0);

    $this->withToken($token)->getJson(route('api.v1.incomes.index'))->assertUnauthorized();
});
```

Add the helper to the same file:

```php
function enableTwoFactor(User $user): void
{
    $provider = app(TwoFactorAuthenticationProvider::class);

    $user->forceFill([
        'two_factor_secret' => encrypt($provider->generateSecretKey()),
        'two_factor_recovery_codes' => encrypt(json_encode(
            Collection::times(8, fn (): string => RecoveryCode::generate())->all(),
        )),
        'two_factor_confirmed_at' => now(),
    ])->save();
}
```

importing `Illuminate\Support\Collection` and `Laravel\Fortify\RecoveryCode`.

**Check `getCurrentOtp()` exists on the installed Fortify** before relying on it
— the contract in this repository declares `verify($secret, $code)`, and the
concrete provider may expose the OTP generator under a different name. If it
does not, generate the code with the same TOTP library Fortify uses rather than
weakening the test to skip the happy path.

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --filter=TokenIssueTest`
Expected: FAIL — `Route [api.v1.tokens.store] not defined.`

- [ ] **Step 3: Write the request**

Create `app/Http/Requests/Api/V1/TokenRequest.php`:

```php
final class TokenRequest extends FormRequest
{
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
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string'],
        ];
    }
}
```

- [ ] **Step 4: Write the controller**

Create `app/Http/Controllers/Api/V1/TokenController.php`:

```php
final class TokenController extends Controller
{
    /**
     * Issue a token for one device.
     *
     * Two-factor is enforced here rather than skipped: a token outlives a
     * session and lives on the most easily stolen device in the house, so
     * trading a password for one without the second factor would remove it.
     */
    public function store(TokenRequest $request, TwoFactorAuthenticationProvider $provider): JsonResponse
    {
        /** @var array{email: string, password: string, device_name: string, code: string|null} $data */
        $data = $request->validated();

        $user = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($data['email'])])->first();

        if (! $user instanceof User || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'Those credentials do not match.']);
        }

        if ($user->hasEnabledTwoFactorAuthentication() && ! $this->passesTwoFactor($user, $data['code'], $provider)) {
            return response()->json(['two_factor' => true], 423);
        }

        return response()->json([
            'token' => $user->createToken($data['device_name'])->plainTextToken,
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
        ]);
    }

    public function destroy(Request $request): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->noContent();
    }

    private function passesTwoFactor(User $user, ?string $code, TwoFactorAuthenticationProvider $provider): bool
    {
        if ($code === null || $code === '') {
            return false;
        }

        $secret = $user->two_factor_secret;

        if (is_string($secret) && $provider->verify(decrypt($secret), $code)) {
            return true;
        }

        return $user->replaceRecoveryCode($code) !== false;
    }
}
```

**On `replaceRecoveryCode()`:** check what Fortify's version returns — in some
releases it returns void and throws when the code is absent. Wrap it to return a
boolean rather than assuming, and make the recovery-code test prove both the
success and the second-use failure.

**On the unknown-address case:** the same `ValidationException` is thrown for an
unknown email and a wrong password, so the endpoint cannot be used to discover
who has an account.

- [ ] **Step 5: Add the routes and the limiter**

`routes/api.php`:

```php
Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('tokens', [TokenController::class, 'store'])
        ->middleware('throttle:api-token')
        ->name('tokens.store');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::delete('tokens/current', [TokenController::class, 'destroy'])->name('tokens.destroy');
    });
});
```

In `FortifyServiceProvider::boot()`, beside the existing limiters:

```php
RateLimiter::for('api-token', function (Request $request): Limit {
    $throttleKey = Str::transliterate(
        Str::lower($request->string('email')->toString()).'|'.$request->ip()
    );

    return Limit::perMinute(5)->by('api-token|'.$throttleKey);
});
```

- [ ] **Step 6: Run the tests, then everything**

```bash
php artisan test --filter=TokenIssueTest
php artisan test
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
composer lint && composer types:check
git add app/ routes/api.php tests/
git commit -m "feat: issue device tokens, honouring two-factor" -- app/ routes/api.php tests/
```

---

### Task 3: Devices on the People screen

**Files:**
- Modify: `app/Http/Controllers/Admin/General/PeopleController.php`, `routes/admin.php`, `resources/js/pages/General/People/Index.vue`
- Test: `tests/Feature/Admin/General/DeviceRevocationTest.php`

**Interfaces:**
- Produces: route `admin.people.revoke-device`, and a `devices` prop on the People page.

**Why the panel and not the phone.** A lost phone cannot revoke its own token.
The decision belongs where you already are: signed in on a laptop.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/General/DeviceRevocationTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\User;

beforeEach(function (): void {
    config(['app.admin_email' => 'owner@example.test']);
    $this->owner = User::factory()->admin()->create(['email' => 'owner@example.test']);
    $this->member = User::factory()->member()->create(['email' => 'her@example.test']);
});

it('lists every device on the people screen', function (): void {
    $this->owner->createToken('Owner MacBook');
    $this->member->createToken('Her iPhone');

    $this->actingAs($this->owner)
        ->get(route('admin.people.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('devices', 2));
});

it('lets the owner revoke anyone device', function (): void {
    $token = $this->member->createToken('Her iPhone');

    $this->actingAs($this->owner)
        ->delete(route('admin.people.revoke-device', $token->accessToken->getKey()))
        ->assertRedirect(route('admin.people.index'));

    expect($this->member->fresh()->tokens()->count())->toBe(0);
});

/**
 * Revocation has to bite immediately, or a stolen phone keeps working until the
 * token would have expired anyway.
 */
it('stops the revoked token working at once', function (): void {
    $plain = $this->member->createToken('Her iPhone');

    $this->withToken($plain->plainTextToken)->getJson(route('api.v1.incomes.index'))->assertOk();

    $this->actingAs($this->owner)->delete(route('admin.people.revoke-device', $plain->accessToken->getKey()));

    $this->withToken($plain->plainTextToken)->getJson(route('api.v1.incomes.index'))->assertUnauthorized();
});

it('hides device revocation from a member', function (): void {
    $token = $this->owner->createToken('Owner MacBook');

    $this->actingAs($this->member)
        ->delete(route('admin.people.revoke-device', $token->accessToken->getKey()))
        ->assertNotFound();

    expect($this->owner->fresh()->tokens()->count())->toBe(1);
});

it('never sends a token value to the browser', function (): void {
    $this->member->createToken('Her iPhone');

    $html = $this->actingAs($this->owner)->get(route('admin.people.index'))->getContent();

    $stored = $this->member->fresh()->tokens()->sole();

    expect($html)->not->toContain($stored->getAttribute('token'));
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --filter=DeviceRevocationTest`
Expected: FAIL — `Route [admin.people.revoke-device] not defined.`

- [ ] **Step 3: Add the prop and the action**

In `PeopleController::index()`, beside `users` and `invites`:

```php
'devices' => PersonalAccessToken::query()
    ->with('tokenable')
    ->latest()
    ->get()
    ->map(fn (PersonalAccessToken $token): array => [
        'id' => $token->getKey(),
        'name' => $token->name,
        'owner' => $token->tokenable instanceof User ? $token->tokenable->name : '—',
        'createdAt' => $token->created_at?->diffForHumans(),
        'lastUsedAt' => $token->last_used_at?->diffForHumans() ?? 'never',
    ])
    ->all(),
```

The hashed token itself is never included. Add the action:

```php
public function revokeDevice(PersonalAccessToken $device): RedirectResponse
{
    $device->delete();

    $this->notifier->success('Device revoked', 'That phone will have to sign in again.');

    return to_route('admin.people.index');
}
```

- [ ] **Step 4: Add the route**

In `routes/admin.php`, inside the `Area::General` group beside the other People
routes:

```php
Route::delete('people/devices/{device}', [PeopleController::class, 'revokeDevice'])->name('people.revoke-device');
```

**Declare it before `people/{invite}`**, or `devices` binds as an invite id and
the route 404s — the same ordering trap the bulk routes have.

Bind the parameter in `AppServiceProvider::boot()` if implicit binding does not
resolve Sanctum's model:

```php
Route::model('device', PersonalAccessToken::class);
```

- [ ] **Step 5: Add the table to the page**

In `resources/js/pages/General/People/Index.vue`, a third section listing
devices with a Revoke button, following the existing invites table exactly.

- [ ] **Step 6: Extend the access matrix resolver**

`tests/Feature/Auth/AreaAccessMatrixTest.php` needs a `'device'` arm in
`parameterValue()`:

```php
'device' => User::factory()->create()->createToken('matrix')->accessToken->getKey(),
```

Without it the new route binds to id `1` and 404s for reasons unrelated to areas.

- [ ] **Step 7: Run everything, build, commit**

```bash
php artisan test
npm run build
composer lint && composer types:check
npm run lint:check && npm run format:check
git add app/ routes/admin.php resources/js tests/
git commit -m "feat: list and revoke device tokens from the panel" -- app/ routes/admin.php resources/js tests/
```

---

### Task 4: The API foundation, proved on one resource

**Files:**
- Create: `app/Http/Controllers/Api/V1/ApiResourceController.php`, `app/Http/Controllers/Api/V1/Budget/IncomeController.php`, `app/Http/Resources/Api/V1/IncomeResource.php`
- Modify: `routes/api.php`, `tests/Feature/ArchTest.php`
- Test: `tests/Feature/Api/SerialisationLeakTest.php`

**Interfaces:**
- Consumes: `AccessProfile`, `EnsureAreaAccess`, the panel's `ResourceTable` and `AdminRequest` subclasses.
- Produces: `ApiResourceController` with the same abstract shape as `AdminResourceController`, and the Incomes endpoints.

**Incomes first, deliberately.** It is the one resource with an ability-gated
field and read-only records, so it exercises everything the other fourteen will
need. Proving the shape here is worth more than fifteen half-finished resources.

- [ ] **Step 1: Delete the probe route**

Remove `profile-probe` from `routes/api.php` and the assertions that use it from
`GuardParityTest`, replacing them with the same comparison against
`api.v1.incomes.index`:

```php
it('resolves the same profile for a session and a token', function (string $email, string $state): void {
    $user = User::factory()->{$state}()->create(['email' => $email]);

    $viaSession = $this->actingAs($user)->getJson(route('api.v1.incomes.index'))->json('data');
    $viaToken = $this->withToken($user->createToken('probe')->plainTextToken)
        ->getJson(route('api.v1.incomes.index'))->json('data');

    expect($viaToken)->toBe($viaSession);
})->with([
    'admin' => ['owner@example.test', 'admin'],
    'member' => ['her@example.test', 'member'],
]);
```

An endpoint that reports a user's own permissions is scaffolding, not a feature.

- [ ] **Step 2: Write the failing leak test**

Create `tests/Feature/Api/SerialisationLeakTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\Income;
use App\Models\User;

beforeEach(function (): void {
    config(['app.admin_email' => 'owner@example.test']);
    $this->owner = User::factory()->admin()->create(['email' => 'owner@example.test']);
    $this->member = User::factory()->member()->create(['email' => 'her@example.test']);
    $this->client = Client::factory()->create(['title' => 'Zzsecret Client']);
    $this->income = Income::factory()->create(['client_id' => $this->client->id, 'amount' => 4321]);
});

function tokenFor(User $user): string
{
    return $user->createToken('test')->plainTextToken;
}

/**
 * API Resources are a third serialisation path. Spec C hides fields by filtering
 * ResourceTable::columns() and ResourceForm::fields(), and neither of those is
 * involved here, so a resource that returns client_id re-opens the leak in a
 * response nothing else inspects.
 *
 * The whole payload is searched rather than named fields, because a field-by-
 * field assertion only covers what somebody remembered to assert.
 */
it('never names a client anywhere in a member payload', function (string $routeName): void {
    $url = $routeName === 'api.v1.incomes.show'
        ? route($routeName, $this->income)
        : route($routeName);

    $body = $this->withToken(tokenFor($this->member))->getJson($url)->getContent();

    expect($body)->not->toContain('Zzsecret Client')
        ->and($body)->not->toContain('"client_id"');
})->with(['api.v1.incomes.index', 'api.v1.incomes.show']);

it('does name the client for an admin, so the test cannot pass by returning nothing', function (): void {
    $body = $this->withToken(tokenFor($this->owner))->getJson(route('api.v1.incomes.index'))->getContent();

    expect($body)->toContain('Zzsecret Client');
});

it('still gives her the amount, because her totals must match his', function (): void {
    $this->withToken(tokenFor($this->member))
        ->getJson(route('api.v1.incomes.index'))
        ->assertOk()
        ->assertJsonPath('data.0.amount', '4321.00');
});

it('degrades the derived source label over the API too', function (): void {
    $hers = $this->withToken(tokenFor($this->member))->getJson(route('api.v1.incomes.index'));
    $his = $this->withToken(tokenFor($this->owner))->getJson(route('api.v1.incomes.index'));

    expect($hers->json('data.0.source'))->toBe('Client work')
        ->and($his->json('data.0.source'))->toBe('Zzsecret Client');
});

it('refuses to let her update an income that names a client', function (): void {
    $this->withToken(tokenFor($this->member))
        ->putJson(route('api.v1.incomes.update', $this->income), [
            'amount' => 999,
            'currency' => 'TRY',
            'date' => now()->toDateString(),
            'description' => 'Changed',
        ])
        ->assertNotFound();

    expect($this->income->fresh()->description)->not->toBe('Changed');
});

it('ignores a client id she posts', function (): void {
    $this->withToken(tokenFor($this->member))
        ->postJson(route('api.v1.incomes.store'), [
            'amount' => 100,
            'currency' => 'TRY',
            'date' => now()->toDateString(),
            'description' => 'Mine',
            'client_id' => $this->client->id,
        ])
        ->assertCreated();

    expect(Income::query()->where('description', 'Mine')->sole()->client_id)->toBeNull();
});
```

**The `amount` assertion pins a string, not a number.** Postgres returns
`decimal` as a string through PDO. Run it once and match what actually comes
back rather than assuming a float.

- [ ] **Step 3: Run it and watch it fail**

Run: `php artisan test --filter=SerialisationLeakTest`
Expected: FAIL — the routes do not exist.

- [ ] **Step 4: Write the base controller**

Create `app/Http/Controllers/Api/V1/ApiResourceController.php`:

```php
/**
 * The API half of AdminResourceController.
 *
 * Deliberately reuses the panel's table, form and FormRequest rather than
 * reimplementing any of them: the filters, the validation and the per-record
 * write guard are the same objects, so the two clients cannot drift apart.
 */
abstract class ApiResourceController extends Controller
{
    abstract protected function table(): ResourceTable;

    abstract protected function form(): ResourceForm;

    /** @return class-string<Model> */
    abstract protected function modelClass(): string;

    /** @return class-string<FormRequest> */
    abstract protected function requestClass(): string;

    /** @return class-string<JsonResource> */
    abstract protected function resourceClass(): string;

    public function index(Request $request): AnonymousResourceCollection
    {
        $resource = $this->resourceClass();

        return $resource::collection($this->table()->records($request));
    }

    public function show(Request $request): JsonResource
    {
        $resource = $this->resourceClass();

        return new $resource($this->findOrFail($request));
    }

    public function store(): JsonResponse
    {
        ['attributes' => $attributes, 'relations' => $relations] = $this->form()->partition($this->validated());

        $record = $this->modelClass()::create($attributes);

        $this->syncRelations($record, $relations);

        $resource = $this->resourceClass();

        return (new $resource($record->fresh()))->response()->setStatusCode(201);
    }

    public function update(Request $request): JsonResource
    {
        $record = $this->findOrFail($request);

        abort_unless($this->isRecordEditable($record), 404);

        ['attributes' => $attributes, 'relations' => $relations] = $this->form()->partition($this->validated());

        $record->update($attributes);

        $this->syncRelations($record, $relations);

        $resource = $this->resourceClass();

        return new $resource($record->fresh());
    }

    public function destroy(Request $request): Response
    {
        $record = $this->findOrFail($request);

        abort_unless($this->isRecordEditable($record), 404);

        $record->delete();

        return response()->noContent();
    }

    protected function isRecordEditable(Model $record): bool
    {
        return true;
    }

    /**
     * Resolving a FormRequest from the container runs its validation, which is
     * how the panel reuses the same rules. The API does the same rather than
     * revalidating, so a rule added for one protects the other.
     *
     * @return array<string, mixed>
     */
    private function validated(): array
    {
        /** @var FormRequest $request */
        $request = resolve($this->requestClass());

        /** @var array<string, mixed> $data */
        $data = $request->validated();

        return $data;
    }

    /**
     * @param  array<string, array<int, mixed>>  $relations
     */
    private function syncRelations(Model $record, array $relations): void
    {
        foreach ($relations as $relation => $ids) {
            if (method_exists($record, $relation)) {
                $record->{$relation}()->sync($ids);
            }
        }
    }
}
```

**Relations are partitioned exactly as the panel does.** Posts and Books carry a
many-to-many `categories`; writing that straight into `create()` would throw,
because it is not a column. `ResourceForm::partition()` already splits them and
is reused here rather than reimplemented.

**File uploads are not handled.** `AdminResourceController` has an `uploads()`
hook for images; the API rejects multipart for now and a phone that wants to
attach a book cover is a later change. Say so in the changelog rather than
leaving it to be discovered.

**`ResourceTable::records()` does not exist yet.** `rows()` returns rendered
cells for the Vue table, which is presentation, not data — the API must not
serve `display` strings. Add a sibling to `ResourceTable`:

```php
/**
 * The models behind a page of rows, filtered and sorted exactly as the table
 * would, but not rendered. The API serialises models; the panel renders cells.
 *
 * @return LengthAwarePaginator<int, Model>
 */
public function records(Request $request): LengthAwarePaginator
```

built from the same `query()`, `applySearch()`, `applyFilters()`, `applySort()`
and `resolvePerPage()` that `rows()` uses, without the `through()` mapping.
Refactor `rows()` to call it, so there is one query path rather than two.

Add `ApiResourceController` to both lists in `tests/Feature/ArchTest.php`.

- [ ] **Step 5: Write the income resource and controller**

`app/Http/Resources/Api/V1/IncomeResource.php`:

```php
/**
 * @mixin Income
 */
final class IncomeResource extends JsonResource
{
    /**
     * client_id is gated on the same ability the panel's column is, because this
     * is a serialisation path that Spec C's field filtering does not reach.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $profile = app(AccessProfile::class);

        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'currency' => $this->currency->value,
            'date' => $this->date?->toDateString(),
            'description' => $this->description,
            'source' => $this->source,
            'income_category_id' => $this->income_category_id,
            'invoice_id' => $this->invoice_id,
            'debt_id' => $this->debt_id,
            'client_id' => $this->when($profile->allows(Ability::SeeClientIdentity), $this->client_id),
            'editable' => $this->client_id === null || $profile->allows(Ability::SeeClientIdentity),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
```

`source` needs no guard: the model's accessor already degrades it, which is why
it was put there rather than in the table.

`app/Http/Controllers/Api/V1/Budget/IncomeController.php` mirrors the panel's,
returning `IncomeTable`, `Income::class`, `IncomeRequest::class`,
`IncomeResource::class`, and overriding `isRecordEditable()` with the same body
the panel's uses.

- [ ] **Step 6: Add the routes**

In `routes/api.php`, inside the authenticated group:

```php
Route::middleware('area:'.Area::Budget->value)->group(function (): void {
    Route::apiResource('incomes', IncomeController::class);
});
```

The **same** `area:` middleware the panel uses, not a copy.

- [ ] **Step 7: Run the tests, then everything**

```bash
php artisan test --filter="SerialisationLeakTest|GuardParityTest"
php artisan test
```

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
composer lint && composer types:check
git add app/ routes/api.php tests/
git commit -m "feat: add the api foundation and the incomes endpoints" -- app/ routes/api.php tests/
```

---

### Task 5: The remaining resources, and the matrix

**Files:**
- Create: fourteen more controllers under `app/Http/Controllers/Api/V1/<Area>/`, fourteen more resources under `app/Http/Resources/Api/V1/`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/ApiAccessMatrixTest.php`

**Interfaces:**
- Produces: every resource the panel exposes, behind its own area.

The fourteen: Posts (Blog); Categories (General); Books, Writers, Publishers
(Library); Utility accounts, Utility bills (Utilities); Clients, Projects,
Repositories, Invoices, WakaTime summaries — read-only, index and show only —
(Work); Expenses, Debts (Budget).

- [ ] **Step 1: Write the failing matrix**

Create `tests/Feature/Api/ApiAccessMatrixTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

/**
 * Every v1 route, crossed with both roles, authenticated by token rather than
 * by session. Generated from the router for the same reason the panel's matrix
 * is: a route added later is covered the day it appears.
 *
 * @return array<string, array{string, string, array<int, string>}>
 */
function apiRoutes(): array
{
    static $cached = null;

    if (is_array($cached)) {
        return $cached;
    }

    $app = require __DIR__.'/../../../bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    restore_exception_handler();
    restore_error_handler();

    $cases = [];

    foreach ($app->make('router')->getRoutes() as $route) {
        $name = $route->getName();

        if (! is_string($name) || ! str_starts_with($name, 'api.v1.')) {
            continue;
        }

        if (in_array($name, ['api.v1.tokens.store', 'api.v1.tokens.destroy'], true)) {
            continue;
        }

        $method = in_array('GET', $route->methods(), true) ? 'GET' : $route->methods()[0];

        $cases[$method.' '.$name] = [$method, $name, $route->parameterNames()];
    }

    return $cached = $cases;
}

/**
 * Which area each route belongs to, by name prefix.
 */
function apiAreaOf(string $routeName): ?string
{
    $map = [
        'api.v1.posts.' => 'blog',
        'api.v1.categories.' => 'general',
        'api.v1.books.' => 'library',
        'api.v1.writers.' => 'library',
        'api.v1.publishers.' => 'library',
        'api.v1.utility-accounts.' => 'utilities',
        'api.v1.utility-bills.' => 'utilities',
        'api.v1.clients.' => 'work',
        'api.v1.projects.' => 'work',
        'api.v1.repositories.' => 'work',
        'api.v1.invoices.' => 'work',
        'api.v1.waka-time-summaries.' => 'work',
        'api.v1.incomes.' => 'budget',
        'api.v1.expenses.' => 'budget',
        'api.v1.debts.' => 'budget',
    ];

    foreach ($map as $prefix => $area) {
        if (str_starts_with($routeName, $prefix)) {
            return $area;
        }
    }

    return null;
}

beforeEach(function (): void {
    config(['app.admin_email' => 'owner@example.test']);
});

/**
 * Compared against the owner's answer rather than a fixed status, for the same
 * reason the panel's matrix is: some routes refuse for reasons of their own, and
 * a hand-written list of those exceptions rots.
 */
it('gives a member what the admin gets inside her areas, and nothing outside them', function (string $method, string $name, array $parameterNames): void {
    $owner = User::factory()->admin()->create(['email' => 'owner@example.test']);
    $ownerStatus = $this->withToken($owner->createToken('matrix')->plainTextToken)
        ->json($method, route($name, routeParameters($parameterNames, $owner)))
        ->status();

    $member = User::factory()->member()->create(['email' => 'her@example.test']);
    $memberStatus = $this->withToken($member->createToken('matrix')->plainTextToken)
        ->json($method, route($name, routeParameters($parameterNames, $member)))
        ->status();

    $area = apiAreaOf($name);
    $hers = $area === null || in_array($area, ['budget', 'utilities', 'library'], true);

    $hers
        ? expect($memberStatus)->toBe($ownerStatus, "{$name} answered her differently from the owner")
        : expect($memberStatus)->toBe(404, "{$name} is a {$area} route and should not exist for her");
})->with(fn (): array => apiRoutes());

it('turns away a request with no token', function (string $method, string $name, array $parameterNames): void {
    $owner = User::factory()->admin()->create(['email' => 'owner@example.test']);

    $this->json($method, route($name, routeParameters($parameterNames, $owner)))
        ->assertUnauthorized();
})->with(fn (): array => apiRoutes());
```

`routeParameters()` is the helper `AreaAccessMatrixTest` already defines. Pest
loads every test file into one process, so it is available — but if the two
files collide on the name, move it into `tests/Pest.php` beside
`userWithoutRole()` rather than duplicating it.

**The token routes are excluded** and covered by `TokenIssueTest`:
`tokens.store` answers a guest and a member identically by design, and
`tokens.destroy` has no area.

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --filter=ApiAccessMatrixTest`
Expected: FAIL — only the incomes routes exist.

- [ ] **Step 3: Write them**

Each controller is the same five lines as the incomes one with different class
names. Each resource lists that model's columns; only `IncomeResource` has a
gated field today.

**Do not** give `WakaTimeSummaryController` store, update or destroy. It is
synced from an API and never authored, which is why the panel's version does not
extend `AdminResourceController` either. Use
`Route::apiResource(...)->only(['index', 'show'])`.

- [ ] **Step 4: Group the routes by area**

Mirror `routes/admin.php`'s structure exactly, using the same `area:` middleware
per group, so a reader can diff the two files and see the same shape.

- [ ] **Step 5: Run the matrix, then everything**

```bash
php artisan test --filter=ApiAccessMatrixTest
php artisan test
```

Expected: PASS. A member case reaching a Work route means that route was left
outside its group.

- [ ] **Step 6: Commit**

```bash
composer lint && composer types:check
git add app/ routes/api.php tests/
git commit -m "feat: expose every panel resource over the api" -- app/ routes/api.php tests/
```

---

### Task 6: Idempotent writes

**Files:**
- Create: `database/migrations/..._create_idempotency_keys_table.php`, `app/Models/IdempotencyKey.php`, `app/Http/Middleware/EnforceIdempotency.php`, `app/Console/Commands/PruneIdempotencyKeys.php`
- Modify: `bootstrap/app.php`, `routes/api.php`, `routes/console.php`
- Test: `tests/Feature/Api/IdempotencyTest.php`

**Interfaces:**
- Produces: middleware alias `idempotent`, honouring an `Idempotency-Key` header for 24 hours.

**Why it exists:** the phone queues writes while offline. Without this, a retry
after a dropped connection records the expense twice and the month's total is
silently wrong.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Api/IdempotencyTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Expense;
use App\Models\User;

beforeEach(function (): void {
    config(['app.admin_email' => 'owner@example.test']);
    $this->owner = User::factory()->admin()->create(['email' => 'owner@example.test']);
    $this->member = User::factory()->member()->create(['email' => 'her@example.test']);
    $this->token = $this->owner->createToken('phone')->plainTextToken;
});

/**
 * @return array<string, mixed>
 */
function expensePayload(string $description = 'Coffee'): array
{
    return [
        'amount' => 100,
        'currency' => 'TRY',
        'date' => now()->toDateString(),
        'description' => $description,
    ];
}

/**
 * The case this exists for: a phone queues a write, the connection drops after
 * the server handled it, and the phone retries.
 */
it('creates one record for a repeated key and returns the first response', function (): void {
    $first = $this->withToken($this->token)
        ->withHeader('Idempotency-Key', 'abc-123')
        ->postJson(route('api.v1.expenses.store'), expensePayload());

    $second = $this->withToken($this->token)
        ->withHeader('Idempotency-Key', 'abc-123')
        ->postJson(route('api.v1.expenses.store'), expensePayload());

    expect(Expense::query()->where('description', 'Coffee')->count())->toBe(1)
        ->and($second->status())->toBe($first->status())
        ->and($second->json())->toBe($first->json());
});

/**
 * Answering a different question with a stored answer is worse than refusing.
 */
it('refuses a key replayed against a different payload', function (): void {
    $this->withToken($this->token)
        ->withHeader('Idempotency-Key', 'abc-123')
        ->postJson(route('api.v1.expenses.store'), expensePayload('Coffee'));

    $this->withToken($this->token)
        ->withHeader('Idempotency-Key', 'abc-123')
        ->postJson(route('api.v1.expenses.store'), expensePayload('Something else'))
        ->assertStatus(422);

    expect(Expense::query()->count())->toBe(1);
});

it('lets two different keys create two records', function (): void {
    foreach (['key-one', 'key-two'] as $key) {
        $this->withToken($this->token)
            ->withHeader('Idempotency-Key', $key)
            ->postJson(route('api.v1.expenses.store'), expensePayload())
            ->assertCreated();
    }

    expect(Expense::query()->where('description', 'Coffee')->count())->toBe(2);
});

it('stops replaying after twenty-four hours', function (): void {
    $this->withToken($this->token)
        ->withHeader('Idempotency-Key', 'abc-123')
        ->postJson(route('api.v1.expenses.store'), expensePayload());

    $this->travel(25)->hours();

    $this->withToken($this->token)
        ->withHeader('Idempotency-Key', 'abc-123')
        ->postJson(route('api.v1.expenses.store'), expensePayload())
        ->assertCreated();

    expect(Expense::query()->where('description', 'Coffee')->count())->toBe(2);
});

/**
 * Keys are scoped per account, so one person cannot use another's key to read
 * back a response that was never theirs.
 */
it('scopes keys per user', function (): void {
    $this->withToken($this->token)
        ->withHeader('Idempotency-Key', 'shared-key')
        ->postJson(route('api.v1.expenses.store'), expensePayload('His'));

    $hers = $this->withToken($this->member->createToken('phone')->plainTextToken)
        ->withHeader('Idempotency-Key', 'shared-key')
        ->postJson(route('api.v1.expenses.store'), expensePayload('Hers'));

    $hers->assertCreated();

    expect($hers->json('data.description'))->toBe('Hers')
        ->and(Expense::query()->count())->toBe(2);
});

it('ignores the header on a read', function (): void {
    $one = $this->withToken($this->token)
        ->withHeader('Idempotency-Key', 'abc-123')
        ->getJson(route('api.v1.expenses.index'));

    Expense::factory()->create();

    $two = $this->withToken($this->token)
        ->withHeader('Idempotency-Key', 'abc-123')
        ->getJson(route('api.v1.expenses.index'));

    expect($one->json('meta.total'))->not->toBe($two->json('meta.total'));
});

it('works normally with no header at all', function (): void {
    foreach (range(1, 2) as $ignored) {
        $this->withToken($this->token)
            ->postJson(route('api.v1.expenses.store'), expensePayload())
            ->assertCreated();
    }

    expect(Expense::query()->where('description', 'Coffee')->count())->toBe(2);
});
```

**Check `meta.total` against a real response** before relying on it — the exact
pagination key depends on how the resource collection is returned, and the read
test is worthless if it asserts a key that is always null.

- [ ] **Step 2: The table**

```php
Schema::create('idempotency_keys', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('key');
    $table->string('endpoint');
    $table->string('payload_hash', 64);
    $table->unsignedSmallInteger('response_status');
    $table->jsonb('response_body');
    $table->timestamp('expires_at');
    $table->timestamps();

    $table->unique(['user_id', 'key']);
});
```

`jsonb` rather than `json`: this is PostgreSQL now, and `jsonb` is what it wants.

The unique index on `(user_id, key)` is what scopes keys per account and stops
one user replaying another's.

- [ ] **Step 3: The middleware**

Stores status and body on the first pass; on a repeat within the window returns
the stored response untouched. A key whose `payload_hash` differs returns **422**
rather than the stored response, because silently answering a different question
is worse than refusing.

Applied to the whole `v1` group — it is a no-op for safe methods and for
requests without the header.

- [ ] **Step 4: Prune**

An artisan command deleting expired rows, scheduled daily in `routes/console.php`
beside whatever is already there.

- [ ] **Step 5: Run everything and commit**

```bash
php artisan test
composer lint && composer types:check
git add app/ database/migrations bootstrap/app.php routes/ tests/
git commit -m "feat: make api writes idempotent" -- app/ database/migrations bootstrap/app.php routes/ tests/
```

---

### Task 7: Release

- [ ] **Step 1: Rector, then the full check**

```bash
composer rector:fix
composer ci:check
echo "exit: $?"
```

- [ ] **Step 2: Frontend gates**

```bash
npm run lint:check && npm run format:check && npm run build
```

- [ ] **Step 3: Confirm the API is actually reachable**

```bash
php artisan route:list --path=api
```

Expected: every `v1` route present, each carrying `auth:sanctum` and its area
middleware except `tokens.store`.

- [ ] **Step 4: Changelog, commit, tag**

Add a `0.13.0` entry covering the API, device tokens, two-factor at issue,
idempotency and the guard-aware profile. Tag `v0.13.0` and push.

- [ ] **Step 5: Check CI properly**

```bash
gh run list --limit 1
gh run view <id> --json conclusion
```

Read the `conclusion` field. `gh run watch --exit-status` has returned 0 for a
failed run in this repository.

- [ ] **Step 6: Try it by hand**

```bash
curl -sX POST https://myblog.test/api/v1/tokens \
  -H 'Accept: application/json' \
  -d 'email=<you>&password=<password>&device_name=curl'
```

Then fetch a resource with the token. This is the step that proves the thing a
phone will actually do, and no test does it end to end.
