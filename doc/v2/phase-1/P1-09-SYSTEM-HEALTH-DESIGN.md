# P1-09 — System Health: DESIGN

**DESIGN ONLY.** No implementation, no deployment.

| | |
| --- | --- |
| Unit | **P1-09 — System Health** (delivery order 11). **P1-10 still follows** |
| PLAN | merge `0385c92c0f959748911b86764b240a6896042782` — D-112 – D-129 **ANSWERED** |
| **Schema** | **NONE.** No table, no column, no migration. See §12 |
| Status | **AWAITING PRODUCT OWNER REVIEW** |

---

## 0. The four sentences this design serves

1. **One authoritative check per operational fact.** A screen may project a
   check into an area; it may never reimplement one to appear there.
2. **A status is returned by a check, never assigned to a row.** Nothing can be
   Available because nobody looked.
3. **The row is the whole payload.** Four fields, allowlisted. There is no
   field for a hostname, a path or an exception, so none can reach the screen.
4. **Opening the page contacts nobody.** Local checks run; Entra does not.

---

## 1. The five areas, and the authoritative source behind every row

**Eleven rows. Nine come from checks that already exist; two are new.**

### Application

| Row | Status from | Source |
| --- | --- | --- |
| Database migrations | `ok` → Available, `!ok` → **Unavailable** | `HealthInspector::migrations()` — **existing** |
| Required configuration | `ok` / `!ok` | `HealthInspector::configuration()` — **existing** |
| Application assets | `ok` / `!ok` | `HealthInspector::assets()` — **existing** |

### Integrations

| Row | Status from | Source |
| --- | --- | --- |
| Microsoft Entra ID | `FAILED` → **Unavailable**; `DEGRADED` → **Degraded**; otherwise **Available**; **no probe ever taken** → **Not checked** | `IdentityHealthCheck::report()` — **existing, P1-02** |

**`report()`, NOT `forInspector()`, and the distinction matters.**
`forInspector()` collapses `DEGRADED` into `ok = true` because `semantiq:health`
is a two-state answer. Reading it here would render a degraded sign-in as
**Available** — a six-state vocabulary fed by a two-state source, which is a
status that looks measured and is not. `report()` carries the state and
`lastProbeAt`; both are P1-02's, so **no second probe exists**.

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
| Database | `ok` / `!ok` | `HealthInspector::database()` — **existing**. Opens a connection and runs `select 1` |
| Session store | round trip | **NEW** — §3.1 |
| Cache store | round trip | **NEW** — §3.2 |
| File storage | `ok` / `!ok` | `HealthInspector::storage()` — **existing**. **Projected here and nowhere else** |

**File storage appears exactly once.** The PLAN's first draft listed it under
Application *and* proposed a filesystem round trip under Connections. Two
authoritative answers to one fact is the duplication this unit exists to
prevent, and the Product Owner caught it. It is a dependency, so it sits with
the other dependencies.

### Service Health

| Row | Status from | Source |
| --- | --- | --- |
| Overall application health | Every `HealthInspector` row that ran | `HealthReport::isHealthy()` — **existing**. The same verdict `/up` and `semantiq:health` give |
| Audit record integrity | `intact` → Available; otherwise **Unavailable** | `AuditChainVerifier::verify()` — **existing, P1-08** |
| Audit evidence store | start instant present → Available; absent → **Unavailable** | `AuditChainHead` — **existing, P1-08** |

**It shows that the evidence store is working. It shows no evidence.** No
event, no actor, no count of rows. D-124: System Health must not become a
second way to read the log.

---

## 2. `HealthStatus` — the fixed six

```php
enum HealthStatus: string {
    case Available;       // A check ran and passed
    case Degraded;        // A check ran; the thing works but needs attention
    case Unavailable;     // A check ran and failed
    case NotConfigured;   // Deliberately absent, and correct
    case NotApplicable;   // Cannot apply to this architecture
    case NotChecked;      // NOBODY HAS LOOKED. Never a synonym for Available
}
```

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

### 3.1 Session store

```
DB::transaction(fn () => write a session row through the configured driver,
                         read it back,
                         and roll back)
```

**It is a different fact from the database check, and that is why it exists.**
`select 1` proves the connection; it does not prove the `sessions` table is
present, readable and writable. A renamed table or a permissions problem on it
leaves the database row green and **signs nobody in** — the outage that looks
healthiest.

**The write is rolled back**, so the round trip happens and nothing persists.
No `DELETE`, no `TRUNCATE`, no DDL — D-128.

**If the driver is ever not `database`**, this check exercises whatever driver
is configured, through the same interface. It does not branch on driver names.

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

**`checkedAt` is present only for a cached result** — today, Entra alone. A live
check has no age to report, and a live row carrying one would suggest its value
might be stale.

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
limited to one per administrator per 60 seconds, already recording
`identity.health.checked` and its state change, and already returning `back()`.
Reusing it means **P1-09 adds no write path, no second probe, no second rate
limiter and no new event** (D-116, D-122, D-125, D-127).

`LifecycleCompletenessTest` asserts the System Health route set as an
**equality**, so a second verb fails the build.

---

## 6. Live, cached, and what opening the page does — D-114

| | |
| --- | --- |
| **On render** | Application, Jobs, Connections and Service Health run **live**. All local, all bounded: three file checks, one `select 1`, one rolled-back session write, one cache round trip, one chain read |
| **On render** | **Entra is NOT contacted.** The row is P1-02's **stored result** with its age |
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
| **H1** | Each of the three reused `HealthInspector` rows reports Available when healthy | Hard-code the status |
| **H2** | **Database unreachable → Unavailable** | Return Available on exception |
| **H3** | **Session store broken → Unavailable** | Swallow the failure |
| **H4** | **Cache store returns the wrong value → Unavailable** | Assert only that no exception was thrown |
| **H5** | **File storage unwritable → Unavailable** | Read the config instead of the directory |
| **H6** | **Entra FAILED → Unavailable; DEGRADED → Degraded** | Source the row from `forInspector()`, which collapses degraded to healthy |
| **H7** | **Entra never probed → Not checked** | Default to Available |
| **H8** | **Queue worker → Not applicable** while `sync` | Report Unavailable, or hard-code Not applicable regardless of driver |
| **H9** | **Scheduler → Not configured** while no task is registered | Hard-code it |
| **H10** | Audit chain broken → Unavailable | Report the chain without verifying it |
| **H11** | **The payload carries only the four allowlisted keys** — asserted on the rendered props | Add a `detail` passthrough |
| **H12** | **No hostname, path, driver, version, tenant id or exception text** anywhere in the payload | Put the caught exception in `explanation` |
| **H13** | A non-`PlatformAdmin` viewer is refused — Auditor, Organisation Administrator and every business role | Lower the route to `EvidenceRead` |
| **H14** | **Rendering emits no Audit event** | Add a `system.health.checked` key |
| **H15** | **Rendering does not contact Entra** — asserted on the probe not being called | Call `recheck()` on render |
| **H16** | `/up` and `semantiq:health` behave identically before and after | Change either |
| **H17** | Every declared row maps to a check; a row with no check fails the build | Add a row with a literal status |

**H7, H8 and H9 are the ones most likely to pass vacuously**, because
`NotChecked`, `NotApplicable` and `NotConfigured` are easy to hard-code and
look correct. Each is broken in **both** directions: forced on when it should
not be, and absent when it should.

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
| `SystemHealthDuplicatesNoCheck` | No check name appears twice across the five areas — correction 1, enforced |

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
says Not configured**; Entra shows genuine health and a real checked age;
*Check again* works and the second press is refused politely; **no hostname,
tenant id, trace, path, credential or business payload**; Service Health shows
Audit integrity safely; responsive, light/dark and Back.

**Carried, not observable without breaking production:** every failure and
degraded state. The Product Owner will not break the database, storage, Entra,
the cache or the session store, and this script will not ask them to.

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

## 13. Status

**DESIGN ONLY — AWAITING PRODUCT OWNER REVIEW.**
No implementation. No schema. No deployment.
**`/up` and `semantiq:health` unchanged. P1-02 remains OPEN / CARRIED /
UNVERIFIED. P1-07 and P1-08 carried items remain carried. D-19 unchanged.
P1-10 is not started.**
