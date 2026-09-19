# Audit tables — deployment and rollback safeguard

**Read this before rolling anything back on production.**

P1-08 creates two tables: `audit_events` and `audit_chain_head`. Together they
are the **only** durable record of who did what on this deployment. There is no
export (D-110), no second copy, and no retention job that could have moved them
somewhere else (D-105).

## Deployment never drops them

`.github/workflows/deploy.yml` runs `php artisan migrate --force` and **never**
`migrate:rollback`. That is the rule, not an observation — a deployment that
could drop the Audit tables would be a deployment that can erase the evidence of
its own predecessor.

## `down()` exists for CI and development only

The migration's `down()` drops both tables, so that
`migrate → rollback → migrate` can be proven against MySQL in CI, exactly as
every other unit proves it.

**On production it destroys every audit row gathered since the day Audit was
installed, and nothing can recover them from the application.**

## A production rollback that would reach the Audit tables

Three conditions, all of them, before anybody runs it:

1. **Explicit operator approval**, recorded outside the system being rolled
   back.
2. **A database-level backup or snapshot taken first**, and verified to restore.
3. A note of what is being rolled back and why, so the gap in the evidence has
   an explanation beside it.

**That backup is an operational safeguard, not a product feature.** It is taken
by an operator with database access. It is not reachable from the application,
no screen offers it, and it is **not** the Audit export that D-110 rules out of
Release 1.

## If evidence is ever lost

Say so. The Audit screen reports a broken chain when a row has been altered or
removed, and an unexplained warning is worse than a recorded one. A gap with a
note beside it is still evidence; a gap nobody will admit to is not.
