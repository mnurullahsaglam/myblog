# myblog-mobile Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** An iPhone app for two people that does everything the panel does, works with no signal, and never shows one of them something the panel would not.

**Architecture:** Four layers — `APIClient`, `Store` (response cache and write queue), `Schema`, and two renderers that build every list and form from the schemas the API now exposes. Fifteen resources cost fifteen PHP declarations that already exist rather than forty SwiftUI screens, and the field hiding arrives on the phone without the phone knowing abilities exist.

**Tech Stack:** Swift 6.3, SwiftUI with Observation, Swift Concurrency, Swift Testing. **Zero third-party dependencies.** iOS 18 minimum, Xcode 26.6.

**Spec:** `docs/superpowers/specs/2026-09-20-swift-client-design.md`

**Repository:** `myblog-mobile` (private, already created). This plan and the spec move there in Task 1.

## Global Constraints

- **iOS 18 minimum.** No API newer than 18 without a fallback.
- **Zero third-party dependencies.** `URLSession` and the Security framework directly.
- **No App Transport Security exceptions.** If a device cannot reach the server, that is a tunnel with a trusted certificate, not a hole in the app.
- **Money is `Decimal`, never `Double`.** A household budget that rounds is a household budget that argues.
- **One `JSONDecoder` date strategy**, which v0.13.1 made possible. Do not add per-model decoders.
- **The idempotency key is generated when a write is created, not when it is sent.** A key per attempt defeats the mechanism entirely.
- **Swift Testing**, not XCTest. `@Test` and `#expect`.
- **Fixtures are captured from the running API**, never hand-written. A fixture that drifts from the server is worse than no fixture.
- **Commit messages carry no AI attribution trailer.** Conventional Commits.
- **Every commit builds and its tests pass:** `xcodebuild test -scheme myblog-mobile -destination 'platform=iOS Simulator,name=iPhone 17'`.

---

## File Structure

| Path | Responsibility |
| --- | --- |
| `myblog-mobile.xcodeproj` | The project |
| `Sources/App/MyblogApp.swift` | Entry point, root routing between locked, signed-out and signed-in |
| `Sources/Networking/APIClient.swift` | One typed entry point: auth header, decoding, error mapping |
| `Sources/Networking/APIError.swift` | Transport, 401, 403/404, 422 with field messages, 5xx |
| `Sources/Networking/Endpoint.swift` | Path, method, query, body, idempotency key |
| `Sources/Auth/TokenStore.swift` | Keychain read, write, delete |
| `Sources/Auth/BiometricGate.swift` | `LAContext`, passcode fallback |
| `Sources/Auth/SessionModel.swift` | Sign in, the 423 step, sign out |
| `Sources/Store/ResponseCache.swift` | Keyed response bodies with fetch times |
| `Sources/Store/WriteQueue.swift` | Persisted pending writes, flush, backoff |
| `Sources/Schema/TableSchema.swift` | Decoded columns, filters, sort |
| `Sources/Schema/FormSchema.swift` | Decoded fields, options, validation hints |
| `Sources/Views/ResourceListView.swift` | The one list renderer |
| `Sources/Views/ResourceFormView.swift` | The one form renderer |
| `Sources/Views/FieldControl.swift` | Field type to SwiftUI control |
| `Sources/Views/RootTabView.swift` | Tabs built from the areas the API answers |
| `Sources/Views/SignInView.swift` | Credentials and the two-factor step |
| `Sources/Views/SettingsView.swift` | Account, server, queue, sign out |
| `Tests/**` | Swift Testing, fixtures under `Tests/Fixtures` |
| `.github/workflows/ci.yml` | `xcodebuild test` on a macOS runner |

---

### Task 1: The project, and a request that arrives

**Files:**
- Create: the Xcode project, `APIClient`, `Endpoint`, `APIError`, `.github/workflows/ci.yml`
- Test: `Tests/APIClientTests.swift`

**Interfaces:**
- Produces: `APIClient.send(_:)` returning decoded values or a typed `APIError`.

**Why this first.** Everything else is untestable until a request can be made
and a response decoded. The first task ends with a real fixture decoding.

- [ ] **Step 1: Create the project**

```bash
cd ~/Herd
git clone https://github.com/mnurullahsaglam/myblog-mobile.git
cd myblog-mobile
```

Create an Xcode project: iOS App, SwiftUI, Swift Testing, **minimum deployment
iOS 18.0**, organisation identifier of your choosing, product name
`myblog-mobile`.

Move the two documents across:

```bash
mkdir -p docs
cp ~/Herd/myblog/docs/superpowers/specs/2026-09-20-swift-client-design.md docs/
cp ~/Herd/myblog/docs/superpowers/plans/2026-09-20-swift-client.md docs/
```

and delete them from `myblog` in the same commit there, so one copy exists.

- [ ] **Step 2: Capture the fixtures**

With Herd running and a token minted from tinker:

```bash
TOKEN=$(cd ~/Herd/myblog && php artisan tinker --execute '
  $o = App\Models\User::query()->where("email", config("app.admin_email"))->firstOrFail();
  echo $o->createToken("fixtures")->plainTextToken;' | tail -1)

mkdir -p Tests/Fixtures

for r in expenses incomes debts utility-bills utility-accounts books writers publishers posts categories clients projects repositories invoices waka-time-summaries; do
  curl -sk -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" \
    "https://myblog.test/api/v1/$r?perPage=3" > "Tests/Fixtures/$r.json"
  curl -sk -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" \
    "https://myblog.test/api/v1/$r/schema" > "Tests/Fixtures/$r.schema.json"
done
```

Revoke the token afterwards. **These are captured, never hand-written** — a
fixture that drifts from the server is worse than no fixture, because it passes.

- [ ] **Step 3: Write the failing test**

`Tests/APIClientTests.swift`:

```swift
import Testing
import Foundation
@testable import myblog_mobile

@Suite("APIClient")
struct APIClientTests {
    @Test("decodes a collection envelope")
    func decodesCollection() throws {
        let data = try Fixture.load("expenses")
        let page = try APIClient.decoder.decode(Page<Expense>.self, from: data)

        #expect(page.data.count == 3)
        #expect(page.meta.currentPage == 1)
        #expect(page.meta.total > 0)
    }

    /// Money is a string over the wire because Postgres returns a decimal that
    /// way. Decoding it as Double would round a household's money.
    @Test("decodes money as Decimal, not Double")
    func decodesMoney() throws {
        let page = try APIClient.decoder.decode(Page<Expense>.self, from: Fixture.load("expenses"))

        #expect(page.data[0].amount == Decimal(string: "1200.00"))
    }

    /// Dates arrive in two shapes: Y-m-d for date columns and ISO 8601 with an
    /// offset for timestamps. One strategy handles both, which is only possible
    /// because the API was made consistent first.
    @Test("decodes both date shapes with one strategy")
    func decodesDates() throws {
        let page = try APIClient.decoder.decode(Page<Expense>.self, from: Fixture.load("expenses"))

        #expect(page.data[0].date != nil)
        #expect(page.data[0].createdAt != nil)
    }

    @Test("maps a 401 to an unauthenticated error")
    func mapsUnauthorized() async throws {
        let client = APIClient(transport: StubTransport(status: 401, body: Data("{}".utf8)))

        await #expect(throws: APIError.unauthenticated) {
            _ = try await client.send(Endpoint.index("expenses"), as: Page<Expense>.self)
        }
    }

    /// A 422 carries the field messages the server produced. The app shows those
    /// rather than reimplementing the rules.
    @Test("carries field messages out of a 422")
    func mapsValidation() async throws {
        let body = Data(#"{"message":"invalid","errors":{"amount":["The amount field is required."]}}"#.utf8)
        let client = APIClient(transport: StubTransport(status: 422, body: body))

        do {
            _ = try await client.send(Endpoint.index("expenses"), as: Page<Expense>.self)
            Issue.record("expected a validation error")
        } catch let APIError.validation(messages) {
            #expect(messages["amount"]?.first == "The amount field is required.")
        }
    }

    @Test("sends the bearer token")
    func sendsToken() async throws {
        let transport = StubTransport(status: 200, body: try Fixture.load("expenses"))
        let client = APIClient(transport: transport, token: { "abc-123" })

        _ = try await client.send(Endpoint.index("expenses"), as: Page<Expense>.self)

        #expect(transport.lastRequest?.value(forHTTPHeaderField: "Authorization") == "Bearer abc-123")
    }
}
```

with `Fixture.load` reading from the test bundle and `StubTransport` conforming
to whatever protocol `APIClient` takes, so no test touches the network.

- [ ] **Step 4: Run it and watch it fail**

```bash
xcodebuild test -scheme myblog-mobile -destination 'platform=iOS Simulator,name=iPhone 17' 2>&1 | tail -20
```

Expected: it does not compile, because none of those types exist.

- [ ] **Step 5: Write the client**

`Endpoint` carries a path, method, query items, an optional body and an optional
idempotency key. `APIClient` takes a transport protocol — `URLSession` in the
app, a stub in tests — a base URL from the build setting, and a token closure.

The decoder:

```swift
static let decoder: JSONDecoder = {
    let decoder = JSONDecoder()
    decoder.keyDecodingStrategy = .convertFromSnakeCase
    decoder.dateDecodingStrategy = .custom { decoder in
        let text = try decoder.singleValueContainer().decode(String.self)

        if let date = dateOnly.date(from: text) { return date }
        if let date = iso8601.date(from: text) { return date }

        throw DecodingError.dataCorrupted(
            .init(codingPath: decoder.codingPath, debugDescription: "Unrecognised date: \(text)")
        )
    }
    return decoder
}()
```

**Date-only is tried first**, because an ISO 8601 parser given `2026-09-15` can
succeed with a midnight local time and silently shift the day across a timezone.

Status mapping: 401 unauthenticated, 403 or 404 notFound — the API deliberately
makes them indistinguishable — 422 validation with the field messages, 5xx
server, anything else transport.

- [ ] **Step 6: Run the tests**

Expected: PASS, six tests.

- [ ] **Step 7: CI**

`.github/workflows/ci.yml` running on `macos-15`:

```yaml
name: CI
on:
  push: { branches: [main] }
  pull_request:
jobs:
  test:
    runs-on: macos-15
    steps:
      - uses: actions/checkout@v5
      - run: xcodebuild test -scheme myblog-mobile -destination 'platform=iOS Simulator,name=iPhone 16'
```

Check which simulators the runner image actually has before pinning a name; a
missing device is a confusing failure.

- [ ] **Step 8: Commit**

```bash
git add .
git commit -m "feat: add the api client and its fixtures"
git push -u origin main
```

---

### Task 2: Signing in, and keeping the token safe

**Files:**
- Create: `TokenStore`, `BiometricGate`, `SessionModel`, `SignInView`
- Test: `Tests/TokenStoreTests.swift`, `Tests/SessionModelTests.swift`

**Interfaces:**
- Produces: `SessionModel` with `signIn(email:password:deviceName:code:)`, `signOut()`, and a `state` of `.locked`, `.signedOut`, `.signedIn`.

- [ ] **Step 1: Write the failing tests**

```swift
@Suite("Signing in")
struct SessionModelTests {
    private func session(_ responses: [StubResponse]) -> SessionModel {
        SessionModel(client: APIClient(transport: StubTransport(responses)), store: InMemoryTokenStore())
    }

    @Test("stores the token on success")
    func storesToken() async throws {
        let session = session([.init(status: 200, body: #"{"token":"abc-123","user":{"id":1,"name":"Her","email":"her@example.test"}}"#)])

        try await session.signIn(email: "her@example.test", password: "x", deviceName: "iPhone")

        #expect(session.state == .signedIn)
        #expect(try session.store.read() == "abc-123")
    }

    /// The account has two-factor on. A 423 is not a failure, it is the second
    /// step; treating it as an error would make the app unusable for the only
    /// two people who will ever run it.
    @Test("asks for a code when the server returns 423")
    func asksForCode() async throws {
        let session = session([.init(status: 423, body: #"{"two_factor":true}"#)])

        try? await session.signIn(email: "her@example.test", password: "x", deviceName: "iPhone")

        #expect(session.step == .twoFactor)
        #expect(try session.store.read() == nil)
    }

    @Test("stores nothing when the code is wrong")
    func wrongCode() async throws {
        let session = session([
            .init(status: 423, body: #"{"two_factor":true}"#),
            .init(status: 423, body: #"{"two_factor":true}"#),
        ])

        try? await session.signIn(email: "her@example.test", password: "x", deviceName: "iPhone")
        try? await session.submitCode("000000")

        #expect(session.step == .twoFactor)
        #expect(try session.store.read() == nil)
        #expect(session.state != .signedIn)
    }

    @Test("sends the device name so the panel can name it")
    func sendsDeviceName() async throws {
        let transport = StubTransport([.init(status: 200, body: #"{"token":"abc","user":{"id":1,"name":"H","email":"h@e.test"}}"#)])
        let session = SessionModel(client: APIClient(transport: transport), store: InMemoryTokenStore())

        try await session.signIn(email: "her@example.test", password: "x", deviceName: "Her iPhone")

        let body = try #require(transport.lastRequest?.httpBody)
        let sent = try JSONSerialization.jsonObject(with: body) as? [String: Any]

        #expect(sent?["device_name"] as? String == "Her iPhone")
    }

    @Test("signing out clears the token and tells the server")
    func signsOut() async throws {
        let transport = StubTransport([
            .init(status: 200, body: #"{"token":"abc","user":{"id":1,"name":"H","email":"h@e.test"}}"#),
            .init(status: 204, body: ""),
        ])
        let session = SessionModel(client: APIClient(transport: transport), store: InMemoryTokenStore())

        try await session.signIn(email: "h@e.test", password: "x", deviceName: "iPhone")
        await session.signOut()

        #expect(try session.store.read() == nil)
        #expect(session.state == .signedOut)
        #expect(transport.lastRequest?.httpMethod == "DELETE")
    }

    /// A failed sign-in must not leave a half-signed-in app.
    @Test("a transport failure leaves the session signed out")
    func transportFailure() async throws {
        let session = SessionModel(
            client: APIClient(transport: FailingTransport()),
            store: InMemoryTokenStore(),
        )

        try? await session.signIn(email: "h@e.test", password: "x", deviceName: "iPhone")

        #expect(session.state == .signedOut)
        #expect(try session.store.read() == nil)
    }

    /// Signing out locally must still happen when the server cannot be reached,
    /// or a phone with no signal cannot be signed out at all.
    @Test("clears the token even when the server call fails")
    func signsOutOffline() async throws {
        let session = SessionModel(client: APIClient(transport: FailingTransport()), store: InMemoryTokenStore())
        try session.store.write("abc-123")

        await session.signOut()

        #expect(try session.store.read() == nil)
        #expect(session.state == .signedOut)
    }
}
```


- [ ] **Step 2: Write the token store**

`TokenStore` wraps the Security framework directly. The item is stored with
`kSecAttrAccessibleWhenUnlockedThisDeviceOnly`, so it never reaches iCloud and
never leaves the device. Read, write and delete, each returning a result rather
than trapping.

**Test it against the real Keychain**, not a mock. A Keychain wrapper that is
only ever exercised against a stub is a wrapper that has never been tested.

- [ ] **Step 3: Write the biometric gate**

`LAContext` with `.deviceOwnerAuthentication`, which falls back to the passcode
on its own. Gate on launch and on returning from background after a grace
period.

**If biometrics are unavailable or refused, the app stays locked** rather than
falling open. There is no "skip".

- [ ] **Step 4: Write the session model and the sign-in view**

One screen, two steps. The 423 response moves it to the code step; the same
screen then posts email, password, device name and code together, because the
API's endpoint takes them together.

`UIDevice.current.name` is the default device name, editable before submitting,
so the panel's People screen shows something recognisable.

- [ ] **Step 5: Run the tests, then try it**

Run the suite, then sign in from the Simulator against Herd and confirm the
device appears on the panel's People screen with the name you gave it.

- [ ] **Step 6: Commit**

```bash
git commit -m "feat: sign in, honouring two-factor, and keep the token in the keychain"
```

---

### Task 3: The cache and the write queue

**Files:**
- Create: `ResponseCache`, `WriteQueue`
- Test: `Tests/ResponseCacheTests.swift`, `Tests/WriteQueueTests.swift`

**Interfaces:**
- Produces: `ResponseCache.store(_:for:)`, `.load(for:)` with a fetch time; `WriteQueue.enqueue(_:)`, `.flush()`.

- [ ] **Step 1: Write the failing tests**

The cases that matter:

```swift
@Suite("Response cache")
struct ResponseCacheTests {
    private func cache() -> ResponseCache {
        ResponseCache(directory: FileManager.default.temporaryDirectory.appending(path: UUID().uuidString))
    }

    /// The reason the cache exists: the Underground.
    @Test("serves a stored body when the request fails")
    func servesOffline() async throws {
        let cache = cache()
        let key = CacheKey(method: "GET", path: "/v1/expenses", query: [:])

        try cache.store(Data("cached".utf8), for: key)

        #expect(try cache.load(for: key)?.body == Data("cached".utf8))
    }

    @Test("reports when it was fetched")
    func reportsAge() async throws {
        let cache = cache()
        let key = CacheKey(method: "GET", path: "/v1/expenses", query: [:])

        try cache.store(Data("x".utf8), for: key)

        let entry = try #require(cache.load(for: key))

        #expect(entry.fetchedAt.timeIntervalSinceNow > -5)
    }

    @Test("replaces a stored body on a fresh fetch")
    func replaces() async throws {
        let cache = cache()
        let key = CacheKey(method: "GET", path: "/v1/expenses", query: [:])

        try cache.store(Data("old".utf8), for: key)
        try cache.store(Data("new".utf8), for: key)

        #expect(try cache.load(for: key)?.body == Data("new".utf8))
    }

    /// Different queries are different answers, not one overwriting the other.
    @Test("keys separately by query")
    func keysByQuery() async throws {
        let cache = cache()
        let one = CacheKey(method: "GET", path: "/v1/expenses", query: ["page": "1"])
        let two = CacheKey(method: "GET", path: "/v1/expenses", query: ["page": "2"])

        try cache.store(Data("first".utf8), for: one)
        try cache.store(Data("second".utf8), for: two)

        #expect(try cache.load(for: one)?.body == Data("first".utf8))
        #expect(try cache.load(for: two)?.body == Data("second".utf8))
    }

    @Test("survives a relaunch")
    func persists() async throws {
        let directory = FileManager.default.temporaryDirectory.appending(path: UUID().uuidString)
        let key = CacheKey(method: "GET", path: "/v1/expenses", query: [:])

        try ResponseCache(directory: directory).store(Data("kept".utf8), for: key)

        #expect(try ResponseCache(directory: directory).load(for: key)?.body == Data("kept".utf8))
    }

    @Test("clearing empties it")
    func clearing() async throws {
        let cache = cache()
        let key = CacheKey(method: "GET", path: "/v1/expenses", query: [:])

        try cache.store(Data("x".utf8), for: key)
        try cache.clear()

        #expect(try cache.load(for: key) == nil)
    }
}

@Suite("Write queue")
struct WriteQueueTests {
    /// The reason the queue exists.
    @Test("sends a queued write once when connectivity returns")
    func sendsOnce() async throws {
        let transport = StubTransport([.init(status: 201, body: #"{"data":{"id":1}}"#)])
        let queue = WriteQueue(client: APIClient(transport: transport), directory: .temporary())

        queue.enqueue(.create("expenses", body: ["amount": 100]))
        await queue.flush()

        #expect(transport.requestCount == 1)
        #expect(queue.pending.isEmpty)
    }

    /// The single most important assertion in this file. A key generated per
    /// attempt would make the server treat each retry as a new write, and the
    /// month's spending would be wrong.
    @Test("reuses the same idempotency key across retries")
    func stableKey() async throws {
        let transport = StubTransport([
            .init(status: 500, body: ""),
            .init(status: 500, body: ""),
            .init(status: 201, body: #"{"data":{"id":1}}"#),
        ])
        let queue = WriteQueue(client: APIClient(transport: transport), directory: .temporary())

        queue.enqueue(.create("expenses", body: ["amount": 100]))

        await queue.flush()
        await queue.flush()
        await queue.flush()

        #expect(Set(transport.idempotencyKeys).count == 1)
        #expect(transport.requestCount == 3)
    }

    @Test("stops retrying on a 422 and surfaces the message")
    func stopsOn422() async throws {
        let body = #"{"message":"invalid","errors":{"amount":["The amount field is required."]}}"#
        let transport = StubTransport([.init(status: 422, body: body)])
        let queue = WriteQueue(client: APIClient(transport: transport), directory: .temporary())

        queue.enqueue(.create("expenses", body: [:]))

        await queue.flush()
        await queue.flush()

        #expect(transport.requestCount == 1)
        #expect(queue.pending.first?.lastError?.contains("amount") == true)
    }

    @Test("retries a 500")
    func retriesOn500() async throws {
        let transport = StubTransport([
            .init(status: 500, body: ""),
            .init(status: 201, body: #"{"data":{"id":1}}"#),
        ])
        let queue = WriteQueue(client: APIClient(transport: transport), directory: .temporary())

        queue.enqueue(.create("expenses", body: ["amount": 100]))

        await queue.flush()
        #expect(queue.pending.count == 1)

        await queue.flush()
        #expect(queue.pending.isEmpty)
    }

    @Test("survives a relaunch with the write still pending")
    func persists() async throws {
        let directory = URL.temporary()
        let first = WriteQueue(client: APIClient(transport: FailingTransport()), directory: directory)

        first.enqueue(.create("expenses", body: ["amount": 100]))
        await first.flush()

        let second = WriteQueue(client: APIClient(transport: StubTransport([.init(status: 201, body: #"{"data":{"id":1}}"#)])), directory: directory)

        #expect(second.pending.count == 1)

        await second.flush()

        #expect(second.pending.isEmpty)
    }

    @Test("keeps order within one resource")
    func ordering() async throws {
        let transport = StubTransport([
            .init(status: 201, body: #"{"data":{"id":1}}"#),
            .init(status: 201, body: #"{"data":{"id":2}}"#),
        ])
        let queue = WriteQueue(client: APIClient(transport: transport), directory: .temporary())

        queue.enqueue(.create("expenses", body: ["description": "first"]))
        queue.enqueue(.create("expenses", body: ["description": "second"]))

        await queue.flush()

        #expect(transport.sentDescriptions == ["first", "second"])
    }
}
```


- [ ] **Step 2: Write them**

Both persist to the app's support directory as JSON, because the volume is a
household's records and not a dataset. `ResponseCache` is keyed by method, path
and sorted query. `WriteQueue` holds an id, an endpoint, a body, a **key
generated at enqueue time**, an attempt count and a last error.

- [ ] **Step 3: Run the tests and commit**

```bash
git commit -m "feat: cache responses and queue writes made offline"
```

---

### Task 4: The two renderers

**Files:**
- Create: `TableSchema`, `FormSchema`, `FieldControl`, `ResourceListView`, `ResourceFormView`
- Test: `Tests/SchemaTests.swift`, `Tests/RendererTests.swift`

**Interfaces:**
- Consumes: `GET /api/v1/{resource}/schema`.
- Produces: one list renderer and one form renderer covering all fifteen resources.

**This is the task the whole approach rests on.** Fifteen resources become
fifteen schemas that already exist in PHP.

- [ ] **Step 1: Write the failing tests**

```swift
@Suite("Schema")
struct SchemaTests {
    /// Every fixture, so a resource whose schema the app cannot read fails here
    /// rather than on a phone.
    @Test("decodes every captured schema", arguments: Fixture.allSchemas)
    func decodesEvery(name: String) throws {
        let schema = try APIClient.decoder.decode(ResourceSchema.self, from: Fixture.load(name))

        #expect(!schema.table.columns.isEmpty)
    }

    @Test("reads a null form for a read-only resource")
    func readOnly() throws {
        let schema = try APIClient.decoder.decode(ResourceSchema.self, from: Fixture.load("waka-time-summaries.schema"))

        #expect(schema.form == nil)
    }

    /// The field hiding arrives from the server. The app does not filter.
    @Test("has no client field in a member's income schema")
    func memberSchema() throws {
        let schema = try APIClient.decoder.decode(ResourceSchema.self, from: Fixture.load("incomes.member.schema"))

        #expect(!schema.form!.fields.contains { $0.key == "client_id" })
    }

    /// A field type the app has never seen must not crash it.
    @Test("falls back for an unknown field type")
    func unknownType() throws {
        let field = try APIClient.decoder.decode(FormField.self, from: Data(#"{"key":"x","type":"quantum","label":"X"}"#.utf8))

        #expect(field.type == .unknown)
    }
}
```

Capture `incomes.member.schema` with a token belonging to the seeded member, so
the hidden-field assertion is against a real response.

- [ ] **Step 2: Model the schemas**

`FieldType` and `ColumnType` are enums with an `unknown` case, decoded from the
string. **Unknown is not an error.** A Laravel field type added next year renders
as a read-only label rather than crashing an app nobody can update quickly.

- [ ] **Step 3: Write `FieldControl`**

The mapping from the spec: text, textarea, money, number, date, datetime,
select, multiselect, toggle, placeholder, image, isbn, repeater. `money` and
`number` bind `Decimal` with a decimal keypad. `image`, `repeater` and unknown
render read-only, which the spec records as a limitation rather than a bug.

- [ ] **Step 4: Write the renderers**

`ResourceListView` takes a schema and a page and renders rows by column type,
respecting `hiddenByDefault`. `ResourceFormView` takes a schema and values,
renders a `Form`, and attaches 422 messages by field key.

- [ ] **Step 5: Run the tests and commit**

```bash
git commit -m "feat: render every list and form from the server's schema"
```

---

### Task 5: Tabs, and the app itself

**Files:**
- Create: `RootTabView`, `MyblogApp`, `SettingsView`
- Test: `Tests/RootTabTests.swift`

- [ ] **Step 1: Write the failing tests**

- A member's token produces Budget, Utilities and Library.
- An admin's produces all six.
- The tabs come from what the API answers, **not** from a map in Swift. There is
  no client-side list of which role sees what; if there were, it would be a
  second source of truth and the wrong one.
- A resource that 404s is absent rather than shown broken.

Deriving the areas needs a source. The API has no "what can I reach" endpoint —
the probe route was deliberately removed. Derive them by attempting each area's
index once at sign-in and keeping those that answer, caching the result. If that
proves clumsy, the honest fix is a small `GET /api/v1/me` returning the areas,
added to the API with its own test — not a hardcoded map in Swift.

- [ ] **Step 2: Build the shell**

Root switches between locked, signed-out and signed-in. Each tab is a
`NavigationStack` over the resources in that area. Settings shows the account,
the server, the pending queue and sign out.

- [ ] **Step 3: Run it**

In the Simulator, sign in as the owner and then as the seeded member, and
confirm the tabs differ and the incomes list shows no client for her.

- [ ] **Step 4: Commit**

```bash
git commit -m "feat: build the tabs from the areas the server grants"
```

---

### Task 6: Release

- [ ] **Step 1: Full test run and a build for a device**

```bash
xcodebuild test -scheme myblog-mobile -destination 'platform=iOS Simulator,name=iPhone 17'
xcodebuild -scheme myblog-mobile -destination 'generic/platform=iOS' build
```

- [ ] **Step 2: Walk it as both people**

Sign in as the owner, then as the member. Confirm on the phone what the tests
assert: different tabs, no client identity anywhere for her, an income with a
client not editable by her, and a write made in Airplane Mode arriving once when
the signal returns.

- [ ] **Step 3: Tag**

```bash
git tag v0.1.0
git push --tags
```

- [ ] **Step 4: Write down what is missing**

A `docs/limitations.md` naming what the spec already records: no image upload,
no repeater editing, no barcode scanning, no push, and a cache that cannot
answer an offline question it was never asked. Written down, these are known
gaps; undocumented, they are bugs somebody rediscovers.
