# P1-08 — Audit: mutation record

## Gate C correction — D-111 atomicity

**Nine further mutations, nine killed — two only after the guard itself was
given something pointing at it.**

| # | Mutation | Result |
| --- | --- | --- |
| M-B1 | Remove `DB::transaction` from `OrganisationService::updateProfile` | **KILLED** — static guard + rollback case |
| M-B2 | Remove it from `UserDirectoryService::reactivate` | **KILLED** |
| M-B3 | Remove it from `StructureService::setStatus` | **KILLED** |
| M-B4 | Replace the runtime atomicity check with `if (false)` | **SURVIVED, then KILLED** |
| M-B5 | Set the test baseline to a literal `0` | **SURVIVED, then KILLED** |
| M-B6 | Remove it from `GroupService::deactivate` | **KILLED** |
| M-B7 | Weaken the check to `transactionLevel() < 0` | **KILLED** |
| M-B8 | Apply the check to **every** outcome class | **KILLED** — refusals and sign-out both break |
| M-B9 | Unwrap `ReviewDecisionService::supersede` | **KILLED** by the static guard alone |

**M-B4 and M-B5 are the ones worth reading.** The runtime guard was present,
ran on every emitter, and had *nothing asserting it worked*. Worse, M-B5 shows
how it could have been silently vacuous: every test runs inside
`RefreshDatabase`'s transaction, so a baseline of 0 means the **harness**
satisfies the check on every emitter's behalf, and code that never opens a
transaction of its own sails through. A guard that is present, runs, and proves
nothing is the exact failure CLAUDE.md §2 describes.

**M-B9 is the one only the static guard caught.** `supersede()` is public and
no test called it outside a transaction, so the runtime guard never saw it.
That is why there are two guards and not one.

---

**Nineteen mutations. Nineteen killed** — one of them only after the test meant
to catch it was found to be measuring something else.

**And four cases were green locally and red on MySQL.** They induced a storage
failure by dropping the table; MySQL commits the open transaction implicitly on
DDL, so the drop ended `RefreshDatabase`'s transaction while Laravel believed it
was still inside one. Nothing on SQLite could have shown that. They now delete
the chain-head row instead — ordinary DML, identical on both engines. See
`P1-08-AUDIT-VERIFICATION.md` §6.1.

Full detail in `P1-08-AUDIT-VERIFICATION.md` §6. Three are worth reading on
their own.

## M-A5 — the guard that reported safety it was not measuring

Removing the verifier's **predecessor comparison** left
`test_a_removed_row_is_reported` passing, because the **head/last-row**
comparison caught the deletion instead. Two different guards, one test, and the
test could not tell which had fired.

It was also the wrong scenario. A forger with database access does not delete a
row: they **edit a field and recompute that row's own hash**, which takes
seconds. Only the link to the *next* row survives that, because they would have
to recompute every later row as well.

`test_a_forged_row_with_a_recomputed_hash_is_reported` exercises exactly that,
and the deletion case now asserts the **specific finding** rather than accepting
any of three.

## M-A2 — the actor mutation, and why one area was not enough

Reading the actor from `user_id` for access events kills **three** cases,
including A5. A5 covers login, user lifecycle, organisation administration,
role administration and access reviews **together**, because user lifecycle and
role administration put opposite things in `user_id` — so a case covering one
area would pass under any single global convention. That is how the original
DESIGN error survived review.

## M-A10 — failing closed in the wrong place

Applying fail-closed to **every** outcome class kills two cases at once: a
refusal whose own evidence fails would raise out of a path whose whole job was
to refuse, and somebody could not sign out while the evidence store was
unavailable. D-111 governs whether a **success** may complete unevidenced. It
never said a refusal should be erased for lack of a place to put it.

## Not observable locally

**M-A4 — dropping `lockForUpdate()` from the chain append — SURVIVES on
SQLite**, which has no `SELECT ... FOR UPDATE`: the locking read compiles away
entirely, so a single-threaded pass would report a lock the writer does not
hold. It runs against MySQL 8.4 in CI, in a step that **fails if the case
skips**. The same honest limitation P1-07 recorded for M-R12.
