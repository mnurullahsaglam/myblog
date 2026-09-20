# Mobile API — Design

**Date:** 2026-09-20
**Status:** Approved, not yet implemented
**Follows:** Spec A (areas and roles, v0.9.0), Spec B (invites, v0.10.0), Spec C (per-user visibility, v0.11.0)
**Precedes:** the `myblog-mobile` Swift client, which consumes this

---

## Goal

A versioned, token-authenticated HTTP API exposing everything the panel exposes,
which enforces the same access rules the panel enforces — proven at a boundary
where none of them currently apply.

## Non-Goals

- **The Swift client.** Its own project, its own repository.
- **Deployment.** This is built and tested entirely locally against Postgres.
  Nothing here needs a server.
- **Push notifications.** Token registration belongs to the client spec, and
  sending needs a worker and a host. Neither exists yet.
- **A public API.** Two people, two phones. No OAuth, no third-party clients, no
  documentation portal.
- **Changing the panel's behaviour.** The web application ends this work doing
  exactly what it does now.

---

## Current state

| Thing | Where it stands |
| --- | --- |
| API routes | None. `bootstrap/app.php` registers `web` and `commands` only |
| Sanctum | Not installed |
| API Resources | None |
| Auth | Session only, via Fortify, with 2FA and passkeys enabled |
| Access model | `AccessProfile`, resolved from the **default guard** |
| Field visibility | Enforced in `ResourceTable::columns()` and `ResourceForm::fields()` |

---

## Part 1 — The access profile learns about tokens

**This comes first because nothing else works without it.**

`AccessServiceProvider` resolves the user with
`$app->make('auth')->guard()->user()` — the *default* guard, which is `web`. A
Sanctum request authenticates on the `sanctum` guard, so that call returns null,
the profile is empty, and every area 404s, every ability denies, every
`hiddenWithout()` field hides and every record becomes uneditable.

It fails closed, which is the right direction and worth keeping. But the
resolution has to become guard-aware:

```php
$user = $request->user() ?? $app->make('auth')->guard()->user();
```

`Request::user()` consults the resolver the authenticating middleware installed,
so it answers correctly for both session and token requests. The default-guard
fallback stays because `actingAs()` in a test sets the guard without setting a
request resolver, and that is how most of this application's 1300 tests
authenticate.

A test asserts the profile resolves identically for the same user under both
session and token authentication. That single assertion is what stops the two
halves of the application drifting apart.

---

## Part 2 — Authentication

### Issuing a token

```
POST /api/v1/tokens        { email, password, device_name }
  → 200 { token, user }                     when 2FA is off
  → 423 { two_factor: true }                when 2FA is on
POST /api/v1/tokens        { email, password, device_name, code }
  → 200 { token, user }
```

**2FA is honoured, not bypassed.** The application has two-factor enabled, and an
endpoint that trades a password for a token without it would quietly remove the
second factor from the most easily stolen device in the house. When
`hasEnabledTwoFactorAuthentication()` is true the first request returns **423
Locked** and issues nothing; the second must carry a valid TOTP code, verified
through Fortify's own `TwoFactorAuthenticationProvider::verify()`, or a recovery
code consumed via `replaceRecoveryCode()`.

Recovery codes are accepted because a phone being set up is exactly when the
authenticator app might be on the device being replaced.

The plaintext token is returned **once**. Sanctum stores a hash, which matches
how invitations already work.

### Rate limiting

The token endpoint is a password oracle and is throttled hardest: **5 attempts
per minute per email and IP**, the same limiter shape the web login uses. Without
this the API is a quieter, faster version of the login form.

### Device tokens in the panel

Each token carries the `device_name` it was issued for. The **People** screen
gains a list of devices per user — name, created, last used — and a revoke
action. A lost phone is cut off from the panel you are already signed into,
which is the only place that decision should be made.

Revoking is `delete()` on the token; Sanctum stops accepting it immediately.

### Token abilities

None. A device token can do exactly what its user can, decided by role, exactly
as on the web. Sanctum's ability system is deliberately unused: introducing a
second, overlapping permission model beside `Area` and `Ability` would give two
answers to one question, and that is the failure this application has spent three
specs avoiding.

---

## Part 3 — The resource surface

`/api/v1`, behind `auth:sanctum`, grouped by area with the **same
`EnsureAreaAccess` middleware the web routes use**. Not a parallel
implementation — the same class, so a change to one cannot miss the other.

All fifteen resources: the fourteen that extend `AdminResourceController` with
index, show, store, update and destroy where the panel has them, plus the
read-only WakaTime summaries. An earlier draft said thirteen, which was
`ParityTest`'s count from before utilities existed.

### The serialisation risk, which is the important part of this spec

Spec C hides fields by filtering `ResourceTable::columns()` and
`ResourceForm::fields()`. **API Resources are a third serialisation path that
neither touches.** An `IncomeResource` that returns `$this->client_id` re-opens
the exact leak Spec C closed, in a response that no existing test inspects.

So:

- Every API Resource that exposes a field requiring an ability consults
  `AccessProfile` and omits it, using Laravel's `when()`.
- `Income::$source` already degrades in the model, so it carries over for free —
  which is the argument for having put it there rather than in the table.
- A test asserts that **no API response for a member contains a client's name**,
  applied across every income endpoint, by searching the rendered JSON rather
  than by inspecting fields.

That last test is the one that matters. Field-by-field assertions check what
somebody remembered to check; searching the payload catches what they forgot.

### Shape

Laravel's API Resources with their default `data` envelope, and the standard
pagination `meta`/`links` for collections. Filters, sorting and search reuse the
query parameters `ResourceTable` already understands, so the phone and the panel
speak the same language and `applyFilters()` stays the single implementation.

### What is not exposed

- **View-as.** A preview is a panel affordance; a token is already scoped to one
  real user.
- **Exports.** They produce a file for a browser to download.
- **Settings.** Global configuration belongs on a keyboard.
- **Invitations.** Issuing one is a deliberate, rare act with a link to hand over.

Feature flags **are** respected: a flagged-off route 404s for a member over the
API exactly as it does in the panel, through the same middleware.

---

## Part 4 — Writes

### Validation reuses the panel's FormRequests

`AdminRequest` subclasses already carry the area check and the conditional
client rule. The API controllers resolve the same classes, so validation cannot
drift between the two clients, and a rule added for the panel protects the phone
the day it is written.

The cost is accepted deliberately: the phone must post the payload shape the
panel posts.

### Idempotent writes

The client queues writes while offline, so a retry must not create a second
expense.

Every unsafe request may carry an **`Idempotency-Key`** header. The first request
with a given key is processed and its status and body stored; a repeat within
**24 hours** returns the stored response without touching the database. Twenty-four
hours covers a phone that was offline overnight, which is the case this exists
for.

One table, `idempotency_keys`: the key, the user, the endpoint, the response
status and body, and a timestamp. Keys are scoped per user, so one account cannot
probe another's. A daily prune removes expired rows.

A key replayed against a **different** endpoint or payload is a client bug and
returns 422 rather than the stored response, because silently answering a
different question is worse than refusing.

### Protected records

Incomes that name a client stay read-only for anyone without
`SeeClientIdentity`, enforced by the same `isRecordEditable()` check the panel
uses. The API returns 404 for edit and update on those records, matching the
panel exactly.

---

## Testing

The API is tested the way the panel was: by what a response contains, not by
what a method returns.

**The boundary:**

- The same user, authenticated by session and by token, produces an identical
  `AccessProfile`.
- Every `/api/v1` route is covered by a matrix crossing both roles, generated
  from the router exactly as `AreaAccessMatrixTest` is, so a route added later is
  covered the day it appears.
- A member's token reaches her three areas and 404s on the rest.

**Serialisation:**

- No income response for a member contains a client's name, asserted by
  searching the JSON across index, show and update.
- The same responses for an admin do contain it, so the test cannot pass by
  returning nothing.

**Authentication:**

- A user with 2FA enabled cannot obtain a token without a code.
- A wrong code issues nothing; a recovery code works once and not twice.
- The token endpoint throttles.
- A revoked token stops working immediately.
- A token belonging to a deleted user stops working.

**Idempotency:**

- The same key twice creates one record and returns the first response both times.
- The same key with a different payload is refused.
- Two different keys create two records.
- A key older than 24 hours no longer replays.
- Keys are scoped per user.

---

## Risks

| Risk | Mitigation |
| --- | --- |
| API Resources leak a field the panel hides | Resources consult the profile; a test searches whole payloads rather than named fields |
| The API and the panel drift apart | The same middleware, the same FormRequests, the same filters |
| A stolen password becomes a permanent token | 2FA is enforced at issue; tokens are revocable per device from the panel |
| A queued write posts twice | Idempotency keys, scoped per user, remembered for 24 hours |
| Token auth silently bypasses the access model | It fails closed today; Part 1 makes it correct, and a test pins both guards to one answer |
| Sanctum abilities become a second permission model | Deliberately unused; role decides |
