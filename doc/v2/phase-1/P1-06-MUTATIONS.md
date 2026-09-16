# P1-06 — Security Status: mutation record

**Every guard below was broken deliberately and observed to fail.** Where a
mutation SURVIVED, that is recorded with what was missing and what was added to
close it — including the two that were survivors first and guards second.

| | |
| --- | --- |
| Unit | **P1-06 — Security Status** |
| PLAN | merge `4d93228c1aa2a603f28db1564a6b6304c4b97ba8` |
| DESIGN | merge `0559315e632d04c071727bc5c8176a2dba475999` |
| Suite | **97 tests, 51,866 assertions** across `tests/Unit/Security`, `tests/Feature/Security` and `SecurityStatusArchitectureTest` |

Each mutation was applied to a **committed** tree and reverted with `git
checkout` afterwards — the P1-05 lesson, where an uncommitted edit was lost to a
revert mid-round.

---

## 1. The aggregation contract

| # | Mutation | Result |
| --- | --- | --- |
| **M-A1** | Drop `Unverified` from `Aggregation::PRECEDENCE` — i.e. copy `IdentityHealthReport::state()`, where `NotChecked` contributes nothing | **CAUGHT** — N-SS2, N-SS3b, N-SS4 |
| **M-A2** | Make `PostureState::contributes()` return `true` for every case, putting `NotApplicable` back into the chain | **CAUGHT** — N-SS3c, and the contributes() guard |
| **M-A3** | Reduce the precedence list to `Critical` only, so `Healthy` is returned whenever nothing is critical | **CAUGHT** — N-SS2, N-SS3, N-SS3b |

> **M-A1 is the mutation this unit exists to prevent.** It is the one a
> competent person is most likely to write, because it is **correct in the file
> it would be copied from.**

## 2. The viewer projection and the leak channels

| # | Mutation | Result |
| --- | --- | --- |
| **M-D1** | Add a passthrough field to `ViewerRow::toArray()` carrying the Entra client secret | **CAUGHT** — the seeded-secret equality sweep |
| **M-E4** | Return the bare aggregate label for a viewer with withheld rows | **CAUGHT** |
| **M-E3** | Fold withheld rows into the caption's "not verified" count | **CAUGHT** |
| **M-G5** | Make `Viewer::for()` always report that platform values may be seen | **CAUGHT** |
| **M-1** | Compute the aggregate over all rows rather than the valued ones | **CAUGHT** — by a TYPE ERROR: `WithheldRow` cannot be passed where a `ViewerRow` is required. Recorded as caught, and noted as a weaker signal than an assertion, which is why M-2 was written |
| **M-2** | The naive "named but not valued": keep the badge, replace only the finding | **CAUGHT** — 6 tests, including the byte-identical payload comparison |
| **M-3** | Sort rows worst-first — "show the problems first" | **CAUGHT** — the byte-identical comparison and the explicit ordering test |

> **M-3 is why the headline assertion compares BYTES.** An assertion that
> checked only the aggregate would have passed while the ordering leaked the
> value.

## 3. Posture controls versus informational metrics

| # | Mutation | Result |
| --- | --- | --- |
| **M-B3** | Give a Restricted grant a state — the tempting caution | **CAUGHT** — N-SS14, N-SS16 |
| **M-E5** | Add a defaulted `state` field to `MetricRow` | **CAUGHT** — N-SS17 |
| **M-C4** | Make owner-without-entitlement a finding | **CAUGHT** — N-SS18 |
| **M-F4** | Drop the `users.status = active` filter from `AdministratorSetGuard::effectiveCount()` | **CAUGHT** |
| **M-F5** | Report a sole administrator as healthy | **CAUGHT** — N-SS9 |

## 4. Domains

| # | Mutation | Result |
| --- | --- | --- |
| **M-C1** | Drop `business_domain_id` from the entitlement count | **CAUGHT** — N-SS21 |
| **M-C2** | Drop it from the Restricted count's correlated subquery | **CAUGHT** — N-SS21 |
| **M-C3** | Report a disabled domain as a fault | **CAUGHT** — N-SS19 |
| **M-G7** | Remove the `#id` suffix so two domains share a control identifier | **CAUGHT** — 3 tests |
| **M-G8** | Drop the qualifier, so an exception cannot name its domain | **CAUGHT** |

> The contamination fixture uses **two domains with deliberately different
> populations** — one grant against three, Standard against Restricted. Two
> domains with matching counts cannot detect a missing `where`: the right answer
> and the wrong answer coincide, and the test reports a safety it is not
> providing.

## 5. The P1-08 boundary and the events catalogue

| # | Mutation | Result |
| --- | --- | --- |
| **M-D2** | Render the raw event key instead of its label | **CAUGHT** — the dotted-identifier sweep of every rendered prop |
| **M-D3** | Hand-copy the event list instead of reading `SecurityEventLogger::events()` | **CAUGHT** |
| **M-D4** | Read `laravel.log` to build a history panel | **CAUGHT** |
| **M-D5** | Record `access.state.unrecognised` on the fail-closed path | **CAUGHT** |
| **M-D6** | Call `IdentityHealthCheck::recheck()` instead of `report()` | **CAUGHT** |

## 6. Routes, authorization and navigation

| # | Mutation | Result |
| --- | --- | --- |
| **M-E1** | Add `POST /console/security/exceptions/acknowledge` | **CAUGHT** — the enumerated verb set, and the runtime route scan |
| **M-E2** | Let `exceptions()` filter on `!== Healthy` instead of `isException()` | **CAUGHT** — N-SS36 |
| **M-G1** | Re-lock the Security Status menu node | **CAUGHT** — 3 tests |
| **M-G2** | Give the Auditor a second action class | **CAUGHT** — N-SS27 |
| **M-G3** | Require `PlatformAdmin` instead of `EvidenceRead` | **CAUGHT** — 3 tests |
| **M-G4** | Give a Business User `EvidenceRead` | **CAUGHT** — N-SS25 |

---

## 7. THE SURVIVORS, and what each one exposed

**Seven mutations survived on first run.** Every one was a real gap in the
suite, not a weakness in the code — and two of them were the exact failures the
PLAN was written to prevent.

### M-B1 — encryption reclassified from `Unverified` to `NotApplicable`

**Survived.** Nothing asserted that an applicable-but-unobservable control keeps
contributing. The mutation takes the row out of the applicable set entirely, so
it stops holding the aggregate down — **a way of reaching green that nobody has
to argue for.**

**Closed by** `test_a_control_that_applies_but_cannot_be_observed_is_unverified_not_out_of_scope`,
which asserts the state AND that `contributes()` still holds, plus
`test_no_release_one_control_is_marked_not_applicable` for D-83. **Re-run:
CAUGHT by both.**

### M-B2 — the external half of step-up inferred from the local half

**Survived.** This is the failure **P1-05 actually hit in production**: the local
redirect configuration was correct and step-up still failed, because Entra had
not registered the URI. The suite covered the local half and never asserted that
the external one stays unverified while the local one is healthy.

**Closed by** three tests, including one that reads `externalHalf()`'s source and
requires it to contain no branch at all — a runtime fixture cannot prove that no
reachable path returns `Healthy`. **Re-run: CAUGHT by all three.**

### M-F3 — the inactive-account gate stops checking WHICH gate refused

**Survived.** `if ($reason !== DeniedInactiveUser)` was replaced with
`if (false)`, so any refusal at all would have been read as the gate holding.
Through the real engine an inactive subject is **always** refused by that gate
before any query runs, so **no fixture could produce the distinguishing case.**

The guard was therefore reporting a safety it was not providing — "denied" is
satisfied by any denial, which is precisely the assertion CLAUDE.md §2 warns
about.

**Closed by** extracting the interpretation into
`EngineGateAdapter::interpretInactiveDecision()`, a pure function, and driving
all four outcomes directly. That is not a testability trick: the judgement —
*refused, but not by the gate I asked about* — is the part that must not be read
as healthy. **Re-run: CAUGHT.**

### M-F6 and M-F7 — route coverage always reports healthy

**Survived.** `B-6` had no reachable red branch under test, because every real
route IS covered. M-F7 went further: accept any middleware parameter, including
a typo that fails closed at request time and is therefore not coverage at all.

**Closed by** two tests that register an unclassified console route, and a route
carrying an unrecognised class, and assert the row turns Critical. **Re-run:
both CAUGHT.**

### M-G6 — domain rows stop contributing to the aggregate

**Survived.** Domain posture was added to the aggregate during EXECUTE — without
it, the badge could read Healthy while the section beneath showed a domain
needing attention, and "enabled with nobody accountable for it" would never
reach Exceptions. The change was made and **not tested.**

**Closed by** `test_a_domain_condition_reaches_the_deployment_aggregate_and_the_exceptions`.
**Re-run: CAUGHT.**

### M-F1 — catch-and-skip in `gather()`

**Survived, and the mutation is INVALID.** Skipping in `gather()` does not remove
the row, because the render loop iterates the **catalogue** and defaults missing
evidence to `Evidence::unavailable()`. The row-presence guarantee is structural
and sits one level above the catch, which is stronger than what the mutation was
testing for.

Recorded as a **no-op mutation**, not as a caught one. It was redone as **M-F1b**
— skip the control in the catalogue loop itself, which is the real omission —
and that was **CAUGHT**.

---

## 7a. GATE C BLOCKER — an inactive administrator counted as a privileged holder

**Found at Gate C review, not by the suite.** PR-3 and the per-domain privileged
row counted a **preserved assignment on a deactivated account**, and reported
*"an administrator also holds business access"* about somebody who cannot reach
a single row: `AccessEngine` denies an inactive user at the **global gate**,
before any role, entitlement, scope or ceiling is considered.

It is the posture-control/metric failure arriving through a posture control
instead of a metric — **inventing risk from a legitimate state**. P1-03
preserves relationships deliberately so access can be restored; a preserved
assignment belongs in **PR-5**, a count with the sentence explaining why it is
harmless, watched by **PR-10**.

Fixed in both call sites by requiring the assignment's own `user` relationship
to be active — the same "effective" definition `AdministratorSetGuard` already
uses (current assignment **and** active account), read through the model rather
than re-implemented. **No engine logic is duplicated:** the query asks who the
assignment belongs to, not whether they may see anything.

| # | Mutation | Result |
| --- | --- | --- |
| **M-H1** | Remove the active-user condition from `GrantPathAdapter::privilegedHoldingBusinessData()` | **CAUGHT** — 3 tests |
| **M-H2** | Remove it from `DomainAdapter::privilegedEntitlementCount()` | **CAUGHT** — 3 tests |
| **M-H3** | Invert it — count only INACTIVE holders | **CAUGHT** — 3 tests |
| **M-H4** | Read a field that is always true instead of the status | **CAUGHT** — 3 tests |

> **M-H4 matters most.** It is the mutation that *looks* like the fix — a
> `whereHas('user', …)` is present, so the shape is right — and it proves the
> tests read the STATUS rather than merely the existence of the relationship.

**Every case is tested in three states — active, inactive, and reactivated.**
Testing only the first two would leave the reactivation path unproven, and that
is the path that must work with **nothing re-granted**.

---

## 8. Two defects the guards found in the implementation

Not mutations: real problems in code already written, surfaced when the
architecture guards were first run.

| Defect | How it was found | Fix |
| --- | --- | --- |
| **`IdentityAdapter` held its own copy of the precedence order** — `Critical`, then `Attention`, then `Unverified` — a second implementation of the one rule this unit is built around, sitting in an adapter where nobody would look for it | `test_the_precedence_rule_exists_in_exactly_one_place`, once it was narrowed from "mentions two states" to "walks an ordered list" | It calls `Aggregation::of()` |
| **The route-verb guard read a fixed 1,400 characters** and ran on into the identity group, reporting a `POST` that belongs to P1-02 — a false red | The guard failed on correct code | Brace matching |

> The first of these is worth reading twice: **the guard against a second
> evaluator found one that already existed**, in code written the same day,
> by the same hand, in the same unit.

---

## 9. What no mutation can reach

**There is no JavaScript test runner in this project.** No CI test renders the
DOM, so no mutation of a React component can be caught by the suite — only that
the server sent the data and that the component source contains no conditional
render or `hidden` attribute.

The four defects in §7 of the verification record were found by **opening the
pages and looking at them**, and could not have been found any other way.
