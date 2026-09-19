# P1-08 — Audit: DESIGN

**DESIGN ONLY.** No implementation, no deployment.

| | |
| --- | --- |
| Unit | **P1-08 — Audit** (delivery order 10). **P1-09 and P1-10 still follow** |
| PLAN | merge `c5580c697e0718793e786ce45b623c3d220b50d9` — D-95 – D-111 **ANSWERED** |
| Status | **AWAITING PRODUCT OWNER REVIEW** |

---

## 0. The four sentences this design serves

1. **One emit boundary.** `SecurityEventLogger::record()` persists. Nothing else
   writes audit evidence, and no call site changes.
2. **Fail closed.** If the evidence cannot be written, the audited thing does
   not silently succeed — **and the failure is never reported after an
   irreversible change has already committed.**
3. **One canonical category per occurrence**, derived, never listed twice.
4. **What a viewer may read is narrower than what is stored**, and a refusal
   leaks nothing.

---

## 1. Schema

**One migration. Two new tables. Nothing existing is altered.**

### 1.1 `audit_events`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint, auto | Insertion order. **Never** the ordering shown to a viewer — `occurred_at` is |
| `sequence` | bigint, **unique** | The chain position. Assigned under the chain lock (§2) |
| `occurred_at` | datetime(6) | **Server clock, at write.** Never client-supplied. Microsecond precision so two events in one request are orderable |
| `event` | varchar(64) | A `SecurityEventLogger` key. Validated against the declared set before insert |
| `category` | varchar(24) | **Derived** (§4), stored so a listing never recomputes 77 mappings per page |
| `actor_type` | varchar(16) | `person` \| `external_subject` \| `system` (D-103) |
| `actor_user_id` | bigint, null | Set **only** when `actor_type = person` |
| `actor_subject` | varchar(255), null | The directory subject for a refused sign-in. **Platform-sensitive** (§7) |
| `actor_tenant` | varchar(64), null | Same |
| `actor_provider` | varchar(32), null | Same |
| `organisation_id` | bigint, null | **NULL means platform-scoped** → System Administrator only (D-99) |
| `subject_user_id` | bigint, null | The person acted **upon**, where there is one |
| `target_type` | varchar(48), null | From `entity_type` |
| `target_id` | bigint, null | From `entity_id` |
| `outcome` | varchar(24), null | From `result` |
| `reason` | varchar(64), null | Fixed vocabulary only. **Never a message** |
| `role` \| `domain_id` \| `scope` \| `sensitivity` | as today | Permitted context keys, as first-class columns |
| `context_expires_at` | datetime, null | The permitted `expires_at` key — today only `bootstrap.grant.issued`, whose TTL is exactly the security fact worth keeping. **Corrected:** the first draft claimed every permitted key was persisted and then omitted this one |
| `previous_hash` | char(64) | The chain (§2) |
| `row_hash` | char(64) | The chain |

**No `ip_address`. No `user_agent`. No free-text column of any kind.** D-101 and
the `ALLOWED_KEYS` discipline: the leak is unrepresentable, not discouraged.

**Foreign keys are `RESTRICT`, and `actor_user_id` / `subject_user_id` are
deliberately NOT declared as foreign keys.** P1-03 permits a user purge; an
audit row must survive the purge of the person it names, and a foreign key would
either block the purge or take the evidence with it. The id is recorded as a
**historic reference**, and the screen renders "Account removed" where it no
longer resolves.

**Indexes:** `(organisation_id, category, occurred_at)`, `(occurred_at)`,
`(event, occurred_at)`, `(actor_user_id, occurred_at)`,
`(subject_user_id, occurred_at)`, `(outcome, occurred_at)`. Every one is
prefixed by the two things every query filters on first, so no listing scans.

### 1.2 `audit_chain_head`

**Exactly one row, id = 1.** `sequence`, `row_hash`, `started_at`,
`updated_at`.

`started_at` is the **evidence start instant** — written once by the migration,
never updated. It is what the screen shows (D-109), and it is a **stored fact**
rather than `MIN(occurred_at)`, which would silently move if the earliest row
were ever removed.

The migration also writes **genesis**: `sequence = 0`,
`row_hash = sha256('semantiq.audit.genesis|' || started_at)`, so the first real
row has a predecessor and a chain of length one is still verifiable.

---

## 2. Immutability, the hash chain, and concurrency

### 2.1 What is chained

```
row_hash = sha256( previous_hash | sequence | occurred_at(ISO-8601, µs) |
                   event | category | actor_type | actor_user_id |
                   actor_subject | actor_tenant | actor_provider |
                   organisation_id | subject_user_id | target_type | target_id |
                   outcome | reason | role | domain_id | scope | sensitivity |
                   context_expires_at )
```

Every persisted field, in a **fixed declared order**, with `\x1f` between fields
and a literal `\x00` for null — so `null` and the empty string cannot collide,
which is the classic way a chain is made forgeable without anyone noticing.

The field list lives in **one constant** and the hash is computed from it by
iteration, so a column added later without being added to the hash is a
**build failure**, not a silent hole.

### 2.2 Concurrency — one lock, taken last

A chain has a predecessor, so it has a serialisation point.

- **`audit_chain_head` row 1 is locked with `lockForUpdate()`** inside the
  caller's transaction; the new row is inserted; the head is updated.
- **It is always the LAST lock taken**, after any domain lock
  (`AdministratorSetGuard`'s set lock, `ReviewDecisionService`'s item lock, the
  entitlement lock). One deterministic global ordering, so audit can never be
  the second half of a lock cycle.
- Two concurrent audited writes therefore **serialise on one row**. That is a
  real throughput cost and it is accepted: this is administration traffic, and a
  chain that skips the lock is a chain that forks.

**RULED: accepted for Phase 1.** One global chain-head lock suits the current
administrative and security event volume, and the design is **not** to be
reworked pre-emptively for throughput. **Recorded as a future scaling
consideration:** if Audit later carries high-volume events, the chain is what to
revisit, not the lock.

**`LOCK IN SHARE MODE` is not used**, and `sequence` is not derived from
`MAX(sequence)+1`: both permit two writers to read the same predecessor.

### 2.3 Verification

`AuditChainVerifier::verify()` walks in `sequence` order and reports the **first
broken link**, distinguishing:

| Finding | Meaning |
| --- | --- |
| `row_hash` mismatch | A field was altered in place |
| `previous_hash` mismatch | A row was **removed**, or inserted out of band |
| Sequence gap | A row was deleted |

Surfaced as a banner on the Audit screen, not as a hidden log line.

### 2.4 What this does NOT claim

**Anyone with database or SSH access can delete rows, and nothing in the
application can prevent it.** Shared cPanel hosting gives us no control over
grants, and claiming a privilege we cannot verify would be exactly the unearned
assurance CLAUDE.md §6 forbids. What this design provides is **detection**, and
the screen says so in those words.

---

## 3. The write path and D-111 fail-closed

### 3.1 Where it happens

Inside `SecurityEventLogger::record()`, **synchronously**, in the caller's
transaction. No queued listener — there are no queue workers on this hosting,
and evidence that depends on a worker nobody runs is evidence that does not
exist.

`record()` keeps its existing guards **first**: unknown event → throw; forbidden
context key → throw. Neither is relaxed; a leak still cannot reach the store.

`Log::info()` is **retained** alongside the insert, for operators. It is not
evidence.

### 3.2 The ordering rule — the hard part

> **Persist the evidence inside the same transaction as the change it
> evidences, or before the change becomes irreversible. Never after.**

| Caller shape | Handling |
| --- | --- |
| A **successful** state change inside `DB::transaction` — every P1-01/03/04/05/07 write | The insert **joins that transaction**. A failed insert rolls the change back with it. **No ordering hazard exists**, and this is most of the surface |
| A **refusal** raised from inside a transaction that is about to roll back | **Written on a separate connection-level transaction that commits independently** — see §3.3 |
| Not in a transaction | `record()` opens one for the insert alone |
| **Sign-in success** | **This is the one true hazard.** `CallbackController::issueSession()` today regenerates the session, writes the session keys, **then** records. Session state is not transactional, so a failed insert would leave somebody signed in and unevidenced. **The order is inverted:** persist `auth.login.succeeded` first, and only then regenerate and populate the session. If persistence fails, **no session is issued** and the person is sent to a refusal state |
| **Sign-in refusal** | The refusal **stands**. Failing closed can make a success fail; it must never turn a refusal into anything else. Persistence failure is `Log::critical` only |
| **Logout, session expiry** | **RULED best effort.** `Log::critical` on failure, and that operational log is **not** audit evidence. Sign-out is never prevented and an expired session is never resurrected because durable storage is unavailable. Successful sign-in and state-changing security/admin operations **remain fail-closed** |

### 3.3 Refusals — evidence that must SURVIVE the rollback

A success and a refusal need **opposite** transaction behaviour, and conflating
them loses evidence.

- A **success** must be rolled back if it cannot be evidenced. That is D-111.
- A **refusal** is already not happening. Its evidence must **not** be written
  into the transaction that is about to roll back — `ReviewDecisionService`
  records `access.review.refused` and then throws, `UserDirectoryService`
  records `user.provision.refused` and then throws, `StepUpService` records
  `access.step_up.refused` and then throws. In every one of those the enclosing
  transaction unwinds, and an insert that joined it would **vanish with the
  refusal it was recording**. A refusal that leaves no trace is precisely the
  attempt somebody wanted hidden.

So the catalogue's declared **outcome class** decides the write mode:

| Outcome class | Write mode | On persistence failure |
| --- | --- | --- |
| **Success / state change** | Joins the caller's transaction | **The change rolls back.** D-111 |
| **Refusal / denial** | Written on a **separate transaction that commits immediately**, independent of the caller's | **The action stays refused**, and `Log::critical` records the operational gap |

**Four properties, asserted directly:**

1. A privileged refusal **remains refused**.
2. Refusal evidence that persisted **survives** the domain transaction's
   rollback.
3. If refusal-evidence persistence itself fails, the action is **still
   refused**, and `Log::critical` records the gap.
4. **No refusal can become a success.** The refusal path never returns a value
   and never swallows the throw; the audit write sits beside it and cannot
   alter it.

**D-111 is not weakened.** It governs whether a *success* may complete
unevidenced — the answer stays no. It never said a refusal should be erased for
lack of a place to put it.

### 3.4 The failure itself

A persistence failure raises `AuditViolation` (the same shape as every other
module's violation), so an administration screen renders a **sentence**, never
an exception. One sentence: *"This action was not completed, because it could
not be recorded. Nothing has been changed."*

`Log::critical` carries the diagnostic for operators. **It is explicitly not
accepted as audit evidence** (D-111), and nothing in the product reads it back.

---

## 4. Event → category derivation

`AuditCategory`: `UserAccess`, `AdminChanges`, `SecurityEvents`,
`ConfigurationChanges`.

**One canonical category per occurrence** (D-98). Derived from
`EventCatalogue`'s existing group for the event, through a single
group → category map — so an event added to the catalogue later is categorised
**by construction**.

| Category | Catalogue groups |
| --- | --- |
| **User Access** | Sign-in **successes** and sign-out; first-run setup; user and group lifecycle; role, entitlement, scope and ceiling changes; step-up requested/completed; review decisions |
| **Security Events** | Every sign-in **refusal**; step-up refused; review refused; access state unrecognised; engine failed; session expired |
| **Admin Changes** | Organisation structure changes; business domain changes |
| **Configuration Changes** | Sign-in configuration (identity health) |

Because refusals split from successes **by event key** rather than by group, the
map is keyed on the event where a group straddles two categories. A completeness
test asserts **every declared event maps to exactly one category**, so a new key
fails the build rather than landing in a default bucket.

---

## 5. Actor resolution — PER EVENT, never a global convention

### 5.1 The correction

The first draft of this design claimed `user_id` is **always** the subject and
`related_id` **always** the actor. **That is false, and the repository proves
it:**

| Event | `user_id` is | Actor comes from |
| --- | --- | --- |
| `auth.login.succeeded` | the person signing in | `user_id` |
| `user.provisioned`, `user.deactivated`, `user.purged` | the **administrator** | `user_id`; the affected person is `entity_id` |
| `organisation.created` | the **creator** | `user_id` |
| `group.member.added` | the **administrator** | `user_id`; the added person is `related_id` |
| `business_domain.owner.assigned` | the **administrator** | `user_id`; the owner is `related_id` |
| `access.role.assigned` | the **subject** | `related_id` |
| `access.review.item.revoked` | the **subject** | `related_id` |
| `auth.login.refused.unknown_identity` | **absent** | the directory `subject` |
| `access.engine.failed` | **absent** | nothing — `system` |

A single rule applied across those would misattribute roughly half the estate,
plausibly and invisibly. **There is no global convention and this design does
not invent one.**

### 5.2 Declared semantics, in the canonical catalogue

`EventCatalogue` — the **existing** list, not a second one — is extended so each
of the 77 keys declares four sources:

| Declaration | Values |
| --- | --- |
| **Actor source** | `UserId` \| `RelatedId` \| `ExternalSubject` \| `System` |
| **Subject source** | `UserId` \| `RelatedId` \| `EntityId` \| `ExternalSubject` \| `None` |
| **Target source** | `EntityTypeAndId` \| `None` |
| **Organisation source** | `Context` \| `None` (platform-scoped) |

`AuditSemantics` is a value object holding the four; `EventCatalogue::semanticsFor()`
returns it. **A key with no declared semantics fails the build** — the
completeness test asserts the declared set and the semantics map are the **same
set**, as an equality, so adding an event without deciding its audit meaning is
not possible.

Because the declarations live beside the labels the catalogue already holds,
there is no second event list to drift.

### 5.3 Actor type

| `actor_type` | When | Fields |
| --- | --- | --- |
| `person` | The declared actor source resolves to a SemantIQ user id | `actor_user_id` |
| `external_subject` | The declared source is `ExternalSubject` — a refused sign-in has no SemantIQ user, which is the point of refusing it | `actor_subject`, `actor_tenant`, `actor_provider`; `actor_user_id` **NULL** |
| `system` | The declared source is `System` — engine failures, unattended checks | All actor identity fields NULL |

**`actor_user_id` is never populated for `external_subject`**, and no lookup is
attempted to "resolve" one. **Never guess an actor.** An unknown actor rendered
as somebody's name is evidence that lies, and a lookup that guesses is how it
happens.

### 5.4 A5 — the test this correction exists for

**A5 asserts the recorded actor against a known truth in all five areas**, not
one:

| Area | Case |
| --- | --- |
| **Login** | `auth.login.succeeded` — actor is the person signing in |
| **User lifecycle** | `user.deactivated` — actor is the **administrator**, subject is the person deactivated |
| **Organisation administration** | `organisation.created` / `team.moved` — actor is the administrator |
| **Role and access administration** | `access.role.assigned` — actor is `related_id`, subject is `user_id` — **the inverse of user lifecycle** |
| **Access reviews** | `access.review.item.revoked` — actor is the reviewer, subject is the person reviewed |

A test covering only one area would pass under any single global convention,
which is exactly how the first draft's error survived review of the design.

---

## 6. Visibility projection

`AuditProjection` answers **two separate questions**, never one:

1. **Which rows may this viewer see?**
2. **Which fields of a visible row may they see?**

Conflating them is the P1-06 defect that showed an Auditor an empty screen.

### 6.1 Rows (D-99)

| Viewer | Rows |
| --- | --- |
| **System Administrator** | `organisation_id = <resolved>` **OR `organisation_id IS NULL`** |
| **Organisation Administrator** | `organisation_id = <own>` only |
| **Auditor** | `organisation_id = <own>` only, **read-only** |

**`NULL` never widens.** It is an explicit `OR organisation_id IS NULL` on the
System Administrator branch only — never an omitted `WHERE`, which is the same
shape that would quietly mean "every organisation".

### 6.2 Fields (D-100)

Platform-sensitive identity fields — `actor_subject`, `actor_tenant`,
`actor_provider` — are **System Administrator only**.

For anyone else they are **absent from the payload**, not null, using P1-06's
`WithheldRow` pattern: the row keeps the **same key set** so the payload shape
never varies with authority (a response whose field set changed with the
viewer's authority is itself the disclosure), and the value is the one constant
withheld sentence.

**No secret, token, code, nonce or grant exists to protect** — `ALLOWED_KEYS`
has always made them unrepresentable, and that guard is unchanged.

---

## 7. Routes, screens, filters

### 7.1 Routes

```
Route::middleware([RequireActionClass::class.':'.ActionClass::EvidenceRead->value,
                   RequireOrganisation::class])
    ->prefix('audit')->name('audit.')->group(function (): void {
        Route::get('/',            [AuditController::class, 'userAccess'])->name('user-access');
        Route::get('admin-changes',  [AuditController::class, 'adminChanges'])->name('admin-changes');
        Route::get('security-events',[AuditController::class, 'securityEvents'])->name('security-events');
        Route::get('configuration',  [AuditController::class, 'configuration'])->name('configuration');
    });
```

**Four GETs and nothing else.** No POST, PATCH, PUT or DELETE exists anywhere
under `/console/audit` — that is how D-97's "no application update or delete
path" is enforced, and `LifecycleCompletenessTest` asserts the route set as an
**equality**, so a fifth verb fails the build.

`EvidenceRead` admits System Administrator, Organisation Administrator and
Auditor to the **endpoint**; §6 decides the **rows**. The class is not the
projection and the projection is not the class.

### 7.2 Menu — D-19 is NOT changed

`ApprovedMenu` changes the `Audit` node from `locked` to
`leaf(..., 'audit.user-access', 'audit.view')`.

That node already sits inside **System Administration**, which D-19 shows to
System Administrators only. **No area, node or policy outside Audit is
touched**, and the System Administration navigation is **not widened**. Auditor
and Organisation Administrator route-level permissions are implemented and
tested; **reaching the screen through the sidebar remains carried**, exactly as
P1-07 carried the domain-owner case.

### 7.3 Screens

Feature → tab → content, the shared Pattern B strip, as every other feature.
Four tabs: **User Access · Admin Changes · Security Events · Configuration
Changes**.

**The evidence start date is on every tab**, above the list, in the viewer's
words:

> *Evidence begins 19 September 2026, 14:03. Activity before that time was not
> recorded and is not available here.*

Not a footnote and not a tooltip — D-109's whole purpose is that nobody mistakes
this for complete earlier history.

Each row reads as a **sentence**, not a key: *"Priya Nair removed the System
Administrator role from Alex Tan — 19 September 2026, 14:07."* The `event` key
never reaches the screen; `EventCatalogue` already holds the business label for
all 77.

**Empty, refusal and chain-broken states are explicit**, and the chain-broken
banner is the one that must not be quiet.

### 7.4 Filters and pagination (D-102)

Server-side only: **category** (implied by tab), **event**, **actor**,
**subject**, **outcome**, **date range**. Pagination in P1-07's existing shape
(`currentPage` / `lastPage` / `total`).

**No free-text search**, because there is no free text to search — and adding a
searchable note field is precisely the leak channel P1-06 refused.

---

## 8. P1-06 and P1-07 boundaries

| | |
| --- | --- |
| **P1-06** (D-106) | Keeps the **catalogue** and the redaction contract. Its `LIMITATION` panel is **rewritten, not removed**: it stops saying "there is no history" and starts pointing at Audit. **`SecurityEventsController` still reads no file and no audit table** — `N-SS28` stands, and Audit does not re-render the catalogue |
| **P1-07** (D-107) | Audit consumes P1-07's **emitted events**. It does **not** read `access_review_cycles` or `access_review_items`. A second projection of a review decision would be a second interpretation of it |

---

## 9. Negative and security test matrix

| # | Case | Mutation that must kill it |
| --- | --- | --- |
| **A1** | Successful sign-in is evidenced, right actor, server timestamp | Drop the persist from `issueSession` |
| **A2** | Each of the four sign-in refusals is evidenced with **no fabricated actor** | Resolve `actor_user_id` by subject lookup |
| **A3** | Role assigned / revoked / entitlement granted evidenced with actor, target, outcome | Drop the target columns |
| **A4** | A denied **privileged** action is evidenced; an **ordinary business denial is NOT** (D-71) | Log every denial — volume buries what matters |
| **A5** | **Actor is the person who acted**, in **all five** areas — login, user lifecycle, organisation administration, role/access administration, access reviews (§5.4) | Apply ANY single global convention. User lifecycle and role administration are inverses, so no one rule satisfies both |
| **A6** | Restricted fields **absent from the rendered payload** for OrgAdmin and Auditor | Return them as `null` instead of absent — *this is the case most likely to pass vacuously, and it is asserted on the payload, never on the session* |
| **A7** | No application path updates or deletes a row | Add a `DELETE` route |
| **A8** | An altered row and a **deleted** row both break the chain and are **reported** | Verify only `row_hash` — a deletion then passes |
| **A9** | Category derived; a new catalogue event is categorised without a second edit | Add a `default =>` bucket |
| **A10** | Unauthorised refusal **byte-identical** for existing and non-existent records | Return 404 for one and 302 for the other |
| **A11** | **Fail closed on sign-in**: persistence fails → **no session is issued** | Persist after `issueSession()`, as today |
| **A12** | **A refusal stays refused** when its persistence fails | Let the failure change the outcome |
| **A13** | A failed persistence inside a business transaction **rolls the change back** | Catch and continue |
| **A14** | `organisation_id IS NULL` rows are **System Administrator only** | Drop the `IS NULL` branch, or omit the `WHERE` |
| **A15** | A forbidden context key still **throws** and reaches neither log nor table | Relax the guard |
| **A16** | **No `ip_address` or `user_agent` column exists** (D-101) | Add one |
| **A19** | **Refusal evidence survives the domain rollback** — the refused review, the refused provisioning and the refused step-up each leave a row after their transaction unwinds | Let the refusal insert join the caller's transaction |
| **A20** | A refusal whose **own** persistence fails is **still refused** | Let the failure propagate as a success |
| **A21** | Every declared event has **declared audit semantics**; the two sets are equal | Add an event with no semantics, or a `default =>` fallback |
| **A22** | `context_expires_at` is persisted and **hashed** — `bootstrap.grant.issued` | Omit it from the hash field list |
| **A17** | Concurrent audited writes produce an **unbroken chain** — MySQL only, single-threaded SQLite cannot observe it | Drop `lockForUpdate()` |
| **A18** | The evidence start date is **rendered on every tab** | Remove it from one |

**Every one is broken deliberately and observed to fail.** A6, A10 and A17 are
flagged as the most likely to pass for the wrong reason: A6 because "not
rendered" and "not present" look alike from the session; A10 because any refusal
satisfies a weak assertion; A17 because it **cannot fail on SQLite at all** and
must run against MySQL in CI, exactly as P1-07's M-R12 recorded.

---

## 10. Migration and rollback

**Up.** Create `audit_events` and `audit_chain_head`; insert the single head row
with `started_at = now()` and the genesis hash. Nothing else is created, altered
or backfilled (D-109).

**Down.** Drops both tables, in that order. **Nothing else is touched**, so a
rollback cannot lose a business record — but it **does** destroy every audit
row gathered since deployment.

**PRODUCTION SAFEGUARD (ruled).** `down()` exists for CI and development
migration testing. Once production holds audit evidence:

| Rule | |
| --- | --- |
| **Deployment must never automatically drop the Audit tables** | The deploy workflow runs `migrate --force` and never `migrate:rollback`; this is asserted, not assumed |
| A production rollback that would destroy audit evidence | Requires **explicit operator approval** and a **database-level backup or snapshot taken first** |
| That backup | Is an **operational safeguard**, not the product export feature D-110 prohibits. It is taken by an operator with database access, is never reachable from the application, and no screen offers it |

Recorded prominently in the deployment and rollback documentation, not only
here.

**Proven on MySQL 8.4 in CI**, migrate → rollback → migrate, and the new
concurrency case runs in the existing MySQL suite step.

---

## 11. Product Owner test script — outline

Written in full at VERIFY. It will carry all twelve required elements, and these
are already known:

- **⚠️ Permanence.** Audit rows are **permanent and cannot be deleted**, by
  design and by ruling (D-105). Anything the Product Owner does while testing —
  including a refused action — is recorded **forever**. This is stated before
  they type anything.
- **No test data is created.** The evidence needed is produced by ordinary use.
- **NOT OBSERVABLE WITH REAL PRODUCTION DATA**, carried, not inferred:
  - **Auditor and Organisation Administrator field withholding** — needs a real
    second privileged person. **None will be created.** Automated evidence: A6,
    A14.
  - **Concurrent writes and chain integrity under load** — not performable by
    one person in a browser. Automated evidence: A17, on MySQL.
  - **Fail-closed behaviour** — needs an induced storage failure in production.
    **It will not be induced.** Automated evidence: A11, A12, A13.
  - **Everything carried from P1-07**, unchanged.

---

## 12. Delivery order for EXECUTE

1. Schema, genesis row, models.
2. Hash chain + verifier, with the MySQL concurrency case **first** — the guard
   that cannot be observed locally is the one to write before the code.
3. Persistence behind `SecurityEventLogger`, joining the caller's transaction.
4. The sign-in ordering inversion and the fail-closed semantics (D-111).
5. Category derivation + completeness test.
6. Projection: rows, then fields.
7. Routes, controller, four screens, filters, evidence start date.
8. P1-06 limitation panel rewrite.
9. Menu node `locked` → `leaf`.
10. Mutations, MySQL, browser verification, handover documents.

---

## 13. What would make this design wrong

- **If fail-closed is expected to apply to sign-out or session expiry.** It does
  not (§3.2), and the reason is stated rather than assumed.
- **If the chain is expected to prevent deletion.** It detects it (§2.4).
- **If the serialisation cost is unacceptable.** One global lock per audited
  write is a real cost. It is the price of a chain that cannot fork, and it is
  named here rather than discovered in production.

---

## 14. Status

**DESIGN ONLY — AWAITING PRODUCT OWNER REVIEW.**
No implementation. No schema created. No deployment.
**P1-02 remains OPEN / CARRIED / UNVERIFIED. P1-07's carried items remain
carried. D-19 is unchanged. P1-09 and P1-10 are not started.**

### 14.1 Product Owner rulings carried into this design

| Item | Ruling |
| --- | --- |
| Chain-head serialisation cost | **Accepted for Phase 1.** Recorded as a future scaling consideration; no pre-emptive redesign |
| Sign-out and session expiry | **Best effort.** Never prevented, never resurrected, `Log::critical` only, and that log is not evidence |
| Rollback | **Approved with a production safeguard** — §10. Deployment never drops the Audit tables; a production rollback needs operator approval and a database backup first, which is an operational safeguard and not the D-110 export |
