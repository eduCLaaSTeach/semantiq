# P1-09 — System Health: DESIGN

**DESIGN ONLY.** No implementation, no deployment.

| | |
| --- | --- |
| Unit | **P1-09 — System Health** (delivery order 11). **P1-10 still follows** |
| PLAN | merge `0385c92c0f959748911b86764b240a6896042782` — D-112 – D-129 **ANSWERED** |
| **Schema** | **NONE.** No table, no column, no migration. See §12 |
| Corrections | **Three applied** — see §14. Correction 1 records a **real defect the Product Owner found in the first draft of this document** |
| Status | **AWAITING PRODUCT OWNER REVIEW** |

---

## 0. The four sentences this design serves

1. **One authoritative check per operational fact.** A screen may project a
   check into an area; it may never reimplement one to appear there.
2. **A status is returned by a check, never assigned to a row.** Nothing can be
   Available because nobody looked.
3. **The row is the whole payload.** Four fields, allowlisted. There is no
   field for a hostname, a path or an exception, so none can reach the screen.
4. **Opening the page contacts nobody, and cannot.** Every call System Health
   makes is a method that holds no network path at all — not a networked method
   that is expected not to fire. The first draft of this design got exactly that
   wrong, and §14 records it.

---

## 1. The five areas, and the authoritative source behind every row

**14 rows: 9 projected from existing authoritative operational sources, 2
genuinely new round-trip checks, and 3 deployment-derived Jobs rows.**

Some of the existing checks are reached through a **new network-free entry
point** onto the same check, because the entry point P1-09 first chose was not
network-free (§13, correction 1). **No check logic is copied, reimplemented or
forked** — only the way in is new.

> **Count corrected after approval.** This section and §0 said *eleven* while
> the tables below listed fourteen — 3 + 1 + 3 + 4 + 3. The tables are what was
> designed and what was built; the number was simply wrong, and wrong in the
> first draft rather than introduced by a correction. The Product Owner
> approved the fix as a **documentation correction that does not reopen
> DESIGN**. The fourteen rows are asserted as an equality in
> `SystemHealthTest`, so the document and the implementation cannot drift
> again.

### Application

| Row | Status from | Source |
| --- | --- | --- |
| Database migrations | `ok` → Available, `!ok` → **Unavailable** | `HealthInspector` `migrations` — **existing check**, read via `inspectLocal()` (§1.2) |
| Required configuration | `ok` / `!ok` | `HealthInspector` `configuration` — **existing check**, via `inspectLocal()` |
| Application assets | `ok` / `!ok` | `HealthInspector` `assets` — **existing check**, via `inspectLocal()` |

### Integrations

| Row | Status from | Source |
| --- | --- | --- |
| Microsoft Entra ID | stored `failed` → **Unavailable**; stored `degraded` → **Degraded**; stored `healthy` → **Available**; **nothing trustworthy stored** → **Not checked** | `IdentityHealthCheck::storedReport()` — **NEW network-free entry point on the existing P1-02 check**, §1.1 |

**NOT `report()`, and NOT `forInspector()`. §1.1 says why, and it is the
correction that mattered most in this round.**

### Jobs

| Row | Status | Why |
| --- | --- | --- |
| Background work | **Available** | `queue.default` is `sync`: work executes **inline**, in the request. A configured architecture, not an absence |
| Queue worker | **Not applicable** | The `sync` driver requires none. **Never Degraded, never Unavailable** — the deployment is working exactly as intended |
| Scheduled tasks | **Not configured** | No scheduled workload exists yet. `routes/console.php` holds only Laravel's stock `inspire`, and `bootstrap/app.php` registers no `withSchedule` |

**Derived from configuration and the registered schedule, never hard-coded.**
`Background work` reads the live `queue.default`; `Queue worker` is
**Not applicable only while that value is `sync`** and becomes a real check the
day it is not; `Scheduled tasks` counts the registered schedule and is
**Not configured only while that count is zero**. A future unit that adds a
scheduled task changes these rows **without touching this code**, which is the
only version of this that cannot go stale.

### Connections

| Row | Status from | Source |
| --- | --- | --- |
| Database | `ok` / `!ok` | `HealthInspector` `database` — **existing check**, via `inspectLocal()`. Opens a connection and runs `select 1` |
| Session store | round trip | **NEW** — §3.1 |
| Cache store | round trip | **NEW** — §3.2 |
| File storage | `ok` / `!ok` | `HealthInspector` `storage` — **existing check**, via `inspectLocal()`. **Projected here and nowhere else** |

**File storage appears exactly once.** The PLAN's first draft listed it under
Application *and* proposed a filesystem round trip under Connections. Two
authoritative answers to one fact is the duplication this unit exists to
prevent, and the Product Owner caught it. It is a dependency, so it sits with
the other dependencies.

### Service Health

| Row | Status from | Source |
| --- | --- | --- |
| **Local service health** | Every **local** `HealthInspector` check | `HealthInspector::inspectLocal()` — §1.2. **A roll-up of the five local checks. NOT the `/up` verdict** |
| Audit record integrity | `intact` → Available; otherwise **Unavailable** | `AuditChainVerifier::verify()` — **existing, P1-08** |
| Audit evidence store | start instant present → Available; absent → **Unavailable** | `AuditChainHead` — **existing, P1-08** |

**The row is named for what it actually rolls up.** It was called *"Overall
application health"* and described as *"the same verdict `/up` and
`semantiq:health` give"*. **That was untrue**, and would have stayed untrue even
if the wording were softened: this roll-up deliberately **excludes identity**
(§1.2), so a deployment where sign-in has failed can show Local service health
**Available** while `/up` returns **503**. A row that claims to be the
deployment verdict and is not is worse than no row — it is the specific failure
CLAUDE.md §2 names, wearing a green tick. Its explanation says, in the user's
words, that it covers this application's own local checks and that Microsoft
Entra ID is reported separately, on its own row, above.

**It shows that the evidence store is working. It shows no evidence.** No
event, no actor, no count of rows. D-124: System Health must not become a
second way to read the log.

---

## 1.1 `IdentityHealthCheck::storedReport()` — correction 1, and the defect behind it

### The defect

The first draft of this design sourced the Entra row from
`IdentityHealthCheck::report()` and asserted, on the strength of P1-02's own
class comment — *"Rendering this NEVER touches the network by choice"* — that
opening System Health contacts nobody.

**The code says otherwise.** `report()` calls `trustAvailability()`, and when
either cached value is absent that method does this:

```php
$metadata = $this->discovery->cachedMetadata();   // cache only
$keys     = $this->discovery->cachedSigningKeys();// cache only

if ($metadata !== null && $keys !== null) { return [...]; }   // network-free

// Nothing cached. ASK.
$metadata ??= $this->discovery->metadata();       // Http::timeout(10)->get(...)
$keys     ??= $this->discovery->signingKeys();    // Http::timeout(10)->get(...)
```

`EntraDiscovery::metadata()` and `signingKeys()` are **read-through**:
`Cache::remember(...)` around an outbound `Http::get` to Microsoft. On a cold
cache — a fresh deployment, a cleared cache, or simply 24 hours of quiet, since
the discovery cache lives for `CACHE_HOURS = 24` — **rendering System Health
would have made two outbound HTTPS calls to Microsoft, with a ten-second
timeout each.**

That is a breach of approved **D-114** and **D-126**, and it is worse than a
slow page: the person who opens a health screen during an incident is exactly
the person whose page would then hang for twenty seconds on the dependency they
are trying to diagnose.

**There is a second path to the same call**, and the first draft did not see it
either. `HealthInspector::inspect()` includes `'identity' => $this->identity()`
→ `IdentityHealthCheck::forInspector()` → `report()` → `trustAvailability()`.
So the **Service Health roll-up** reached Microsoft too, by a different route,
and choosing `report()` over `forInspector()` did nothing to avoid it. §1.2
closes that path.

**This is the failure CLAUDE.md §2 describes**, found in a design rather than a
test: a guarantee that read as measured and was inherited from a comment.

### The correction

**No second probe is added.** A clearly bounded, network-free snapshot entry
point is added to the **existing** P1-02 `IdentityHealthCheck`:

```php
/**
 * P1-02 identity health as ALREADY ESTABLISHED. Reads stored state only.
 *
 * NO NETWORK PATH EXISTS IN THIS METHOD. It does not call EntraDiscovery at
 * all - not metadata(), not signingKeys(), not probe(), and not the cached
 * accessors. It reads the two P1-02 cache keys this class already writes and
 * returns what they say. Absence is reported as absence.
 */
public function storedReport(): StoredIdentityHealth
```

```php
final readonly class StoredIdentityHealth {
    public function __construct(
        public string $state,        // failed | degraded | healthy | not_checked
        public ?string $checkedAt,   // ISO-8601, from LAST_RESULT_KEY
        public ?string $lastProbeAt, // ISO-8601, from LAST_PROBE_KEY
    ) {}
}
```

| Requirement | How it is met |
| --- | --- |
| **Zero outbound network calls** | The method body references `Cache` and nothing else. `EntraDiscovery` is never touched, so no read-through path exists to take |
| **Only already-stored P1-02 information** | `LAST_RESULT_KEY` (`{state, at}`, written by `recheck()`) and `LAST_PROBE_KEY` (`{reachable, reason, at}`) — both **already written by P1-02**, both unchanged by this unit |
| **`Not checked` when nothing trustworthy is stored** | No stored result, a non-array value, a missing `state`, or a `state` that is not one of P1-02's four constants → `not_checked`. **Not Available, not Degraded, not Unavailable** |
| **FAILED / DEGRADED / HEALTHY preserved where they really exist** | The stored state is returned **as stored**. P1-02 writes `IdentityHealthReport::state()`, which already carries the full three-way distinction, so nothing is collapsed — this is precisely what `forInspector()` would have destroyed |
| **Checked-at / probe age exposed** | `checkedAt` and `lastProbeAt` are rendered as an age beside the row, so a reader sees how old the answer is rather than mistaking it for now |
| **Never invents Available from absence** | `healthy` is returned **only** when the stored state literally is `healthy`. There is no `?? 'healthy'`, no `default =>` that lands on Available, and no boolean that a missing key satisfies |

**Why a stored snapshot rather than a cheaper live re-evaluation.** Running
P1-02's nine checks with a cache-only trust read would look richer, and would be
wrong in the red direction: with nothing cached and no probe ever run,
`identityTrustAvailable` returns **FAILED** — *"Microsoft's sign-in settings
could not be obtained, so people cannot sign in"* — on a deployment where
nobody has looked. Inventing **Unavailable** from absence is the same defect as
inventing Available from it, and a false red on a working sign-in is the thing
P1-02's own `directoryIdentityConsistent` comment refuses to ship. **Absence is
reported as `Not checked`, and the *Check again* control is right there.**

**The screen stays honest about staleness.** The row reads as a stored result
with its age. It never says "now".

### The guard that makes this non-vacuous

A passing test proves nothing here unless it would fail on the original code.
So the boundary test **empties the discovery caches first** — the exact
condition under which the defect fired — then renders System Health:

1. `Cache::forget` the metadata and JWKS keys, `LAST_RESULT_KEY` and
   `LAST_PROBE_KEY`, so `trustAvailability()` would fall through to the network.
2. `Http::preventStrayRequests()` and `Http::fake()` — any outbound call fails
   the test rather than silently succeeding against a recorded response.
3. Bind an `EntraDiscovery` double whose `metadata()`, `signingKeys()` and
   `probe()` **fail the test if called at all**, so the boundary is asserted at
   the class, not only at the socket.
4. GET System Health as a System Administrator; assert 200, assert
   `Http::assertNothingSent()`, and assert the Entra row renders
   **Not checked**.

**Mutation that must kill it:** put `storedReport()` back to `report()`. The
test must fail — and that mutation is the original design, which is the point.

---

## 1.2 `HealthInspector::inspectLocal()` — the same checks, without identity

`inspect()` reaches identity, so **System Health does not call `inspect()`.**
But the five local checks are still the authoritative answers to their facts and
**must not be copied into P1-09** — a second `is_writable()` or a second
`select 1` is exactly the duplication §0.1 forbids.

So the existing class gains a projection over its **own existing private
methods**:

```php
public function inspect(): HealthReport      // UNCHANGED BEHAVIOUR
{
    return new HealthReport([
        ...$this->localChecks(),
        'identity' => $this->identity(),     // /up and semantiq:health keep this
    ]);
}

/** Local dependencies only. No identity, therefore no network path. */
public function inspectLocal(): HealthReport
{
    return new HealthReport($this->localChecks());
}

/** @return array<string, array{ok: bool, detail: string}> */
private function localChecks(): array
{
    return [
        'database'      => $this->database(),
        'migrations'    => $this->migrations(),
        'configuration' => $this->configuration(),
        'storage'       => $this->storage(),
        'assets'        => $this->assets(),
    ];
}
```

**`database()`, `migrations()`, `configuration()`, `storage()` and `assets()`
are not touched.** Not renamed, not moved, not reimplemented. `inspect()`
returns the same six checks, in the same order, with the same values — so
**`/up` and `semantiq:health` are unchanged**, which H16 asserts as a
regression, and `inspect()` remains authoritative for both.

**`inspectLocal()` is a projection, not a second inspector.** If a future unit
changes how storage is checked, both callers change together, because there is
still exactly one implementation.

**Guard:** an architecture test asserts that `localChecks()` and the `inspect()`
key set differ by exactly the one key `identity`, so a sixth local check added
later cannot silently miss System Health, and identity cannot silently
re-enter it.

---

## 2. `HealthStatus` — the fixed six

```php
enum HealthStatus: string {
    case Available = 'available';           // A check ran and passed
    case Degraded = 'degraded';             // A check ran; it works but needs attention
    case Unavailable = 'unavailable';       // A check ran and failed
    case NotConfigured = 'not_configured';  // Deliberately absent, and correct
    case NotApplicable = 'not_applicable';  // Cannot apply to this architecture
    case NotChecked = 'not_checked';        // NOBODY HAS LOOKED. Never Available
}
```

**A backed enum, and the backing values are part of the contract** — correction
3. A pure enum has no value to serialise: it cannot cross to Inertia as data,
so the payload would have had to carry `->name`, which makes the wire format an
accident of PHP identifiers rather than a decision. The declared strings are
what the props carry and what the React layer matches on, so a rename on either
side is a visible change rather than a silent one. The style matches
`ActionClass` and the rest of the codebase's backed enums.

**Six, because five cannot say "we do not know".** `NotChecked` is the whole
reason the enum is not five long: a health page whose worst honest answer is
Available reports safety it never measured, which is the failure mode CLAUDE.md
§2 describes and the one a health screen is most prone to.

**`NotConfigured` and `NotApplicable` are not failures and not successes.**
They carry their own presentation — neutral, never green and never red — so a
reader is not invited to treat "the scheduler is not configured" as either good
news or an incident.

---

## 3. The two genuinely new checks

**Both are round trips.** A check that reads `config('cache.default')` and
reports Available is the hard-coded success this unit exists to prevent.

### 3.1 Session store — **database driver only** (correction 2)

**It is a different fact from the database check, and that is why it exists.**
`select 1` proves the connection; it does not prove the session table is
present, readable and writable. A renamed table or a permissions problem on it
leaves the database row green and **signs nobody in** — the outage that looks
healthiest.

#### The defect in the first draft

It said the round trip *"exercises whatever driver is configured, through the
same interface"* and *"does not branch on driver names"*, while wrapping the
whole thing in `DB::transaction(...)`.

**Those two sentences cannot both be true.** A database transaction can only
roll back a database write. Against `file`, the round trip would leave a session
file on disk; against `redis` or `dynamodb`, a live key in the store. The
rollback would succeed, roll back nothing, and the page would report a clean
round trip **while litter accumulated in the real session store on every
render** — from a screen whose only purpose is to be trustworthy.

#### What Phase 1 implements

**The approved target store, and only that.**

> **CORRECTED AFTER PRODUCTION VERIFICATION.** This paragraph said
> *"`SESSION_DRIVER=database` on this deployment (verified)"*. **It was not
> verified.** The value was read from `.env.example` — a repository file — and
> reported as deployment reality. Asking the server gave a different answer:
>
> | | |
> | --- | --- |
> | **Effective production driver** | **`file`** (configuration not cached, so this is live) |
> | **Repository / architectural target** | **`database`** — unchanged, and `.env.example` still declares it |
>
> Production running `file` is **deployment drift from the intended target**,
> not a new architectural decision. It is recorded as a carried Phase 1
> alignment finding in `PHASE-1-PLAN.md` §10, to be resolved before final Phase
> 1 acceptance together with the privilege-change/revocation behaviour.

**P1-09 supports a database-session round trip only**, so on the current
deployment the *Staying signed in* row correctly reads **Not checked**. That is
the honest answer, not a health failure — and the Product Owner accepted it as
such rather than requiring the row to go green.

`config('session.table')` is `sessions` and `config('session.connection')`
selects the connection, **when the driver is `database`**.

```php
if (config('session.driver') !== 'database') {
    return NotChecked;   // never Available
}

return DB::connection(config('session.connection'))->transaction(function () {
    $id = 'semantiq-health-'.bin2hex(random_bytes(16));   // collision-resistant
    insert  one row into config('session.table') with that id
    select  it back by that id
    compare what came back
    throw   a private rollback signal      // the transaction rolls back
});
```

| Rule | How |
| --- | --- |
| **Never touches a real user session** | The identifier is synthetic and 32 hex characters of `random_bytes`. It matches no issued session id, and **nothing is read, updated or deleted by any other key** — the check only ever addresses the row it just inserted |
| **Nothing is left behind** | The insert and the read happen inside one transaction that **always** rolls back, including on success. The rollback is the normal path, not the error path |
| **No `DELETE`, no `TRUNCATE`, no DDL** | D-128. One `INSERT`, one `SELECT`, one `ROLLBACK`. The table is never created, altered, dropped or truncated, so MySQL's implicit commit on DDL — which cost P1-08 four green-on-SQLite, red-on-MySQL cases — cannot be reached |
| **A missing or unusable table is Unavailable** | The insert throws, the transaction rolls back, and the row reports **Unavailable** with a fixed sentence. This is the outage the check exists to find |
| **An unsupported driver is `Not checked`** | Not Available. The screen states plainly that the configured session store is one this check cannot exercise, so nobody reads silence as health |

**The database row does NOT cover for this.** When the driver is unsupported,
the session row says `Not checked` and says nothing more. Reporting Available
on the strength of `select 1` having passed is precisely the "a status nobody
measured" failure — it would claim the session store works because a *different*
check on a *different* fact passed.

**Redis, file and DynamoDB adapters are NOT implemented here.** None is
deployed. Writing an adapter for a store that does not exist would ship
untestable code against an imagined deployment, and the day one of them is
adopted is the day its round trip gets designed against a real store and a real
rollback strategy. Until then the answer is the honest one: **Not checked.**

#### Tests (correction 2 requires all four)

| # | Case | Mutation that must kill it |
| --- | --- | --- |
| **S1** | `database` driver, table present → round trip succeeds → **Available** | Hard-code Available |
| **S2** | Session table missing or unusable → **Unavailable** | Swallow the exception and report Available |
| **S3** | **After the check, no synthetic session row remains** — counted before and after, and the synthetic id is queried for directly | Drop the transaction, or commit instead of rolling back |
| **S4** | Driver is not `database` → **Not checked**, and **never Available** | `default => Available`, or fall through to the database check's result |

**S2 induces the failure at the boundary, not with DDL.** The check is pointed
at a table name that does not exist, through the configured value — no
`Schema::drop()`, no destructive operation, D-128 honoured.

### 3.2 Cache store

`Cache::put` a throwaway key with a ten-second lifetime → `Cache::get` →
`Cache::forget`. **The value is compared**, not merely retrieved: a cache that
returns `null` for everything satisfies "no exception was thrown", which is the
weaker assertion and the one that passes on a broken cache.

**The key is namespaced and unmistakable.** It cannot collide with an
application key, and nothing reads it back later.

---

## 4. The row, and why the payload is an allowlist

```php
final readonly class HealthRow {
    public function __construct(
        public string $name,        // business words. "Session store", never "session.driver"
        public HealthStatus $status,
        public string $explanation, // a FIXED sentence, chosen from a closed set
        public ?string $checkedAt,  // a human age, and ONLY where a result is cached
    ) {}
}
```

**Four fields, and the absence of the fifth is the design.** There is no field
for a hostname, a database name, a username, a connection string, a path, a
driver, a version, a capacity figure, an endpoint, a configuration value or an
exception — **so none can reach the screen**. D-119 and D-120 are satisfied by
there being nowhere to put them, not by remembering to strip them.

This is the `ALLOWED_KEYS` pattern P1-00 established and `/up`'s two-word
allowlist repeats: **the leak is unrepresentable rather than discouraged.**

**`explanation` is chosen, never caught.** Every check returns one of its own
declared sentences. `HealthInspector::database()` already does exactly this —
it catches the exception and returns *"Could not open a database connection."*
**because the message carries the host, database name and user.** That precedent
becomes the rule for every row.

**`checkedAt` is present only for a stored result** — today, Entra alone, from
`storedReport()` (§1.1). A live check has no age to report, and a live row
carrying one would suggest its value might be stale. The reverse matters more:
**the one row that is not live always shows its age**, so a stored `healthy`
from last Tuesday cannot be read as a measurement taken now.

---

## 5. Authorisation — D-117

```php
Route::middleware(RequireActionClass::class.':'.ActionClass::PlatformAdmin->value)
    ->prefix('system-health')->name('system-health.')->group(function (): void {
        Route::get('/', [SystemHealthController::class, 'show'])->name('show');
    });
```

**`PlatformAdmin`, not `EvidenceRead`, and the difference is the point.**
Evidence access and infrastructure access are different authorities: an Auditor
reads what happened; an operator reads whether the machine is working. Placing
this behind `EvidenceRead` would hand an Auditor and an Organisation
Administrator a view of the deployment's infrastructure, which nobody decided
to give them.

**System Administrator only in Phase 1.** The node already sits inside System
Administration, so **D-19 needs no change and nothing is widened**.

**ONE GET. NO WRITE ROUTE AT ALL.**

The *Check again* control posts to **P1-02's existing endpoint**,
`identity.health.recheck` — already behind `PlatformAdmin`, already rate
limited to one per administrator per 60 seconds, and already recording
`identity.health.checked` and its state change. Reusing it means **P1-09 adds
no write path, no second probe, no second rate limiter and no new event**
(D-116, D-122, D-125, D-127).

**One line of P1-02 changes, and it is raised here rather than done quietly.**
That endpoint's two refusal branches already return `back()`, but its success
branch returns `redirect()->route('identity.health')` — a hard destination. An
administrator pressing *Check again* on System Health would therefore be
**silently moved to a different screen**, which is the kind of thing §4 of
CLAUDE.md exists to catch. The success branch becomes `back()`, matching the
two branches beside it.

**P1-02's own screen is unaffected.** Its form posts from
`/console/identity/health`, so `back()` resolves to exactly the URL the hard
redirect named. The flash and the errors are unchanged, and no existing test
asserted the destination — only the flash — so this is verified by adding the
assertion that was missing rather than by editing one that existed.

**`SystemHealthHasNoWriteRoute` (§9) asserts that route set as an equality**,
in the shape `SecurityStatusArchitectureTest` and `AuditImmutabilityTest`
already use — brace-matched group body, comments stripped — so a second verb
fails the build rather than quietly becoming a write path.
`LifecycleCompletenessTest` is **not** the guard for this: it is P1-01's
organisation-lifecycle test and knows nothing about this prefix. Naming the
wrong test would have left the guarantee unguarded while reading as covered.

---

## 6. Live, cached, and what opening the page does — D-114

| | |
| --- | --- |
| **On render** | Application, Jobs, Connections and Service Health run **live**, through `inspectLocal()` (§1.2) and the two new round trips. All local, all bounded: three file/config checks, one `select 1`, one rolled-back session write, one cache round trip, one chain read |
| **On render** | **Entra is NOT contacted, and there is no code path by which it could be.** The row is P1-02's **stored result** with its age, read by `storedReport()` (§1.1), which holds no reference to `EntraDiscovery` |
| **On render** | **`HealthInspector::inspect()` is NOT called**, because it reaches identity. `inspectLocal()` is |
| **On the button** | P1-02's existing re-check probes Entra, subject to its existing timeout and rate limit |

**Opening a health page must not generate load on somebody else's service.** A
page that probes an external system on every render becomes a load generator
the moment two people leave it open — and the person who opens it to
investigate an incident is exactly the person who would.

**No auto-refresh, no polling, no background loop** (D-126). Local values are
current as of the render; the page says so.

**Timeout is Degraded, never Unavailable** (D-115). *"We could not reach it in
the time allowed"* and *"it is down"* are different claims, and only one of
them is ours to make about Microsoft.

---

## 7. What this design does NOT do

- **`/up` is untouched.** Still outside the web middleware group, still exempt
  from maintenance mode, still two words, still 200 or 503. Turning it into an
  authenticated diagnostic would break the deployment probe and re-open the
  stack-trace leak it was moved to close.
- **`semantiq:health` is untouched**, and both still gate deployment.
  `HealthInspector::inspect()` keeps all six checks, identity included; the new
  `inspectLocal()` is an additional read, not a replacement (§1.2).
- **No second identity probe, and no second identity check.** `storedReport()`
  reads what P1-02 already stores and adds no probe, no cache key, no timeout
  and no rate limiter (§1.1).
- **No session driver adapter for a store that is not deployed.** Redis, file
  and DynamoDB are `Not checked`, not guessed (§3.1).
- **No remediation.** No restart, no migration, no cache clear, no worker
  start. A page that can fix things is a page that can break them, and this one
  is read by somebody who is already having a bad day.
- **No metrics, history, graphs or uptime percentages.**
- **P1-06 keeps its own rows.** Security Status answers *configured securely?*;
  System Health answers *operating?*. No shared row, no cross-rendering
  (D-123).

---

## 8. Test matrix — every status, including the three that are not outcomes

| # | Case | Mutation that must kill it |
| --- | --- | --- |
| **H1** | Each reused `HealthInspector` row reports Available when healthy | Hard-code the status |
| **H2** | **Database unreachable → Unavailable** | Return Available on exception |
| **H3** | **Session store: S1–S4 of §3.1** — round trip, missing table, nothing left behind, unsupported driver → Not checked | Swallow the failure; commit instead of rolling back; report Available for an unsupported driver |
| **H4** | **Cache store returns the wrong value → Unavailable** | Assert only that no exception was thrown |
| **H5** | **File storage unwritable → Unavailable** | Read the config instead of the directory |
| **H6** | **Stored `failed` → Unavailable; stored `degraded` → Degraded; stored `healthy` → Available** | Source the row from `forInspector()`, which collapses degraded to healthy |
| **H7** | **Nothing trustworthy stored → Not checked** — no stored result, a non-array value, a missing `state`, and an unrecognised `state`, each asserted | Default to Available; `?? 'healthy'`; a `default =>` arm landing on Available |
| **H7a** | **`storedReport()` makes no outbound call on a COLD discovery cache** — caches emptied, `Http::preventStrayRequests()`, an `EntraDiscovery` double that fails the test if `metadata()`, `signingKeys()` or `probe()` is called | **Call `report()` instead of `storedReport()`** — i.e. revert to the first draft. The test must fail |
| **H7b** | **`storedReport()` exposes the stored checked-at and probe age** | Return null for both; render "now" |
| **H8** | **Queue worker → Not applicable** while `sync` | Report Unavailable, or hard-code Not applicable regardless of driver |
| **H9** | **Scheduler → Not configured** while no task is registered | Hard-code it |
| **H10** | Audit chain broken → Unavailable | Report the chain without verifying it |
| **H11** | **The payload carries only the four allowlisted keys** — asserted on the rendered props | Add a `detail` passthrough |
| **H12** | **No hostname, path, driver, version, tenant id or exception text** anywhere in the payload | Put the caught exception in `explanation` |
| **H13** | A non-`PlatformAdmin` viewer is refused — Auditor, Organisation Administrator and every business role | Lower the route to `EvidenceRead` |
| **H14** | **Rendering emits no Audit event** | Add a `system.health.checked` key |
| **H15** | **Rendering contacts nothing external** — `Http::assertNothingSent()` across the WHOLE page render, not just the Entra row | Call `recheck()` on render; call `inspect()` rather than `inspectLocal()` |
| **H15a** | **`HealthInspector::inspectLocal()` returns the five local checks and NO `identity` key** | Include identity in the projection |
| **H15b** | **`HealthInspector::inspect()` still returns all six checks, identity included, with unchanged values** | Drop identity from `inspect()` while "simplifying" |
| **H16** | `/up` and `semantiq:health` behave identically before and after — status codes, body, and the failing-check list | Change either |
| **H17** | Every declared row maps to a check; a row with no check fails the build | Add a row with a literal status |
| **H18** | **`HealthStatus` backing values are exactly the six declared strings**, and the rendered props carry those strings | Change a backing value; serialise `->name` |

**H7, H8 and H9 are the ones most likely to pass vacuously**, because
`NotChecked`, `NotApplicable` and `NotConfigured` are easy to hard-code and
look correct. Each is broken in **both** directions: forced on when it should
not be, and absent when it should.

**H7a is the one case whose mutation is a previously approved design.**
Reverting `storedReport()` to `report()` restores the first draft of this
document exactly, so if H7a still passes after that mutation it is measuring
something other than the boundary it names — the M-A5 failure from P1-08,
repeated. It is run as a mutation and the result recorded, not assumed.

**Failure is induced at the dependency boundary** — a fake cache repository, a
session driver that throws, a chmod'd directory, a stubbed identity report.
**Never with DDL**, which commits the open transaction on MySQL and cost four
green-locally, red-on-MySQL cases in P1-08 (D-128).

---

## 9. Architecture guards

| Guard | Asserts |
| --- | --- |
| `SystemHealthNamesNoBusinessModel` | The module references no business model, no `BusinessDomain`, no `User` record content |
| `SystemHealthHasNoWriteRoute` | The route set is exactly one GET, as an equality |
| `SystemHealthRowIsAllowlisted` | `HealthRow` has exactly four properties. A fifth fails the build |
| `SystemHealthAddsNoEventKey` | `SecurityEventLogger::events()` is unchanged in count and content |
| `SystemHealthDuplicatesNoCheck` | No check name appears twice across the five areas — the PLAN's duplication correction, enforced |
| `SystemHealthReachesNoNetwork` | The P1-09 module references **no** `EntraDiscovery`, no `Http` facade, no `IdentityHealthCheck::report()`, no `forInspector()` and no `HealthInspector::inspect()`. **A static guard, so the boundary survives a refactor that no runtime test happens to cover** |
| `HealthInspectorLocalExcludesIdentityOnly` | `inspectLocal()`'s key set is `inspect()`'s minus exactly `identity`. A sixth local check cannot go missing from System Health, and identity cannot come back into it |
| `SystemHealthImplementsNoSessionDriverAdapter` | The session check branches on `database` and returns `NotChecked` otherwise. No `redis`, `file` or `dynamodb` branch exists — correction 2 |

---

## 10. Browser matrix

Five areas × 1440px and 390px × light and dark = **4 renders**, plus the
*Check again* round trip and its rate-limited second press.

Checked: every row shows a name, a status and a sentence; no dotted key, no
path, no identifier; `Not applicable` and `Not configured` read as neutral
rather than as faults; no element crosses either viewport edge — **measured per
element, not per page**, which is the only sweep that has ever caught anything
in this project.

---

## 11. Product Owner test script — outline

Full script at VERIFY, following D-129 exactly: five areas render; statuses and
explanations understandable; **queue worker says Not applicable**; **scheduler
says Not configured**; Entra shows either a genuine stored state with a real age
or an honest **Not checked**; *Check again* works and the second press is
refused politely; **no hostname, tenant id, trace, path, credential or business
payload**; Service Health shows Audit integrity safely, and **Local service
health reads as this application's own checks rather than as a verdict on
Microsoft**; responsive, light/dark and Back.

**Carried, not observable without breaking production:** every failure and
degraded state. The Product Owner will not break the database, storage, Entra,
the cache or the session store, and this script will not ask them to.

**Also carried:** the network boundary itself. *"No outbound call was made while
that page rendered"* is not something a person can see in a browser. Its
evidence is H7a and H15, and the script will say so rather than implying the
Product Owner confirmed it.

---

## 12. SCHEMA — NONE, and that is a finding rather than an omission

**P1-09 creates no table, no column and no migration.** Every row is derived at
render from configuration, the filesystem, a live query, a round trip, or a
cached result P1-02 already stores.

The one candidate for storage would be a history of past health — and there is
no requirement for one. Adding a table to hold a page's own render would be a
schema nothing reads.

**If DESIGN review finds a requirement that needs storage, it is a blocker to
be raised, not a table to be added.** None was found.

---

## 13. The three corrections, recorded

| # | What was wrong | Where it is now right |
| --- | --- | --- |
| **1** | **`report()` is not network-free.** `trustAvailability()` falls through to `EntraDiscovery::metadata()` and `signingKeys()` — read-through, outbound HTTP — whenever the discovery cache is cold. `HealthInspector::inspect()` reached the same call a second way, via `forInspector()`. The approved D-114 / D-126 guarantee was therefore false as designed | §1.1 `storedReport()` — a bounded, stored-only entry point on the **existing** P1-02 check, with H7a proving the boundary on a **cold** cache. §1.2 `inspectLocal()` — the same five local checks, identity excluded, no logic copied. The Service Health roll-up renamed **Local service health**, because it is not the `/up` verdict |
| **2** | **A `DB::transaction` cannot roll back a file, Redis or DynamoDB write.** The first draft claimed the session round trip was driver-agnostic *and* transactional. Against a non-database driver it would have littered the real session store on every render | §3.1 — the deployed **database** driver only: transactional insert and read-back against `config('session.table')`, a synthetic 32-hex identifier, always rolled back, no DDL. Any other driver is **Not checked**, never Available, and the database check is not allowed to stand in for it. Tests S1–S4 |
| **3** | The `HealthStatus` example declared `enum HealthStatus: string` with no backing values — which does not compile, and left the wire format undecided | §2 — the six backing values are declared and are part of the contract, asserted by H18 |

**Corrections 1 and 2 were both found by reading the code the design depended
on, rather than the comment above it.** P1-02's class comment says rendering
*"NEVER touches the network by choice"*; the method four lines further down
does. That is the pattern this project keeps producing, and it is why §1.1's
guard empties the cache before it asserts anything.

---

## 14. Status

**DESIGN ONLY — AWAITING PRODUCT OWNER REVIEW.**
No implementation. No schema. No deployment.
**Three Product Owner corrections applied — §13.**
**`/up` and `semantiq:health` unchanged. P1-02 remains OPEN / CARRIED /
UNVERIFIED. P1-07 and P1-08 carried items remain carried. D-19 unchanged.
P1-10 is not started.**
