# P1-07 — Access Reviews: verification record

**What was executed and observed.** A passing automated test is not the same
claim as an observed production result, and nothing below presents one as the
other.

| | |
| --- | --- |
| Unit | **P1-07 — Access Reviews** |
| PLAN | merge `da79fd01947f0972f9d04f161e2c188d25b884d4` (D-84 – D-94) |
| DESIGN | merge `a830488f36e0c8b9040a81cae7b458f991103134` (B-1 resolved as B-1a) |
| Status | **GATE C — implementation complete, not merged and not deployed** |

---

## 1. What was built

| Area | Files |
| --- | --- |
| Domain model | `app/Modules/Reviews/Support/` — `ReviewState`, `ReviewKind`, `ReviewDecision`, `DecisionBasis`, `SupersededReason`, `Composition`, `ReviewViolation` |
| Models | `AccessReviewCycle`, `AccessReviewItem` |
| Services | `ReviewerAuthority`, `ReviewCycleGenerator`, `ReviewDecisionService` |
| Controllers | `AccessReviewsController` (reads), `AccessReviewDecisionController` (writes) |
| Screens | `Pages/Reviews/{Privileged,Domains,Overdue}.jsx`, `Components/{ReviewPage,ReviewTabs}.jsx` |
| Schema | one migration, **two new tables**, nothing altered on any P1-05 table |
| Events | six new keys, **no `ALLOWED_KEYS` change** |
| P1-05 correction | `StepUpAction::RevokeOrganisationAdministrator` + enforcement (§5) |

---

## 2. Tests

| Suite | Result |
| --- | --- |
| Full suite | **820 tests, 816 passed, 4 skipped** (the four pre-existing skips), 72,823 assertions |
| `tests/Feature/Reviews` | 38 passed |
| `tests/Architecture` | 171 passed |
| `vendor/bin/pint --test` | passed |

Coverage against the PLAN's catalogue: **N-R1 – N-R27**, plus the three §12
correction cases. Every one names the mutation that must break it.

---

## 3. Mutations

**21 run, 21 caught.** Three survived first time and each was a real gap:

- **M-R6** — the fixture let the mutation revoke the right row by luck.
- **M-R88** — a permitted self-review and a refusal both leave the item
  `pending`, so the assertion could not tell them apart.
- **M-R12** — a lock a single-threaded SQLite test cannot observe.

All three are recorded in full, with what was changed, in `P1-07-MUTATIONS.md`.
**M-R12's real evidence is the MySQL run, not the source guard**, and the record
says so.

---

## 4. Two defects the guards found in code already written

- **An existence oracle on the decide endpoint.** Route-model binding answered
  404 for a missing item while the controller answered 302 for a forbidden one —
  enough to map other people's reviews by probing identifiers. Caught because
  N-R2 asserts the two responses are **identical** rather than asserting a
  status.
- **A prefix guard that matched too much.** `console/access-reviews` was being
  checked against `console/access`'s action class, and any future
  `console/access-*` feature would have hit the same thing.

Both are described in `P1-07-MUTATIONS.md`.

---

## 5. The bounded P1-05 correction

**Ruled by the Product Owner at PLAN §9: a real P1-05 implementation
inconsistency, not a policy choice.**

`RoleCatalogue::requiringStepUp()` has always returned System Administrator
**and** Organisation Administrator. `StepUpAction` carried
`grant_organisation_administrator` but no revoke case, so **revoking an
Organisation Administrator — removing somebody who holds `AccessAdmin` — went
through with no fresh sign-in.**

| Changed | |
| --- | --- |
| `StepUpAction` | Added `RevokeOrganisationAdministrator` |
| `AccessController` | `REVOKE_STEP_UP_ACTIONS` map, covering both roles — the gap is closed **where it originated**, in Roles & Access, not only in P1-07 |
| `StepUpController` | The new action routes to the existing `performRevoke` |

**Not changed:** `RoleCatalogue`, `RoleAssignmentService`, `EntitlementService`,
`AccessEngine`, the access schema, the role model, or any accepted decision.

### Evidence

| Test | Mutation | Result |
| --- | --- | --- |
| `test_every_role_requiring_step_up_has_both_a_grant_and_a_revoke_action` | Delete the new case | **CAUGHT** |
| `test_roles_and_access_revoke_of_an_organisation_administrator_requires_step_up` | Drop the map arm | **CAUGHT** |
| `test_revoking_an_organisation_administrator_from_a_review_requires_step_up` | Same | **CAUGHT** |

The first is the valuable one: **derived from `requiringStepUp()`**, so a role
added there later without both actions fails immediately. **It would have caught
the original omission.**

---

## 6. Browser verification — what was actually observed

Chromium at `/opt/pw-browsers/`, signed in as the System Administrator.
**Measured on the tab and on the row, never on the page** — the G2 lesson.

| Requirement | Observed |
| --- | --- |
| Three tabs, all three screens, **390 × 844 and 1440 × 1100, light and dark** | 12 screen/viewport/theme combinations, **0 failures** |
| Tabs readable and tappable at 390px | All three in viewport, no label clipped inside its own box, every tab hit-tests to itself |
| No horizontal overflow | **Zero elements** cross either viewport edge on any of the twelve; the strip does not scroll itself |
| Sidebar discoverability | "Access Reviews" present and reachable from the rail on every run |
| Browser **Back** | Clicked forward through all three tabs, then Back twice, returning exactly along the trail |
| Console errors | **None**, with web fonts stubbed rather than aborted so the harness could not manufacture its own |
| Developer terminology | Swept the rendered text for *entitlement, role_assignment, enum, grant path, composition, fingerprint, null, undefined* — **none present** |
| Empty states | *"Nothing is overdue."* renders as a sentence, not a dash |

**Looked at, not only measured.** The screens were inspected in both themes at
both widths. **Two polish defects were found by reading them** and are recorded
in `P1-07-MUTATIONS.md` — neither would have failed a test.

---

## 7. What is NOT claimed

- **Nothing is deployed.** This is Gate C: one PR, unmerged.
- **The MySQL run is CI's.** A new `Run the Reviews suite against MySQL` step
  exists with a ≥30 floor and a skip check. **Its result is CI's to report**, and
  the concurrency evidence depends on it — M-R12 survives on SQLite.
- **Nobody signed in on production.** Every browser observation above is local.
- **Visibility and discoverability are human observations**, recorded as
  observed. No automated test in this project renders the DOM.

---

## 8. Carried gates

| Gate | Status |
| --- | --- |
| **P1-02 provider-wide SSO Re-check** | **OPEN / CARRIED / UNVERIFIED** — Phase 1 orchestration. **Untouched.** No second permanent System Administrator was manufactured |
| **P1-04 disabled-domain gate** | CLOSED (P1-05). Unchanged |
| **B-9b — Microsoft's acceptance of the step-up return** | Unchanged, permanently `unverified` in Release 1. P1-07 adds three step-up actions and claims nothing new about Microsoft |
| **NEW — domain-owner review observed live** | **OPEN / CARRIED.** The owner basis is implemented and unit-tested; no business role passes an administration action class and D-19 shows the sidebar to System Administrators only, so an owner cannot yet reach the screen. B-1a keeps every item decidable in the meantime |
| **NEW — reviewer separation of duties observed live** | **OPEN / CARRIED.** Needs a genuine second privileged person in normal operation |

---

## 9. NOT CURRENTLY OBSERVABLE WITH REAL PRODUCTION DATA

Production holds **1 active System Administrator, 0 current entitlements, 0
scopes, 0 ceilings**. **None of the following is an implementation defect.**

| What cannot be observed on production | Automated evidence instead |
| --- | --- |
| Revoke changing effective access | N-R5 |
| One path revoked, another retained | N-R6 |
| Reviewer separation of duties | N-R3, N-R11 |
| Self-review as the exception path | N-R88, `ReviewLeakageTest` |
| Last-administrator refusal | N-R18 |
| Concurrent decisions | N-R12, **on MySQL** |
| Domain-owner review | N-R22, `ReviewAuthorityTest` |

**No production privilege, entitlement or administrator will be created to make
any of these observable.**

---

## 10. Deviations from the approved DESIGN

Two, both small, both reported rather than absorbed.

### 10.1 P1-07 has its own step-up actions instead of re-using P1-05's

The DESIGN said a review's revoke would re-use `RevokeSystemAdministrator`.
**It cannot.** `StepUpController::perform()` routes on the action alone and every
parameter comes from the stored row, so re-using P1-05's action would have
returned from Microsoft into P1-05's performer: the access would end and **the
review item would stay pending forever** — a decision the evidence would never
show.

P1-07 therefore declares `ReviewRevokePrivileged`, `RevokeRestrictedEntitlement`
and `SelfReview`. Two of the three names are exactly as approved.

**This is a strengthening:** the stored row is unambiguous about which surface
began the confirmation, and P1-05's performers cannot be reached with a review's
target.

### 10.2 The chosen decision is held on the review item, not in the pending row

So that **nothing about a P1-07 decision is written into a P1-05 table**, which
is what DESIGN §10 required. The item is found again on return by the reviewed
object's id, which is unique because generation guarantees at most one `pending`
item per object.

---

## 11. Professional-polish gate

Asked honestly of every screen, at both widths and in both themes:

> Would a professional SaaS product team be comfortable showing this exact screen
> to a customer?

**Yes, after the two corrections in §6.** Before them, one row answered a
present-tense question with a past-tense record, and another carried P1-05's
internal caveat about a reserved future partition onto a business screen.
Neither would have failed a test.
