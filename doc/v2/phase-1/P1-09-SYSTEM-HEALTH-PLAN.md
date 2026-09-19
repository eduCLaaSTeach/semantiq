# P1-09 — System Health: PLAN

**PLAN ONLY.** No DESIGN, no implementation, no schema, no deployment.

| | |
| --- | --- |
| Unit | **P1-09 — System Health** (delivery order 11). **P1-10 Administration Home still follows** |
| Menu | System Administration → **System Health** — currently a **locked** node in `ApprovedMenu` |
| Areas | **Application · Integrations · Jobs · Connections · Service Health** |
| Purpose | Operational visibility **without exposing business data** |
| Exit | Platform support can identify operational failures safely |
| Status | **PLAN APPROVED. D-112 to D-129 ANSWERED** — 19 September 2026, §6 |

---

## 0. The four sentences this unit serves

1. **A status nobody measured is not a status.** Nothing is Healthy because no
   check exists; that case has its own word.
2. **SemantIQ already has real health checks.** P1-09 gives them a screen and a
   permission boundary. It does not write a second set.
3. **Operational detail is not business data, and is not free either.** A
   hostname, a tenant id, a connection string and a stack trace are all things
   this screen must be unable to show.
4. **Report the deployment that exists**, not the one a health dashboard
   usually assumes.

---

## 1. INVENTORY — what is actually here

Read from the repository and the deployed configuration, not assumed.

### 1.1 `/up` — public liveness

`routes/health.php`, registered **outside the web middleware group**, and the
reason is recorded in the file: the session driver is `database`, so a liveness
route inside that group **cannot answer when the database is down** — which is
exactly when a monitor needs an answer. It was observed returning a 500 with a
stack trace before it was moved.

It holds no session, sets no cookie, reads no CSRF token, is exempt from
maintenance mode, and its body is **one of exactly two words** (`ok` /
`unhealthy`), asserted against an allowlist rather than screened by a denylist.
200 or **503**.

**P1-09 must not touch it.** Turning it into an authenticated diagnostic page
would break the deployment probe and re-open the leak it was moved to close.

### 1.2 `semantiq:health` — the SSH-only operator report

`HealthCommand` → **`HealthInspector`**, which is the authoritative source.
**Six checks, every one a real operation**, and its docblock already states the
rule this unit depends on: *"A check that cannot fail is not a check."*

| Check | What it actually does |
| --- | --- |
| `database` | Opens a connection and runs `select 1` — not a config read |
| `migrations` | Asks the migrator for outstanding migrations |
| `configuration` | `ConfigurationValidator::problems()` — required keys, production-only keys, connection keys by driver, and `APP_DEBUG` in production |
| `storage` | Writability of `storage/framework`, `storage/logs`, `bootstrap/cache` |
| `assets` | `public/build/manifest.json` present |
| `identity` | **P1-02's own check**, collapsed — deliberately not a second copy, so the command and the screen cannot disagree |

**Details are already operator-facing and carry no secret.** The database check
returns *"Could not open a database connection."* rather than the exception,
**because the message carries the host, database name and user**.

### 1.3 Deployment probes

`deploy.yml` already gates on **both**: `semantiq:health` over SSH, and a curl
of `/up`. Neither is P1-09's to change.

### 1.4 P1-02 Identity & SSO health

`IdentityHealthCheck` performs a **live Entra discovery probe** and stores the
result with `Cache::put` for **7 days** (`LAST_PROBE_KEY`, `LAST_RESULT_KEY`).
`HealthController::recheck` is **rate limited to one per administrator per 60
seconds**, and records `identity.health.checked` / `identity.health.state_changed`.

**This is the precedent for live-vs-cached and for rate limiting**, and P1-09
should not invent a different one.

### 1.5 P1-06 Security Status

Posture, controls, exceptions and privileged access, through 13 adapters —
including `HostingAdapter` and `SessionAdapter`, which touch operational
ground. It answers *"is this deployment configured securely?"*, which is a
different question from *"is it working?"*.

### 1.6 P1-08 Audit

Durable evidence, `AuditChainVerifier::verify()`, and a stored evidence start
instant. The chain verification is an **operational** fact about the evidence
store and belongs on a health screen; the evidence itself does not.

### 1.7 THE DEPLOYMENT REALITY — and it is smaller than a dashboard assumes

| Thing | Actually configured |
| --- | --- |
| Session | `database` |
| Cache | **`file`** — not Redis |
| Queue | **`sync`** — jobs execute **inline, in the web request, by design**. A configured choice, not a missing queue |
| **Queue worker** | **NOT REQUIRED**, because of the line above |
| **Scheduler** | **NONE.** `routes/console.php` contains only Laravel's stock `inspire` command, and `bootstrap/app.php` registers no `withSchedule` |
| Redis | **NOT PRESENT** |
| Mail | **NOT CONFIGURED** |
| Fabric / Power BI / any Phase 2–3 service | **NOT PRESENT** |
| External integrations | **Microsoft Entra ID, and nothing else** |
| Hosting | Shared cPanel, MySQL 8.4 |

**This is the single most important thing in this plan.** A generic health
dashboard would show Redis, a queue depth and a scheduler heartbeat. None of
those exists here, and rendering them green would be a lie about the
deployment.

**And the opposite mistake is just as bad.** `sync` is a deliberate
architecture, not a gap: showing a missing worker as degraded would report a
fault where the deployment is working exactly as intended. See **D-121**.

---

## 2. Scope of Release 1

**In scope**

- One read-only System Health feature with the five approved areas.
- Reuse of `HealthInspector` and `IdentityHealthCheck` as the **authoritative**
  checks, given a screen and a permission boundary.
- A vocabulary that can express **failed**, **degraded**, **not configured** and
  **not applicable** as different things.
- Field-level protection for operational detail.
- The `System Health` menu node from **locked** to **delivered**.

**Explicitly out of scope**

- **Any change to `/up`.**
- A second set of health checks, a second inspector, or a parallel probe.
- Metrics, history, graphs, uptime percentages or alerting.
- Remediation of any kind — no restart, no cache clear, no migration run.
- **P1-10 Administration Home.**
- Anything that would make this screen generate load on an external service.

---

## 3. The five areas — proposed mapping

Derived from §1, so every row is something that already exists or is honestly
absent.

| Area | Checks | Source |
| --- | --- | --- |
| **Application** | Migrations outstanding · Configuration problems · Runtime directories writable · Build assets present | `HealthInspector` (existing) |
| **Integrations** | **Microsoft Entra ID** — discovery reachable, configuration complete, last probe outcome and its age | `IdentityHealthCheck` (existing, P1-02) |
| **Jobs** | Inline job execution · Queue worker · Scheduler | Configuration, read precisely. See D-121 |
| **Connections** | Database · Session store · Cache store · Filesystem | `HealthInspector::database()` and `HealthInspector::storage()`, **both existing**, plus **two new bounded checks** — session store and cache store |
| **Service Health** | The roll-up — the same verdict `/up` and `semantiq:health` give · Audit chain intact · Evidence start instant present | `HealthInspector::isHealthy()`, `AuditChainVerifier` |

**Only TWO genuinely new checks** are proposed — **session store** and **cache
store** — and each is a **round trip**, not a config read: write a value, read
it back, delete it. A check that reads `config('cache.default')` and reports
Available is precisely the hard-coded success this unit must not ship.

**CORRECTED: there is no new filesystem check.** The first draft listed storage
under Application *and* proposed a filesystem round trip under Connections,
which is two authoritative answers to one operational fact — exactly the
duplication this unit exists to avoid. **`HealthInspector::storage()` remains
the one authoritative filesystem and storage check.** A single source may be
**projected** into more than one area of a screen; it may not be
**reimplemented** to appear there.

---

## 4. The distinctions this plan is required to make

| Pair | How they differ here |
| --- | --- |
| **Health vs configuration/readiness** | Configuration says the keys are present; health says the round trip worked. `ConfigurationValidator` is the first; `select 1` is the second. Both are shown, separately |
| **Internal vs external** | Database, cache, session, storage and assets are ours. **Entra is somebody else's**, and its failure is a different sentence and a different urgency |
| **Not configured / not applicable vs failed** | `QUEUE_CONNECTION=sync` means jobs are **configured to run inline** — it is not an absence. The **worker** is **Not applicable**, because this architecture deliberately needs none. The **scheduler** is **Not configured**, because no scheduled workload exists yet. None is a fault, and none is a silent Available |
| **Live check vs cached result** | P1-02 already probes live and remembers for 7 days. A cached answer must **say when it was taken**; an answer with no age is indistinguishable from a fresh one, which is how a stale green survives an outage |

**Nothing is reported Healthy because no check exists.** Every status carries
the check that produced it, and a status with no check behind it is a build
failure (D-112 / D-129).

---

## 5. Security boundaries

**Never, and unrepresentably rather than by discipline:** passwords, secrets,
access or refresh tokens, client secrets, connection strings, raw `.env`
values, exception traces, SQL, business-domain records, learner or customer
payloads.

**The precedent is already set.** `HealthInspector::database()` catches the
exception and returns a fixed sentence *because the message carries the host,
database name and user*. P1-09 extends that rule to every new check: a check
returns a **status and a chosen sentence**, never a caught exception, and never
a value read from configuration.

**Hostnames and tenant ids are the sharp edge.** `identity.microsoft.tenant_id`
is a real directory identifier and the Entra discovery URL contains it. See
**D-119**.

---

## 6. Product Owner decisions — **D-112 to D-129 — ALL ANSWERED**

**Approved 19 September 2026.** Where a ruling differs from the recommendation
the plan offered, **the ruling is what DESIGN implements.**

| # | Decision | **RULING** |
| --- | --- | --- |
| **D-112** | Status vocabulary | **APPROVED — a fixed six-state enum:** Available · Degraded · Unavailable · Not configured · Not applicable · Not checked. **No arbitrary or free-text status values** |
| **D-113** | Checks per area | **APPROVED WITH CORRECTION.** The five-area mapping stands, but the **existing filesystem and storage health is reused**. Only **session** and **cache** require genuinely new checks |
| **D-114** | Live vs cached | **APPROVED.** Application, Connections and local Service Health checks run **live on render** — local and bounded. Entra uses **P1-02's stored result with its checked-at age**, and **opening System Health never contacts Entra** |
| **D-115** | External timeout | **APPROVED.** The existing bounded timeout. A timeout or network uncertainty is **Degraded**, never a definitive claim that Microsoft is unavailable. A cached result **displays its age**. If no meaningful result was ever obtained: **Not checked**, not Available |
| **D-116** | Read-only? | **APPROVED — operationally read-only.** GETs remediate nothing. The **only** POST is the existing manual *Check again* for Entra health. No restart, migration, cache clear, worker start or corrective operation |
| **D-117** | Who may access | **APPROVED — SYSTEM ADMINISTRATOR ONLY in Phase 1.** The screen and the re-check use the existing **`PlatformAdmin`** action class, **not `EvidenceRead`**. This is infrastructure visibility, not audit-evidence access, so an Auditor and an Organisation Administrator are **not** admitted. **D-19 stays consistent and no sidebar widening is required** |
| **D-118** | What a row may contain | **APPROVED.** Only: a business-readable check name, a status, a **fixed safe explanation**, and a last-checked age where relevant. **No raw configuration value and no raw exception** |
| **D-119** | Infrastructure identifiers | **APPROVED — ABSENT, not hidden-for-some.** Hostnames, tenant ids, database names, usernames, connection strings and the like **do not appear in the UI at all** |
| **D-120** | Connection metadata | **APPROVED — operational state only.** No driver, version or database name; no cache driver detail; no physical path; no filesystem capacity; no server hostname; no connection endpoint |
| **D-121** | Jobs | **APPROVED WITH CORRECTED SEMANTICS.** Invent no queue or scheduler infrastructure. Production shows: **work executes inline under the `sync` queue model**; **queue worker → Not applicable**; **scheduler → Not configured**. A missing worker is **never** degraded or unavailable — this architecture deliberately requires none |
| **D-122** | P1-02 Identity health | **APPROVED.** P1-09 **consumes P1-02 as the authoritative source**, and the manual Entra re-check **calls the existing P1-02 operation** rather than implementing a second probe |
| **D-123** | P1-06 Security Status | **APPROVED — distinct.** Security Status answers *configured securely?*; System Health answers *operating?*. **No duplicate rows and no competing evaluation** |
| **D-124** | P1-08 Audit | **APPROVED.** Service Health may show **chain intact / not intact** and the **evidence start fact**. It must **not** expose Audit event contents and must **not** become another Audit viewer |
| **D-125** | Audit evidence from health checks | **APPROVED — NO NEW AUDIT NOISE.** Rendering System Health produces **no** event; running local checks produces **no** event. P1-02's manual re-check keeps emitting its existing `identity.health.checked` and state-change evidence. **No new `system.health.checked` key** |
| **D-126** | Refresh | **APPROVED.** No auto-refresh, no polling, no background browser loop. Local values refresh on ordinary navigation or reload; Entra gets an explicit **Check again** control |
| **D-127** | Rate limiting | **APPROVED.** Reuse P1-02's **one external re-check per administrator per 60 seconds**. Local checks need none |
| **D-128** | Failure simulation in tests | **APPROVED.** Through dependency boundaries, fakes or safe substitutes. **No DDL**, no destructive database operation, no deliberate production outage — and the tests must prove the check **can genuinely fail**, not merely force the displayed status |
| **D-129** | Production acceptance | **APPROVED — normal production behaviour only.** The Product Owner verifies: five areas render; statuses and explanations are understandable; the worker correctly says **Not applicable**; the scheduler says **Not configured**; Entra shows genuine existing health and checked age; **Check again** works and respects the rate limit; no hostname, tenant id, trace, path, credential or business payload appears; Service Health shows Audit integrity safely; responsive, light/dark and Back all work. **Nothing will be broken to demonstrate a failure state** — those stay Gate C automated evidence |

---

## 7. Minimum proof — and the one that will be hardest

| Requirement | How it is proven |
| --- | --- |
| Status comes from a real check | Each check has a case that **breaks its dependency** and asserts the row goes red. A check that cannot fail is not a check |
| Error and degraded states are represented | Distinct statuses, distinct sentences, and a case per state |
| No secret exposure | The rendered payload is asserted against an **allowlist of keys**, not screened for known-bad strings. `/up`'s two-word allowlist is the precedent |
| No unrestricted business payload | An architecture guard: the System Health module may not name a business model |
| Permission boundary enforced | Asserted on the **rendered payload** for each viewer, never on the session — P1-08's A6, which is the case most likely to pass vacuously |

**The hardest is "never Healthy because nothing was measured."** A status enum
alone does not prevent it. The proposal is that a check must **return** its
status rather than have one assigned, and that a completeness test ties every
declared check to a row — the P1-08 semantics pattern, which caught exactly
this class of gap.

---

## 8. Carried gates — untouched by this plan

| Item | State |
| --- | --- |
| **P1-02 provider-wide SSO Re-check** | **OPEN / CARRIED / UNVERIFIED.** P1-09 renders P1-02's health; it does not own or close that gate |
| **P1-07 live-verification items** | **CARRIED**, unchanged |
| **P1-08 carried items** | **CARRIED**, unchanged |
| **D-19 — sidebar shown to System Administrators only** | **CARRIED.** System Health sits inside System Administration, which is already System-Administrator-only, so nothing outside it is widened |

---

## 9. What would make this plan wrong

- **If System Health is expected to show queue depth, worker heartbeats or a
  Redis panel.** None exists. Showing them would mean inventing them.
- **If "Available" is expected to mean everything is fine.** It means every
  check that ran, passed. On day one the **worker reads Not applicable** and the
  **scheduler reads Not configured** — both correct, and neither a fault.
- **If the Product Owner expects to see a failure state in production.**
  Nothing will be broken to demonstrate one (D-129).

---

## 10. Exit criteria

1. The five areas render, each row carrying a status **produced by a check**.
2. Failure and degraded states are reachable and tested by breaking the
   dependency.
3. No secret, hostname, tenant id, connection string, trace, SQL or business
   record can reach the payload — asserted by allowlist.
4. The operational-detail boundary is enforced and observed on the rendered
   payload.
5. `/up` and `semantiq:health` are **unchanged**, and still gate deployment.
6. Every guard proven non-vacuous, with its mutation recorded.
7. A Product Owner Test Script stating plainly what cannot be observed in
   production, and why.

---

## 11. Status

**PLAN APPROVED — 19 September 2026. D-112 to D-129 ANSWERED.**
No implementation, no schema and no deployment were produced by this plan.
**DESIGN is the next gate.**

**`/up` and `semantiq:health` are unchanged and stay that way.**
**P1-02 remains OPEN / CARRIED / UNVERIFIED. P1-07 and P1-08 carried items
remain carried. D-19 is unchanged — System Health is System Administrator only,
so nothing outside System Administration is widened. P1-10 is not started.**
