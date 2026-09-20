# Invites and Registration — Design

**Date:** 2026-09-20
**Status:** Approved, not yet implemented
**Follows:** `2026-09-20-roles-and-area-access-design.md` (Spec A, shipped as v0.9.0)

---

## Goal

A second person joins the panel by opening a link sent to her email, choosing
her own password, and landing in exactly the areas the invite named — without
the owner ever typing her password or editing a seeder.

Spec A shipped the access model and seeded her account from `MEMBER_EMAIL`. That
was scaffolding, and this replaces it.

## Non-Goals

- **Self-service signup.** There is no public registration form. An account
  exists because someone with access to General created an invite for it.
- **Multi-tenancy or teams.** Two people, shared household data, no ownership
  columns. Unchanged from Spec A.
- **Column-level visibility and Pennant.** Still Spec C.
- **Email verification as a Fortify feature.** Covered under *Verification*
  below: invited addresses are already proven, and enabling the feature would
  wall off the accounts that already exist.
- **Queued mail.** One recipient, one message. `QUEUE_CONNECTION=sync` stays.

---

## Current state

| Thing | Where it stands |
| --- | --- |
| Registration | Off. No route exists. |
| Password reset | Off. `config/fortify.php:167` says so in a comment. |
| Email verification | Off, though `User` implements `MustVerifyEmail`. |
| Mail | `MAIL_MAILER=log`. Nothing has ever been delivered. |
| `CreateNewUser` | Present, unused, and silently ignores `role`. |
| Auth pages | `Login.vue`, `ConfirmPassword.vue`, `TwoFactorChallenge.vue`. |
| Second account | Seeded from `MEMBER_EMAIL` if set. |
| Database | MySQL in development, SQLite in CI. |

The comment at `config/fortify.php:167` — "this is a single-user panel and the
only account is provisioned by hand" — stops being true in this spec and must be
rewritten, not merely contradicted.

---

## Mail

`resend/resend-php` is added. The transport itself already ships with the
framework; without the package `app('mail.manager')->mailer('resend')` throws
`Class "Resend" not found`.

Two configurations, one code path:

```dotenv
# Local. Mailtrap catches everything; nothing reaches a real inbox.
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=
MAIL_PASSWORD=

# Production. The account and sending domain already exist.
MAIL_MAILER=resend
RESEND_API_KEY=
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME="${APP_NAME}"
```

`phpunit.xml:32` already pins `MAIL_MAILER=array`, so tests assert delivery with
`Mail::fake()` and nothing leaves the machine. No test may depend on a value
only a local `.env` carries — the trap that has broken CI here before.

---

## The invite

### Table

One migration, `create_invites_table`, one table:

| Column | Type | Why |
| --- | --- | --- |
| `id` | id | |
| `email` | string | Who it is for. Fixed; she cannot change it on the form. |
| `role` | string | Cast to `UserRole`. The invite is where access is decided. |
| `token_hash` | string, unique | SHA-256 of the token. |
| `expires_at` | timestamp | Creation plus 48 hours. |
| `accepted_at` | timestamp, nullable | |
| `revoked_at` | timestamp, nullable | |
| `invited_by` | foreignId → users | Nullable on delete, so removing a user does not remove the history. |
| timestamps | | |

`role` is a plain string cast to the enum, matching `users.role` and every other
enum column in this application: adding a role stays a one-line change with no
migration.

### The token

64 random characters from `Str::random(64)`, stored only as
`hash('sha256', $token)`. The plaintext exists in the URL, in the email, and in
the panel's copy field — never in the database. A leaked database yields no
working links.

Lookup is by hash, so it is an indexed equality match rather than a scan, and
the comparison is `hash_equals` against the stored value to keep it
timing-independent.

### One live invite per address

An invite is **usable** when `accepted_at`, `revoked_at` are both null and
`expires_at` is in the future. There may be at most one usable invite per email
address.

This is enforced in `CreateInvite`, not by a database index. A partial unique
index (`UNIQUE(email) WHERE accepted_at IS NULL ...`) is exactly the right tool
and SQLite supports it, but **MySQL does not** — and MySQL is the development
connection while SQLite is CI's. An index that exists on one and not the other
is worse than no index, because the rule would then be tested on a database that
does not resemble the one in use.

`CreateInvite` therefore revokes any existing usable invite for the address
before issuing a new one, inside a transaction. "Invite her again" is a normal
thing to do when the first link expired, and it should not error.

Creating an invite for an address that already has a user is a validation
failure, not a silent no-op.

---

## Accepting

Two routes, public, outside `auth`:

```
GET  /invite/{token}   invite.show
POST /invite/{token}   invite.store
```

Both rate-limited to six attempts per minute per IP. A token that is expired, revoked, already accepted,
malformed or simply wrong returns **404** — the four are indistinguishable, for
the same reason a hidden area 404s in Spec A. A signed-in visitor who opens an
invite link is redirected to the dashboard rather than shown the form.

The form collects `name`, `password` and `password_confirmation`. The email is
displayed but not editable, and the submitted value is ignored entirely if one
is sent: the invite is the authority on who this account is for.

`AcceptInvite` runs in a transaction and, in order:

1. Re-resolves the invite by token hash **with `lockForUpdate()`** and
   re-checks usability. The check at display time is not trusted at submit
   time, and the lock is what makes two simultaneous accepts safe rather than
   merely unlikely.
2. Creates the user with the invite's `email` and `role`, the submitted name,
   and a hashed password.
3. Sets `email_verified_at` — see *Verification*.
4. Sets `accepted_at`.
5. Signs her in and regenerates the session.

Two concurrent accepts of the same invite must produce one user, not two. The
row lock serialises them: the second transaction blocks until the first commits,
then reads `accepted_at` as set and 404s like any other used token. The unique
index on `users.email` is the backstop if the lock is ever lost to a refactor —
the second insert fails rather than producing a duplicate account.

She lands on her profile with a flash notification inviting her to add a
passkey. No column tracks whether the prompt was shown — passkeys are already
enabled at `config/fortify.php:175`, and a one-time notification is enough for a
household of two.

### Verification

`email_verified_at` is set at acceptance. Clicking a link delivered to an
address proves control of that address at least as well as a verification mail
would, and sending a second mail immediately after the first is noise.

`Features::emailVerification()` stays **off**. Turning it on would put the
owner's existing account — and any account created before this spec — behind a
verification wall for no gain.

---

## Password reset

`Features::resetPasswords()` is enabled, and the comment above the features
array is rewritten to say what is now true.

Two Inertia pages join the three that exist:

- `Auth/ForgotPassword.vue`, posting to `/forgot-password`
- `Auth/ResetPassword.vue`, posting to `/reset-password` with a hidden `token`
  from `request()->route('token')`

Both are wired through `Fortify::requestPasswordResetLinkView()` and
`Fortify::resetPasswordView()` in `FortifyServiceProvider`, beside the three
calls already there. Fortify registers the routes and the `password.reset` named
route that `Illuminate\Auth\Notifications\ResetPassword` builds its URL from.

This is the part that makes an emailed invite safe to depend on. Without it, a
forgotten password means the owner editing the database by hand, which is
precisely the dependence this spec exists to remove.

A password reset for an address with no account must behave identically to one
with an account. Laravel's default already does this; a test pins it.

---

## The screen

**People**, in `Area::General` — owner-only through the existing area mapping,
so nothing new is needed to keep it away from a member.

It lists two things:

- **Users**: name, email, role, when they joined.
- **Invites**: email, role, status, expiry, who sent it.

Status is derived, never stored: *pending*, *accepted*, *revoked* or *expired*,
computed from the three timestamps. A stored status column would be a second
source of truth that a clock can falsify.

Actions: **invite**, **revoke**, **resend** and **copy link**. The link is shown
in the panel alongside the email exactly as asked, so a delivery failure or a
spam folder is recoverable without a database query.

The table reuses the project's `ResourceTable` contract. The **form** contract
is not reused: an invite is created and revoked, never edited, so a modal action
with two fields fits better than a resource form with an edit route that would
have to be disabled.

Routes are added to the General group in `routes/admin.php`, which means the
Spec A access matrix covers them the moment they exist — it is generated from
the router, so no test needs updating to gain that coverage.

`CreateNewUser` gets `role` wired in. It is Fortify's registration action and
currently ignores the column; leaving it ignorant would make it a loaded gun for
whoever enables registration next.

---

## What this leaves behind

`MEMBER_EMAIL` seeding stays. `migrate:fresh --seed` giving a working member
account is worth more during development than the tidiness of a single path, and
the seeder is not a security surface.

---

## Testing

The cases that matter are the ones where a token is not what it claims to be:

- An **expired** token 404s, and the invite is still visibly expired in the panel.
- A **revoked** token 404s.
- A token **reused after acceptance** 404s and creates no second user.
- A **tampered** token 404s — one character changed.
- A token that is **valid but for an address that now has a user** fails without
  creating anything.
- **Two concurrent accepts** of one invite produce exactly one user.
- Creating an invite for an address that **already has a user** is a validation
  error.
- Creating a second invite for an address that already has a **usable** invite
  revokes the first rather than erroring, and the first link then 404s.
- An **already signed-in** visitor is redirected, not shown the form.

And the ones about what she ends up with:

- The accepted user has exactly the areas the invite's role names — asserted
  against `Area::cases()`, not a hand-written list.
- An invite naming `admin` produces an admin. The route is owner-only, so this
  is asserted at the action level.
- `email_verified_at` is set.
- She is signed in afterwards, and the session id changed.

Mail:

- An invite sends exactly one mail, to the invited address, containing the
  plaintext token.
- The token in the mail is **not** the value stored in `token_hash`.
- Revoking sends nothing.
- Resending sends again without changing the token, so an earlier copy of the
  link still works.

Password reset:

- A reset for an unknown address responds identically to one for a known
  address.
- The reset pages render.

The rate limiter is asserted by exhausting it, not by reading the configuration.

---

## Risks

| Risk | Mitigation |
| --- | --- |
| A leaked database yields working invite links | Only the SHA-256 hash is stored |
| Mail is misconfigured and the invite never arrives | The link is shown in the panel; delivery is not the only path |
| She forgets her password and is locked out | Password reset is enabled in this same spec, which is why it is here and not later |
| Two accepts race and create two users | Re-check inside a transaction, plus the unique index on `users.email` |
| An expired link looks like a broken site | 404 is deliberate and consistent with Spec A; the panel shows the real status |
| MySQL and SQLite disagree about partial indexes | The one-live-invite rule lives in application code, tested on both |
| `CreateNewUser` ignoring `role` becomes a hole later | Wired up in this spec even though registration stays off |
