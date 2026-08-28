# Day work note - 26 August 2026

Written at the pause point so the next session starts from fact rather than
memory. Read STOPPING POINT and STARTING POINT first; everything after is
detail.

**This note supersedes `TaskCompleted-2026-08-24.md` as the CURRENT state. It
does not replace it.** That note remains accurate for the day it describes and
must not be edited into a state that was not true on 24 August. See "The
stopping-point rule" at the end.

---

## STOPPING POINT

**R1.4c-i is MERGED and its CODE IS DEPLOYED. It is NOT ACCEPTED.**

`main` is at `660d74c427a9e9dab3d611d39e69396cab5953e9`.

| Item | State |
|---|---|
| R1.1 Platform Foundation | CONFIRMED |
| R1.2 Identity and Access | CONFIRMED |
| R1.3 Security Foundation | CONFIRMED |
| R1.4a profiles and privacy contact | **ACCEPTED** |
| R1.4b auditor capability, exceptions, retention, audit log | **ACCEPTED** |
| R1.4c-i PDPA-01 Privacy Requests - implementation | **MERGED** |
| R1.4c-i - code deployment | **COMPLETE** |
| R1.4c-i - acceptance | **NOT YET** |
| R1.4c-ii breach register, governance overview | **NOT STARTED** |
| **R1.4 Gate 4 overall** | **IN PROGRESS** |
| R1.5 to R1.7 | LOCKED |

### What is true about the release

| Fact | Value |
|---|---|
| PR #29 | Merged |
| Merge SHA | `660d74c427a9e9dab3d611d39e69396cab5953e9` |
| `main` SHA | `660d74c427a9e9dab3d611d39e69396cab5953e9` |
| CI on the merge commit | success - run 32948329073 |
| Deploy to cPanel (SSH) | **completed successfully** - run 32948329096 |
| `/sign-in` on the live site | HTTP 200, healthy |
| Production database | **UNCHANGED. No migration has been run.** |

### The gap between deployed code and deployed database

The deploy workflow **ships code only**. It runs `key:generate` and
`optimize:clear` and contains no `artisan migrate` step at all.

R1.4c-i adds three migrations. Until they are run on the server, the live
database is one release behind the live code, and Privacy Requests reports
**Migration required** rather than rendering an empty list - because "no
requests" and "cannot see" are different facts and the screen must not confuse
them.

That state was confirmed on production only as far as it can be without
credentials:

| Probe | Result | What it establishes |
|---|---|---|
| `/admin/governance/does-not-exist-abc123` | 404 | control - unknown routes 404 |
| `/admin/governance/privacy-requests` | 302 to `/sign-in` | the route exists, so R1.4c-i code is live |
| `/admin/governance/privacy-requests/1` | 302 to `/sign-in` | detail route live |
| `/admin/governance/privacy-requests/abc` | 404 | `whereNumber()` constraint live, SEC-DEC-058 |

The rendered "Migration required" screen itself was **reproduced locally**
against the merged commit with the three tables absent. **It is not a
production observation.** This session holds no production credential and must
not hold one.

## STARTING POINT

**Next action: obtain approval for, then run, the three R1.4c-i production
migrations. Nothing else is in front of that.**

Nothing is half-finished in the repository. No branch is left open and no PR is
unmerged. What remains is production work and documentation, listed below.

---

## Remaining R1.4c-i acceptance work

These are distinct steps. None has been started.

| # | Step | Nature | Approval |
|---|---|---|---|
| 1 | Run the three R1.4c-i production migrations | Production database change | **Separately approved step** |
| 2 | Install the two `privacy_correction_notes` append-only production triggers - `BEFORE UPDATE` and `BEFORE DELETE` | Production database change | **Separately approved step** |
| 3 | Verify production trigger EXISTENCE and definitions against the approved SQL. **Do NOT intentionally fire destructive trigger tests on production** | Read-only verification | Part of step 2's approval |
| 4 | Complete the separate R1.4b + R1.4c-i context-register backfill | Documentation-only PR | Ordinary review |
| 5 | Record final production and browser verification evidence | Documentation | Ordinary review |
| 6 | Request R1.4c-i acceptance | Only after 1 to 5 | Product owner |

**SEC-DEC-066 remains an acceptance condition.** R1.4c-i cannot be accepted
until both triggers exist on production, their definitions match the approved
SQL in `doc/execution/R1.4c-PLAN.md` section 1.8, and the automated proof is
recorded.

On step 3: the triggers' FIRING is proved in the automated suite against real
triggers in a test database, including an unqualified raw `DELETE`. On
production, only their existence and definition are checked. Deliberately
attacking a live evidence table to watch it refuse is not a test worth the risk.

---

## Unresolved governance decisions carried forward

**These are not code defects.** They are positions nobody has yet decided, and
SemantIQ shows them truthfully rather than inventing a compliance posture it
does not have.

| Item | Current state | Why it reads that way |
|---|---|---|
| AI processing geography | **Not determined** | No decision has been taken. A screen that guessed would be asserting a sovereignty position on the customer's behalf |
| Retention periods, all 7 personal-data categories | **Not Configured** (7 of 7) | Software must not invent a legal retention period. The Retention screen states plainly that 7 of 7 categories have none, rather than letting an empty table read as coverage |

Both must stay visible and must stay honest. Neither blocks R1.4c-i acceptance;
both are owner decisions.

---

## R1.4b screen captures - what they prove

`doc/screenshots/R1.4b/` is historical evidence for the accepted R1.4b batch and
its README was corrected today for precision.

**Provenance, unchanged:** local verification captures, **not** production
screenshots, with email addresses masked to `person@example.test` in the browser
DOM before the shutter.

**The SEC-DEC-063 pair** - `audit-logs.png` and `audit-logs-auditor.png` - shows
the same Audit Log feature and substantially the same audit history, viewed
under two authorization contexts:

- System Administrator: `WHEN  WHAT  WHO  OUTCOME  FROM`
- Auditor: `WHEN  WHAT  WHO  OUTCOME`

**The FROM / network-origin column is structurally absent for the Auditor** -
not blanked, not redacted, not hidden with CSS. It is never selected, so it
never reaches the page.

The two captures are **not identical snapshots**: the badges read `All Events
27` and `All Events 28`. The extra row is the Auditor's own
`auth.login.succeeded` at 14:41:31 UTC, recorded between the two shutters.
Everything at 14:41:27 and earlier appears in both. The README previously said
"the same screen, the same data, two readers", which claimed more than the
images support; the column comparison carries SEC-DEC-063 and does not depend on
the row sets matching.

The differing navigation between the two roles is **separate, supporting
authorization evidence** - filter-not-fork navigation, a different control - and
is deliberately not folded into the SEC-DEC-063 conclusion.

---

## What R1.4c-i turned out to be

Seven release-blocking defects were found by the product owner in review after
the branch was first opened. None was caught by the test suite as it stood.
Each is now covered by a test that was **first proved to fail against the
unfixed code**.

| # | Defect | Decision |
|---|---|---|
| 1 | Separation of duties existed only in the permission tier | SEC-DEC-086 |
| 2 | Lifecycle methods wrote fields and audit events before validating the transition | SEC-DEC-088 |
| 3 | A same-state move silently replaced the recorded release evidence | SEC-DEC-088 |
| 4 | `AuditLogger::record()` swallows failure, so an action could commit with its evidence missing | SEC-DEC-089 |
| 5 | `retreat()` decided what a response may disclose and recorded it nowhere | SEC-DEC-090 |
| 6 | The named reviewer and the audit actor were not forced to be the same person | SEC-DEC-091 |
| 7 | **Authorization is not approval** - the second approver never authenticated or acted | SEC-DEC-093 |

Two working rules came out of it and are recorded in
`doc/execution/R1.4c-i-INVARIANTS.md`:

**The public mutator rule.** Every public governance mutator must independently
establish who is acting, whether that actor is authorized, whether any second
approver is authorized, whether the audit identity matches the decision
identity, and whether failure leaves the database unchanged. A controller or a
hidden screen is not accepted as the only enforcement layer.

**Identity, authority, participation, evidence.** For every approval control,
prove all four separately. A user id supplied by somebody else proves neither
participation nor consent.

Defects 2, 3, 4 and 6 shared one shape: **the suite proved that an exception was
thrown and never looked at the database row behind it.** Every negative test in
this batch now asserts the row.

### Disclosure widening is fail closed

D5 is enforced fail closed. A disclosure may be **narrowed** now. **Widening is
refused** until an independently authenticated second-approval workflow exists,
and the `$approver` argument was removed from `retreat()` rather than left in
place meaning something it could not mean.

**That workflow is not assigned to R1.4c-ii or to any batch.** Its scope needs
separate approval.

---

## Where things are

| What | Where |
|---|---|
| The twelve invariants and the two working rules | `doc/execution/R1.4c-i-INVARIANTS.md` |
| R1.4c plan, corrected counts, trigger SQL | `doc/execution/R1.4c-PLAN.md` |
| Decisions SEC-DEC-079 to SEC-DEC-093 | `doc/context/SECURITY_PRIVACY_DECISIONS.md` |
| R1.4b evidence and captures | `doc/screenshots/R1.4b/` |
| Release verification record | `doc/execution/ADMIN-FOUNDATION-RELEASE-1-VERIFICATION.md` |

`IMPLEMENTATION_STATUS.md` has **not** been advanced for R1.4c-i, and
`doc/PROJECT_MAP.html` has **not** been regenerated as accepted. Neither may
change until acceptance.

---

## The three migrations were run on production

Run by the product owner on 26 August against `smntqc2sadm_transdb`. The
post-migration verification returned **PASS on every row**: three tables
present, column counts 30 / 14 / 12, all five named indexes correct with the
right column counts and the unique key genuinely unique, foreign keys 8 / 2 / 5,
`audit_event_id` on RESTRICT, the three migrations recorded, **all three tables
empty**, and **no correction-note triggers installed** - which is correct,
because installing them is a separate approved step.

### What the migration evidence is, and what it is not

**The `php artisan migrate --force` console output was not captured.** The
product owner ran the command and observed the three migrations move from
`Pending` to `Ran`, but did not keep the terminal output.

That observation is recorded here as what it is - **an operator observation,
not captured command output.** It is not presented as a transcript.

The durable evidence is stronger than the console text would have been, because
it was read from the database rather than from a scrollback:

| Evidence | Source |
| --- | --- |
| The three migrations are recorded in the ledger | Post-check row `E1`, reading the `migrations` table - which is the same table `migrate:status` reads |
| The three tables exist with the right shape, indexes and keys | Post-check rows `B1` to `D4`, 14 rows, all PASS |
| The tables are empty | Post-check `F1` to `F3` |
| No triggers were installed | Post-check `G1` |

`CONFIRM-MIGRATION-LEDGER.sql` covers the one thing `E1` did not: whether any
OTHER migration is still pending across the whole application.

### The ledger is exact-set reconciled against approved main

`CONFIRM-MIGRATION-LEDGER.sql`, generated by
`tools/generate-migration-ledger-sql.php` from commit
`660d74c427a9e9dab3d611d39e69396cab5953e9`, was run on production:

| Check | Observed | Verdict | Detail |
| --- | --- | --- | --- |
| K1. every migration on approved main is in the ledger | `0 missing` | PASS | `none` |
| K2. the ledger holds nothing unexpected | `0 unexpected` | PASS | `none` |
| K3. the three R1.4c-i migrations, by exact name | `3 of 3` | PASS | all three, batch 7 |
| K4. all three landed in ONE batch | `1 distinct batch` | PASS | `batch 7` |
| K5. highest batch in the ledger | `7` | INFO | was 6 before this migration |

**Both directions are zero.** Everything the approved commit expects is
present, and the ledger holds nothing the approved commit does not contain.
That is a set reconciliation, not a count - the distinction matters, because
the version of this script it replaced asserted `COUNT(migrations) = 33` and
would have passed on 32 correct rows plus one obsolete row while a real
migration was still pending.

The batch number moved 6 to 7 exactly as predicted, and all three migrations
share that batch, so they landed in a single `migrate` run rather than being
applied piecemeal.

**The expected list was never typed.** It is read from
`git ls-tree <sha> database/migrations/` and emitted by a committed generator,
so it cannot drift from the repository. The generated file records the commit
it came from.

### The pre-check raised two STOP rows, and both were my script's fault

`CHECK-R1.4c-i-PRE.sql` reported `4 of 7 expected` migrations and `4 of 5
expected` tables for R1.4a and R1.4b. **Production was right both times and the
script was wrong both times.**

| Row | What the script asserted | Why it could never pass |
| --- | --- | --- |
| C1 | Seven migrations match its LIKE patterns | Only four filenames can ever match. `create_data_sovereignty_profiles` does not match `_create_sovereignty%`, and `add_structured_privacy_contact` does not match `_add_privacy_contact%`. The "7" was invented, not counted |
| C2 | A table named `sovereignty_profiles` | That table has never existed. The real name is `data_sovereignty_profiles` |

**This is the third time a verification script of mine has made a correct
production system look broken** - after the R1.4b `COUNT(*)` versus
`COUNT(DISTINCT INDEX_NAME)` error, and the two diagnostics that returned empty
because they carried a `DATABASE()` filter while no database was selected.

The pattern is the same each time: **an expectation written from memory rather
than read from the repository.** The corrected script lists migration and table
names in full instead of matching by pattern, so the expectation cannot drift
from the filenames again.

**`CONFIRM-R1.4a-R1.4b.sql` was run and closes both rows positively:**

| Check | Observed | Verdict |
| --- | --- | --- |
| H1. all six R1.4a + R1.4b migrations recorded | `6 of 6` | PASS |
| H2. all five R1.4a + R1.4b tables present | `5 of 5` | PASS |
| H3. which tables are present | `data_protection_profiles, data_sovereignty_profiles, ...` | INFO |
| H4. the audit index migration recorded | `1` | PASS |

The previous batches were fully migrated the whole time. Nothing on production
needed fixing, and nothing on production was changed to make these rows pass.

## Documentation cleanup carried forward

Found in review, recorded rather than fixed, because production code is not
edited as part of a migration step.

**`database/migrations/2026_08_29_090100_create_privacy_request_records_table.php`
carries a stale explanatory comment.** Lines 41 to 44 say "WIDENING NEEDS TWO
PEOPLE ... requires a second approver who is not the first". That was true of an
earlier draft and is no longer true of the code.

Current truth, per SEC-DEC-093:

| | |
| --- | --- |
| narrowing | supported |
| widening | **fail closed** |
| independently authenticated second-approval workflow | **not yet scoped** |

The comment is non-executable, does not affect the schema the migration
creates, and **does not block the migration**. It belongs with the
context-register backfill PR (acceptance step 4), not with a production change.

`reviewer_action` keeps its wider vocabulary - `kept`, `narrowed`, `widened` -
and that part of the comment stays accurate: the column can express all three
even though only `narrowed` can currently be written.

---

## The stopping-point rule

**Historical evidence and current operational state must never be conflated.**

A day note is immutable historical evidence. When the project state changes,
create a NEW current-state note rather than rewriting the old note into a state
that was not true on that day. `TaskCompleted-2026-08-24.md` correctly says R1.4c
had not been started, because on 24 August it had not been. That sentence stays.

**And for visual authorization evidence:**

- Describe exactly what the capture proves.
- Do not say "same data" unless the snapshots are actually identical.
- **Structural absence of a protected field or column is stronger evidence than
  an empty value**, and should be described as absence rather than as blanking.
