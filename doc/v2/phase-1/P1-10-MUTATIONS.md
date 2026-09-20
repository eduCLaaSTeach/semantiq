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

## M-P10-11 and M-P10-12 — the two guards the DESIGN names — **both KILLED**

**These two existed only in comments until the Gate C proof list was walked
against the code.** Three separate code comments referenced
`NoDirectIdentityConfigRead` and `EnvIsNotIdentityAuthorityAfterCutover` as
though they were tests. **They were not.** A comment asserting a coverage that
does not exist is worse than no comment, because the next reader believes it.

**M-P10-11 — a direct identity read restored, in the ARRAY shape.**

```php
foreach (['MICROSOFT_TENANT_ID' => 'identity.microsoft.tenant_id'] as $name => $key) {
    if ((string) config($key) === '') { … }
}
```

Chosen deliberately: **this is the shape a call-shape guard misses**, and four
of the eleven original reads looked exactly like it.

```
KILLED: test_only_the_configuration_source_names_the_identity_keys
```

**M-P10-12 — the "make it more robust" fallback.**

```php
tenantId: $this->string($stored, 'tenant_id') ?: (string) config('identity.microsoft.tenant_id'),
```

```
KILLED: test_the_store_branch_reads_no_environment
```

**This is the edit somebody makes in good faith.** It looks like defensiveness
and it is the two-authorities defect: the store is edited, the old `.env` value
keeps working, everything looks correct, and nobody finds out until the `.env`
secret expires — at which point the deployment breaks for a reason that has not
been true for months.

**Writing the guard also found a flaw in the guard.** The first version compared
the iterator's path against reflection's with `===`; one is built from
`__DIR__.'/../..'` and the other is absolute, so the comparison excluded
nothing and the guard failed against the one file it exists to permit. Both
sides now go through `realpath()`.

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

---

# Gate C corrections — the second round of mutations

The Product Owner held the merge of PR #131 and named four implementation gaps
against the approved D-148 / D-159 / D-165. Nineteen mutations were run against
the corrections. **Twelve were killed on the first attempt. Three survived, and
each survivor changed something** — one exposed a badly chosen mutation, one
exposed a weak test, and one showed the *mutant was better than the original*.

| | |
| --- | --- |
| Killed first time | 12 |
| Survived, then killed after strengthening the test | 2 |
| Survived and found to be EQUIVALENT | 1 (recorded, with what it taught) |
| Re-run mutations | 4 |

---

## Correction 1 — D-159 step-up and Bootstrap reconfirmation

| ID | Mutation | Result |
| --- | --- | --- |
| **M-C1-1** | `update()` passes the whole `$secrets` array to the writer instead of `$partition['establish']` — a replacement saves directly, with no step-up | **KILLED** (2 of 7) |
| **M-C1-2** | An extra `'leaked' => <plaintext>` key added to the step-up target array | ***SURVIVED* — and correctly.** See below |
| **M-C1-2b** | The plaintext concatenated into `subject_intent`, a **persisted** column every listing reads | **KILLED** (1 of 7) |
| **M-C1-3** | The First-Run branch's privileged-change condition replaced with `if (false)` — a replacement during setup asks for no password | **KILLED** (3 of 10) |
| **M-C1-4** | `nominate()` short-circuits its reconfirmation with `true \|\|` | **KILLED** (2 of 10) |

### M-C1-2 — the mutation that was wrong, not the test

The mutation added a plaintext credential to the array handed to
`StepUpService::begin()`. Nothing failed.

**That is correct behaviour, and the mutation was the flawed thing.** `begin()`
does not persist the array it is given — it reads *named keys* out of it and
writes those to explicit columns. An unknown key is silently dropped and never
reaches the database, the log or the response. The P1-05 seam is a **allowlist
by construction**, which is precisely the property that makes it safe to pass a
target array across a module boundary at all.

So the mutation could not leak anything. **M-C1-2b** was written to leak through
the channel that *does* persist — `subject_intent` — and was killed immediately.
Recorded rather than quietly replaced, because "the guard held" and "the
mutation was incapable of breaking it" are different claims and only one of them
is evidence.

---

## Correction 2 — D-165 Bootstrap session policy

| ID | Mutation | Result |
| --- | --- | --- |
| **M-C2-1** | `IDLE_MINUTES` raised to 100000 — the 30-minute idle limit removed | **KILLED** (3 of 11) |
| **M-C2-2** | `ABSOLUTE_HOURS` raised to 100000 — the 4-hour absolute limit removed | **KILLED** (2 of 11) |
| **M-C2-3** | `touch()` called *before* the expiry checks rather than after them | **KILLED** (3 of 11) |

M-C2-3 is the one worth keeping in mind. It is the edit somebody makes for
tidiness — record the activity first, then validate — and it makes the absolute
limit unreachable by refreshing the idle clock on the very request that should
have been refused.

---

## Correction 3 — Identity ownership (D-148)

| ID | Mutation | Result |
| --- | --- | --- |
| **M-C3-1** | `identity` added back to every console route constraint | **KILLED** (1 of 6) |
| **M-C3-2** | `index()` returns `$this->projection->all()` again, so identity arrives as an editable card | **KILLED** (1 of 6) |
| **M-C3-3** | `IntegrationFamily::writableOnTheConsole()` includes `Identity` | **KILLED** (2 of 6) |

M-C3-2 is why the props are asserted as well as the routes. A route that does
not resolve stops the *write*; it does nothing about a screen that still ships
the directory and application identifiers into the page source and renders a
form that posts nowhere. To a reader that is a second Identity administration
surface which happens to be broken.

---

## Correction 4 — Not configured, and explicit removal

| ID | Mutation | Result |
| --- | --- | --- |
| **M-C4-1** | An absent configuration row defaults to `NotChecked` — the original defect | **KILLED** (4 of 12) |
| **M-C4-2** | The `$stored === NotChecked &&` guard dropped from the derivation | ***SURVIVED*. The mutant was better.** See below |
| **M-C4-2b** | The guard restored after the code adopted the mutant | **KILLED** (1 of 14) |
| **M-C4-2c** | The `NotApplicable` arm dropped from the match | **KILLED** (1 of 14) |
| **M-C4-3** | `removeSecret()` deletes the credential directly, with no step-up | **KILLED** (1 of 12) |
| **M-C4-4** | `$submitted !== ''` dropped, so a blank field counts as a submitted secret | ***SURVIVED* twice.** See below |
| **M-C4-4c** | The same, against the strengthened case | **KILLED** (1 of 15) |
| **M-C4-5** | The unknown-secret-name check replaced with `if (false)` | **KILLED** (1 of 12) |

### M-C4-2 — the mutation that was an improvement

`SetupProjection` derived *Not configured* only when the stored status was
already `NotChecked`, on the stated reasoning that a real test result must not
be overwritten. Removing that condition **changed no test**, because the
condition protects a state the writer already prevents: every write invalidates
the stored status, so "a positive stored result" and "an incomplete
configuration" cannot normally hold at once.

**Normally.** If they ever do — a restored backup, a direct database edit, a
future write path that forgets to invalidate — the original code displayed a
stale **Available** beside a configuration missing a required field. That is the
most misleading sentence this screen is capable of: it reports a working
integration that cannot possibly work.

So the mutant was adopted as the implementation, `NotApplicable` was given an
explicit arm (it is a product statement rather than a report about the
configuration), and two cases were added that write the impossible pair directly
and assert what is shown. **The docblock that justified the original guard was
also wrong, and has been replaced rather than left to mislead the next reader.**

### M-C4-4 — the assertion that was satisfied by a refusal

The first version of `test_a_blank_secret_field_keeps_the_saved_value` asserted
only that the credential was still present afterwards. Dropping `!== ''` left it
present too — because the blank then counted as a **replacement**, the request
was redirected to Microsoft, and nothing had been written *yet*. The mutation
survived while the screen had quietly become one where changing a port sends you
to re-authenticate and then blanks your password on return.

This is CLAUDE.md §2 exactly: *"an assertion satisfied by any refusal."* The case
now asserts the whole behaviour — no step-up redirect, **no staged row**, the
credential intact, and the non-secret field actually saved.

**And it survived a second time.** With the stronger assertions in place the
mutation *still* changed nothing, for a completely different reason: Laravel's
`ConvertEmptyStringsToNull` had already turned the blank field into `null`
before the controller ran, so `is_string()` rejected it unaided. The explicit
check had **never been the thing enforcing the rule**.

That is worth stating plainly rather than glossing as "equivalent mutant": the
promise this screen makes to an administrator — *leave it blank to keep it* —
was resting on a global framework middleware, and would have broken the day
anybody reordered or removed it for an unrelated reason. A third case now drives
the same request with that conversion disabled, which is the only way the
controller's own check is observable at all, and **M-C4-4c** kills the mutation
there.

---

## Defects these mutations found in the implementation

| Found by | The defect |
| --- | --- |
| **P1-08's atomicity guard** | `IntegrationSecretStepUpCompletion::complete()` recorded a state-changing event while relying entirely on `StepUpService::consume()` to have opened the transaction. True on the real path, and not a property the class held itself — the static guard cannot see a transaction two classes away, and a guard that has to be told which callers are trustworthy is the exemption list it exists to avoid. It now opens its own (nested, so a savepoint on the real path) |
| **`SecretsAreDecryptedInOnePlace`** | `StagedChangeStore` decrypted its own column and passed the plaintext to `put()`. It worked, and it made a **second place in the application where a secret becomes readable** — so "no secret escapes" stopped being one claim to check. The ciphertext is now handed to `IntegrationSecretStore::adoptStaged()`, which owns every decrypt |
| **Reading `confirmThroughMicrosoft` adversarially** | It stages `array_key_first($replace)`. Every family declares exactly one secret *today*, so a family that gained a second would have the second **silently dropped** — typed, confirmed at Microsoft, never saved, discoverable only at the next connection test. It now refuses, and `OneSecretPerFamilyTest` makes the day that assumption ends a red build |
| **Reading the rendered screen** | The AI and Fabric cards showed a **"Not configured" badge beside the sentence "This has not been checked yet."** Both halves were individually correct; only the combination was wrong, which is why no test caught it. The fallback sentence now follows the status |
| **Reading the rendered screen at 390px** | `overflow-wrap: anywhere` on panel headings — added in the first round for an unbreakable 33-character email address — also shrinks a heading's intrinsic min-content width to one character. Beside the status badge in a flex row, the browser then squeezed ordinary headings until they broke mid-word: **"Microso / ft Fabric"** and "AI / service" on a customer's screen. Fixed by scoping `anywhere` to the address token itself, letting the head row wrap, and giving the heading block `min-width: 0` — which is where the overflow fix belonged in the first place. **Both cases were re-measured**, because the obvious repair reintroduced the 426px overflow the original rule existed to stop |

## Flaws found in the TESTS, this round

| Case | The flaw |
| --- | --- |
| **`test_a_blank_secret_field_keeps_the_saved_value`** | Satisfied by a refusal. See M-C4-4 |
| **M-C4-2's premise** | The docblock claimed the `NotChecked` guard protected a real test result from being overwritten. It does not — `! isConfigured()` cannot fire for a complete configuration. The reasoning was wrong and the guard was weaker than the mutant |
| **M-C1-2 itself** | Written against a channel that does not persist, so it could not have leaked whatever the code did |
| **`NotConfiguredAndRemovalTest` (first run)** | A private helper named `status()` overrides `PHPUnit\Framework\TestCase::status()`, which is `final` — a **PHP fatal error**, so the file did not run at all and reported as "no output" rather than as a failure |

---

# Gate C round 3 — mutations

The Product Owner held the merge again with three product and security gaps.
**Seventeen mutations were run against the corrections. All seventeen were
killed on the first attempt.**

That is a better result than round 2 and a less interesting one. It is stated
plainly rather than presented as a triumph: the two rounds before this produced
four survivors between them, and a clean sheet is what happens when the tests
are written before the code rather than to fit it. The useful entries in this
file remain the survivors above.

| | |
| --- | --- |
| Killed first time | **17 of 17** |
| Survived | 0 |

---

## Correction 1 — post-install SSO management (P1-02)

| ID | Mutation | Result |
| --- | --- | --- |
| **M-R3-1** | `EntraController::update()` writes the fields through the configuration writer before staging them | **KILLED** (3 of 17) |
| **M-R3-2** | `IdentityReconfiguration::activate()` skips the probe result check and commits whatever was staged | **KILLED** (2 of 17) |
| **M-R3-3** | The partial-clear refusal is removed, so a configured deployment can have its directory emptied | **KILLED** (1 of 17) |
| **M-R3-4** | The staged identity change is consumed without the `consumed_at IS NULL` guard, making the confirmation replayable | **KILLED** (1 of 17) |
| **M-R3-5** | `applyFields()` stops incrementing the identity revision, so the previous directory's cached health survives | **KILLED** (1 of 17) |

**M-R3-2 is the one that matters.** It is the shape of the whole correction: the
confirmation proves *who is asking*, and the probe proves *whether the answer
works*. Applying on the first without the second activates an unverified
directory and leaves nobody able to sign in — including the administrator who
just confirmed — with the evidence saying it succeeded.

**M-R3-1 is the edit somebody makes to be helpful.** "Save what they typed so
they do not have to type it again." It is exactly what the Platform Integrations
controller used to do, and round 3 correction 2 is the Product Owner finding it
there.

---

## Correction 2 — destination changes are privileged

| ID | Mutation | Result |
| --- | --- | --- |
| **M-R3-6** | `host` removed from `Email`'s destination fields | **KILLED** (2 of 10) |
| **M-R3-7** | `endpoint` removed from `Ai`'s | **KILLED** (1 of 10) |
| **M-R3-8** | `tenant_id` and `client_id` removed from `Fabric`'s | **KILLED** (1 of 10) |
| **M-R3-9** | The controller saves the fields before staging the privileged change | **KILLED** (6 of 18) |
| **M-R3-10** | `from_name` — a display-only field — classified as a destination | **KILLED** (2 of 10) |
| **M-R3-11** | Any *submitted* destination field is privileged, whether or not its value changed | **KILLED** (1 of 10) |
| **M-R3-12** | A staged reconfiguration applies its secret and drops its fields | **KILLED** (1 of 18) |

**M-R3-10 and M-R3-11 are the two that guard the other direction.** A control
that demands a Microsoft round trip for editing a sender name, or for pressing
Save with nothing edited, is a control people learn to click through — and then
it is not protecting anything. Both are killed, so the rule cannot be made
either too narrow or too broad without the build saying so.

**M-R3-12** is the mixed-state mutation. It leaves the deployment holding the
new destination beside the old credential, which is precisely the window the
staging design exists to close.

---

## Correction 3 — D-153 test email

| ID | Mutation | Result |
| --- | --- | --- |
| **M-R3-13** | The recipient is read from the request (`$request->input('to') ?? …`) | **KILLED** (1 of 11) |
| **M-R3-14** | The bootstrap principal is ignored, so setup falls through to the User path | **KILLED** (1 of 11) |
| **M-R3-15** | The one-per-minute limiter is removed | **KILLED** (1 of 11) |
| **M-R3-16** | A missing send-from address falls back to the SMTP username | **KILLED** (1 of 11) |
| **M-R3-17** | The recipient is written into the audit evidence | **KILLED** (1 of 11) |

**M-R3-13 is the open relay.** It is one line, it looks like a convenience
("let an administrator check it arrives somewhere else"), and it turns a
diagnostic into a way of sending mail from the customer's own domain, through
their own authenticated server, to anywhere.

**M-R3-16 is the quieter one.** Falling back to the username is the obvious
tidy-up, and it makes the test pass for a sender the deployment will never
actually send from — a green result that proves nothing about the permission
that fails in production.

---

## Flaws found in the TESTS this round

Recorded because a clean mutation sheet would otherwise imply the tests were
right first time. They were not.

| Case | The flaw |
| --- | --- |
| **`PostInstallSsoChangeTest`'s Microsoft stub** | `Http::fake()` **appends** stubs and the FIRST match wins. Calling it a second time with a different tenant left the original answering, so the probe received the OLD directory's issuer for the NEW directory's URL and reported a good candidate as broken. Three cases failed for a reason unrelated to the code. There is now ONE stub, switched by a property, which cannot be got wrong by ordering — and `IdTokenValidationTest` already recorded the same trap |
| **The same stub's return type** | Inside a fake, `Http::response()` returns a `PromiseInterface`, not a `Response`. A `: Response` hint made every call throw a `TypeError`, which `ProviderProbe` catches and reports as *"Microsoft did not answer"* — a network-shaped failure with no network involved |
| **`test_the_entra_screen_offers_the_change…`** | Used `assertSee` on an Inertia response, which carries JSON props and no rendered markup. It failed against a screen that was correct. It now reads the component source, and the behavioural claim is made by the route case above it |
| **The partial-clear cases** | Asserted `=== ''`, which never fires: `ConvertEmptyStringsToNull` turns a blank box into `null` first. The controller now detects *submitted and empty* in both shapes, and a case drives the request with that middleware disabled — the same lesson round 2 recorded for the blank-secret rule, arriving in a second place |
| **The bootstrap test-email case** | Left the permanent System Administrator from `setUp()` in place. First-Run exists only while a deployment has none, so the case was signed out at the door and asserted against an empty log — it would have passed no matter what the code did |
| **A test that tried to extend a `final` class** | The first draft subclassed `TestEmailSender` to fake it. Dropping `final` would have removed the guarantee the test exists to check — that nothing can override where the message goes — so the seam is now a declared interface and the class stays final |

## Defects these corrections found elsewhere

| Found by | The defect |
| --- | --- |
| **Reading `IdentityPage`** | It renders a refusal from `errors.identity` only. A **flashed** `refusal` — which is what a rejected Entra candidate produces — would have gone to a blank page on every Identity screen. `HandleInertiaRequests` already carries a note about exactly this happening to Access Reviews, where no automated test caught it either: `assertSessionHas('refusal')` passes on a message that reaches the session and never reaches the screen |
| **Reading the rendered change screen** | Every Identity page carried the header *"Everything here is read-only: identity settings are held on the server and are not changed from this screen."* After correction 1 that is false — and it was being shown at the top of the change screen itself, contradicting the form two inches below it |
| **Reading the rendered Integrations card** | *"Testing checks that SemantIQ can reach this service… It does not send anything"* sat two inches above a new button that sends an email. The sentence is now tied to **Test connection** by name, and the sending action carries its own |
