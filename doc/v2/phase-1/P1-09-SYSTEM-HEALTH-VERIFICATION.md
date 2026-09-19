# P1-09 — System Health: verification

**Gate C. Not merged, not deployed.** Everything below was executed and
observed in this environment. Where something was not observed, it says so.

| | |
| --- | --- |
| Unit | **P1-09 — System Health** (delivery order 11). **P1-10 still follows** |
| PLAN | merge `0385c92` — D-112 – D-129 answered |
| DESIGN | merge `7f6e129` — three Product Owner corrections applied |
| Schema | **NONE.** No table, no column, no migration |
| Suite | **997 tests, 991 passed, 6 skipped** (all six pre-existing), 75,232 assertions |
| Mutations | **51 run, 51 killed.** Six survived first — `P1-09-MUTATIONS.md` §1 |
| Gate C review | Three further corrections applied — §1b |
| Status | **AWAITING PRODUCT OWNER GATE C APPROVAL** |

---

## 1. The three corrections, as implemented

### Correction 1 — the network boundary

`IdentityHealthCheck::storedReport()` reads the two P1-02 cache keys and
nothing else. **It does not reference `EntraDiscovery` at all** — not
`metadata()`, not `signingKeys()`, not `probe()`, not the cached accessors — so
there is no read-through to fall through to. Absence returns `not_checked`;
`failed`, `degraded` and `healthy` are returned exactly as stored; the stored
instant and the probe instant come back with them.

`HealthInspector::inspectLocal()` calls the same five private methods
`inspect()` calls, through a shared `localChecks()`. **No check logic is
copied.** `inspect()` still returns all six checks, in the same order, with the
same values.

The roll-up row is **Local service health**, and the screen says it excludes
sign-in.

**Observed, not asserted from a comment:**

| Evidence | Result |
| --- | --- |
| `report()` **does** reach the network on a cold cache | **Confirmed** — `test_the_defect_this_guards_against_is_real` asserts it. If this ever stops holding, the guards below stop being load-bearing, and that is worth knowing |
| `storedReport()` on a cold cache | **Zero outbound requests.** Discovery caches emptied first, every request recorded by a closure, and the discovery cache keys still empty afterwards |
| Whole page render, cold cache | **Zero outbound requests** |
| The sign-in row on a cold cache | **Not checked** — not Available, not Unavailable |
| In a real browser | The only offsite request on the page is the **Google Fonts stylesheet from the shared app chrome** (P1-01), present on every SemantIQ screen. **No request to Microsoft.** See §5 |
| Mutation M-1 (restore `report()`) | **KILLED** by four cases |
| Mutation M-2 (restore `inspect()`) | **KILLED** by two |

**Why the recorder is a closure and not an assertion.** `$this->fail()` inside
an HTTP fake throws an `AssertionFailedError`, and `trustAvailability()` catches
`Throwable` — so the failure would be swallowed and the test would pass. The
record survives the catch.

**Why the cache is emptied first.** With trust already cached, `report()` takes
the network-free branch and the test passes **on the original code**. Cold cache
is the condition under which the defect actually fired.

### Correction 2 — the session round trip

Database driver only. `config('session.connection')`, `config('session.table')`,
a 32-hex synthetic identifier behind a fixed prefix, one `INSERT`, one keyed
`SELECT`, always rolled back. **No `DELETE`, no `TRUNCATE`, no DDL** — asserted
on the statements the connection actually ran, not on the source.

Any other driver is **Not checked**, never Available, and the database check is
not allowed to stand in for it. **No Redis, file or DynamoDB adapter exists**,
and an architecture guard fails the build if one appears.

| Case | Result |
| --- | --- |
| S1 round trip against the database store | **Available** |
| S2 table not there | **Unavailable**, induced through the configured table name — no DDL |
| S3 nothing left behind | Count before and after, **and** the synthetic id queried for directly |
| S3b a real session is untouched | Present, unchanged, same payload and same last-activity |
| S4 seven unsupported drivers | **Not checked** on every one, and the driver name never reaches the screen |
| S4 other direction | Not checked **goes** when the driver is supported |
| Statements issued | Exactly one `insert`, exactly one keyed `select`, and none of `drop`/`truncate`/`alter`/`create`/`delete` |
| A store that accepts writes and returns nothing | **Unavailable** — §1 of the mutation record explains why this case had to be built |

### Correction 3 — the enum

Six cases, six backing values, asserted as an equality and as the wire format
the React layer matches on.

---

## 1b. Three further corrections, after the Gate C review

The Product Owner reviewed head `06242c7` and found two defects in the
implementation. Both are recorded here because both were **real**, and neither
would have been caught by anything already in the suite.

### Correction A — the cache check raced with itself

`CacheStoreCheck` used **one fixed key** for every invocation. Two System
Health renders overlapping by milliseconds therefore raced on a single address:

> A writes its value → B overwrites with its own → A reads B's value →
> **A reports Unavailable, on a cache that is working perfectly.**

Either request's `forget()` could also remove the other's key mid-flight, so
the loser could just as easily read `null`. **A false health incident under
perfectly healthy operation** — and the first false red is what teaches
everybody to ignore the next real one.

**Fixed with a unique key per invocation** — the same namespace prefix plus 32
hex characters of `random_bytes` — not with a lock. Serialising renders behind
a mutex to protect a throwaway diagnostic value would make the screen slower
and could stall under contention, and **a health page must not become the thing
that needs diagnosing**. Two checks that never share an address cannot race.

The short TTL, the strict `put → get → compare`, the cleanup and the
application-key isolation are all unchanged.

| Evidence | Result |
| --- | --- |
| 25 consecutive checks | **25 distinct keys**, each `semantiq:system-health:cache-round-trip:` plus 32 hex characters |
| Two checks against one shared store | **Both Available**, two distinct keys forgotten, **nothing left in the store** — the outcome a person would see, not just the mechanism |
| Key isolation | Falls inside no key P1-02 owns; still inside the P1-09 namespace |
| Mutation: restore the constant key | **KILLED** by three cases |
| Mutation: unique-but-predictable key (a counter) | **KILLED** |
| Mutation: forget the prefix instead of the generated key | **KILLED** by two |

**A live race was attempted and is NOT offered as evidence.** 160 concurrent
renders against the running application produced zero false Unavailable — and
that result is worthless, because `php artisan serve` handles one request at a
time by default, so the race could not have fired whatever the code did. It is
the "green for a reason unrelated to what it claims to check" failure, in a
load test.

Re-running it against the **mutated** build on a ten-worker server, to find out
whether the harness reproduces the race at all, **deadlocked on SQLite file
locking** after the first round and produced no verdict. Production runs MySQL,
so that is a local-harness limit rather than a finding — but it means **no live
concurrency proof exists**, and none is claimed.

**What the correction rests on** is the unit evidence above: 25 invocations
producing 25 distinct keys of the right shape, two checks interleaved against
one shared store both reporting Available and leaving nothing behind, and three
mutations — including the exact previous implementation — each observed to
fail. The race is removed by construction rather than caught by a test, which
is why the test asserts the *property* that makes it impossible.

### Correction B — a stored sign-in state could claim a result with no age

`storedReport()` validated the stored **state** and took the stored **instant**
on trust. So this cache shape —

```php
['state' => 'healthy']
```

— rendered as **Microsoft Entra ID — Available**, with no *"Last checked …"*
beneath it. A malformed instant did the same, because the age formatter returns
`null` rather than throwing.

**The age is the screen's only defence against a stale cached answer**, so an
answer whose measurement time is unknown reads as a measurement taken now. That
is the exact failure `storedReport()` exists to prevent, arriving through the
other field. D-114.

**Both halves are now required.** A stored state is trustworthy only when the
state is one of `healthy` / `degraded` / `failed` **and** the instant actually
parses. Either missing or malformed returns **Not checked**, with no age
invented. The zero-network guarantee is untouched — parsing a string reaches
nobody, and the boundary cases assert that.

| Case | Result |
| --- | --- |
| Valid state + valid instant | The state, **with** its age |
| Valid state + **no** `at` key | **Not checked**, no age |
| Valid state + null, empty or whitespace instant | **Not checked**, no age |
| Valid state + `'recently'`, `'2026-13-45T99:99:99'`, an integer or an array | **Not checked**, no age |
| Garbage state + valid instant | **Not checked**, no age |
| On the **rendered row** | A state with no time renders **Not checked**; a row carrying a real status always carries an age |
| Mutation: drop the instant validation | **KILLED** by two cases |
| Mutation: check `is_string()` only, never parse | **KILLED** |
| Mutation: accept an empty instant | **KILLED** |
| Mutation: reject the instant but still report the state | **KILLED** |

### Correction C — the approved row count

The DESIGN said **eleven**; its own tables list **fourteen**. Approved by the
Product Owner as a **documentation correction that does not reopen DESIGN**.
The DESIGN, the module docblocks, the screen's comments and the test name now
read *14 rows: 9 projected from existing authoritative operational sources, 2
genuinely new round-trip checks, and 3 deployment-derived Jobs rows*.

**The implementation did not change.** It had fourteen rows throughout, and the
fourteen are asserted as an equality.

---

## 2. What this unit does NOT do

- **`/up` is untouched.** Still outside the web middleware group, still exempt
  from maintenance mode, still two words, still 200 or 503.
- **`semantiq:health` is untouched** — still six checks, identity included,
  still exit 1 on a failing check.
- **No schema.** No table, no column, no migration. Guarded.
- **No new event key.** The catalogue is still 77. Rendering records nothing —
  asserted at runtime **and** statically.
- **No write route.** One GET, asserted as an equality.
- **No second probe, no second rate limiter, no second filesystem check, no
  second chain walk.**

**The one change to an approved unit, raised rather than made quietly:**
P1-02's re-check endpoint returned `redirect()->route('identity.health')` on
success while its two refusal branches returned `back()`. It now returns
`back()`. From the SSO Health screen that is the same URL, so P1-02's own
behaviour is unchanged — **verified in a real browser**, and now asserted by a
test that did not exist before. Without it, pressing *Check sign-in now* on
System Health moved the administrator to a different screen.

---

## 3. Three defects found in the repository while building this

None is P1-09's, and each is fixed here because leaving it would leave a guard
reading as covered while nothing was looking at it.

1. **Two delivered-set arrays repeated `'Access Reviews'` and `'Audit'`.**
   `AuthenticationFlowTest` and `IdentityAccessBoundaryTest` each listed both
   keys twice. **PHP collapses a duplicate key silently**, so the second pair
   asserted nothing — the array was two entries shorter than it read. The
   comment three lines above each one describes this exact defect, from the
   last time it happened.
2. **`LifecycleCompletenessTest` was credited with a guard it does not hold.**
   The DESIGN named it as the guard on the System Health route set. It is
   P1-01's organisation-lifecycle test and knows nothing about the prefix.
   Corrected in the DESIGN before merge; the real guard is
   `SystemHealthHasNoWriteRoute`.
3. **The DESIGN said "eleven rows" and its own tables listed fourteen.**
   3 + 1 + 3 + 4 + 3 = 14. The count was wrong in the first draft and survived
   the correction round. **The tables, not the count, are what was built.**
   Raised rather than silently corrected, because the Product Owner had
   approved a document carrying that number — and then **approved the fix as a
   documentation correction that does not reopen DESIGN**. The DESIGN, the
   module docblocks, the screen's comments and the test name now all read
   *14 rows: 9 projected from existing authoritative operational sources, 2
   genuinely new round-trip checks, and 3 deployment-derived Jobs rows*, and
   the fourteen are asserted as an equality so the two cannot drift again.
   **The implementation did not change.**

---

## 4. Test coverage

| Area | Cases |
| --- | --- |
| `SystemHealthTest` | 27 — every status in both directions, the payload allowlist on the rendered props, the leak sweep with an injected failure whose message carries a host and a SQLSTATE, the business-language guard, authorisation across six roles plus anonymous, no event recorded, the shape equality |
| `SessionStoreCheckTest` | 9 — S1–S4, nothing left behind, a real session untouched, the statement guard, the synthetic identifier, no transaction left open |
| `SessionRoundTripReadsBackTest` | 3 — a store that accepts writes and returns nothing, and one that returns the wrong row |
| `CacheStoreCheckTest` | 8 — empty, lying, forgetful, throwing, the probe key, **25 distinct keys, two interleaved checks, and application-key isolation** |
| `NetworkBoundaryTest` | 15 — the defect's reality, the cold-cache boundary, the stored contract over eight shapes of rubbish, **eight shapes of unusable instant**, a garbage state with a good instant, the rendered row, the projection's keys **and values**, the deployment verdict |
| `DeploymentGateUnchangedTest` | 7 — `/up`, `semantiq:health`, `inspect()`'s six checks, a failing identity still failing the gate, the projection unaffected by it |
| `SystemHealthArchitectureTest` | 11 — the route-set equality, the static network boundary, the four-property row, the six backing values, no event key, no reimplemented check, no undeployed-driver adapter, no schema, no write method, no business model |

**Two test fixtures were themselves wrong and were corrected, not worked
around:**

- The storage failure was induced with `chmod 0500`. **The CI container runs as
  root**, which can write to a read-only directory, so the check stayed green
  and the case would have been skipped on the one machine that runs it. It now
  moves the storage path instead, which breaks the dependency for every user.
- The identity-only failure was first induced by emptying the identity keys,
  then by forcing `app.env=production`. **Both also broke the `configuration`
  check** — for reasons that have nothing to do with sign-in — so the local
  projection went red and the code looked wrong when the fixture was. Identity
  is now configured correctly and only its *trust* is removed.

---

## 5. Browser verification — observed, not expected

Local deployment, one organisation, one System Administrator, a real session
written through Laravel's own session store. `SESSION_DRIVER=database` and
`CACHE_STORE=file`, the shapes production runs.

**Five areas × 1440px and 390px × light and dark = 4 full renders**, plus the
*Check sign-in now* round trip, its rate-limited second press, the sidebar
journey and Back.

| Check | Observed |
| --- | --- |
| Elements crossing either viewport edge | **0** — measured **per element**, not per page. The page-level scroll check also passed, and it is the weaker one: P1-08 found a four-pixel overflow that page-level scrolling never revealed |
| All fourteen rows render at both widths | Yes. At 390px the status badge wraps below the row name rather than squeezing it |
| **All six statuses rendered live** | **Available, Unavailable, Needs attention, Not applicable, Not configured, Not checked** — five occurred naturally; Degraded was produced from a stored P1-02 state rather than claimed from a passing test |
| Neutral statuses read as neutral | Yes — *Not applicable* and *Not configured* are dashed and grey in both themes; *Not checked* is filled and grey. Distinguishable **without colour** by their marks (`–` and `?`) |
| Dotted key, path, driver, hostname, identifier or figure on screen | **None** |
| Stored age | *"Last checked 3 hours ago"* on the sign-in row only. No other row carries one |
| Sidebar | **System Health is present, unlocked, and reachable by clicking** — `aria-current="page"` on arrival. Navigation that exists but cannot be discovered is not navigation |
| *Check sign-in now* | Records the check, shows *"Health re-checked."* and **stays on System Health**. This is the P1-02 redirect fix, confirmed in a browser rather than inferred from a test |
| Second press | Refused politely — *"Health was checked moments ago. Try again shortly."* No timer, no countdown |
| Back | Returns to `/console` |
| Focus | The one control takes a visible 2px focus ring |
| Console errors | **One, and it is not this unit's.** The shared Google Fonts stylesheet in the app chrome fails with `ERR_CERT_AUTHORITY_INVALID` because **Chromium in this sandbox does not trust the session's egress proxy CA**. It is present on every SemantIQ screen and has nothing to do with P1-09. Not "fixed" here |

### 5.1 Two defects found by looking, and fixed before this handover

1. **The scheduler read *"Not set up"*.** Friendlier, and **not what D-121
   says**. The Product Owner test script quotes *"scheduler says Not
   configured"*, so a script written against the approved decision would have
   failed on wording alone. The six status phrases are now pinned by a test,
   read from the component's source — there is no JavaScript test runner in
   this project, so nothing else would have caught a drift.
2. **An area was named after a row inside it.** The area *Background work*
   contained a row *Background work*, so the same words appeared twice, three
   lines apart, meaning the heading and one of the things under it. The UI
   standard forbids naming a group after its cluster; this is the same mistake
   one level down. The area is now **Tasks and timetables**, and a test asserts
   no area shares a name with its own rows.

### 5.2 The polish gate

> Would a professional SaaS product team be comfortable showing this exact
> screen to a customer?

**Yes**, after the two fixes above. Every row is a business noun and a finished
sentence; no implementation term, figure or identifier appears; the three
neutral statuses read as neutral rather than as faults; both themes and both
widths are consistent.

---

## 6. What is NOT verified, and why

| Not verified | Why |
| --- | --- |
| **Anything in production** | This unit is **not merged and not deployed** |
| **MySQL, locally** | No MySQL server in this environment. **CI now runs the System Health suite against MySQL 8.4 as a required step** — see below |
| **A real Microsoft Entra outage** | Would mean breaking sign-in on purpose. The *Unavailable* and *Needs attention* states were rendered from stored P1-02 state, which is what the screen reads in production too |
| **A real database, cache, session-store or filesystem outage in production** | Same. Every failure state is proven against a broken dependency in tests and rendered in a browser; none was induced on a running system |
| **The network boundary in a browser, as a person could see it** | *"No outbound call was made while that page rendered"* is not visible on a screen. Its evidence is the boundary tests and the request recorder, and the Product Owner test script says so rather than implying they confirmed it |
| **A second privileged reader** | Would mean creating a second permanent System Administrator |
| **A live cache race between two real concurrent renders** | `php artisan serve` is single-threaded by default, so the 160-render pass could not have caught it; a ten-worker run against the **mutated** build deadlocked on SQLite file locking before reaching a verdict. Production runs MySQL, so this is a harness limit. **§1b Correction A rests on the unit cases and three mutations, not on a load test** |

### 6.1 A gap in CI, found by reading the green run

**The MySQL job ran People, Domains, Access, Security, Reviews and Audit — and
not System Health.** So the one check in this unit that opens a transaction and
writes a row was proven on SQLite only, on a green build.

That is the shape of the P1-08 failure exactly: four cases green on SQLite and
red on MySQL, because the two engines disagree about what a transaction does.
This unit's round trip runs on a **savepoint** — it is inside the transaction
`RefreshDatabase` already holds — and savepoint behaviour is precisely where
they have already diverged once here.

**A required `Run the System Health suite against MySQL` step was added**, with
the same floor-count and no-skip guards the other suites carry, so a path that
matches nothing fails the build rather than reporting zero tests as success.

---

**P1-02's provider-wide SSO re-check remains OPEN / CARRIED / UNVERIFIED.**
**P1-07 and P1-08 carried items remain carried. D-19 unchanged. P1-10 not
started.**
