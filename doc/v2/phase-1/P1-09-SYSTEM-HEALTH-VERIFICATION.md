# P1-09 — System Health: verification

**Gate C. Not merged, not deployed.** Everything below was executed and
observed in this environment. Where something was not observed, it says so.

| | |
| --- | --- |
| Unit | **P1-09 — System Health** (delivery order 11). **P1-10 still follows** |
| PLAN | merge `0385c92` — D-112 – D-129 answered |
| DESIGN | merge `7f6e129` — three Product Owner corrections applied |
| Schema | **NONE.** No table, no column, no migration |
| Suite | **989 tests, 983 passed, 6 skipped** (all six pre-existing), 75,145 assertions |
| Mutations | **44 run, 44 killed.** Six survived first — `P1-09-MUTATIONS.md` §1 |
| Status | **AWAITING PRODUCT OWNER GATE C REVIEW** |

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
3. **The DESIGN says "eleven rows" and its own tables list fourteen.**
   3 + 1 + 3 + 4 + 3 = 14. The count was wrong in the first draft and survived
   the correction round. **The tables, not the count, are what was built**, and
   the fourteen rows are asserted as an equality. Recorded here rather than
   silently corrected, because the Product Owner approved a document carrying
   that number.

---

## 4. Test coverage

| Area | Cases |
| --- | --- |
| `SystemHealthTest` | 26 — every status in both directions, the payload allowlist on the rendered props, the leak sweep with an injected failure whose message carries a host and a SQLSTATE, the business-language guard, authorisation across six roles plus anonymous, no event recorded, the shape equality |
| `SessionStoreCheckTest` | 9 — S1–S4, nothing left behind, a real session untouched, the statement guard, the synthetic identifier, no transaction left open |
| `SessionRoundTripReadsBackTest` | 3 — a store that accepts writes and returns nothing, and one that returns the wrong row |
| `CacheStoreCheckTest` | 5 — empty, lying, forgetful, throwing, and the probe key |
| `NetworkBoundaryTest` | 12 — the defect's reality, the cold-cache boundary, the stored contract over eight shapes of rubbish, the projection's keys **and values**, the deployment verdict |
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
| **MySQL** | No MySQL server in this environment. The suite runs on MySQL **in CI**, and this unit's session round trip is deliberately DML-only for exactly that reason |
| **A real Microsoft Entra outage** | Would mean breaking sign-in on purpose. The *Unavailable* and *Needs attention* states were rendered from stored P1-02 state, which is what the screen reads in production too |
| **A real database, cache, session-store or filesystem outage in production** | Same. Every failure state is proven against a broken dependency in tests and rendered in a browser; none was induced on a running system |
| **The network boundary in a browser, as a person could see it** | *"No outbound call was made while that page rendered"* is not visible on a screen. Its evidence is the boundary tests and the request recorder, and the Product Owner test script says so rather than implying they confirmed it |
| **A second privileged reader** | Would mean creating a second permanent System Administrator |

**P1-02's provider-wide SSO re-check remains OPEN / CARRIED / UNVERIFIED.**
**P1-07 and P1-08 carried items remain carried. D-19 unchanged. P1-10 not
started.**
