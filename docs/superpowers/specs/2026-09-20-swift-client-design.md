# myblog-mobile — Design

**Date:** 2026-09-20
**Status:** Approved, not yet implemented
**Follows:** the mobile API (v0.13.1)
**Lives in:** `myblog-mobile`, a new repository. This document is written here
because that repository does not exist yet, and moves with the code.

---

## Goal

An iPhone app for two people that does everything the panel does, works on the
Underground, and never shows one of them something the panel would not.

## Non-Goals

- **iPad, Mac, Watch.** iPhone, portrait, iOS 18 and later.
- **The App Store.** Two devices, distributed through the developer account.
- **Push notifications.** They need a server that is awake; Plesk is not up yet.
  The token registration hook is designed for but not built.
- **Offline writes to relationships.** Queued writes cover the common case: a
  new expense, a paid bill. Re-pointing a foreign key while offline is not worth
  the conflict rules it would need.
- **Image upload.** See *What the phone cannot do yet*.

---

## Prerequisite: the schema endpoint

The panel renders every list and form from a server-driven schema:
`ResourceTable::schema()` returns typed columns, `ResourceForm::schema()` returns
typed fields. One Vue component covers fifteen resources because of it.

The app does the same, so the API gains one endpoint:

```
GET /api/v1/{resource}/schema
  → { table: {columns, filters, defaultSort, searchable, perPage},
      form:  {fields, columns} }
```

**It inherits the access model for free.** `schema()` already calls
`visibleColumns()` and `visibleFields()`, which filter on `AccessProfile`. A
member asking for the incomes schema does not receive the client field, so the
phone cannot render a control for something she may not see — without a single
line of iOS code knowing that abilities exist.

This is the whole argument for schema-driven UI here: the security work lands on
the phone automatically, and fifteen resources cost fifteen PHP declarations
that already exist rather than forty SwiftUI screens.

A field added in Laravel appears on the phone without an Xcode build.

---

## Architecture

SwiftUI with the Observation framework, Swift Concurrency throughout, **zero
third-party dependencies** — `URLSession` and the Security framework directly.
Tests in Swift Testing.

Four layers, each independently testable:

| Layer | Responsibility |
| --- | --- |
| `APIClient` | One typed entry point. Auth header, decoding, error mapping |
| `Store` | The response cache and the outbound write queue |
| `Schema` | Decoded table and form schemas, cached like any other response |
| `Views` | One list renderer, one form renderer, a handful of bespoke screens |

### Decoding

`JSONDecoder` with **one** date strategy, which is only possible because
v0.13.1 made every resource emit `Y-m-d` for dates and ISO 8601 with an offset
for timestamps. Dates arrive in two shapes, so the strategy is a custom closure
that tries the date-only format first and falls back to ISO 8601.

Money arrives as a **string** (`"1200.00"`) because that is what Postgres returns
for a decimal through PDO. It is decoded into `Decimal`, never `Double`. A
household budget that rounds is a household budget that argues.

---

## Authentication

1. Email, password, device name → `POST /api/v1/tokens`.
2. **423** means two-factor: the same screen asks for a six-digit code or a
   recovery code and posts again.
3. The plaintext token is written to the **Keychain** with
   `kSecAttrAccessibleWhenUnlockedThisDeviceOnly` — it never syncs to iCloud and
   never leaves the phone.
4. Every launch, and every return from background beyond a grace period, gates
   access behind **Face ID** (`LAContext`), falling back to the device passcode.

The token is not an app secret; it is a credential for one person on one device.
Losing the phone is answered from the panel's People screen, not from the app.

Signing out deletes the token locally **and** calls
`DELETE /api/v1/tokens/current`, so a stolen backup cannot resurrect it.

---

## The cache

**Responses are cached, not records.** One store, keyed by the request:

```swift
CachedResponse(key: "GET /v1/expenses?page=1", body: Data, fetchedAt: Date)
```

No `@Model` classes, no local schema, no migrations, no two-way sync. The server
remains the only source of truth, and deleting the cache loses nothing.

The cost is honest and worth naming: **the phone cannot query across cached
data**. It can show the pages it has already fetched and nothing else — no
offline search, no offline total across a filter that was never requested. For
reading last month's expenses on the Underground, that is enough. For anything
richer, this decision is the one to revisit.

Schemas are cached the same way, so a form still renders with no signal.

Freshness: a cached response is shown immediately and refreshed in the
background. The list shows when it was last updated, because a number with no
date is worse than no number.

---

## The write queue

A write made offline is appended to a queue persisted beside the cache and
flushed when connectivity returns.

Every queued write carries an **`Idempotency-Key`**, a UUID generated once when
the write is created and reused on every retry. The API remembers keys for 24
hours and replays the original response byte for byte, so a retry after a
dropped connection cannot record the expense twice. **The key is generated at
creation, not at send** — a key generated per attempt would defeat the entire
mechanism.

A queued write that fails with a 4xx is not retried: it is surfaced to the
person with the server's validation message, because the payload is wrong and
trying again will not fix it. A 5xx or a transport failure is retried with
backoff.

The queue is visible. A pending write shows in the list it belongs to, marked as
not yet sent.

---

## The interface

A **tab bar by area**, matching the panel's clusters and the areas the API
enforces. Tabs are built from the areas the signed-in user actually has, so a
member sees Budget, Utilities and Library and no others. There is no client-side
list of which role sees what; the tabs follow what the API answers.

### Two renderers

**`ResourceListView`** takes a table schema and a page of records. Column types
map to presentation:

| Column type | Rendered as |
| --- | --- |
| `text` | Label, truncated |
| `money` | Right-aligned, currency-formatted from the row's currency |
| `date`, `datetime` | Medium date, relative for recent timestamps |
| `badge` | Capsule, coloured from the schema's variant |
| `boolean` | SF Symbol checkmark or dash |
| `image` | Thumbnail, async-loaded |
| `count`, `number` | Right-aligned numeral |

**`ResourceFormView`** takes a form schema. Field types map to controls:

| Field type | Control |
| --- | --- |
| `text`, `textarea` | `TextField`, `TextField(axis: .vertical)` |
| `money`, `number` | `TextField` with a decimal pad, `Decimal` bound |
| `date`, `datetime` | `DatePicker` |
| `select`, `multiselect` | `Picker` from the schema's options |
| `toggle` | `Toggle` |
| `placeholder` | Read-only label |
| `image` | Read-only thumbnail — see below |
| `isbn`, `repeater` | See below |

Validation errors come from the server's 422 and are attached to the field by
key. The phone does not reimplement the rules; it shows what the API said.

### What the phone cannot do yet

Stated plainly rather than discovered later:

- **`image` fields are read-only.** The API rejects multipart uploads — the
  resource controller has no equivalent of the panel's `uploads()` hook. So a
  book cover or a bill document can be viewed, not replaced. Photographing a
  receipt, which was one of the original reasons for the app, needs that
  endpoint first.
- **`repeater` fields are read-only**, so a utility bill's itemised lines can be
  read but not edited on the phone. A repeater is a small form inside a form and
  deserves a deliberate design rather than a generic one.
- **`isbn`** renders as a plain text field. The lookup button is a panel
  affordance; scanning a barcode is the version worth building on a phone, and
  it is not this version.

### Bespoke screens

Not everything is a list of records:

- **Sign in**, including the two-factor step.
- **A dashboard per area** — this month's spending, bills due — built from the
  existing index endpoints rather than a new one.
- **Settings**: the signed-in account, the server address, sign out, and the
  pending-write queue.

---

## Development

The Simulator reaches Herd directly, because it trusts the Mac's certificates:
`https://myblog.test` works with no exception and no tunnel. The server address
is a build setting, so a real device later points at Plesk without a code
change.

**No App Transport Security exceptions.** If a real device needs to reach the Mac
before Plesk exists, that is a tunnel with a trusted certificate, not a hole in
the app that someone forgets to close.

CI is GitHub Actions on a macOS runner: `xcodebuild test` against an iOS 18
simulator, plus `swift format --lint`.

---

## Testing

The API is tested against the real database, so the app is tested against real
responses: fixtures captured from the running API, not hand-written JSON that
drifts from what the server sends.

- **Decoding**: every resource's fixture decodes, including both date shapes and
  money as `Decimal`. A fixture that stops matching the API is a failing test
  rather than a crash on someone's phone.
- **Auth**: a 423 leads to the code step; a wrong code does not store a token; a
  token is written to the Keychain and read back; signing out removes it locally
  and calls the API.
- **The cache**: a response is served from cache when offline; a stale entry is
  refreshed; clearing the cache loses no data.
- **The write queue**: a write made offline is queued; flushing sends it once; a
  retry reuses the same idempotency key; a 422 stops the retry and surfaces the
  message; a 500 retries with backoff.
- **The renderers**: a schema with every field type renders without crashing —
  the snapshot that catches a new Laravel field type the phone has never seen.
- **Tabs**: a member's token produces three tabs, an admin's produces six,
  driven by what the API returns rather than by a hardcoded map.

---

## Risks

| Risk | Mitigation |
| --- | --- |
| A new Laravel field type crashes the form | The renderer falls back to a read-only label for unknown types, and a test renders every known type |
| Schema-driven UI feels unnative | The renderers map to real SwiftUI controls, not a web view; bespoke screens exist where they earn it |
| The cache cannot answer an offline question | Named as a limitation above rather than hidden; revisit with a local store if it bites |
| A retried write posts twice | One idempotency key per write, generated at creation, honoured by the API for 24 hours |
| A member sees a field she should not | The schema is filtered server-side by the same `AccessProfile` the panel uses |
| The token leaks from a backup | Keychain, this-device-only, not iCloud-synced; revocable from the panel |
| Image capture was a founding reason and is absent | Stated as a gap; it needs a multipart endpoint in the API first |
