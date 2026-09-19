# P1-07 — Access Reviews: DESIGN

| | |
| --- | --- |
| Unit | **P1-07 — Access Reviews** |
| PLAN | **PRODUCT OWNER APPROVED 19 September 2026**, D-84 – D-94 answered |
| Baseline | `main` at the PLAN merge; live application `f282571f9dbb24cb1d49c8d8249d350c18b3763f` |
| Status | **DESIGN — PRODUCT OWNER APPROVED, 19 September 2026.** B-1 resolved as **B-1a**. EXECUTE authorised on merge |

**This document says what engineering builds.** It does not restate the PLAN;
where the PLAN already settled something it is cited (`PLAN §5x`) rather than
repeated. It assumes D-84 – D-94 as approved.

> **B-1 (§3.4) is RESOLVED as B-1a**, Product Owner, 19 September 2026.
> Everything below is approved specification.

---

## 1. What is built

Three read-and-decide screens under one unlocked menu node, **two** new tables, one
authority algorithm, one generation service, one decision service, six event
keys, and **one bounded corrective prerequisite in P1-05** (§12).

**P1-05 is consumed, not modified** (except §12's bounded correction). The
engine, catalogue, role model, access schema and revocation services are
untouched.

---

## 2. Data model

### 2.1 Two tables

**`access_review_cycles`**

| Column | Type | Meaning |
| --- | --- | --- |
| `id` | PK | |
| `organisation_id` | FK, nullable | Matches `role_assignments`' nullability |
| `started_at` | datetime | When the Product Owner's administrator started it |
| `due_at` | datetime | Chosen at start (D-89). **UTC instant** |
| `started_by_user_id` | FK users | Who decided to run it |
| `timestamps` | | |

**There is deliberately no `closed_at`.** A cycle is open exactly while it has a
`pending` item. Storing it would create a second source of truth that can drift
from the items — and, with no scheduler (PLAN §2.9), nothing would reliably
maintain it.

**`access_review_items`**

| Column | Type | Meaning |
| --- | --- | --- |
| `id` | PK | |
| `access_review_cycle_id` | FK cycles | |
| `kind` | string(16) | `privileged` \| `domain` |
| `subject_user_id` | FK users | Whose access is reviewed |
| `role_assignment_id` | FK, **nullable** | Set when `kind=privileged` — **the reviewed object** (D-84) |
| `domain_entitlement_id` | FK, **nullable** | Set when `kind=domain` — **the reviewed object** (D-84) |
| `business_domain_id` | FK, nullable | Domain items. Denormalised for authority and filtering |
| `role_code` | string(40) | Snapshot |
| `state` | string(16) | `pending` \| `retained` \| `revoked` \| `superseded` |
| `due_at` | datetime | **Copied from the cycle**, so an item is self-contained for overdue |
| `composition` | json, nullable | What the reviewer was shown (§2.3) |
| `composition_fingerprint` | string(64) | Hash of `composition` — the strict re-read comparison (D-93) |
| `decided_at` | datetime, nullable | |
| `decided_by_user_id` | FK, nullable | |
| `decision_basis` | string(32), nullable | `system_administrator` \| `organisation_administrator` \| `domain_owner` \| `self` |
| `self_review` | boolean | Denormalised from `decision_basis` for indexed filtering and evidence |
| `superseded_reason` | string(40), nullable | `object_ended` \| `composition_changed` \| `subject_role_ended` |
| `timestamps` | | |

**Exactly one of `role_assignment_id` / `domain_entitlement_id` is set.** Enforced
by a model-level invariant and by `AccessReviewItemShapeTest`; MySQL 8.4 has no
partial index that could express it, and a CHECK constraint would not survive the
project's migration conventions cleanly.

### 2.2 Why there is no separate `decisions` table

The PLAN listed decision as a concept (§5O). It is **1:1 with a terminal item and
never edited** — terminal states are terminal, there is no reopen, and a later
review is a new item in a new cycle. A separate table would add a join to every
read for a row that can never be more than one, and would introduce the
possibility of an item with two decisions, which the state machine forbids.

**The decision therefore lives on the item.** Its fields are write-once: the
service refuses to write them when `state <> 'pending'`, and
`ReviewDecisionImmutabilityTest` (N-R25) proves it.

### 2.3 The composition snapshot

For `kind=domain`, at generation:

```json
{
  "role_code": "manager",
  "business_domain_id": 4,
  "scopes": [ {"id": 31, "type": "team", "target_id": 7},
              {"id": 33, "type": "business_unit", "target_id": 2} ],
  "ceiling": {"id": 88, "sensitivity": "confidential"}
}
```

Scopes are **sorted by id** before hashing, so the fingerprint is stable and two
equal compositions cannot hash differently — the same discipline P1-06 used when
it asserted byte-identical payloads.

For `kind=privileged` the composition is `{"role_code": "..."}`. Platform
privilege has no composition beneath it (`RoleCatalogue.php:32-35`), so the
fingerprint changes only if the role does, which cannot happen within one
assignment.

`composition_fingerprint = sha256(canonical_json(composition))`.

---

## 3. Reviewer authority

One algorithm, used in three places: to filter lists, to authorise a single item,
and to decide whether self-review is permitted. **It is never stored** (PLAN
§5O) — stored authority goes stale, which is the drift this unit exists to catch.

### 3.1 Privileged items — derived, not parallel

```
canReviewPrivileged(actor, item):
    if not actor.isActive():            return false
    for role in currentRoleCodesOf(actor):
        if item.role_code ∈ RoleCatalogue::grantableBy(role):
            return true
    return false
```

**`grantableBy()` is the single source.** Because an Organisation Administrator's
list excludes `system_administrator` (`RoleCatalogue.php:117-130`), N-R3 holds
**by derivation** — there is no second list that could agree today and drift
tomorrow. `grantableBy(Auditor)` returns `[]`, so Auditor decides nothing.

### 3.2 Domain items

```
canReviewDomain(actor, item):
    if not actor.isActive():                                  return false
    if actor ∈ currentOwnersOf(item.business_domain_id):       return true   // domain_owner
    if actor holds SystemAdministrator:                        return true   // see B-1
    return false
```

`currentOwnersOf()` reads `business_domain_owners` where `ended_at IS NULL`.
**P1-07 never writes that table, and nothing here makes the engine read it** —
N-R22.

### 3.3 Self-review (D-88)

```
mayDecide(actor, item):
    if actor.id != item.subject_user_id:  return canReview(actor, item)
    if not canReview(actor, item):        return false
    return otherEligibleReviewers(item) is EMPTY
```

`otherEligibleReviewers(item)` = active users, excluding the subject, for whom
`canReview` is true. Computed from current assignments and current ownership at
**decision time**, never cached.

When it is empty and the actor decides their own item: `decision_basis = 'self'`,
`self_review = true`, step-up required (§6), and a **distinguishable** event
(§8). The screen labels it.

### 3.4 B-1 — **RESOLVED as B-1a**, 19 September 2026

Two accepted rules put D-87's original *"System Administrator fallback only where
no owner exists"* out of reach, and neither can be changed inside P1-07:

1. **No business role passes any route gate.** `RoleCatalogue::MATRIX` gives
   Executive, Domain Owner, Manager and Business User **only** `BusinessData`,
   and every console route uses `RequireActionClass` with an administration
   class. A domain owner is typically a business user.
2. **D-19 hides the sidebar** from everybody but System Administrators.

Implementing the fallback strictly would have left every item in an *owned*
domain permanently undecidable — the stuck-item failure D-88 was approved to
avoid.

#### The approved rule (B-1a — this amends D-87)

| | |
| --- | --- |
| **System Administrator** | **Eligible to review every Domain Review item** — not only where no owner exists |
| **Current domain owner** | Remains the **preferred and accountable** reviewer, shown as such on the row |
| **`decision_basis`** | `domain_owner` when an owner legitimately performs the review; `system_administrator` otherwise |
| **Domain ownership** | Still grants **zero** business-data access — N-R22 |
| **D-19 and the action-class catalogue** | **Unchanged by this unit.** No new action class, no navigation redesign |

§3.2 implements exactly this. No item is ever undecidable, and owner
accountability is preserved and recorded rather than assumed.

**Domain-owner live review remains a carried P1-07 verification item** until a
legitimate owner can actually reach the review surface (§13).

## 4. Population generation

One service, one transaction, idempotent.

### 4.1 Privileged population (D-85)

```sql
current role_assignments (ended_at IS NULL)
  WHERE role_code IN  ⋃ RoleCatalogue::rolesPermitting(c)
                        for c in RoleCatalogue::administrationClasses()
  AND   subject users.status = 'active'
```

Both helpers already exist (`RoleCatalogue::rolesPermitting()`,
`::administrationClasses()`); the union is the only new line.
**Derived from the catalogue, never a literal list of three role codes.** A role
added later with an administration class is included by construction —
`PrivilegedPopulationIsDerivedTest` (N-R26) breaks if it is hard-coded.

### 4.2 Domain population (D-86)

```sql
current domain_entitlements (ended_at IS NULL)
  WHERE  current ceiling sensitivity IN ('confidential','restricted')
     OR  EXISTS current scope WHERE scope_type IN ('domain','organisation')
```

`('domain','organisation')` is **derived from `ScopeType::coversWholeDomain()`**
(`ScopeType.php:67-70`), not written out — the same reason as §4.1.

Inactive subjects are excluded from generation (their access is already denied,
PLAN §2.4); an item whose subject *becomes* inactive later stays open and is
marked (§5).

### 4.3 Idempotency (D-89, N-R21)

Two unique indexes make re-running generation harmless:

```
unique (access_review_cycle_id, role_assignment_id)
unique (access_review_cycle_id, domain_entitlement_id)
```

MySQL permits many NULLs in a unique index, which is exactly the required
behaviour: privileged rows have a NULL entitlement and do not collide.

Generation uses `insertOrIgnore`. In addition, **an object with a `pending` item
in any cycle is skipped**, so starting a second cycle cannot produce two open
questions about one grant.

---

## 5. Superseded — the exact rule (D-93)

Evaluated in **two** places, with identical logic in one method:

- **at render**, so a reviewer is not shown a decidable action for something that
  has moved;
- **at decision, inside the transaction, after locking** — this is the
  authoritative one.

```
supersedeReason(item) -> ?reason
    if item.kind == privileged:
        a = RoleAssignment::find(item.role_assignment_id)
        if a is null or a.ended_at is not null:   return 'object_ended'
        return null
    e = DomainEntitlement::find(item.domain_entitlement_id)
    if e is null or e.ended_at is not null:       return 'object_ended'
    if e.assignment.ended_at is not null:         return 'subject_role_ended'
    if fingerprintOf(liveComposition(e)) != item.composition_fingerprint:
                                                  return 'composition_changed'
    return null
```

When it returns non-null the item becomes `superseded` and **no write reaches the
access model** — N-R7.

### 5.1 What is *not* superseding

| Situation | Behaviour |
| --- | --- |
| Subject became inactive | Item stays `pending`, **marked**. Engine already denies. Retain means "this grant should survive reactivation" |
| Subject reactivated | Nothing special |
| Domain disabled / re-enabled | Item stays `pending`, **marked**. Access is fail-closed regardless |
| Subject moved team or business unit | Scope rows unchanged, so the fingerprint is unchanged. The *effect* may differ; the composition is re-read at render so the reviewer sees current wording |

**Retain performs no write to the access model at all** (D-91, PLAN §5I), so a
review structurally cannot revive stale access. That is stronger than any filter,
and N-R4 asserts it as byte-identical access rows before and after.

---

## 6. Decision flow, and step-up (D-92)

### 6.1 One transaction

```
DB::transaction:
  1  item = AccessReviewItem::lockForUpdate()->find(id)
  2  if item.state != 'pending'            -> refuse "already decided"   (N-R12, N-R4-after-revoke)
  3  if not mayDecide(actor, item)         -> refuse, generic            (N-R11, N-R14)
  4  lock the reviewed object row(s) FOR UPDATE   (same rows P1-05 locks)
  5  r = supersedeReason(item)
     if r: item.state='superseded'; reason=r; event; COMMIT; return      (no access write)
  6  if decision == 'retain':
        item.state='retained'                     -- NO access-model write
     else:
        privileged -> RoleAssignmentService::revoke(assignment, actor)
        domain     -> EntitlementService::revoke(entitlement, actor)
        item.state='revoked'
  7  item.decided_at/by/basis/self_review
  8  emit exactly one event
```

Step 2 before step 3 is deliberate: a decided item is refused identically
regardless of authority, so the refusal is not an oracle for who may review what.

**All P1-05 refusals pass through unchanged** — the administrator floor
(`AdministratorSetGuard`), entitlement currency, and `grantableBy()`. P1-07 adds
no exception. N-R18.

### 6.2 Step-up enforcement — the bypass this unit must not become

**Revoking a System Administrator already requires step-up in P1-05.** A review
controller that called `RoleAssignmentService::revoke()` without it would be a
step-up bypass for the most privileged action in the product.

The requirement is therefore a **property of the decision, computed from the
item**, not a flag a screen sets:

```
stepUpActionFor(item, decision) -> ?StepUpAction
    if decision is self-review                        -> SelfReview
    if decision == 'retain'                           -> null            // D-92
    if item.kind == privileged:
        role == system_administrator                  -> RevokeSystemAdministrator
        role == organisation_administrator            -> RevokeOrganisationAdministrator   // §12
        else                                          -> null
    if item.kind == domain and live ceiling == restricted
                                                      -> RevokeRestrictedEntitlement
    return null
```

`AccessReviewDecisionController` calls this and, when non-null, routes through the
existing `StepUpService` exactly as the Roles & Access controller does — same
`begin` / `resolve` / `verifyFreshness` / `consumeAndPerform` sequence, same
5-minute lifetime, same freshness tolerance.

**N-R19 is written first and asserts the enforcement, not the wiring**: it calls
the decision endpoint for a System Administrator revoke *without* a resolved
step-up and asserts the assignment is still current. The mutation — remove the
`stepUpActionFor` call — must make it fail.

**Two new `StepUpAction` cases are required**: `SelfReview` and
`RevokeRestrictedEntitlement`. `RevokeOrganisationAdministrator` is §12's
corrective prerequisite. `RevokeSystemAdministrator` already exists.

**No weaker confirmation is substituted, and no Microsoft MFA is claimed.**
SemantIQ requires a fresh sign-in; whether Entra asks for a second factor is the
customer's policy. B-9b stays `unverified`.

---

## 7. Concurrency

| Case | Mechanism |
| --- | --- |
| Two reviewers, near-simultaneous | `lockForUpdate` on the item; the loser sees "already decided". **One decision, one event** — N-R12 |
| Access changed between open and submit | §5, inside the lock — `superseded`, no write |
| Duplicate revoke (double submit, retry) | Step 2 refuses. **No second `ended_at` write, no second event** |
| Retain after revoke | Step 2 refuses; terminal is terminal |
| Authority removed while page open | Step 3 re-authorises — N-R11 |
| Generation run twice | §4.3 |

**Tested on MySQL 8.4, not only SQLite**, because `lockForUpdate` semantics
differ — the P1-05 precedent.

---

## 8. Routes, action classes and events

### 8.1 Routes

```
Route::middleware(RequireActionClass::class.':'.ActionClass::EvidenceRead->value)
    ->prefix('access-reviews')->name('access-reviews.')->group(...)
        GET  /                 → privileged      (AccessReviewsController@privileged)
        GET  /domains          → domains         (…@domains)
        GET  /overdue          → overdue         (…@overdue)

Route::middleware(RequireActionClass::class.':'.ActionClass::AccessAdmin->value)
    ->prefix('access-reviews')->name('access-reviews.')->group(...)
        POST /cycles           → start a cycle   (System Administrator only, checked in the controller)
        POST /items/{item}/decide → decide       (per-item authority re-checked, §6.1 step 3)
```

- **Reads at `EvidenceRead`** — System Administrator, Organisation Administrator
  and Auditor. Auditor sees; `grantableBy(Auditor) = []` means Auditor decides
  nothing, so read-only falls out of the algorithm rather than being a special
  case.
- **Decisions at `AccessAdmin`**, plus the per-item check. Neither is sufficient
  alone: the class admits you to the endpoint, the algorithm decides the row.
- `RequireOrganisation` **is** included (unlike P1-06 — reviews presuppose an
  organisation).
- **No dynamic segment collides**: `{item}` is numeric and sits under `items/`.
- The route list drives B-1: no business role passes `EvidenceRead`.

### 8.2 Menu

`ApprovedMenu.php:129` — `locked(...)` becomes
`leaf($area, 'Access Reviews', 'i-clipboard-list', 'access-reviews.privileged', 'access-reviews.view')`.
**One line. No navigation redesign.** D-19 is unchanged and not fixed here.

### 8.3 Events (D-94)

Six new constants; **`ALLOWED_KEYS` is not extended.**

| Key | Context used (all already permitted) |
| --- | --- |
| `access.review.cycle.started` | `entity_id`, `related_id`, `result` |
| `access.review.item.retained` | `user_id`, `related_id`, `entity_id`, `role`, `domain_id`, `result` |
| `access.review.item.revoked` | as above, plus `sensitivity` |
| `access.review.item.superseded` | `user_id`, `entity_id`, `reason`, `result` |
| `access.review.item.self_reviewed` | `user_id`, `related_id`, `entity_id`, `role`, `result` |
| `access.review.refused` | `user_id`, `related_id`, `reason`, `result` |

`user_id` is the **subject**, `related_id` the **actor** — the established P1-05
convention (`EntitlementService` uses exactly that).

**No free text anywhere.** `reason` carries a fixed vocabulary
(`already_decided`, `not_permitted`, `object_ended`, `composition_changed`,
`subject_role_ended`), never a message — N-R17.

Self-review emits `self_reviewed` **instead of** `retained`/`revoked`, with
`result` carrying the decision. That is what makes it distinguishable — N-R24.

**These keys will appear on the accepted P1-06 Security Events screen.** Its
count is derived, so nothing breaks; the screen gains rows, as recorded in
PLAN §5N.

---

## 9. Screens

Shared **Pattern B** strip (`.org-tabs`), three tabs, unchanged from the
corrected shared component. **P1-07 introduces no strip of its own**, so the
G2 mobile correction and `TabStripFitsANarrowScreenTest` apply as-is.

| | Privileged Reviews | Domain Reviews | Overdue Reviews |
| --- | --- | --- | --- |
| Rows | Privileged items the actor may see | Domain items the actor may see | **Projection** over both, `pending` and past due |
| Per row | Person, role, basis, due, overdue marker, state | + domain, scope composition **in words**, ceiling, preferred reviewer | + which tab it belongs to |
| Filters | State, role, overdue, person | + domain, sensitivity | State-free (always pending) |
| Actions | Retain / Revoke | Retain / Revoke | Links through; no separate action |
| Empty | *"No privileged access is awaiting your review."* | *"No sensitive domain access is awaiting your review."* | *"Nothing is overdue."* |
| Refusal | Identical for not-permitted and not-found — N-R2 | same | same |

- **Overdue is a projection, not an entity** — no second state to drift.
- **Counts on tabs are the actor's own**, never deployment totals — N-R23.
- **Pagination on all three.** Nothing loads an organisation into a browser.
- Scope composition is rendered with **`GrantPathReference::narrate()`**, reusing
  P1-05's wording rather than inventing a second description.
- **No business record appears anywhere** — N-R15.
- Self-reviewed rows are visibly labelled.
- Marked-but-decidable rows (inactive subject, disabled domain) carry a plain
  sentence explaining that access is already denied.

Responsive: 390px and desktop, both themes, browser Back between tabs — the
G2/G2a standard.

---

## 10. Migration design

One migration, additive only.

- Creates `access_review_cycles`, `access_review_items`.
- **No column is added to, or altered on, any P1-05 table.** No data migration.
- Indexes: `(state, due_at)` for overdue; `(access_review_cycle_id, state)`;
  `(subject_user_id)`; `(business_domain_id)`; the two unique indexes of §4.3.
- Foreign keys to `users`, `role_assignments`, `domain_entitlements`,
  `business_domains`, `organisations` — **`RESTRICT`, never `CASCADE`**: a review
  record must survive the access it reviewed (N-R16). Since P1-05 never deletes,
  RESTRICT can never fire in practice; it is there so a future delete cannot
  silently take the evidence with it.
- **Rollback drops only these two tables.** No grant can be lost.
- `migrate` → `rollback` → `migrate` is run on **MySQL 8.4** in CI, and the D-49
  rollback step counts CI depends on are unaffected because this is a new file.

---

## 11. Negative-test traceability

Every PLAN case maps to a named test and a mutation. **Order of construction:
N-R19 first.**

| Case | Test | Mutation |
| --- | --- | --- |
| N-R1 | `ReviewVisibilityTest::only_items_within_authority` | Drop the authority filter |
| N-R2 | `ReviewRefusalTest::forbidden_and_missing_are_identical` | 404 vs 403 |
| N-R3 | `ReviewerAuthorityTest::org_admin_cannot_review_system_administrator` | Replace derivation with a literal list |
| N-R4 | `RetainWritesNothingTest` | Make retain touch `updated_at` |
| N-R5 | `RevokeChangesEffectiveAccessTest` (through `AccessEngine`) | Cache the decision |
| N-R6 | `IndependentPathsTest` | Revoke by user+domain instead of entitlement id |
| N-R7 | `SupersededCannotResurrectTest` | Let retain write the values back |
| N-R8 | `InactiveSubjectStaysDeniedTest` | Remove the engine's inactive gate |
| N-R9 | `DisabledDomainStaysDeniedTest` | Skip the enabled filter |
| N-R10 | `UnknownItemStateFailsClosedTest` | Add a permissive `default` |
| N-R11 | `AuthorityRevalidatedOnSubmitTest` | Authorise on render only |
| N-R12 | `ConcurrentDecisionTest` (**MySQL**) | Drop `lockForUpdate` |
| N-R13 | `OverdueIsDeterministicTest` | Compare local dates |
| N-R14 | `RefusalLeaksNothingTest` | Include the subject's name |
| N-R15 | `NoBusinessDataOnReviewScreensTest` | Add `BusinessData` to the query |
| N-R16 | `ReviewRecordSurvivesRevocationTest` | `CASCADE` the FK |
| N-R17 | `NoFreeTextInEventsTest` | Add a `comment` key |
| N-R18 | `LastAdministratorRefusedFromReviewTest` | Bypass `AdministratorSetGuard` |
| **N-R19** | **`ReviewRevokeRequiresStepUpTest`** | **Remove the `stepUpActionFor` call** |
| N-R20 | `NewAssignmentPeriodDoesNotInheritReviewTest` | Key the item on user+role_code |
| N-R21 | `GenerationIsIdempotentTest` | Remove the already-open check |
| N-R22 | `OwnershipGrantsNoAccessTest` | Let the engine read `business_domain_owners` |
| N-R23 | `TabCountsAreTheActorsOwnTest` | Count without the authority filter |
| N-R24 | `SelfReviewIsDistinguishableTest` | Emit the ordinary event |
| **N-R25** *(new)* | `ReviewDecisionImmutabilityTest` | Allow a decided item to be re-decided |
| **N-R26** *(new)* | `PrivilegedPopulationIsDerivedTest` | Hard-code the three role codes |
| **N-R27** *(new)* | `DomainPopulationIsDerivedTest` | Hard-code `('domain','organisation')` |

Architecture guards: **exactly one authority algorithm**, **exactly one
supersede rule**, and **no access-ending SQL outside P1-05's services** — the
last is the P1-06 precedent that caught a second precedence implementation.

---

## 12. Bounded corrective prerequisite in P1-05

**Ruled by the Product Owner (PLAN §9): a real P1-05 implementation
inconsistency, not a policy choice.**

`RoleCatalogue::requiringStepUp()` returns System Administrator **and**
Organisation Administrator. `StepUpAction` has `grant_organisation_administrator`
but **no `revoke_organisation_administrator`**. P1-07 cannot enforce D-92 without
it.

### Exactly what changes

| Change | Detail |
| --- | --- |
| Add | `StepUpAction::RevokeOrganisationAdministrator = 'revoke_organisation_administrator'` plus its `description()` arm |
| Enforce | The **P1-05 Roles & Access revoke controller** requires step-up when the revoked role is Organisation Administrator — closing the same gap where it originated |
| Enforce | P1-07's decision controller uses it via §6.2 |

### Exactly what does not change

**No redesign of P1-05.** No change to `RoleCatalogue`, `RoleAssignmentService`,
`EntitlementService`, `AccessEngine`, the access schema, the role model or any
accepted decision. `requiringStepUp()` already says this; the code is being
brought up to it.

### Tests

| Test | Mutation |
| --- | --- |
| `StepUpActionCoversRequiringStepUpTest` — **every role in `requiringStepUp()` has both a grant and a revoke action** | Remove the new case. **This guard would have caught the original gap** |
| `RevokeOrganisationAdministratorRequiresStepUpTest` — from **Roles & Access** | Remove the controller gate |
| N-R19 — the same, from **Access Reviews** | Remove `stepUpActionFor` |

The first is the valuable one: it is derived from the catalogue, so it fails for
any future role added to `requiringStepUp()` without both actions.

### Honesty

This is a **defect in an accepted unit**, surfaced by P1-07 and corrected with
P1-07's approval. It is called out in the PR rather than folded silently into the
diff, and the P1-05 verification record will be annotated at EXECUTE.

---

## 13. Product Owner test script — shape

Full script written at EXECUTE; its shape is fixed now.

**Testable on production:** sidebar discoverability; three tabs; starting a
cycle and seeing the generated population; only permitted reviews visible;
**retain, then confirming in Roles & Access that nothing changed**; overdue via a
short due date; a refusal by direct URL; **390px mobile**, light and dark;
browser Back; no business data; no developer terminology.

**Needs fixtures — NOT CURRENTLY OBSERVABLE WITH REAL PRODUCTION DATA**
(production: 1 active System Administrator, 0 entitlements, 0 scopes, 0
ceilings): revoke changing effective access; two independent paths; reviewer
separation of duties; self-review as the exception path; last-administrator
refusal; concurrency; **domain-owner review** (B-1).

**No production privilege, entitlement or administrator will be created to make
any of these observable.**

### Carried gates this unit will create

| Gate | Prerequisite |
| --- | --- |
| **Domain-owner review observed live** | A legitimate domain owner who can actually reach the review surface. The remaining limitation is the **D-19 boundary**, not B-1 — B-1 is resolved (§3.4) |
| **Reviewer separation of duties observed live** | A genuine second privileged person in normal operation |

Both are **P1-07** gates. **Neither is the P1-02 provider-wide SSO Re-check**,
which remains **OPEN / CARRIED / UNVERIFIED** under Phase 1 orchestration,
untouched by this unit.

---

## 14. EXECUTE order

1. §12's corrective prerequisite **and its three tests** — first, because
   everything privileged depends on it
2. Lifecycle states and the supersede rule, pure
3. Authority algorithm (§3) + N-R3, N-R22, N-R26, N-R27
4. Migration (§10) + MySQL migrate/rollback/migrate
5. Generation service + N-R21
6. Decision service (§6.1) + N-R4, N-R5, N-R6, N-R7, N-R25
7. **Step-up (§6.2) + N-R19**
8. Routes and authorisation + N-R1, N-R2, N-R11, N-R14
9. Three screens + N-R15, N-R23
10. Events + N-R17, N-R24
11. Remaining N-R cases; MySQL concurrency
12. Browser verification: 390px, desktop, both themes, Back
13. Mutations recorded honestly; handover documents

---

## 15. Status

**DESIGN — PRODUCT OWNER APPROVED, 19 September 2026.**

**B-1 resolved as B-1a** (§3.4), amending D-87: System Administrator is eligible
for every Domain Review item; the current domain owner remains the preferred and
accountable reviewer. **No open blockers.**

**EXECUTE is authorised on merge of this document.** D-19 and the P1-05 action
class catalogue are **not** changed by this unit, and **P1-02 remains untouched**.
