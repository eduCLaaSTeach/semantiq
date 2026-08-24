# DEC-005 - Repair the failed retention migration in place

Status: decided, pending the product owner's approval of the production steps
Date: 2026-08-24
Supersedes: nothing
Related: SEC-DEC-078, PDPA-03, release R1.4b

## What happened

The R1.4b production migration run stopped part way through:

```
2026_08_28_090000_create_sovereignty_exceptions_table  2s DONE
2026_08_28_090100_create_retention_policies_table      1s FAIL

SQLSTATE[42000]: 1059 Identifier name
'retention_policies_organisation_id_personal_data_category_id_unique'
is too long
```

Laravel derives an index name from the table and the columns. For this unique
key that produces a 67 character name. MySQL rejects any identifier over 64
characters. The migration therefore created the table and then failed adding
the key.

## Why no test caught it

The test suite runs on SQLite, which imposes no identifier length limit. The
name is legal there and illegal on MySQL, so the defect was invisible to the
full 536 test suite, to Pint, to CI, and to the local browser pass. The
production database is the only engine in the pipeline that enforces the rule,
and it is the one place a migration is not run before release.

This is the important part of the finding. It is not that one index name was
long. It is that the suite's database differs from the production database in a
way that can pass a release through every gate and still fail on the server.

## The state this left behind

MySQL does not roll DDL back. The failed run therefore left:

| Object | State |
| --- | --- |
| `sovereignty_exceptions` | created, and recorded in `migrations` |
| `retention_policies` | created, unique key missing, NOT recorded in `migrations` |
| the two `audit_events` indexes | never attempted |

A plain re-run cannot recover this. The migration is unrecorded, so Laravel
would run it again, and the `CREATE TABLE` would fail because the table is
already there.

## Decision

Three parts.

### 1. Fix the migration in place rather than adding a repair migration

`CLAUDE.md` says never to edit an already merged or applied migration, and to
add a new one instead. That rule is suspended for this one file, deliberately,
because the usual remedy cannot work here:

- the migration is NOT recorded as applied on the live database, so Laravel
  will run it again on the next `migrate`;
- a follow-up migration would therefore run only after this one had already
  failed a second time.

The file is edited to pass an explicit 38 character name,
`retention_policies_org_category_unique`. The constraint it creates is
identical; only the label changes.

This is safe precisely because the migration is unapplied. It would not be safe
for a migration that had succeeded anywhere, and the rule stands for those.

### 2. Drop the half-created table before re-running

`retention_policies` is dropped so the corrected migration can create it
cleanly. This is a destructive statement, and it is acceptable here only
because the table is provably empty and provably new:

- it was created minutes ago by the run that failed;
- no application code has ever been able to write to it, because the screens
  refuse while `GovernanceStorage` reports the table missing;
- `CHECK-R1.4b-RECOVERY.sql` gates the drop on a live `COUNT(*) = 0` and
  returns STOP rather than PASS on any other number.

If that count is not zero, this decision does not authorise the drop and the
recovery stops for re-diagnosis.

### 3. Add a permanent guard

`tests/Feature/Schema/MigrationIdentifierLengthTest` computes every index,
unique and foreign key name that every migration would generate, and fails
above 64 characters. It reads the migration source rather than a live schema,
so it returns the same answer on SQLite as it would on MySQL.

It was verified against the real defect: reverted to the original code, it
fails and names the exact 67 character identifier.

## Alternatives rejected

**Shorten the column names.** `personal_data_category_id` is the correct name
and appears in the foreign key, the model and the documentation. Renaming a
column to satisfy an index label would be the wrong object changing.

**Set a shorter table prefix or rely on Laravel truncating.** Laravel does not
truncate; it hands the name to the driver. A prefix change would affect every
table in the application to fix one key.

**Run the suite against MySQL in CI.** Worth considering separately, and it
would catch more than this. It is a larger change to the pipeline than this
incident justifies on its own, and the static guard catches this specific class
without it. Recorded here as an open question rather than adopted.

## Consequences

- One migration file is edited after merge. That is a recorded exception, not a
  new practice.
- The recovery needs a destructive `DROP TABLE` on the live database, gated on
  a proven-empty check and on the product owner's explicit approval.
- The suite gains a guard that would have caught this before the release
  shipped.
- The gap between the test database and the production database remains real
  for anything the guard does not cover. Noted as an open risk.
