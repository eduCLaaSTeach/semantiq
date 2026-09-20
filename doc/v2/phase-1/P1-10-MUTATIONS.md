# P1-10 — mutations run, and what each one proved

CLAUDE.md §2: *"A test that cannot fail is worse than no test: it reports safety
that does not exist. For every guard, break it deliberately and observe the test
fail."*

**One mutation in this record SURVIVED on the first run.** It is kept, with what
it exposed and what was added to kill it, because the surviving mutation is the
most useful entry in a file like this — a list in which everything was killed
first time is a list nobody should quite believe.

| | |
| --- | --- |
| Unit | **P1-10 — Platform Integrations & Setup** |
| DESIGN | merge `a7aef47` — the six Product Owner corrections applied |
| Status | **IN PROGRESS** — this file is appended to as each guard is built |

---

## M-P10-1 — bootstrap openness re-derived from the predicate alone — **SURVIVED**

**The mutation.** `BootstrapAccess::isOpen()` reduced to the first draft's rule:

```php
// was: UNCONFIGURED ∧ (not closed ∨ recovery context)
return BootstrapAdministrator::current() !== null;
```

This is the exact defect Correction 3 exists to close, and the Product Owner
named it as the one B11 must fail on.

**Result: ALL ELEVEN CASES PASSED.** B11 did not fail.

**Why.** `BootstrapCloser` writes *two* things — `disabled_at` **and** an
unusable password hash — and the unusable hash refuses on its own. So B11 was
being satisfied by the hash replacement while claiming to guard the openness
predicate. It is the precise failure CLAUDE.md §2 describes: *"a test that
passes for a reason unrelated to what it claims to check."*

**What was added.** `B11b` puts a **closed principal** and a **working bcrypt
hash** together — the state an operator `UPDATE`, a half-finished recovery path
or a future *"let them set a new password"* convenience would produce — and
asserts the refusal anyway. Re-running M-P10-1 then killed it:

```
KILLED: test_b11b_a_closed_principal_is_refused_even_with_a_working_password
KILLED: test_b11b_the_recovery_context_is_the_only_thing_that_opens_a_closed_principal
```

**The two locks are now guarded separately**, which is what "defence in depth"
has to mean if it is to mean anything: each layer has a test that fails when
only that layer is removed.

---

## M-P10-2 — the first draft's closing write, removed entirely — **KILLED**

**The mutation.** `BootstrapCloser::close()` with its two writes deleted,
leaving the draft's stated behaviour: *"nothing is disabled by a write."*

**Killed by three cases:**

```
test_b11_the_old_password_stays_refused_when_every_administrator_is_deactivated
test_b11_the_old_hash_does_not_survive_the_closure
test_b14_recovery_closes_again_when_an_administrator_is_restored
```

**B11 is the load-bearing one.** It establishes a permanent System
Administrator, deactivates every one of them, asserts the computed predicate
really has returned to UNCONFIGURED — so the case is exercising what it claims
— and then supplies **the correct original password** and requires a refusal.

---

## M-P10-3 — `bootstrap.signin.succeeded` back to `BestEffort` — **KILLED**

**The mutation.** The first draft's outcome class restored in `EventCatalogue`.

**Killed by:**

```
test_b15_a_bootstrap_sign_in_that_cannot_be_evidenced_issues_no_session
test_b15_an_unevidenced_attempt_leaves_no_trace_of_success
```

**B15 supplies the CORRECT credential**, deliberately. A case that also got the
password wrong would be satisfied by any refusal — including the ordinary one —
and would pass just as happily with `BestEffort` restored.

**The assertion is the session, not the exception.** Session state is not
transactional: a rollback cannot take a session key back, so only *"no session
exists"* catches an ordering defect. P1-08's own sign-in case is written the
same way, and for the same reason.

**The evidence store is broken by deleting the chain head row, not by dropping a
table.** MySQL commits the open transaction implicitly on DDL, so a `Schema::drop()`
would end `RefreshDatabase`'s wrapping transaction while Laravel believed it was
still inside one — four P1-08 cases were once green on SQLite and red on the
engine production uses.

---

## Defects these guards found in the implementation, not in the design

Recorded because they are the return on writing the mutation before the code is
finished, and because two of them were found by guards **another unit** wrote.

| Found by | The defect |
| --- | --- |
| **B14** | `BootstrapCloser` treated `disabled_at` alone as "already closed". During a recovery episode `disabled_at` **is** set — deliberately, because redemption opens the *session* rather than the principal — so the second close was a no-op and the recovery password survived a restored administrator. Recovery would have been a mode the deployment stayed in. "Already closed" now means **both** halves: the flag *and* an unusable hash |
| **P1-08's atomicity guard** | `BootstrapRecovery::issue()` wrote the token row and recorded `bootstrap.recovery.issued` outside any transaction. A token that exists with no record of who issued it is a credential that reopens local password login with nothing to audit |
| **The bcrypt driver** | `Hash::check()` against the unusable sentinel **raises** rather than returning false, so a closed principal would have produced a 500 with a stack trace instead of the generic refusal every other case gets — an error page appearing for exactly one reason is a disclosure. The check is now *"does the hasher recognise this value at all"*, which also covers a blank column or a half-written row |
| **The full suite** | `/up` returned 500 instead of 503 with the database down. `HealthInspector` wrapped the identity check in a `try/catch` that looked complete and was not: **constructor injection built the check, the provider and the discovery client before the method body ran**, so a failure while constructing them escaped the guard written to contain it |
| **The full suite** | `PlatformSetting::current()` used `firstOrCreate`, so every identity resolution — including the one behind the unauthenticated entry page — was a **write** |
| **`test_the_client_secret_is_read_in_exactly_one_place`** | The guard scanned `app/Modules/Identity` only. P1-10 moved the read to `Platform\Setup`, so left alone it would have found **zero** readers and passed — a guard reporting a safety that had simply moved out of its field of view |
