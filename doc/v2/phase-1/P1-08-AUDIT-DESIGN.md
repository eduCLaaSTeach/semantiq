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
| `role` \| `domain_id` \| `scope` \| `sensitivity` | as today | The remaining permitted context keys, as first-class columns |
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
                   outcome | reason | role | domain_id | scope | sensitivity )
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
| Already inside `DB::transaction` — every P1-01/03/04/05/07 write | The insert **joins that transaction**. A failed insert rolls the change back with it. **No ordering hazard exists**, and this is most of the surface |
| Not in a transaction | `record()` opens one for the insert alone |
| **Sign-in success** | **This is the one true hazard.** `CallbackController::issueSession()` today regenerates the session, writes the session keys, **then** records. Session state is not transactional, so a failed insert would leave somebody signed in and unevidenced. **The order is inverted:** persist `auth.login.succeeded` first, and only then regenerate and populate the session. If persistence fails, **no session is issued** and the person is sent to a refusal state |
| **Sign-in refusal** | The refusal **stands**. Failing closed can make a success fail; it must never turn a refusal into anything else. Persistence failure is `Log::critical` only |
| **Logout, session expiry** | Best effort, `Log::critical` on failure. Refusing to let somebody sign out because the evidence store is full is a worse outcome than the gap, and the session is already gone |

### 3.3 The failure itself

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

## 5. Actor resolution

| `actor_type` | When | Fields |
| --- | --- | --- |
| `person` | A signed-in SemantIQ user acted | `actor_user_id`. `actor_subject` only where the event already carries it |
| `external_subject` | A **refused** sign-in — there is no SemantIQ user, which is the point of refusing | `actor_subject`, `actor_tenant`, `actor_provider`. `actor_user_id` **NULL** |
| `system` | No human actor — scheduled health checks, engine failures | All actor identity fields NULL |

**`actor_user_id` is never populated for `external_subject`**, and no lookup is
attempted to "resolve" one. An unknown actor rendered as somebody's name is
evidence that lies, and a resolution that guesses is how it happens.

**The convention that is easiest to get backwards, stated once:** in
`SecurityEventLogger` context, `user_id` is the **subject** and `related_id` is
the **actor**. The mapper inverts nothing; test **A5** exists specifically
because an implementer reading `user_id` as "the actor" would produce a
plausible, completely wrong audit trail.

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
| **A5** | **Actor is the person who acted**, never the subject | Swap `user_id` and `related_id` in the mapper |
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

**Down.** Drop both tables, in that order. **Nothing else is touched**, so a
rollback cannot lose a business record — but it **does** discard the evidence
gathered since deployment, and the deployment note must say so rather than
presenting rollback as free.

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
