# Day work note - 29 August 2026

**This is the CURRENT operational state.** It supersedes
`TaskCompleted-2026-08-26.md`, which is marked historical and whose STARTING
POINT would, if followed today, repeat work already completed.

Neither earlier note is edited into a state that was not true on its own day.
That is the stopping-point rule in `CLAUDE.md`, and this file exists because the
26 August note was extended past the point where it was still true - which is
the exact failure the rule was written to prevent.

---

## STOPPING POINT

**The R1.4c-i production migrations are COMPLETE AND VERIFIED. The corrected
Privacy Requests UI is in PR #30, OPEN and NOT MERGED. R1.4c-i is NOT
ACCEPTED.**

| Item | State |
|---|---|
| R1.1 Platform Foundation | CONFIRMED |
| R1.2 Identity and Access | CONFIRMED |
| R1.3 Security Foundation | CONFIRMED |
| R1.4a profiles and privacy contact | **ACCEPTED** |
| R1.4b auditor capability, exceptions, retention, audit log | **ACCEPTED** |
| R1.4c-i implementation | **MERGED via PR #29** |
| R1.4c-i production migrations | **COMPLETE AND VERIFIED** |
| R1.4c-i corrected UI | **PR #30 OPEN, NOT MERGED** |
| R1.4c-i corrected UI production verification | **NOT YET** |
| `privacy_correction_notes` triggers | **NOT INSTALLED** |
| Context-register backfill | **OUTSTANDING** |
| R1.4c-i acceptance | **NOT YET** |
| R1.4c-ii | **NOT STARTED** |
| **R1.4 Gate 4 overall** | **IN PROGRESS** |
| R1.5 to R1.7 | LOCKED |

### The two SHAs that matter

| | |
|---|---|
| `main`, before PR #30 merges | `660d74c427a9e9dab3d611d39e69396cab5953e9` |
| PR #30 head | `27d3fdb4973ae312bd0d8147c7d2a5edc36a0514` |

### What is true about production right now

| Fact | State |
|---|---|
| R1.4c-i code | Deployed and live |
| The three R1.4c-i migrations | **Run, and verified in both directions** |
| `privacy_requests`, `privacy_request_records`, `privacy_correction_notes` | Exist, correct shape, **empty** |
| The two append-only triggers | **NOT installed** - separate approved step |
| Privacy Requests functional check | **Passed**, signed in |
| Privacy Requests visual presentation | **Defect found, corrected locally, NOT yet production-verified** |
| Production database since the migration | **Unchanged** |

## STARTING POINT

**Next action: obtain merge approval for PR #30.**

Then, in this order:

| # | Step | Approval |
|---|---|---|
| 1 | Obtain merge approval for PR #30 | **Product owner** |
| 2 | Merge and deploy PR #30 | Follows from 1 |
| 3 | Recheck the corrected Privacy Requests UI on production | Observation only |
| 4 | Take a **fresh** production database backup | Required before 5 |
| 5 | Install the two approved append-only triggers | **Separately approved** |
| 6 | Verify trigger existence and definitions, **read-only** | Part of 5 |
| 7 | Complete the R1.4b + R1.4c-i context-register backfill | Own reviewed PR |
| 8 | Request R1.4c-i acceptance | **Product owner** |

### Do not

- **Do not rerun the migrations.** They are done and verified.
- **Do not install the triggers before separate approval.**
- **Do not start R1.4c-ii.**
- **Do not mark R1.4c-i accepted.**

SEC-DEC-066 remains an acceptance condition: R1.4c-i cannot be accepted until
both triggers exist on production, their definitions match the approved SQL in
`doc/execution/R1.4c-PLAN.md` section 1.8, and the automated proof is recorded.

**On step 4:** the existing backup - `<DATABASE_NAME>.sql.gz`, 26 August 2026,
17:30 SGT - is evidence for the migration period and **is not the
trigger-installation checkpoint**. It predates the migration, so restoring it
would roll back the R1.4c-i schema as well as everything since.

**On step 6:** the triggers' FIRING is proved in the automated suite against
real triggers, including an unqualified raw `DELETE`. On production only their
existence and definition are checked. Deliberately attacking a live evidence
table to watch it refuse is not a test worth the risk.

---

## Unresolved governance decisions carried forward

**Not code defects.** Positions nobody has decided, which SemantIQ shows
truthfully rather than inventing a compliance posture it does not have.

| Item | Current state | Why it reads that way |
|---|---|---|
| AI processing geography | **Not determined** | No decision taken. A screen that guessed would assert a sovereignty position on the customer's behalf |
| Retention periods, all 7 personal-data categories | **Not Configured** (7 of 7) | Software must not invent a legal retention period. The screen states plainly that 7 of 7 have none |

Neither blocks R1.4c-i acceptance. Both are owner decisions.

---

## What PR #30 contains

Four separable areas. **It does not complete R1.4c-i acceptance** - the
context-register backfill is not in it.

| Area | Content |
|---|---|
| UI correction | Both Privacy Requests screens moved onto the canonical design-system classes. No new CSS |
| Regression protection | `DesignSystemContractTest`, `MigrationExpectationTest` |
| Production verification evidence | Migration and schema evidence, exact-set ledger reconciliation, the signed-in production observation, backup evidence |
| Verification utilities | Four SELECT-only SQL scripts and the generator that emits one of them |

It changes no controller, service, business rule, permission, route, migration
or schema, and made no production change.

---

## Where things are

| What | Where |
|---|---|
| Current state | **This file** |
| 26 August state, historical | `doc/TaskCompleted-2026-08-26.md` |
| 24 August state, historical | `doc/TaskCompleted-2026-08-24.md` |
| The twelve invariants and the working rules | `doc/execution/R1.4c-i-INVARIANTS.md` |
| R1.4c plan, counts, approved trigger SQL | `doc/execution/R1.4c-PLAN.md` |
| Decisions SEC-DEC-079 to SEC-DEC-093 | `doc/context/SECURITY_PRIVACY_DECISIONS.md` |
| Operator SQL | `doc/execution/CHECK-*.sql`, `CONFIRM-*.sql` |
| UI captures, local | `doc/screenshots/R1.4c-i-ui/` |

`IMPLEMENTATION_STATUS.md` has **not** been advanced for R1.4c-i, and
`doc/PROJECT_MAP.html` has **not** been regenerated as accepted. Neither may
change until acceptance.

---

## The rule this file exists to honour

**Historical evidence and current operational state must never be conflated.**

A day note is immutable historical evidence. When the project state changes,
create a NEW current-state note rather than rewriting the old note into a state
that was not true on that day. The new note names the note it supersedes; the
old note is never edited to agree with it.

The 26 August note was allowed to keep growing after its STOPPING POINT stopped
being true, so it simultaneously said the production database was unmigrated and
recorded the migration that had been run. **A document cannot be the current
plan and a historical record at the same time.** That is why this file exists,
and why the 26 August note now carries a banner rather than a rewrite.
