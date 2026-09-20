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

## M-P10-4 — the `where()` constraint removed from the grant route — **KILLED**

**The mutation.** `Route::get('{grant}', …)` returned to its unconstrained
production form.

**Killed by two cases:**

```
test_the_grant_route_is_constrained_to_the_token_shape
test_every_static_route_resolves_with_the_declaration_order_reversed
```

**The forward case still passed.** Resolving each URI against the live router
succeeds on the unconstrained route set too, because the live set happens to be
in the lucky order. **Re-registering the collection backwards removes the luck
and leaves only the constraint**, which is the property actually being claimed.

The guard also asserts the constraint **accepts** twenty real `Str::random(64)`
tokens. A pattern that rejected genuine grants would pass a
"no static route matches" test while breaking First-Run for everyone — a guard
satisfied by being wrong in the other direction.

---

## M-P10-5 — the fresh-installation authority move deleted — **KILLED**

**The mutation.** The `DB::transaction` block removed from
`IdentityCutover::commitFreshInstallation()`, so a passing verification leaves
`identity_source` at `env` — the defect Correction 4 describes.

**Killed by:**

```
test_c4_a_verified_fresh_installation_makes_microsoft_auth_read_the_store
test_c4_no_cutover_command_is_required
```

**C4 configures no identity `.env` at all.** A case that left the harness's
identity configuration in place could pass by accidentally reading the
environment — which is the behaviour being ruled out — and would keep passing
with the fix removed.

**C4 asserts on `app(IdentityProvider::class)->isConfigured()`**, resolved
through the container exactly as `/auth/microsoft` resolves it. Asserting only
on the source would have left the container bindings unproven, and they are the
half that had to change.

---

## M-P10-6 — revision binding replaced by `Cache::forget()` alone — **KILLED by H5 only**

**The mutation.** The revision stripped from `resultKey()` and `probeKey()`,
leaving the housekeeping `forget()` as the sole invalidation mechanism.

**Result, exactly as the DESIGN predicted:**

```
SURVIVED: test_h4_changing_the_tenant_makes_the_stored_identity_result_unreadable
KILLED:   test_h4_the_revision_is_what_changes_the_key
KILLED:   test_h5_invalidation_survives_a_cache_that_ignores_forget
```

**H4's behavioural case survives a `forget()`-only implementation**, because
`forget()` works perfectly against an ordinary cache. That is not a weakness in
H4 — it is the reason H5 exists, and the DESIGN said so before the code was
written: *"A `forget()`-only implementation passes H4 and fails H5."*

**H5 required a new cache behaviour to be honest.** `BrokenCacheStore` gained
`IGNORES_FORGET`: it stores and returns values perfectly and simply never
removes one, reporting success — **what a file cache does when the unlink
fails**, which is the cache production actually runs. The case asserts the old
entry is *still physically present and readable* after the change, and that
`storedReport()` says **Not checked** anyway, because nobody asks for that key
any more.

**One assertion in the first draft of H5 was vacuous** and is recorded here
rather than quietly fixed: `assertTrue($store->forgetIgnored ?? true)` referred
to a property that does not exist, so `?? true` made it pass unconditionally.
It was replaced by an assertion on the real cache contents.

---

## M-P10-7 — the bootstrap guard trusts the session key alone — **KILLED**

**The mutation.** `RequireBootstrapSession` reduced to `if (! is_int($id))`,
dropping the live `isOpen()` re-read — the "check the state at sign-in only"
shortcut D-166 exists to forbid.

**Killed by:**

```
test_b3_and_b4_an_established_session_fails_on_its_next_request
test_b4_the_session_key_survives_and_grants_nothing
```

**B4 asserts the session key is STILL PRESENT after the refusal**, which is the
whole point. Production runs **file** sessions, so a shutdown that depended on
deleting session state would be defeated by a file that happens to remain. The
guarantee has to be that the guard *re-reads the world*, not that somebody
removed something.

---

## M-P10-8 — `BootstrapCloser` knows it is unprotected and proceeds — **KILLED**

**The mutation.** The `throw` removed from the `DB::transactionLevel() === 0`
check, leaving the check itself in place.

**Killed by:** `AuditAtomicityTest::test_every_state_changing_emitter_is_inside_a_transaction`,
which re-flagged `BootstrapCloser.php::close`.

**Why the mutation exists.** `BootstrapCloser` opens no transaction — it
*requires its caller's*, which is stricter, and the only correct choice here:
opening its own would create exactly the window it exists to remove. The
atomicity guard was taught that **mechanism** rather than given an exemption,
and this mutation is the proof that the teaching is conditional: **a level check
alone does not qualify; the throw is what makes it a guard rather than a
comment.**

---

## M-P10-9 — an invented CSS custom property — **KILLED**

**The mutation.** `var(--focus)` reintroduced on the setup field focus ring.

**Killed by:** `EveryCssTokenIsDeclaredTest`.

**This guard was written because the first draft of the setup stylesheet used
FIVE tokens that do not exist** — `--page`, `--hover`, `--selected`, `--focus`,
`--danger` — every one of which looks exactly like a real one. CSS drops an
undefined custom property **silently**: no build failure, no test failure, no
render failure. What it produces is a focus ring that never appears, a dead
hover state, an error message in the body colour and a panel with no
background. Every one of those is on the professional-polish gate, and none of
them fails anything until somebody looks.

**Its first run found three PRE-EXISTING usages** of `var(--edge)` in P1-05 and
P1-08 code. An undefined token invalidates the whole `border` declaration, so
the Audit filter controls, the Audit rows and the access-decision path panel
have been rendering **with no border at all**. Fixed in passing and marked as
carried-in.

**The guard strips comments first.** The notes explaining why a token was wrong
necessarily name it, so a guard that read them would be failed by its own
explanation — and the fix would be to delete the explanation, which is the
wrong lesson.

---

## M-P10-10 — a re-encryption method added to the secret store — **KILLED**

**The mutation.** `IntegrationSecretStore::reEncrypt()` — the tooling the
Product Owner explicitly said not to build in P1-10.

**Killed by:** `NoKeyRotationToolingTest::test_no_rotation_or_re_encryption_tooling_was_built`.

**Why this guard exists rather than a sentence in a document.** *"We recorded a
key version"* reads like a mitigation, and the next person to touch this will
be tempted to treat it as one — to write a rotation command *"since the version
is already there"*. `key_version` records **which** key encrypted a row; it
cannot decrypt a row whose key is gone, and a version column on an
undecryptable ciphertext tells you accurately which key you no longer have.

The file also asserts that **nothing branches on `key_version`**: a decrypt
path that did would be promising a capability — reading an old key — that this
deployment does not have.

---

## Flaws found in the TESTS, recorded rather than quietly fixed

A mutation record that lists only code defects implies the tests were right
first time. These were not.

| Case | The flaw |
| --- | --- |
| **B11** | Satisfied by the hash replacement while claiming to guard the openness predicate. See M-P10-1 — the surviving mutation |
| **H5** (first draft) | `assertTrue($store->forgetIgnored ?? true)` referred to a property that does not exist, so `?? true` made it pass unconditionally. Replaced by an assertion on the real cache contents, which required a new `IGNORES_FORGET` cache behaviour to be honest |
| **`FirstRunRoutesDoNotCollide`** | Keyed the expectation map on URI alone, so `POST first-run/sign-in` overwrote the `GET`, and the case asserted a GET resolves to the POST's route name. It failed for a reason with nothing to do with route collisions — which would have taught the next person to loosen the assertion rather than fix the key |
| **`test_the_client_secret_is_read_in_exactly_one_place`** | Scanned one directory. The read moved out of it, so left alone the guard would have found **zero** readers and passed |
| **The browser sweep** | Checked `page.url().includes(path)`, and `/first-run` is a prefix of every other path — so the sign-in page counted as the overview. It also treated the sandbox's blocked Google Fonts request as a product console error, and used a SHORT nominated address, which fits at 390px and hides the overflow a real one causes |

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
| **P1-08's atomicity guard, a second time** | `integration.configuration.changed` was recorded in the controller, one line after the write returned — outside the transaction, so a failed audit write could not roll the configuration change back. Nothing about the controller *looked* wrong, which is what the static guard is for |
| **The browser sweep at 390px** | The nominated address sits in a heading — `Send this link to the.new.administrator@example.test` — and is one unbreakable 33-character word. It made the main column **402px wide inside a 390px viewport**. A short address fits and hides it entirely |
| **The browser sweep, implementation terms** | The AI provider field was a text input whose LABEL carried the permitted values: *"AI service (azure_openai or openai)"*. A raw enum value on a customer's screen, and the field had the wrong control — a choice typed as free text also lets somebody enter "Azure OpenAI" and learn nothing until the connection test says the provider cannot be checked. Both it and the mail security field are now selects |
| **Reading the rendered screens** | `.org-action-quiet` is a MODIFIER — every other use in the codebase pairs it with `.org-action`. Used alone, "Sign out" and "Test connection" rendered as bare browser buttons. The setup inputs also deviated from `.org-form input` in four ways, including a canvas background on a white card that reads as *disabled* |
| **Reading the rendered screens** | The First-Run overview rendered the step rail **and** a list of the same four integrations with the same status — two identical lists on one screen, each claiming to be the way to navigate. And the blocked nomination state named the blocker without offering any way to act on it, at the last step of a setup flow, which is where somebody gives up |
