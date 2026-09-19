# P1-07 — Access Reviews: mutation record

**Every guard broken deliberately, and what happened.** A guard nobody has
broken is a guard nobody has tested (`CLAUDE.md` §2).

Run against the committed tree with `vendor/bin/phpunit` per case. The script is
in the session scratchpad; each entry names the exact edit.

## Result

**21 mutations, 21 caught.** Three survived on the first run, each a real gap,
each investigated and closed rather than reclassified.

| # | Mutation | Guard | Result |
| --- | --- | --- | --- |
| **M-R19a** | `stepUpActionFor()` returns null in the decision controller | `ReviewStepUpTest` | **CAUGHT** |
| **M-R19b** | Drop the Organisation Administrator arm from `AccessController::REVOKE_STEP_UP_ACTIONS` | `ReviewStepUpTest` | **CAUGHT** |
| **M-R19c** | Delete `StepUpAction::RevokeOrganisationAdministrator` | `ReviewStepUpTest` | **CAUGHT** |
| M-R1 | Remove `scopeVisible()` from the listing | `ReviewAuthorityTest` | CAUGHT |
| M-R2 | 404 for a missing item, 302 for a forbidden one | `ReviewAuthorityTest` | CAUGHT |
| M-R3 | Replace `grantableBy()` with `RoleCode::cases()` | `ReviewAuthorityTest` | CAUGHT |
| M-R4 | Revoke on both branches, so retain writes to the access model | `ReviewDecisionTest` | CAUGHT |
| M-R6 | Revoke by subject + domain instead of by entitlement id | `ReviewDecisionTest` | CAUGHT *(after fixture fix)* |
| M-R7 | Drop the fingerprint comparison from `supersedeReason()` | `ReviewDecisionTest` | CAUGHT |
| M-R11 | Skip the authority re-check on submit | `ReviewAuthorityTest` | CAUGHT |
| M-R12 | Remove `lockForUpdate()` | `ReviewsConsumeAccessTest` | CAUGHT *(see below)* |
| M-R13 | Compare local dates instead of instants for overdue | `ReviewGenerationTest` | CAUGHT |
| M-R21 | Remove the already-pending check from generation | `ReviewGenerationTest` | CAUGHT |
| M-R24 | Emit the ordinary event for a self-review | `ReviewLeakageTest` | CAUGHT |
| M-R25 | Remove the terminal-state check | `ReviewDecisionTest` | CAUGHT |
| M-R26 | Hard-code the privileged role codes | `ReviewGenerationTest` | CAUGHT |
| M-R27 | Drop the whole-domain half of the domain population | `ReviewGenerationTest` | CAUGHT |
| M-R88 | Remove the self-review guard from `basisFor()` | `ReviewLeakageTest` | CAUGHT *(after assertion fix)* |
| M-RB1 | Restore D-87's strict "fallback only where no owner exists" | `ReviewAuthorityTest` | CAUGHT |
| M-RA1 | Write `ended_at` directly instead of calling P1-05's service | `ReviewsConsumeAccessTest` | CAUGHT |
| M-RA2 | Add a second `grantableBy()` authority list in a controller | `ReviewsConsumeAccessTest` | CAUGHT |

---

## The three survivors, and what each one revealed

### M-R6 — the test passed for a reason unrelated to what it claimed

N-R6 creates two independent grant paths for the same person in the same domain
and revokes one. The mutation replaces "revoke the entitlement by id" with
"revoke by subject and domain".

**It survived** because the fixture reviewed the **first** entitlement: the
mutated query found both rows and `firstOrFail()` happened to return the one
under review. The assertion held by luck.

**Closed** by reviewing the **second** path instead, so the mutation now ends the
wrong grant. Same case, same assertions, a fixture that no longer flatters the
code.

### M-R88 — an assertion satisfied by the wrong mechanism

The self-review guard is `basisFor()` returning null when another eligible
reviewer exists. The test posted a self-review decision and asserted the item
was still `pending`.

**It survived** because a *permitted* self-review redirects to a step-up
confirmation — which also leaves the item `pending`. The test could not tell a
refusal from a confirmation.

**Closed** by asserting on the authority directly (`basisFor()` returns null) and
by asserting the refusal redirect is **not** a step-up location.

This is the failure `CLAUDE.md` §2 names: *an assertion satisfied by any
refusal*. It is also the second time in this project a test has passed because
two different outcomes looked the same from outside.

### M-R12 — a lock a single-threaded test cannot observe

Removing `lockForUpdate()` survived the behavioural suite on **SQLite**, because
one test process cannot observe a lock that is not there.

**Recorded honestly rather than reclassified.** Two things now cover it:

1. `ReviewsConsumeAccessTest::test_the_decision_and_the_reviewed_object_are_locked`
   — a source guard, so the lock cannot quietly disappear. It is **not** the
   evidence.
2. **The MySQL concurrency run in CI** — a new `Run the Reviews suite against
   MySQL` step with a ≥30 test floor and a skip check, because `lockForUpdate`
   semantics differ between the engines and MySQL is what production uses.

---

## Defects the guards found in code already written

Two, neither of which a passing suite would have shown.

### An existence oracle on the decide endpoint

N-R2 asserts a forbidden item and a missing item refuse **identically**. It
failed on first run: route-model binding answered **404** for an id that does not
exist while the controller answered **302** for one the viewer may not touch — a
directory-enumeration oracle letting somebody map other people's reviews by
probing identifiers.

**Fixed** by resolving the item in the controller and refusing identically. It is
the same defect the P1-01 anonymous sweep found on records, arriving by a
different route, and it was caught because the test asserts the two responses are
**the same** rather than asserting a particular status.

### A prefix guard that matched too much

`RouteAuthorizationMatrixTest` matched route prefixes with `str_starts_with`, so
`console/access-reviews` was checked against **`console/access`**'s action class.
Any future `console/access-*` feature would have hit the same thing.

**Fixed** by matching on a segment boundary. The MATRIX now also carries a *list*
of permitted classes per prefix, because Access Reviews legitimately reads at
`EvidenceRead` and decides at `AccessAdmin` — listing both keeps every other
prefix exactly as strict as it was.

---

## Two polish defects found by looking, not by testing

Both on rendered screens, both invisible to a green suite.

| Defect | What was wrong |
| --- | --- |
| **"You can review this as: Reviewed by the person who holds the access"** | `DecisionBasis::label()` was reused for a present-tense question. It is the sentence for a *decided* row. Split into `label()` and `prospectiveLabel()` — two different sentences the screen genuinely needs |
| **"Every record in this domain… Today this is the same as Domain."** | P1-05's scope description ends with implementation context about a reserved future partition. True, and meaningless to somebody deciding whether a person should still reach Finance. The screen now renders the **first sentence only** |
