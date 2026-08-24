# Day work note - 24 August 2026

Written at the pause point so the next session starts from fact rather than
memory. Read STOPPING POINT and STARTING POINT first; everything after is
detail.

---

## STOPPING POINT

**Gate 4 batches R1.4a and R1.4b are complete, merged, live in production,
migrated, and formally accepted. R1.4c has not been started.**

`main` is at `c5974ab8568fd08ec71029086e7e2675be4ca245`. CI and Deploy both
green. Production is serving and the live database is fully migrated - there is
no pending migration on the server.

| Item | State |
|---|---|
| R1.1 Platform Foundation | CONFIRMED |
| R1.2 Identity and Access | CONFIRMED |
| R1.3 Security Foundation | CONFIRMED |
| R1.4a profiles and privacy contact | **ACCEPTED** 24 August 2026 |
| R1.4b auditor capability, exceptions, retention, audit log | **ACCEPTED** 24 August 2026 |
| R1.4c privacy requests, breach register, governance overview | **NOT STARTED** |
| **R1.4 Gate 4 overall** | **IN PROGRESS** |
| R1.5 to R1.7 | LOCKED |

Nothing is half-finished. No branch is left open, no PR unmerged, no migration
pending. The working branch `claude/claas2saas-semantiq-frontend-575jkv` is
fully merged into `main`.

## STARTING POINT

**Next action: restate the exact approved scope of R1.4c and the unresolved
items carried into it. Present it and wait. Do not write code first.**

The product owner asked for exactly this, in these words:

```
Tomorrow, when I return, first restate the exact approved scope of R1.4c
and the unresolved items carried into it before doing any implementation.
```

Before drafting that restatement, read in this order:

1. `CLAUDE.md`
2. `IMPLEMENTATION_STATUS.md` - the R1.4 row
3. `doc/execution/R1.4-GATE-4-DATA-PROTECTION-PDPA-PLAN.md` - the approved plan
4. `doc/execution/ADMIN-FOUNDATION-RELEASE-1-VERIFICATION.md` sections 14 and 15
5. `doc/execution/decisions/DEC-002-pdpa-applies.md`, `DEC-004`, `DEC-005`
6. `doc/context/SECURITY_PRIVACY_DECISIONS.md` - SEC-DEC-062 to SEC-DEC-078
7. `doc/MENU_STRUCTURE.md` and `doc/ROLE_MODEL.md`
8. `.claude/reference-template/ui-and-ux-layout-template-shared.md`

### R1.4c approved scope, as recorded in the gate 4 plan

Three features, plus the screen that can only be built once they exist:

| ID | Feature | Note |
|---|---|---|
| PDPA-01 | Privacy Requests | Access, correction and withdrawal requests |
| PDPA-02 | Breach Register | Incident recording, assessment, PDPC notification decision |
| D3 | Governance Overview | Built LAST, because it summarises what the other batches created |

Four migrations remain from the plan's list of eleven:

```text
6  create_privacy_requests_table
7  create_privacy_request_records_table
8  create_privacy_correction_notes_table
9  create_breach_incidents_table
```

Migrations 1 to 5, 10 and 11 are done and live.

### Unresolved items carried into R1.4c

These are the things a restatement must put in front of the product owner. Do
not assume any of them is settled.

**1. SEC-DEC-066 gates gate 4 acceptance, and it is not discharged.**
`privacy_correction_notes` must be append-only, enforced by `BEFORE UPDATE` and
`BEFORE DELETE` database triggers, and the decision states plainly that **gate 4
is not accepted until those triggers exist on production and the proof is
recorded**. Triggers stay out of migrations by SEC-DEC-037's reasoning, so they
are a separately controlled production step. R1.4c is where this lands.

**2. The `audit_events` DELETE trigger has never been proved on production.**
Recorded in earlier verification. The product owner has explicitly declined to
run the DELETE-trigger proof, and that instruction stands. Do not run it.
Section 15 of the verification document now records that both triggers survived
a failed migration and a manual recovery, which is evidence of existence but
still not a proof that the DELETE trigger fires.

**3. AI processing geography is `Not determined` on the live sovereignty
profile.** Visible on the production Sovereignty Exceptions screen. Correct
behaviour - SemantIQ will not guess a sovereignty position - but it is an open
question for the product owner, and cross-geo AI stays OFF until answered.

**4. No retention period is configured for any of the 7 categories.** Also
correct: those values are compliance-owned and SemantIQ never fills them. The
screen says `Not Configured` and warns that 7 of 7 have no period. Worth raising
because a filled-in retention table is what people assume protection looks like.

**5. Running CI against MySQL is an open question, deliberately not adopted.**
Recorded in DEC-005. See the incident below for why it matters.

**6. An Auditor on this deployment must be a federated account.** SEC-DEC-054
makes the credential form refuse local accounts below System Administrator. Not
a defect, but it decides how an Auditor is onboarded.

### Standing instructions still in force

Do not, without a fresh explicit approval:

```text
start R1.4c implementation before restating its scope
switch SESSION_DRIVER to database
enable HSTS
run the DELETE-trigger proof
change production .env
change queue, mail, storage or the deploy workflow
run a production migration
run any rollback
```

---

## What happened today

### 1. R1.4a accepted

Verified on production by the product owner. Personal / Sensitive Data showed
all 7 categories. Evidence in verification section 14.

### 2. Data dictionary and entity relationship model built

`doc/DATA_DICTIONARY.md` and `doc/ENTITY_RELATIONSHIP.md`, generated by
`tools/generate-data-docs.php`, which parses the migrations for types AND reads
the live schema for existence and keys, and **refuses to write if the two
disagree**. 28 tables, 327 columns, 69 foreign keys. Guarded by
`tests/Feature/Documentation/DataDocsTest.php`.

### 3. R1.4b built, shipped, broken in production, recovered, and accepted

Built in the product owner's required order: Auditor capability first because it
touches gate 2 authorization core, then exceptions, retention, audit log.

PR #26 merged as `f920f41`. CI and deploy green.

**Then the production migration failed.** Full incident in verification section
15. Short version:

- Laravel generated a 67 character index name; MySQL's limit is 64 (error 1059).
- The suite runs on SQLite, which has **no identifier length limit**, so the
  name is legal in every test and illegal on the production engine.
- It cleared 536 tests, Pint, CI, a green deploy and a browser pass, and was
  still undeployable.
- MySQL does not roll DDL back, so it failed **destructively**: table created,
  key missing, migration unrecorded. A plain re-run could not fix it.

Recovery, each step separately approved: a read-only pre-check confirmed the
state on all 9 points and proved the table empty, then PR #27 shipped the fix,
then `DROP TABLE retention_policies`, then re-run. Two DONE lines, no rollback.

**The append-only guarantee held throughout.** Both triggers survived and
`audit_events` stayed at 19 rows before and after.

PR #27 also added `MigrationIdentifierLengthTest`, which computes every index,
unique and foreign key name from the migration SOURCE and fails above 64
characters - so it gives the MySQL answer while running on SQLite. Verified
against the real defect by reverting the fix and confirming it names the exact
67 character identifier.

### 4. R1.4b accepted, with production evidence

```text
Retention:              7 categories, all Not Configured, no false claim
Sovereignty Exceptions: approved position shown, no exception in force
Audit Logs:             live, five presets
Audit integrity:        2 triggers present, 19 rows before and after
Migration recovery:     repaired via PR #27, no rollback, permanent guard added
```

### 5. Screen captures

`doc/screenshots/R1.4b/` with a README explaining provenance. Captured from a
local server running the accepted code, not from production - this session
cannot sign in to production. Every email address is masked to
`person@example.test` in the browser DOM before the shutter, because `CLAUDE.md`
forbids committing personal data in a screenshot.

The pair worth looking at is `audit-logs.png` and `audit-logs-auditor.png`: the
same screen and the same data, where the System Administrator's table carries a
`FROM` column and the Auditor's does not have that column at all.

---

## Lessons this day produced

**1. The test database is not the production database, and the difference can
pass a release through every gate.** SEC-DEC-078. The static guard closes this
specific class. Running CI against MySQL would close more and is recorded as an
open question in DEC-005, not adopted.

**2. A verification script is code, and an untested check is a liability.**
This is the SECOND consecutive batch where one of my verification scripts made a
working system look broken. Section 14.11 recorded the first and set the rule
"compute a verdict, never print numbers to compare". That rule was followed this
time and was **not enough**, because the verdict itself was computed wrongly:
the script counted rows in `information_schema.STATISTICS`, which holds one row
per COLUMN, so a two-column key read as two indexes and reported FAIL on a
correct schema. **Run every verification script against a known-good system
before sending it to the product owner.**

**3. A destructive recovery needs a gate that can say no.** The pre-check
returned STOP rather than PASS on any row count other than zero, so a non-empty
table would have halted the drop instead of being destroyed on an assumption.
That gate is the reason the drop was safe to approve.

---

## Where things live

| What | Where |
|---|---|
| Implementation status | `IMPLEMENTATION_STATUS.md` |
| Gate 4 plan | `doc/execution/R1.4-GATE-4-DATA-PROTECTION-PDPA-PLAN.md` |
| Verification, R1.4a | `doc/execution/ADMIN-FOUNDATION-RELEASE-1-VERIFICATION.md` section 14 |
| Verification, R1.4b | same file, section 15 |
| Recovery pre-check | `doc/execution/CHECK-R1.4b-RECOVERY.sql` |
| Post-migration check | `doc/execution/CHECK-R1.4b.sql` |
| Decisions | `doc/execution/decisions/DEC-001` to `DEC-005` |
| Security decisions | `doc/context/SECURITY_PRIVACY_DECISIONS.md`, to SEC-DEC-078 |
| Data dictionary | `doc/DATA_DICTIONARY.md` |
| Entity relationships | `doc/ENTITY_RELATIONSHIP.md` |
| Screen captures | `doc/screenshots/R1.4b/` |
| Previous day note | `doc/TaskCompleted-2026-08-23.md` |
