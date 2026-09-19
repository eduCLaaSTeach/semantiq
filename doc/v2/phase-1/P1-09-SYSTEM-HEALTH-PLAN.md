# P1-09 — System Health: PLAN

**PLAN ONLY.** No DESIGN, no implementation, no schema, no deployment.

| | |
| --- | --- |
| Unit | **P1-09 — System Health** (delivery order 11). **P1-10 Administration Home still follows** |
| Menu | System Administration → **System Health** — currently a **locked** node in `ApprovedMenu` |
| Areas | **Application · Integrations · Jobs · Connections · Service Health** |
| Purpose | Operational visibility **without exposing business data** |
| Exit | Platform support can identify operational failures safely |
| Status | **AWAITING PRODUCT OWNER REVIEW** — D-112 to D-129 |

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
| Queue | **`sync`** — every job runs inline, in the web request |
| **Queue worker** | **NONE** |
| **Scheduler** | **NONE.** `routes/console.php` contains only Laravel's stock `inspire` command, and `bootstrap/app.php` registers no `withSchedule` |
| Redis | **NOT PRESENT** |
| Mail | **NOT CONFIGURED** |
| Fabric / Power BI / any Phase 2–3 service | **NOT PRESENT** |
| External integrations | **Microsoft Entra ID, and nothing else** |
| Hosting | Shared cPanel, MySQL 8.4 |

**This is the single most important thing in this plan.** A generic health
dashboard would show Redis, a queue depth and a scheduler heartbeat. Three of
those do not exist here, and rendering them green would be a lie about the
deployment. See **D-121**.

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
| **Jobs** | Queue connection · Worker presence · Scheduler presence | Configuration + **honest absence**. See D-121 |
| **Connections** | Database · Session store · Cache store · Filesystem | `HealthInspector::database()` plus **new, bounded** reads for the other three |
| **Service Health** | The roll-up — the same verdict `/up` and `semantiq:health` give · Audit chain intact · Evidence start instant present | `HealthInspector::isHealthy()`, `AuditChainVerifier` |

**Only three genuinely new checks** are proposed — session store, cache store
and filesystem — and each is a **round trip**, not a config read: write a value,
read it back, delete it. A check that reads `config('cache.default')` and
reports Available is precisely the hard-coded success this unit must not ship.

---

## 4. The distinctions this plan is required to make

| Pair | How they differ here |
| --- | --- |
| **Health vs configuration/readiness** | Configuration says the keys are present; health says the round trip worked. `ConfigurationValidator` is the first; `select 1` is the second. Both are shown, separately |
| **Internal vs external** | Database, cache, session, storage and assets are ours. **Entra is somebody else's**, and its failure is a different sentence and a different urgency |
| **Not configured / not applicable vs failed** | `QUEUE_CONNECTION=sync` is **Not configured**, not Unavailable. Mail is **Not applicable** in Phase 1. Neither is a fault, and neither is Healthy |
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

## 6. Product Owner decisions — **D-112 to D-129 — ALL OPEN**

| # | Decision | Recommendation |
| --- | --- | --- |
| **D-112** | Status vocabulary | **Available · Degraded · Unavailable · Not configured · Not applicable**, plus **Not checked** for a check that could not run. Six, because five cannot express "we do not know". Fixed enum; no free text |
| **D-113** | Which checks belong to each area | §3. Reuse `HealthInspector` and `IdentityHealthCheck` wholesale; **three new round-trip checks only** — session, cache, filesystem |
| **D-114** | Live vs cached | **Application, Connections and Service Health are LIVE on render** — all are local and cheap. **Integrations is CACHED**, shown with its age, re-checked only on request, exactly as P1-02 already does |
| **D-115** | Timeout for external dependencies | A short, fixed timeout on the Entra probe; a timeout renders **Degraded**, never Unavailable, because "we could not reach it in 3 seconds" and "it is down" are different claims |
| **D-116** | Read-only? | **Yes, entirely.** GETs, plus **one POST** for a manual re-check that performs no remediation — the same shape as P1-02's re-check |
| **D-117** | Who may access | **Open.** Recommendation: the screen at `EvidenceRead`; **operational detail at `PlatformAdmin`** — System Administrator only. An Auditor reading evidence is not the same as an operator reading infrastructure |
| **D-118** | Which technical details may be shown | Status, the check's name in business words, a chosen sentence, and an age for a cached result. **No value read from configuration** |
| **D-119** | Hostnames, tenant ids, connection strings | **Open, and the sharpest one.** Recommendation: **none of them, to anybody**. Not withheld-for-some — absent. A tenant id identifies the customer's directory, and a health screen is a poor reason to put one on a page |
| **D-120** | Do connection checks expose metadata? | Recommendation: **state only**. No driver name, no version, no database name, no free disk figure. Each is a small fingerprinting gift |
| **D-121** | Jobs with no scheduler or worker | **Open.** Reality: `QUEUE_CONNECTION=sync`, no worker, no scheduler. Recommendation: **Not configured**, with one plain sentence saying work runs inline. **Never Healthy, never Unavailable** |
| **D-122** | Relationship to P1-02 Identity health | Recommendation: System Health **renders P1-02's check** and links to the Identity & SSO screen. It does not re-probe and does not re-interpret. Two screens disagreeing about one deployment is worse than one screen |
| **D-123** | Relationship to P1-06 Security Status | Both remain. P1-06 answers *"configured securely?"*; P1-09 answers *"working?"*. Recommendation: **no shared rows** and no cross-rendering |
| **D-124** | Relationship to P1-08 Audit | Recommendation: System Health shows that the **evidence store is working** — chain intact, start instant present. It shows **no evidence** and never becomes a second way to read the log |
| **D-125** | Do health checks create Audit evidence? | **Open.** Recommendation: **a manual re-check does; rendering does not.** P1-02 already has `identity.health.checked`. Logging every page view would bury the events that matter — D-71's rule, and P1-08 inherits the noise |
| **D-126** | Refresh / manual re-check | Recommendation: explicit button, no auto-refresh, no polling. A page that re-probes on a timer is a page somebody leaves open overnight |
| **D-127** | Rate limiting | Recommendation: **reuse P1-02's** one-per-administrator-per-60-seconds on anything touching an external service. Local checks need none |
| **D-128** | Production-safe degraded simulations for tests | Recommendation: break the **dependency**, never the check — the `EngineBoundaryTest` and `AuditFailClosedTest` precedent. **And never with DDL**: MySQL commits the open transaction implicitly, which cost four green-locally, red-on-MySQL cases in P1-08 |
| **D-129** | What the Product Owner can verify in production | **Open, and it needs stating before DESIGN.** Realistically: that the screen exists, that every row carries a status and a sentence, that **Jobs says Not configured**, that Integrations shows a real last-probe age, and that no hostname, tenant id or trace appears. **Failure states are not observable without breaking production, and nothing will be broken to show them** |

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
- **If "Healthy" is expected to mean everything is fine.** It means every
  check that ran, passed. Jobs will read **Not configured** on day one and that
  is the correct answer.
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

**PLAN ONLY — AWAITING PRODUCT OWNER REVIEW.**
No DESIGN. No implementation. No schema. No deployment.
D-112 to D-129 are open and none is assumed answered.
**P1-10 is not started.**
