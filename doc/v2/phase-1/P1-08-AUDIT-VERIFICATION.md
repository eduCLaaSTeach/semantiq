# P1-08 — Audit: verification record

**What was executed and observed.** A passing automated test is not the same
claim as an observed production result, and nothing below presents one as the
other.

| | |
| --- | --- |
| Unit | **P1-08 — Audit** |
| PLAN | merge `c5580c697e0718793e786ce45b623c3d220b50d9` (D-95 – D-111 answered) |
| DESIGN | merge `43d5addf5788c056a6a3c62ccbfdb8de5dc93ebd` (three corrections) |
| Implementation | merge `68c5c6f9f72f0ce93eaa12787e58e80c51b88c90`, deploy run **141** |
| Status | **PRODUCT OWNER ACCEPTED. GATE D CLOSED. P1-08 CLOSED** — 19 September 2026, §12 |

---

## 1. What was built

| Area | Files |
| --- | --- |
| Vocabulary | `Security/Catalogue/` — `AuditCategory`, `ActorSource`, `SubjectSource`, `TargetSource`, `OrganisationSource`, `OutcomeClass`, `EventSemantics`, and `EventCatalogue::SEMANTICS` (77 entries) |
| Seam | `Platform/Security/EvidenceRecorder` (interface), `UnrecordedEvidence` |
| Module | `Modules/Audit/` — `AuditEvent`, `AuditChainHead`, `AuditHash`, `AuditWriter`, `AuditChainVerifier`, `AuditProjection`, `AuditController`, `AuditViolation`, `AuditServiceProvider` |
| Screens | `Pages/Audit/Log.jsx`, `Components/AuditTabs.jsx`, `.aud-*` styles |
| Schema | one migration, **two new tables**, nothing altered on any existing table |
| Events | **none added.** P1-08 stores the existing 77 and extends the vocabulary by nothing |

---

## 2. THE CORRECTION THAT MATTERED MOST — actor semantics

The DESIGN's first draft asserted `user_id` is always the subject and
`related_id` always the actor. **The repository disproves it**, and the Product
Owner caught it before a line was written:

| Event | `user_id` holds |
| --- | --- |
| `auth.login.succeeded` | the person signing in — the **actor** |
| `user.provisioned`, `user.deactivated`, `user.purged` | the **administrator**; the affected person is `entity_id` |
| `organisation.created`, `team.moved` | the **creator** |
| `group.member.added` | the **administrator**; the member is `related_id` |
| `access.role.assigned` | the **subject**; the actor is `related_id` |
| `access.review.item.revoked` | the same — **the inverse of user lifecycle** |
| `auth.login.refused.unknown_identity` | nothing. There is no SemantIQ account |
| `access.engine.failed` | nothing. There is no actor |

A single rule would have misattributed roughly half the estate — **plausibly,
and invisibly**: a trail that reads perfectly and names the wrong person.

So every one of the 77 keys declares its own actor, subject, target and
organisation source, in the **existing** catalogue.
`AuditSemanticsTest::test_every_declared_event_has_declared_audit_semantics`
asserts the declared set and the semantics map are the **same set, as an
equality** — an event added without deciding its audit meaning fails the build.

`A5` asserts the recorded actor in **all five** areas the Product Owner named.
A case covering one would pass under any single convention, which is exactly
how the error survived design review.

---

## 3. Bounded corrections to existing units

Each uses a **key that was already permitted**. No event was added and no
`ALLOWED_KEYS` change was made.

| Change | Why it was necessary |
| --- | --- |
| `organisation_id` added to the five P1-05 entitlement/scope/ceiling events, the three P1-07 review events, group membership, user activate/deactivate/purge, `auth.login.succeeded`, `auth.login.refused.inactive`, `auth.logout` and the three step-up events | **D-99 would have been nominally correct and factually wrong.** Without it those rows are platform-scoped, so an Organisation Administrator would never see their own organisation's access or review evidence |
| `user_id` and `organisation_id` added to `user.provision.refused` | The refusal recorded **nobody**. A refusal that names no actor is evidence of an attempt by no one |
| `CallbackController::issueSession()` records **before** it regenerates the session | D-111. Session state is not transactional, so the old order left somebody signed in and unevidenced when the write failed. See §5 |

**`auth.session.expired` was deliberately left platform-scoped.** The middleware
records it before it loads the user, so no organisation is available, and
reordering P1-00's session checks to make one available is outside this unit.
System Administrator only, and stated rather than glossed.

---

## 3a. GATE C CORRECTION — D-111 atomicity, the systemic gap

**The Product Owner refused Gate C on a direct contradiction with approved
D-111**, and was right to.

The DESIGN said a state-changing operation and its evidence share a
transaction. **They did not.** `OrganisationService::updateProfile` saved and
then recorded, with nothing around it; so did `UserDirectoryService::reactivate`
and three `GroupService` methods. A failed audit write would have raised
**after** the change had committed — the operation reporting failure while
having in fact happened.

### 3a.1 What the repository-wide audit found

**54 state-change emit sites reviewed**, across every module.

| Finding | |
| --- | --- |
| Emit sites examined | **54** |
| Already atomic | 26 |
| **Corrected** | **14 methods**, across 6 modules |
| Reclassified rather than wrapped | **2 events** |
| False positives in the first static pass | 12 — private helpers already called from inside a transaction |

**Methods corrected**

| Module | Method |
| --- | --- |
| Organisation | `OrganisationService::updateProfile` |
| Organisation | `StructureService::setStatus` — the one path every activation and deactivation of a legal entity, business unit, department and team goes through |
| Organisation | `StructureService::createLegalEntity`, `createBusinessUnit`, `createDepartment`, `createTeam`, `moveTeam`, `associate`, `dissociate` |
| Organisation | `MembershipService::add`, `MembershipService::remove` |
| Organisation | `ManagementService::clearManager` |
| People | `UserDirectoryService::reactivate` |
| People | `GroupService::deactivate`, `reactivate`, `removeMember` |
| Access | `StepUpService::begin` |
| Platform | `GrantIssuer::issue` |
| Domains | `BaselineDomainInitialiser::initialise` |
| Reviews | `ReviewDecisionService::supersede` — **public**, so it takes its own boundary. No test exercised the unwrapped path; only the static guard saw it |

**Reclassified, not exempted**

| Event | Now | Why a transaction cannot apply |
| --- | --- | --- |
| `auth.login.succeeded` | `StateChangeRecordedFirst` | The session is not in the database. The guarantee is the **order**: evidence first, then the session, so a failure leaves nothing to roll back |
| `identity.health.state_changed` | `BestEffort` | It is a **cache** note about whether Microsoft is reachable. Nothing in the database changes; demanding an atomic boundary was demanding the impossible |

**There is no exemption list, and a test asserts there is none.**

### 3a.2 Two guards, because neither is sufficient

| Guard | Catches | Proven by |
| --- | --- | --- |
| **Runtime** — `AuditWriter` refuses a `StateChange` outside a transaction | Any emitter a test exercises | M-B4, M-B5, M-B7 |
| **Static** — `AuditAtomicityTest` walks all 54 emit sites, following private helpers to their callers | The emitter **no test exercises**, at build time | M-B1, M-B2, M-B6, M-B9 |

The runtime guard needs a **baseline**: `RefreshDatabase` holds a transaction
open for every test, so without it the harness satisfies the check on every
emitter's behalf. **Two mutations survived the first measurement because of
exactly that** — disabling the check changed nothing, and setting the baseline
to a literal 0 made it vacuous. `AuditAtomicityGuardTest` now points at the
guard itself, and both are killed.

### 3a.3 Fail-closed tests for every corrected class

`AuditRollbackTest` — **11 cases, every one asserting the BUSINESS STATE rather
than the exception**, because an assertion that the call threw passes just as
happily when the change survived, which is the defect itself.

| Case | Asserts |
| --- | --- |
| Organisation update | the name is unchanged |
| User reactivation | the account is still inactive |
| Group deactivate / reactivate | the status is unchanged |
| Group membership removal | `left_at` is still null |
| Team membership removal | `left_at` is still null |
| Structure deactivation | the business unit is still active |
| Clearing a manager | the reporting line is still current |
| **A successful change** | the change **and** its evidence both committed |
| An already-atomic role grant | still rolls back — so a fix to P1-01 that broke P1-05 would fail |

The failure is induced by **removing the chain-head row**, never by dropping a
table: MySQL commits the open transaction implicitly on DDL, which cost four
green-locally, red-on-MySQL cases once already (§6.1).

---

## 4. Tests

| Suite | Result |
| --- | --- |
| Full suite | **915 tests, 909 passed, 6 skipped**, 73,945 assertions |
| `tests/Feature/Audit` | 52 (50 + **2 MySQL-only**) |
| `tests/Architecture` | 183 passed |
| `vendor/bin/pint --test` | passed |

The six skips are the four pre-existing ones plus the two chain-head
concurrency cases, which **cannot fail on SQLite** and run against MySQL in CI.

---

## 5. D-111, and the ordering it demanded

| Case | Behaviour | Test |
| --- | --- | --- |
| A state change that cannot be evidenced | **Rolls back.** The insert joins the caller's transaction | A13 |
| A sign-in that cannot be evidenced | **No session is issued** | A11 |
| A refusal whose evidence fails | **Stays refused**, `Log::critical` only | A12 / A20 |
| Sign-out, session expiry | **Never prevented** | ruled, tested |

**A11 asserts the session, not the exception**, because that is the only thing
that catches the defect: an assertion on the throw would pass with the old
ordering, where somebody was signed in first and unevidenced afterwards.

### 5.1 Refusals need the OPPOSITE behaviour, and this is where the work went

`ReviewDecisionService`, `UserDirectoryService` and `StepUpService` each record
a refusal and **then throw**, inside `DB::transaction`. Evidence that simply
joined that transaction would **vanish with the refusal it was recording** —
and a refusal that leaves no trace is precisely the attempt somebody wanted
hidden.

A second database connection was not available: the test database is SQLite
in-memory, where a second connection is a second **empty** database, so that
design would have had to be trusted rather than proven.

So a refusal is written immediately **and remembered** while a transaction is
open. If the transaction unwinds, the row is written again — **conditionally**,
because `StepUpService` records a refusal inside a transaction that then
*commits*, and an unconditional re-write would record that refusal twice. A
duplicated refusal is a falsified count, which is its own kind of lying.

**The limit, stated:** if the process dies between the rollback and the
re-write, that refusal is lost, and `Log::critical` is the only trace.

---

## 6. Mutations — 18 run, 18 killed, one after the guard itself was fixed

| # | Mutation | Result |
| --- | --- | --- |
| M-A1 | Remove the persist from `SecurityEventLogger::record()` | **KILLED** — 19 cases |
| M-A2 | Read the actor from `user_id` for access events | **KILLED** — 3 cases, including A5 |
| M-A3 | Give `semanticsFor()` a `default =>` bucket | **KILLED** |
| M-A4 | Drop `lockForUpdate()` from the chain append | **MySQL only.** Recorded honestly — see §7 |
| M-A5 | Remove the predecessor comparison from the verifier | **KILLED — after the test was fixed.** See below |
| M-A5b | Remove the sequence-gap check | **KILLED** |
| M-A6 | Drop the head/last-row comparison | **KILLED** |
| M-A7 | Return `null` instead of the withheld sentence | **KILLED** |
| M-A8 | Drop the `orWhereNull` branch from the projection | **KILLED** |
| M-A9 | Record the sign-in **after** the session writes | **KILLED** |
| M-A10 | Fail closed on every outcome class | **KILLED** — sign-out and refusals both |
| M-A11 | Class `access.review.refused` as a `StateChange` | **KILLED** |
| M-A12 | Drop the existence check from the recovery | **KILLED** — the refusal is recorded twice |
| M-A13 | Add a `DELETE` route under `/console/audit` | **KILLED** |
| M-A14 | Drop a column from `AuditHash::FIELDS` | **KILLED** |
| M-A15 | Swallow the `AuditViolation` | **KILLED** |
| M-A16 | Remove the evidence start date from the payload | **KILLED** |
| M-A17 | Bind `UnrecordedEvidence` in the container | **KILLED** |
| M-A18 | Make the writer a per-resolution binding | **KILLED** |
| M-A19 | Let a failed sign-in fall through to the console instead of the refusal screen | **KILLED** |

### 6.1 A GREEN LOCAL SUITE THAT WAS RED ON MySQL

**The first CI run failed, and the cause was the test technique rather than the
code.** All four fail-closed cases induced a storage failure with
`Schema::drop('audit_events')`. On SQLite, DDL is transactional and it worked.
**On MySQL, DDL commits the open transaction implicitly** — so the drop silently
ended `RefreshDatabase`'s wrapping transaction while Laravel went on believing
it was inside one, and the next savepoint rollback failed with
*"SAVEPOINT trans2 does not exist"*.

Four cases were green on SQLite and red on **the engine production uses**, and
nothing local could have told the difference. They now **delete the chain-head
row** instead: every append reads it under a lock and cannot proceed without it,
so the failure reaches `AuditWriter` exactly as a full disk would, through
ordinary DML that behaves the same on both engines.

The same run surfaced a second thing worth fixing: a sign-in that could not be
evidenced raised out of the callback and would have shown a stack trace on a
login page. It now redirects to the ordinary **"sign-in unavailable"** screen.
An unevidenced sign-in is a security failure; so is an exception on a login
page, and the person cannot act on either. **M-A19** covers it.

`EvidenceNotRecorded` moved from `Modules\Audit` to `Platform\Security` as part
of that fix: the sign-in callback has to react to a failed write, and an earlier
unit naming a later one is the boundary reversal P1-07's Gate C caught once
already. The seam is Platform's, so its failure is too.

### 6.2 M-A5 SURVIVED FIRST, and the test was the defect

Removing **both** the predecessor comparison and the sequence check left
`test_a_removed_row_is_reported` passing — because the head/last-row comparison
caught the deletion instead. The case was reporting safety it was **not
measuring**: CLAUDE.md §2 exactly.

A deletion is also not what a forger does. Somebody with database access
**edits a field and recomputes that row's own hash**, which is easy. Only the
link to the *next* row survives that. `test_a_forged_row_with_a_recomputed_hash_is_reported`
was added to exercise precisely that, and the deletion case now names the
finding it must produce rather than accepting any of them.

---

## 7. What is NOT proven here

- **The chain-head lock cannot be observed on SQLite.** `lockForUpdate()`
  compiles away, so M-A4 **survives locally** and the measurement would report
  a lock the writer does not hold. It runs against MySQL 8.4 in CI, in a step
  added for it, which fails if the case skips.
- **MySQL migrate → rollback → migrate ran in CI, not here.** No MySQL server
  is available in this environment.
- **Nothing in production has been observed.** This unit is not merged and not
  deployed.

---

## 8. Browser verification — observed, not expected

Local deployment, one organisation, one System Administrator. **Every row was
produced through `SecurityEventLogger`**, not inserted — so what the browser
rendered is what production would store.

Four tabs × 1440px and 390px × light and dark = **16 screens**.

| Check | Observed |
| --- | --- |
| Evidence start date | Present on **all sixteen** — *"Evidence begins 19 September 2026, 08:07. Activity before that time was not recorded and is not available here."* |
| Rows read as sentences | *Role assigned · Signed in · Team moved · Sign-in refused — account not recognised · Sign-in health changed* |
| Dotted event key anywhere | **None.** Swept for any `a.b.c` identifier |
| Internal terms | **None** — swept for `user_id`, `related_id`, `entity_id`, `organisation_id`, `row_hash`, `sequence`, `state_change`, `best_effort`, `undefined`, `null` |
| Chain banner | Absent — the chain verified on every load |
| Tabs | In viewport, unclipped and clickable at both widths; wrap below 640px, the G2 correction inherited unchanged |
| Elements crossing the edge | **0**, after the defect below |
| Console errors | None |

### 8.1 Three defects found by looking, and fixed before this handover

1. **The Action filter overflowed a 390px screen by four pixels.** A `<select>`
   sizes itself to its **widest option**, and *"Sign-in refused — account not
   recognised"* is wide. `min-width: 0` was present and looked sufficient;
   `max-width: 100%` was what it needed. **The page did not scroll sideways** —
   only the element-level sweep saw it. That is the G2 lesson for the fourth
   time.
2. **A raw directory identifier under a business label.** A refused sign-in read
   *"About: e7c1d2f0-outside-the-directory"* — an internal identifier answering
   "about whom?", and a duplicate of the Directory account field two columns
   along. The subject is now left empty for an outside account; the identifier
   appears only where it is labelled as one and withheld from viewers who may
   not see it.
3. **A database row number on screen.** *"Role assignment #12"* is a key that
   means nothing to the reader. The id is still stored — evidence should point
   at the exact record — and the screen shows the kind of thing instead.
   Who, about whom and when already tell two rows apart.

Also: `About` is suppressed where it is the same person as `Who`, because
*"Who: Priya Nair / About: Priya Nair"* on a sign-in is noise, and noise is
what a reader learns to skip.

---

## 9. What the Product Owner should know before testing

- **Audit is permanent.** There is no delete path and no retention job (D-105).
  Anything done while testing — **including a refused action** — is recorded
  for good.
- **Evidence begins when this is deployed.** Nothing before that exists
  anywhere searchable (D-109), and the screen says so on every tab.
- **"Tamper-resistant" means detectable, not impossible.** Anybody with
  database or SSH access can delete rows and no application can stop them on
  this hosting. The chain makes it impossible to do **unnoticed**.
- **An Auditor still cannot reach the screen** (D-19, carried). The route-level
  permission is implemented and tested; the sidebar is unchanged and **no
  account was created** to make it observable.

---

## 10. Deployment and production verification

| | |
| --- | --- |
| Implementation merge | `68c5c6f9f72f0ce93eaa12787e58e80c51b88c90` (PR #117) |
| Verification workflow merge | `ddd12478336823f9ef81362c060c7cafd031c325` (PR #118) |
| Post-merge CI | run **294 SUCCESS** |
| Deployment | `Deploy to cPanel (SSH)` run **141 SUCCESS** — `migrate --force`, as always |
| Production verification | `Verify P1-08 Audit state (read-only)` run **1 SUCCESS** |

### 10.1 Schema, observed on production

| Check | Observed |
| --- | --- |
| `audit_events` exists | **yes** |
| `audit_chain_head` exists | **yes** |
| Chain-head rows | **exactly 1**, at id 1, sequence 0 |
| Genesis hash | **64 characters** — a sha256 digest |
| Evidence start instant | **populated** |
| `ip_address` column | **absent** |
| `user_agent` column | **absent** |
| Undeclared columns | **none** — asserted in both directions, so an extra column and a missing one each fail |
| Pre-existing tables altered | **none**. `users`, `role_assignments`, `domain_entitlements`, `access_review_items`, `pending_step_ups`, `organisations` and `business_domains` all unchanged |
| Chain verification | **intact**, over 0 rows |

### 10.2 Route surface, read from the DEPLOYED route table

```
GET console/audit
GET console/audit/admin-changes
GET console/audit/configuration
GET console/audit/security-events
```

Four GETs and nothing else, each behind `EnsureSessionIsCurrent`,
`RequireActionClass:evidence_read` and `RequireOrganisation`. Read with
`route:list` **on the server**, because what matters is what is served rather
than what the repository says.

**Every write verb answers 405** on production: `POST`, `PUT`, `PATCH` and
`DELETE` against `/console/audit` are Method Not Allowed. No write path exists
to reach.

### 10.3 Unauthenticated behaviour

All four Audit routes redirect an unauthenticated visitor to the sign-in page,
**identically to every other console route**, and the returned page contains
**none** of `audit_events`, `row_hash`, `chain_head`, `occurred_at`,
`Evidence begins` or `User Access`. The refusal is not an oracle for whether
there is evidence behind it.

**No 500s anywhere.** `/`, `/up` and all four tabs answered cleanly.

### 10.4 Evidence count immediately after deployment: ZERO

**0 rows, and that is correct.** D-109: evidence begins when the storage does,
nothing was backfilled, and no event has been emitted on production since the
migration ran. **Nothing was manufactured to change that number.**

### 10.5 What was NOT verified on production, and why

- **The signed-in screens.** Reaching them needs a Microsoft sign-in, which
  only the Product Owner can perform. Their rendered state is the 16-screen
  local pass (§8) plus Gate D.
- **Chromium could not be pointed at production.** The session's egress proxy
  re-terminates TLS and the browser does not read its CA bundle; disabling
  certificate verification to get a screenshot is not an acceptable trade. The
  production HTTP checks above were made over HTTPS **with** that bundle, and
  the browser evidence stays local and honest about being local.

---

## 11. Gate D — the Product Owner script

**Eight checks, normal production activity only.** Nothing here asks for a
failure to be induced, an account to be created, or data to be invented.

### Before you start

**Audit is permanent.** There is no delete, no archive and no export. Anything
you do while testing is recorded for good, under your name — and a refused
action is recorded exactly because it was refused.

**The log is empty right now.** Evidence begins at the moment shown on the
screen, and nothing before it was ever stored anywhere searchable. **An empty
first tab is correct**, not a fault — check 3 is what puts the first row in it.

| # | Check | Expected | PASS / FAIL |
| --- | --- | --- | --- |
| 1 | Open **System Administration → Audit** | Four tabs: **User Access, Admin Changes, Security Events, Configuration Changes**. No warning banner | |
| 2 | Read the line above the list | *"Evidence begins \<date, time\>. Activity before that time was not recorded and is not available here."* — on **every** tab | |
| 3 | **Sign out and sign back in normally.** Return to **Audit → User Access** | An entry reading **Signed in**, naming **you**, with today's date and time | |
| 4 | Read that entry closely | **Who**, **Outcome** and the time read as plain English. No codes, no identifiers, no field names, nothing you would have to ask a developer about | |
| 5 | Choose an **Action** from the dropdown and press **Apply**; then set **From** to tomorrow and **Apply**; then **Clear** | The list narrows, then shows *"Nothing here matches what you are looking for."*, then returns in full. Every dropdown option reads as business English | |
| 6 | Look across **all four** tabs | **No dotted keys** (`auth.login.succeeded`), **no row numbers** (`#12`), **no directory identifiers**, no secrets. Anything technical is either absent or plainly labelled as withheld | |
| 7 | Narrow the window to phone width, switch **light** and **dark**, then press browser **Back** | Nothing cut off or past the edge; both themes look deliberate; Back returns along the trail | |
| 8 | Open **Security Status → Security Events** | Still the **catalogue** — what is recorded and how it is protected — now pointing at Audit for what happened. **Not** a second copy of the history | |

### Not in this script, and carried as automated evidence

**Database failure, chain corruption, concurrency, failed sign-in accounts and
additional privileged users are not to be induced.** Each would mean breaking
production on purpose or manufacturing access that should not exist.

| Carried | Automated evidence |
| --- | --- |
| Fail-closed behaviour | `AuditFailClosedTest`, `AuditRollbackTest` — 15 cases |
| The tamper warning | `AuditChainTest` — altered, removed, end-removed and forged |
| Concurrent writes | `AuditChainConcurrencyTest`, **MySQL in CI** |
| An Auditor or Organisation Administrator reading Audit | `AuditRoutesTest`, `AuditVisibilityTest`. **D-19 carried** — the sidebar is unchanged and no account was created |
| Evidence older than the deployment | Does not exist. D-109 |


---

## 12. P1-08 ACCEPTED — Gate D closed

**Product Owner Gate D: 8 of 8 PASS.** 19 September 2026, on production.

| | |
| --- | --- |
| Implementation merge | `68c5c6f9f72f0ce93eaa12787e58e80c51b88c90` |
| Deployment | `Deploy to cPanel (SSH)` run **141** — success |
| Production verification | `Verify P1-08 Audit state (read-only)` run **1** — success |
| Gate D | **CLOSED** |
| P1-08 | **CLOSED** |

### 12.1 What the Product Owner observed in production

| # | Check | Result |
| --- | --- | --- |
| 1 | **System Administration → Audit**, four tabs | **PASS** |
| 2 | Evidence-start statement | **PASS** |
| 3 | A real **Signed in** entry after an ordinary sign-out and sign-in | **PASS** |
| 4 | Correct actor, business-readable outcome and time | **PASS** |
| 5 | Filters | **PASS** |
| 6 | No raw event key, no database row id, no directory internals, no secrets | **PASS** |
| 7 | Phone width, light and dark, browser Back | **PASS** |
| 8 | P1-06 Security Events still the catalogue, not a duplicate history | **PASS** |

**These are observed production results.** Check 3 in particular is the one
that could not be inferred from anything automated: the first real row in the
log was put there by ordinary use, by the Product Owner, and read back on the
screen under their own name.

### 12.2 Carried forward — NOT closed by this acceptance

Accepting P1-08 closes P1-08. It closes nothing else.

| Carried item | State |
| --- | --- |
| **P1-02 provider-wide SSO Re-check** | **OPEN / CARRIED / UNVERIFIED.** A Phase 1 orchestration gate. P1-08 never owned it and did not touch it |
| **P1-07 live-verification items** | **CARRIED**, unchanged |
| **D-19 — sidebar shown to System Administrators only** | **CARRIED.** Auditor and Organisation Administrator route-level evidence permissions are implemented and tested; the sidebar limitation stands, and **no account was created** to make it observable |
| **P1-08's own §11 items** | **CARRIED** — fail-closed behaviour, the tamper warning, concurrency and a second privileged reader. Each would have required breaking production on purpose or manufacturing access that should not exist |

**P1-09 System Health and P1-10 Administration Home still follow.** P1-08 was
never the last Phase 1 unit.
