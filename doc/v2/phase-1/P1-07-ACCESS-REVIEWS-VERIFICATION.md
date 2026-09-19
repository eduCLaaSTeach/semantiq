# P1-07 — Access Reviews: verification record

**What was executed and observed.** A passing automated test is not the same
claim as an observed production result, and nothing below presents one as the
other.

| | |
| --- | --- |
| Unit | **P1-07 — Access Reviews** |
| PLAN | merge `da79fd01947f0972f9d04f161e2c188d25b884d4` (D-84 – D-94) |
| DESIGN | merge `a830488f36e0c8b9040a81cae7b458f991103134` (B-1 resolved as B-1a) |
| Final correction | merge `c92a0aad1123653876127d9ea792c10253937a8a`, deploy run **137** — §15 |
| Status | **PRODUCT OWNER ACCEPTED. GATE D CLOSED. P1-07 CLOSED** — 19 September 2026, §16 |

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

---

## 12. Gate C corrections — the four blockers

The first Gate C submission was rejected with four implementation blockers. All
four are closed; the mutations are in `P1-07-MUTATIONS.md`.

| # | Blocker | How it is closed |
| --- | --- | --- |
| **1** | A step-up could execute stale or misdirected intent | `subject_type`, `subject_id`, `subject_intent` are bound into the confirmation at *begin*. The mutable `pending_decision` is gone, and the completion **addresses the item by id** rather than searching for one |
| **2** | `Access → Reviews` reverse dependency | `StepUpCompletion` + `StepUpCompletionRegistry` in P1-05. **Zero references to `Modules\Reviews` anywhere in `app/Modules/Access`**, asserted by a guard |
| **3** | Missing `RequireOrganisation`, organisation-blind queries | Middleware on both groups; `organisation_id` on the item; cycle starts from the **resolved** organisation; generation, listing, counts and authority all scoped, with the platform-scoped role handled deliberately |
| **4** | Auditor saw nothing | Visibility separated from decision authority. Auditor reads evidence, `decidable` is false, no action path exists |

### 12.1 The one schema decision raised

**`pending_step_ups` gains three generic columns.** Binding a confirmation to an
exact intent means storing that intent somewhere the confirmation owns, and
anything the review screen owns is mutable while the reviewer is away at
Microsoft.

They are **deliberately generic**: P1-05 stores an opaque kind, an id and an
intent string and never interprets any of them. That is what lets P1-07 bind its
own object without P1-05 depending on P1-07, and what will let a later unit do
the same without another migration.

**P1-07 still owns exactly two tables.** This is a P1-05 table gaining three
nullable columns, raised here as the DESIGN's §10 required.

### 12.2 Re-run after the corrections

| | Result |
| --- | --- |
| Full suite | **833 tests, 829 passed, 4 pre-existing skips**, 72,939 assertions |
| `tests/Feature/Reviews` | 47 passed |
| `tests/Architecture` | passed, including 4 new guards |
| Pint | passed |
| Mutations | **8 new, 8 caught**; the original 21 re-run and still caught |
| Browser | **12 combinations, 0 failures**, plus the Auditor observed with **3 rows and 0 action buttons** |

### 12.3 What is still not claimed

- **The step-up round trip is not automated.** M-RD1 — the controller's dispatch
  to the registry — is held by a source guard only, because driving it needs a
  real Microsoft return. P1-05 recorded the same limitation and verified it in a
  browser.
- **The Auditor cannot reach the screen from the menu.** `sidebar: false` was
  observed: D-19 shows the sidebar to System Administrators only. **Unchanged by
  this unit**, and the same limitation P1-06 raised. It does not affect
  authorisation, which is route-level.

---

## 13. Production deployment and verification — 19 September 2026

Merged **`c7069f7f87fe9d7aa7d89256c39ed3de9e30430f`**; **deploy run 135
succeeded**; post-merge CI recorded below.

**The migration ran as part of the deployment** (`php artisan migrate --force`
over SSH, `deploy.yml:461`). Two new tables and three nullable columns on an
existing one; no data migration.

### Verified against production without signing in and without changing anything

Read through `verify-access` run 10 — **manual dispatch, read-only, every
statement from a `SELECT`**, and it reports codes, types and counts only, never
a name or an email.

| Check | Result |
| --- | --- |
| **Two P1-07 tables exist** | `access_review_cycles` **true**, `access_review_items` **true** |
| **Three generic step-up columns exist** | `subject_type`, `subject_id`, `subject_intent` — **all true** |
| **The mutable decision column is gone** | `access_review_items.pending_decision` **absent** — asserted, not assumed |
| **No access row was changed by the migration** | 1 active System Administrator, **0** current entitlements, **0** current scopes, **0** current ceilings, 3 users, 3 enabled domains — **identical to the state recorded in `P1-06-SECURITY-STATUS-VERIFICATION.md` §9a** |
| **No review cycle was created** | `review_cycles_total` **0**, `review_items_total` **0**. Starting one is the Product Owner's, at Gate D |
| `users.platform_role` still absent | true — the D-49 position is unchanged |

### Verified over HTTP, unauthenticated

| Check | Result |
| --- | --- |
| All three review routes reachable | `302` to sign-in — deployed, not `404`, not `500` |
| Every other console route unchanged | Security Status, Security Events, Roles & Access, Identity & SSO, Business Domains, Users & Groups — all `302` |
| GET-only on the read routes | `POST`/`PUT`/`PATCH`/`DELETE` on `/console/access-reviews` → **405** |
| The decide endpoint refuses anonymously | **419**, never a `500` and never an action |
| The refusal discloses nothing | No review, role or access wording anywhere in the unauthenticated response |
| The deployed bundle is the verified one | `build/assets/app-ire3WRv1.css` and `app-u3xEMmnP.js` are **byte-for-byte the local build the browser checks ran against** |
| Three tabs shipped, no raw key | *Privileged Reviews*, *Domain Reviews*, *Overdue Reviews* present; **zero** `access.review.*` identifiers in the client bundle |

### One observation worth recording

`verify-access` warns that **9 step-up confirmations are open** (14 total). These
pre-date P1-07 — leftovers from earlier acceptance testing whose five-minute
lifetime has long expired. `StepUpService::resolve()` refuses an expired row, so
nothing is reachable through them. **Not a defect, and not introduced here**,
but it is in the record rather than left for somebody to find.

### What could NOT be verified from here

**Nobody signed in.** Sign-in is Microsoft Entra SSO, so the three screens were
not rendered as the authenticated System Administrator on production, the
sidebar node was not clicked, and no decision was taken. Sections A–G of the
Product Owner Test Script exist for exactly that, and this record does not claim
any of it.

**No review cycle exists on production**, deliberately. Starting one creates
permanent records, and that is the Product Owner's decision at Gate D.

---

## 14. Gate D — three defects found by the Product Owner, and their correction

**Gate D FAILED on the deployed build `c7069f7f87fe9d7aa7d89256c39ed3de9e30430f`.**
The failed observation stays in this record; it is not erased by the fix.

### 14.1 What the Product Owner saw on production

| Screen | Observation |
| --- | --- |
| Privileged Reviews | Several historical System Administrator rows marked **Access confirmed**, mixed with one current row awaiting review |
| Domain Reviews | *"No sensitive domain access is awaiting your review."* — **correct**, production has no qualifying access. But **Start a review cycle was shown here** |
| Overdue Reviews | *"Nothing is overdue."* — **correct**. But **Start a review cycle was shown here too**, and clicking it from either tab returned them to Privileged Reviews |
| Remove this access | Clicked on their own System Administrator review. **No Microsoft fresh-sign-in appeared. The button appeared to do nothing.** |

### 14.2 The root cause, and why one defect produced two symptoms

**Defect 3 caused Defect 2's clutter.**

The screen submitted with `useForm({ decision: 'retain' })` and then
`post(url, { data: { decision } })`. Inertia types those submit options as
`Omit<VisitOptions, 'data'>` — **the `data` key is excluded** — so it was
silently dropped and **every click sent `retain`**.

So "Remove this access" quietly *confirmed* the access. That is why the button
looked inert, and why the screen accumulated rows marked *Access confirmed*: each
cycle's "removal" was a confirmation.

> **A green suite did not catch this.** Every behavioural test posted to the
> server directly, where the decision is whatever the test sends. The defect
> lived entirely in the shape of the client call, and this project has no
> JavaScript test runner to click a button. **The browser check now reads the
> request bodies off the wire.**

### 14.3 The three corrections

| Defect | Correction |
| --- | --- |
| **1 — the cycle control** | `Start a review cycle` is offered on **Privileged Reviews only**, with the sentence *"Starts one review cycle covering privileged and sensitive domain access."* A second overlapping cycle is refused with *"A review cycle is already in progress. Complete the outstanding reviews before starting another cycle."* — previously it silently created an **empty** cycle, which is safe and baffling |
| **2 — historical clutter** | The three screens project the **current/latest cycle** only. **Nothing is deleted**: every historical row stays exactly where it is, permanently, and P1-08 owns the experience for reading it |
| **3 — the decision** | `router.post(url, { decision })`. The decision is the request body, never a form default a click hopes to override |

### 14.4 Evidence

| | |
| --- | --- |
| New tests | `ReviewCycleProjectionTest` (6), `ReviewDecisionSubmissionTest` (4) |
| New architecture guards | the decision is never a form default; exactly one screen offers to start a cycle |
| Mutations | **7 targeted, 7 caught** — M-D1a/b, M-D2a/b, M-D3a/b/c |

**M-D3a is the one that matters**: defaulting an unrecognised decision to
`retain` is precisely what the browser was doing, and the test now fails on it.
A missing or unrecognised decision **decides nothing** — a default is still a
decision nobody made.

### 14.5 Browser verification of the correction

Run against **the Product Owner's exact data shape**: one completed cycle and one
current cycle, both containing a review of the same access.

| Requirement | Observed |
| --- | --- |
| Start control on Privileged Reviews only | `start=1` on Privileged, **`start=0`** on Domain and Overdue, in all four viewport/theme combinations |
| Wording beside the control | Present |
| Only the current cycle shown | Row counts match the current cycle exactly; the completed cycle contributes **nothing** |
| Confirm sends `retain` | Read **off the wire**: `{"decision":"retain"}` |
| Remove sends `revoke` | Read **off the wire**: `{"decision":"revoke"}` |
| Remove enters the step-up flow | Landed on `/console/access/step-up/<reference>` — **the flow the Product Owner never saw** |
| Empty states unchanged | *"Nothing is overdue."* still rendered |
| No horizontal clipping | **0 elements** cross either viewport edge |
| Browser Back | Returns exactly along the trail |
| No developer terminology | Swept; none present |

**One regression was caught by that sweep and fixed before merge.** The new
explanatory sentence sat in the shared `org-section-actions` slot, which is
`flex: 0 0 auto` — so a child that is a sentence rather than a button could not
shrink and pushed past the screen edge at 390px. The **page** did not scroll
sideways, so only the element-level check saw it. That is the G2 lesson a third
time.

### 14.6 What is NOT claimed

- **The last-administrator refusal was not exercised on production.** The
  automated test walks the whole journey — click, step-up bound to that exact
  item and decision, then the administrator floor refusing — but **no second
  System Administrator was manufactured**, so the Product Owner's own retest
  stops at the Microsoft confirmation.
- **The empty-state sentences are browser evidence, not test evidence.** They are
  rendered by React; there is no server-side rendering and no JavaScript test
  runner, so a server-side assertion on the wording would be checking nothing.

---

## 15. Gate D — final correction: a refused review answers on the review screen

**Gate D retest, one remaining defect.** On the Product Owner's own System
Administrator review, *Remove this access* → Microsoft confirmation → they
landed on **Roles & Access** reading the administrator-floor refusal, with no
indication of what had become of the review they were doing.

### 15.1 Why it happened

`ReviewStepUpCompletion` caught only `ReviewViolation`. A P1-05 refusal — the
administrator floor — propagated as an `AccessViolation`, so it was handled by
P1-05's own `StepUpController::refuseToIndex()`, whose destination is
`access.index`. That is the **right** destination for an action begun in Roles &
Access and the wrong one for an action begun in Access Reviews.

The destination was never the review's to begin with, so the correction is on
**P1-07's side of the boundary**. P1-05's own refusal destination is unchanged,
and a test asserts that directly.

### 15.2 What the correction does

| Requirement | How |
| --- | --- |
| Review item stays **Pending** | The state write in `ReviewDecisionService` happens *after* the revoke that threw, and the inner transaction rolls back to its savepoint. Not retained, not revoked, not superseded — **none of those happened** |
| Step-up **consumed**, no replay | The refusal is **returned**, not rethrown, so the surrounding transaction commits the consumption. One confirmation authorises one attempt; a refused attempt was still the attempt |
| Back to the **originating** screen | `originatingScreen()` routes on `$item->kind` — Privileged → `/console/access-reviews`, Domain → `/console/access-reviews/domains` |
| The **business** refusal is shown | P1-05's own sentence, flashed as `refusal`. This unit does not paraphrase the administrator floor |
| Evidence under the **existing** key | `ReviewDecisionService::refuse()`, `access.review.refused`, reason `sole_administrator`. **No new event key** |
| System Administrator assignment untouched | The floor refused; nothing was ended |

### 15.3 A SECOND DEFECT, FOUND ONLY BY OPENING THE SCREEN

With the redirect corrected, the browser showed **a blank page**.

`refusal` was **never among the shared Inertia props**. `ReviewPage` read
`props.refusal`, found nothing, and rendered nothing — so *every* refusal the
review screens have ever raised went to a page that said nothing at all. Roles &
Access renders its refusals out of `errors`, which Inertia shares for us; Access
Reviews flashes `refusal`, which nobody shared.

**No automated test caught it, and the suite was green.**
`assertSessionHas('refusal', …)` passes on a message that reaches the session
and never reaches the screen — *the two are different claims.* This is
CLAUDE.md §2 exactly: a test that passes for a reason unrelated to what it
claims to check.

Fixed by sharing `refusal` beside `confirmation`, and covered by a test that
asserts the **prop the screen renders**, not the session key.

While there, the review banners were brought onto the shared pattern every other
feature uses — `role="alert"` / `role="status"`, the **Refused.** label, the
confirmation suppressed when both are present. They were bare `<p>` elements, so
a refusal was announced to nobody using a screen reader.

### 15.4 Mutations — every guard broken deliberately

| # | Mutation | Result |
| --- | --- | --- |
| M-RD1 | Remove the `AccessViolation` catch (the shipped behaviour) | **KILLED** — redirect becomes `/console/access`, no refusal flashed, step-up left reusable (3 cases) |
| M-RD2 | `originatingScreen()` returns the privileged route unconditionally | **KILLED** — the domain case fails |
| M-RD3 | Drop the `refuse()` call | **KILLED** — the refusal leaves no evidence |
| M-RD4 | Point `refuseToIndex()` at the review screen | **KILLED** — Roles & Access no longer answers on Roles & Access |
| M-RD5 | Move the item's state write above the revoke call | **SURVIVED — recorded, not hidden.** The inner transaction rolls back either way, so the *transaction* is what holds the item Pending, not the ordering. Claiming otherwise would credit the case with a guarantee it does not provide |
| M-RD6 | Mark the item decided before calling the decision service | **KILLED** (4 cases) |
| M-RD7 | Close the item in the refusal handler — the "tidy it away" change | **KILLED** — the review would read as decided for access that is still there |
| M-RD8 | `AdministratorSetGuard::refuseIfLast()` stops refusing | **KILLED** (6 cases) — the review path runs **through** the floor. P1-07 implements none of its own |
| M-RD9 | Remove `refusal` from `HandleInertiaRequests::share()` | **KILLED** — and the session assertion still passes, which is the whole point of §15.3 |

### 15.5 Browser verification — observed, not expected

Local deployment, sole active System Administrator, their own privileged review.
**Nothing was manufactured to produce the refusal**: no second administrator was
created and none was deactivated. The deployment genuinely has one.

**Microsoft's own leg is not exercisable locally** — there is no Entra
configuration on the local deployment. Everything either side of it ran as
production runs it: the reference the *browser* was issued was resolved by
`StepUpService::resolve()` against the browser's own session, then executed
through `consumeAndPerform()` and the registered completion.

| Check | Observed |
| --- | --- |
| Row before the click | 1 row, *"Awaiting review"*, Role *System Administrator*, both decision buttons |
| *Remove this access* | Departed to `/console/access/step-up/<reference>` |
| Completion result | Target `**/console/access-reviews**`, refusal *"This is the only active System Administrator…"*, `consumed_at` set |
| Banner on return | *"**Refused.** This is the only active System Administrator. Add or retain another before removing this one."*, `role="alert"`, danger edge, visible in **all four** viewport/theme combinations |
| Review after the refusal | Still **1 row, "Awaiting review", both buttons** |
| Success banner | Absent — a refusal and a confirmation never show together |
| Horizontal clipping | **0 elements** cross either viewport edge at 1440px and 390px |
| Console errors | None |
| Implementation terms on screen | None — swept for `sole_administrator`, `AccessViolation`, `step_up`, `role_assignment`, `exception` |

### 15.6 What is NOT claimed

- **The Microsoft round trip was not exercised in a browser.** No Entra
  configuration exists locally. That leg is exactly what the Product Owner's one
  retest covers.
- **The MySQL Reviews suite ran in CI, not locally.** No MySQL server is
  available in this environment; the `Run the Reviews suite against MySQL` step
  in `ci.yml` is the evidence.
- **No production review cycle was created**, and the production observation
  remains the Product Owner's to make.

---

## 16. P1-07 ACCEPTED — Gate D closed

**Product Owner final production retest: PASS.** 19 September 2026.

| | |
| --- | --- |
| Final correction merge | `c92a0aad1123653876127d9ea792c10253937a8a` |
| Deployment | `Deploy to cPanel (SSH)` run **137** — success |
| Gate D | **CLOSED** |
| P1-07 | **CLOSED** |

### 16.1 What the Product Owner observed in production

| Observation | Result |
| --- | --- |
| Returned to **Access Reviews**, not Roles & Access | **PASS** |
| Refusal banner visible — *"This is the only active System Administrator. Add or retain another before removing this one."* | **PASS** |
| Review remains **Awaiting review** | **PASS** |
| System Administrator access **remains in place** | **PASS** |
| Current-cycle projection remains correct | **PASS** |

This is an **observed production result**, not a passing test presented as one.

### 16.2 Carried forward — NOT closed by this acceptance

Accepting P1-07 closes P1-07. It closes nothing else.

| Carried item | State |
| --- | --- |
| **P1-02 provider-wide SSO Re-check** | **OPEN / CARRIED / UNVERIFIED.** A Phase 1 orchestration gate. P1-07 never owned it and did not touch it |
| **P1-07 live-verification items that could not legitimately be manufactured** | **CARRIED**, unchanged, as listed in §11 of the Product Owner Test Script — removing access and seeing it disappear, two grants with one removed, somebody else reviewing your access, a domain owner reviewing, two reviewers deciding at once, and removing the last System Administrator successfully |

Every one of those stays carried for the reason it was carried: exercising it
would mean **manufacturing production access, a second administrator, or false
organisational history**. None is an implementation defect, and none was closed
by inference from a passing test.

### 16.3 Where the evidence lives

| Subject | Section |
| --- | --- |
| Gate C build, tests, mutations | §1 – §12 |
| Deployment and production verification | §13 |
| Gate D failure and the three corrections | §14 |
| Gate D final correction, and the blank-screen defect it exposed | §15 |
| Acceptance | this section |
