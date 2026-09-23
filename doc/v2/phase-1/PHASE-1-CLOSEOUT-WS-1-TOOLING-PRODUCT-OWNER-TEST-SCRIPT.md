# Phase 1 Closeout — WS-1 Tooling: Product Owner Test Script

## `ensure-session-driver.sh` and `align-session-driver.yml`

> # ⛔ DO NOT DISPATCH `align-session-driver.yml`
>
> **This script asks you to test TOOLING, not a production change.** Production
> still runs `SESSION_DRIVER=file`, and the production GO has **not** been
> granted. **Nothing in this document asks you to change production**, and
> nothing in it should be read as permission to.

---

## 1. Feature or task being tested

**The two artefacts §5.3 of the WS-1 runbook specified, now built:**

| Artefact | What it is |
| --- | --- |
| `deployment/ensure-session-driver.sh` | The only mechanism in the repository that can change `SESSION_DRIVER` on the server. One key, two permitted values, two arguments |
| `.github/workflows/align-session-driver.yml` | The manual, confirmed, `main`-guarded workflow that runs it |

**This is the tooling gate, not the change gate.** It closes runbook state 2
(*tooling implementation*). **State 3 — the production GO / NO-GO — is
untouched and remains NOT AUTHORISED.**

---

## 2. Deployed build / merge SHA

| | |
| --- | --- |
| **Branch** | `claude/phase-1-closeout-ws1-session-driver-tooling` |
| **Review round** | **4** — the rollback-completion state gap raised in round 3 is corrected. The script is STILL unchanged; all seven blockers so far have been in the workflow |
| **Base** | `main` = `9fe513e07683166d53469afe86ab1ade508622a7` — the merged WS-1 runbook |
| **Deployed?** | **NO. This branch is not merged and not deployed.** The tooling exists only in the pull request |
| **Production at the time of writing** | `SESSION_DRIVER=file` — `verify-session-store` run `35688043476` |

---

## 3. Preconditions

| | |
| --- | --- |
| **1** | You can see the pull request in GitHub |
| **2** | You have read WS-1 runbook §5.3 (the specification) and §5.4 (the three states) |
| **3** | **You accept that this review does not grant the production GO**, and that a separate explicit authorisation is still required before anything runs |

**No server access is needed.** Nothing in this script requires you to sign in
to production, open a terminal, or touch the deployment.

---

## 4. Test data required

**None.** No account, no organisation, no domain, no record of any kind is
created, changed or read by anything in this script.

---

## 5. ⚠️ Warning — permanence

**Nothing in this script creates permanent data, because nothing in it writes
anything.** Every step is reading a file in the pull request or reading a CI
result.

> **The permanence warning that DOES apply is the one you are not being asked to
> trigger.** Dispatching `align-session-driver.yml` would sign every signed-in
> user out, and rolling back would sign them out a second time. **That is why
> this script stops short of it.**

---

## 6. Steps, and what you should see

### Step 1 — The workflow cannot fire by itself

**Open** `.github/workflows/align-session-driver.yml` and read the `on:` block
near the top.

**Expected:** it contains `workflow_dispatch:` and nothing else. **No `push:`,
no `schedule:`, no `workflow_call:`.** A person choosing to run it is the only
way it ever runs.

**PASS / FAIL:** ☐

---

### Step 2 — Nothing else can run it, and the deployment cannot touch the driver

**Open** `.github/workflows/deploy.yml` and search it for `SESSION_DRIVER`.

**Expected:** **no match.** The deployment fires on every push to `main`,
including a documentation merge; it must have no way to change the session
driver. It still carries `ensure-session-lifetime.sh` for `SESSION_LIFETIME`,
which is a different key and unchanged by this work.

**PASS / FAIL:** ☐

---

### Step 3 — Running it needs a deliberate, typed confirmation

**In the alignment workflow, read the `confirmation:` input and the step named
*"Refuse unless the confirmation phrase is exact"*.**

**Expected:** the run stops unless the phrase `ALIGN SESSION DRIVER` is typed
exactly. **A dropdown alone is not enough** — a repeat dispatch on a stray click
would sign every user out.

**PASS / FAIL:** ☐

---

### Step 4 — It refuses to run from anywhere but `main`

**Read the step named *"Refuse unless this was dispatched from main"*.**

**Expected:** an exact comparison against `refs/heads/main`, and `exit 1` if it
does not match. Without it, an unreviewed script on a branch could be piped
straight onto the production server.

**PASS / FAIL:** ☐

---

### Step 5 — There is nowhere to type an arbitrary setting

**Read the whole `inputs:` block.**

**Expected:** **exactly three inputs** — the driver `target` (a dropdown of
`database` and `file` only), the `confirmation` phrase, and the
`rollback_takeover` phrase added in round 3 (step 12). **No key field, no value
field, no free-text setting name.** All three are fixed phrases or a dropdown;
this is not an environment editor and there is nowhere for it to become one.

**PASS / FAIL:** ☐

---

### Step 6 — The script refuses rather than guessing

**Open** `deployment/ensure-session-driver.sh` and read the refusal messages.

**Expected:** it refuses, leaving the file untouched, when there is no `.env`,
when `SESSION_DRIVER` is absent, when there is more than one of them, when the
current value is something it does not recognise, when the target is not `file`
or `database`, and when it is not given exactly two arguments.

**In particular:** a MISSING `SESSION_DRIVER` is **refused, not added.**
Inventing production configuration from a command-line argument is worse than
declining to act.

**PASS / FAIL:** ☐

---

### Step 7 — Rollback is the same script, not a separate one

**Read the `target` dropdown options and the script's second argument.**

**Expected:** `database` is the change and `file` is the rollback, and **both
run the same code.** A rollback path made of different code is a path nobody has
ever run, exercised for the first time under pressure.

**PASS / FAIL:** ☐

---

### Step 8 — The automated evidence is real, not decorative

**Open the CI run on the pull request.**

**Expected:** green, and among the tests:

| Suite | Cases | What they do |
| --- | --- | --- |
| `SessionDriverDeploymentTest` | 32 | **Run the actual script** against throwaway `.env` fixtures containing a fake client secret and APP_KEY |
| `SessionDriverAlignmentWorkflowTest` | 66 | The workflow's safety contract, comments stripped first — and for the orchestration, **whole sequences of the workflow's own steps executed** against a stubbed server, gates evaluated and outputs carried between them |

**The pull request body lists 59 deliberate mutations** — 10 against the script,
49 against the workflow — **each one recorded with the test that caught it.**
Ten of the script tests are paired: one half breaks the rewrite and proves the
guard refuses; the other half **also removes the guard** and proves the damage
actually lands. That second half is what shows the first half was the guard
doing the work.

**PASS / FAIL:** ☐

---

### Step 9 — The one thing the tooling does better than its sibling

**In the pull request body, read the finding recorded under *"What the mutation
testing found"*.**

**Expected:** with the pre-rename mode and ownership check removed — which is
how `ensure-session-lifetime.sh` is structured today — the test observed `.env`
**actually replaced and left at mode `644`** instead of `600`, with the failure
reported only afterwards. With the check in place, `.env` was untouched and
still `600`.

**This is an observed result from a real run, not a claim about the code.**

**PASS / FAIL:** ☐

---

### Step 10 — An already-aligned deployment is never taken down

> **ROUND 2.** This is the second blocker you raised. The script was always
> idempotent; the workflow was not.

**Read the step named *"Establish the starting session driver and decide whether
anything must change"*, then look at the `if:` line on each of *"Open the
maintenance window"*, *"Align SESSION_DRIVER"* and *"Clear compiled caches"*.**

**Expected:** the plan step reads the current driver **and the maintenance
state** off the server, and plans `noop` only when the driver already equals
what you asked for **AND production is serving**. Each of the three steps above
carries `if: steps.plan.outputs.operation == 'change'`.

> **ROUND 4 CORRECTED THE RULE.** Deciding this on the driver alone is what
> stranded a half-finished rollback — see step 16.

**So dispatching `database` at a deployment already on `database` opens no
maintenance window, pipes no script, clears no cache and signs nobody out.** It
reports *"no change required"* and stops.

**The automated proof is not a reading of that logic — the test EXTRACTS the plan
step's shell and RUNS it**, against a stubbed server, for all four combinations
of starting driver and target. `file` → `file` and
`database` → `database` must plan no change; the two mixed pairs must plan
one.

**PASS / FAIL:** ☐

---

### Step 11 — A failure after the site came back up puts it back down

> **ROUND 2.** This is the first and most serious blocker you raised, and you
> were right: the workflow claimed something it never did.

**Read the step named *"Establish the exact state after a failure"*.**

**Expected:** in the branch where the driver DID change, the step runs
`php artisan down --retry=60` itself, then **reads the maintenance state back
out of the application** and only reports maintenance if the application agrees.
If `artisan down` failed, it reports a CRITICAL error saying maintenance could
**not** be guaranteed and that production must be checked over SSH immediately.

**Why this matters:** the normal *"Close the maintenance window"* step runs
`artisan up` **before** the HTTPS verification and the reporting. A failure in
either of those used to leave a changed, unverified deployment **serving users**
while the log said it had been left in maintenance.

**The automated proof runs the recovery step's own shell** with exactly that
fixture — driver changed, `artisan up` already run, site live — and asserts on
the commands it actually sent to the server. A paired case runs the same fixture
against a copy of the workflow with only that `artisan down` removed, and
requires production to be left **serving**. Without that second half, the first
would prove nothing.

**PASS / FAIL:** ☐

---

### Step 12 — Maintenance windows: whose it is, and who may take it

> **ROUND 2 raised this; ROUND 3 CORRECTED THE RULE ITSELF.** The round-2 answer
> — *"pre-existing maintenance always refuses"* — was too blunt, and it created
> a deadlock. See step 13.

**Read the step named *"Take the maintenance window"*, and the `if:` line on
*"Close the maintenance window"*.**

**Expected — the workflow records WHERE its authority came from, in three
distinct values rather than one vague flag:**

| Origin | What it means | May it close the window? |
| --- | --- | --- |
| **`opened`** | Production was LIVE and this run took it down itself | **Yes** — on success, and on a failure that changed nothing |
| **`takeover`** | Production was ALREADY down, the target is `file`, and the operator supplied the separate rollback confirmation | **On success only.** A failed rollback leaves it down |
| **`none`** | This run holds no window | **Never.** It must not issue `php artisan up` at all |

**And the rules that decide it:**

| Situation | Outcome |
| --- | --- |
| Production LIVE | Take it down, origin `opened` |
| Production in maintenance, target **`database`** | **Always refused.** A forward alignment never takes over a window, whatever is typed |
| Production in maintenance, target **`file`**, exact rollback confirmation | **Permitted** — origin `takeover` |
| Production in maintenance, target `file`, wrong or missing confirmation | **Refused**, production untouched |
| Maintenance state unreadable | **Refused**, production untouched |

**Established from the application** — `app()->isDownForMaintenance()` — not from
an HTTP status. A proxy or cache can return 503 without the application being
down, and a custom maintenance page can return 200.

**Ownership is recorded only after the authority is real:** after `artisan down`
actually succeeded, or after the takeover was authorised **and the window
re-confirmed**.

**PASS / FAIL:** ☐

---

### Step 13 — The advertised rollback can actually be run

> **ROUND 3, blocker 5. This one was a genuine deadlock and it deserves
> reading twice.**

Round 2's workflow did all three of these at once:

1. told the operator the rollback was *"dispatch this workflow with target
   `file`"*;
2. deliberately left production **in maintenance** after a failed forward
   change;
3. **refused any run that found production in maintenance.**

**So the documented way out could not run.** The tooling would have stranded
production down, with its own instructions pointing at a door it had locked.

**Read, in the pull request body, the case
`test_a_failed_forward_run_can_be_rolled_back_by_the_same_workflow`.**

**Expected:** it runs **both dispatches against one stubbed server**, in
sequence, as the runner would — gates evaluated, step outputs carried forward:

| | |
| --- | --- |
| **Run 1** | `file` → `database`. The driver changes, the site comes back up, and the HTTPS verification then fails. Recovery must leave production **down**, on `database`, and its message must name the takeover confirmation |
| **Run 2** | `database` → `file`, production already in maintenance, takeover confirmation supplied. It must be **accepted**, assume the window, run **the same script**, verify `file`, pass health, and **close the window** |
| **Final state** | **`file`, and LIVE** |

**That is the deliverable: a deployment an operator could actually recover.**

**PASS / FAIL:** ☐

---

### Step 14 — A refusal that wrote nothing keeps writing nothing

> **ROUND 3, blocker 4.**

Round 2's recovery ran `php artisan optimize:clear` **before** it knew whether
this run had mutated anything. A run that refused because production was in
somebody else's maintenance window would therefore go on to clear compiled
caches **inside that window** — so the workflow's own claim to *"refuse before
any mutation"* was false.

**Read the step named *"Record that the driver mutation is about to be
attempted"*, and the `mutation_attempted` check in the recovery step.**

**Expected:** the flag is written in **exactly one place**, in the step
**immediately before** the driver script runs. Recovery clears caches **only**
when that flag is set; otherwise it says so and its inspection is **read-only**.

**The automated proof runs the whole refusal sequence** and asserts the recorded
remote-command log contains **no** `artisan down`, **no** `artisan up`, **no**
`optimize:clear` and **no** script invocation — an outcome, not a wording.

**PASS / FAIL:** ☐

---

### Step 15 — Cancellation, and what it does not promise

> **ROUND 3, blocker 6.**

**Read the `if:` on the recovery step, and the top of the workflow file.**

**Expected:** the condition is `failure() || cancelled()` — a cancelled run no
longer bypasses every piece of recovery reasoning.

**And the honest limit is stated rather than glossed:** GitHub can terminate a
runner before any step executes, so the file says it **cannot promise** recovery
in that case, tells you not to cancel once the maintenance sequence has begun,
and names the two things an operator must establish over SSH before
re-dispatching — **the effective session driver** and **the application
maintenance state**.

**PASS / FAIL:** ☐

---

### Step 16 — The three operations, and why "driver already right" is not enough

> **ROUND 4, blocker 7. This is the last gap in the recovery path, and it is
> the same shape as the previous two: a state the tooling could reach and not
> leave.**

Round 3 could get production to **`file` + still in maintenance** — a rollback
whose `.env` rewrite landed and whose later steps did not. Dispatching `file`
again then planned a **no-op**, reported *"nothing to do"*, and **left the
deployment dark**. The recovery it advertised could not recover it.

**Read the planning step, *"Establish the starting session driver and decide
whether anything must change"*.**

**Expected — it reads the driver AND the maintenance state, and plans one of
three operations:**

| Operation | When | What runs |
| --- | --- | --- |
| **`change`** | The driver differs from the target | The mutation script, the cache clear, everything |
| **`rollback_completion`** | Driver already **`file`**, production **still down**, exact takeover confirmation supplied | **No script. No `.env` rewrite. No cache clear.** Verify the driver, check health, bring production back up |
| **`noop`** | Driver already equals the target **AND production is live** | **Nothing** |

**And these are refused rather than planned:**

| Situation | Outcome |
| --- | --- |
| `file` + in maintenance, **no** takeover confirmation | **Refused.** Production stays down, nothing is written |
| `file` + in maintenance, **wrong** phrase | **Refused** before anything reaches the server |
| **`database`** + in maintenance, target `database` | **Refused.** It is not a completion, and the message says the recovery target is `file` |

**PASS / FAIL:** ☐

---

### Step 17 — The whole way out, run end to end

**In the pull request body, read
`test_the_three_run_recovery_sequence_ends_live_on_file`.**

**Expected:** three dispatches against **one** stubbed server, in sequence, with
the gates evaluated and step outputs carried forward:

| | |
| --- | --- |
| **Run A** | Forward `file` → `database`. The HTTPS check fails after the site is back up. Ends **`database` + down** |
| **Run B** | Rollback takeover `database` → `file`. The rewrite lands; health then fails. Ends **`file` + down** — *the state round 3 could not leave* |
| **Run C** | Dispatch `file` with the confirmation. Recognised as **`rollback_completion`**: no script, no cache clear, driver verified, health passed, window closed. Ends **`file` + LIVE** |

**And the direction is always `file`.** A failed rollback leaves the driver on
`file`; a message telling you to take over the window with target `database`
would re-apply the change that just failed. **No recovery message in this
workflow names `database` as the dispatch target** — asserted directly.

**Also asserted, by running it:** a completion whose health check fails leaves
the window **active**; and a completion that fails **after** `artisan up` has
run is **put back into maintenance**, because the driver never changed and the
round-3 recovery would have reported the window as active while production was
in fact serving.

**PASS / FAIL:** ☐

---

### Step 18 — The report says what each operation actually did to people

> **FINAL EVIDENCE CORRECTION.** Non-blocking, and it changes no production
> control logic — only what the run record claims.

The report step runs for a **change** and for a **rollback completion**, and it
told both of them that *"every signed-in user | signed out"*.

**That is true of a change.** It rewrites `.env` and moves the session store, so
every existing sign-in stops being readable.

**It is not true of a completion.** That path rewrites nothing and moves no
store — anyone signed out was signed out by the change it is *finishing*. Saying
otherwise records a second production interruption that never happened.

**Read the *"Report what was done"* step.**

**Expected:**

| Operation | Reported session impact |
| --- | --- |
| **`change`** | *"existing sign-ins were invalidated by the driver switch, and everyone must sign in again — the expected, accepted effect"* |
| **`rollback_completion`** | *"**no driver switch occurred in this run**, so it caused no additional session invalidation"* |

**The automated proof runs both paths and reads the RENDERED SUMMARY**, asserting
the completion's record contains neither *"signed out"* nor *"sign in again"*.

**PASS / FAIL:** ☐

---

## 7. Negative, refusal and security cases

**All of them are automated and listed in step 8.** They are not repeated as
manual steps here, because reproducing them by hand would mean running the
script against a file you had to construct, and the automated versions are both
stronger and reproducible.

**The security-relevant ones, stated plainly:**

| | |
| --- | --- |
| **No secret is printed** | Asserted on both output streams, on success **and on refusal** — refusal messages are where a value normally leaks |
| **No backup of `.env` is written** | A backup of a secrets file is a second secrets file |
| **A killed run leaves no copy behind** | Proven by killing the script mid-write; and a copy left by something untrappable is swept by the next run |
| **Every other byte survives** | Proven by a hash comparison inside the script, **before** the file is replaced |

---

## 8. Visual and UX checks

**NOT APPLICABLE — and this is not an omission.** This change adds one shell
script, one workflow file and two test files. **It renders no screen, adds no
route, changes no navigation and touches no user-facing surface.** The
professional-polish gate has nothing to inspect.

---

## 9. Evidence to capture

| | |
| --- | --- |
| **1** | Your PASS / FAIL for steps 1 – 18 |
| **2** | The CI run number and its result |
| **3** | Anything in the script or workflow you want changed **before** the production GO is considered |

---

## 10. What CANNOT currently be tested, and why

> **THIS SECTION IS THE POINT OF THE DOCUMENT. Read it before recording any
> result above as evidence that the tooling works in production.**

| What | Why not | Where it goes instead |
| --- | --- | --- |
| **That the workflow successfully changes the production session driver** | **It has never been run, and must not be.** The production GO has not been granted | **Carried to the WS-1 execution window.** Runbook §6 step 5, §8 proof 5 |
| **That the script behaves identically on the cPanel host** | The behavioural tests run on Linux in CI. The host's `stat`, `sed` and `wc` are handled by the script's BSD/GNU fallbacks, but **that is a design provision, not an observation** | **Carried to the execution window.** The script's own refusals are the safety net: an unreadable mode or a failed rewrite aborts with `.env` untouched |
| **That the maintenance-window ordering behaves as intended live** | The step order is asserted in the repository; **the live behaviour of `artisan down` / `artisan up` around a driver change has not been observed** | **Carried to the execution window.** Runbook §6 |
| **That a real host reaches the `file` + in-maintenance state the same way the stub does** | The three-run sequence is executed against a stubbed server, so the ORCHESTRATION is proven. **That the cPanel host fails at exactly those points is not something a repository can observe** | **Carried to the WS-1 execution window.** What is proven is that if production reaches that state, the tooling can leave it |
| **That a cancelled run's recovery actually completes** | GitHub can terminate a runner at any point, including before the recovery step starts. **The condition now covers cancellation; the runner surviving long enough to honour it is not something this repository can guarantee** | **NOT CURRENTLY OBSERVABLE, AND NOT CLAIMED.** The workflow says so at the top and names what an operator must establish over SSH if it happens |
| **That `php artisan down` is genuinely idempotent on this host** | Laravel reports *"Application is already down"* and exits 0, which is what the recovery step relies on when the window was never closed. **That is read from the framework, not observed on the server** | **Carried to the execution window.** If it were not idempotent the recovery would still not expose production — it verifies the state afterwards rather than assuming the command worked |
| **That the failure path leaves the correct state ON THE REAL HOST** | It would require deliberately failing a production run | **NOT CURRENTLY OBSERVABLE WITH REAL PRODUCTION DATA.** Manufacturing a production failure to watch the recovery is not a test worth its cost. **What round 2 changed is how much is now observable WITHOUT it:** the recovery step's own shell is executed against a stubbed server, so the commands it sends — and does not send — are proven. What remains unobserved is only that the real host answers those commands as expected |
| **That `sessions.user_id` is NULL on the production host specifically** | Established from source and corroborated only by a transient local probe that was **not retained** — see runbook §2A | **Already carried.** It is a CL-11 design constraint, recorded in runbook §9A |

**None of the above is an implementation defect.** Each is a thing that a
repository and its CI genuinely cannot see, recorded here rather than inferred
from a passing test.

---

## 11. Status

| | |
| --- | --- |
| **State 1 — runbook** | **APPROVED**, merged at `9fe513e` |
| **State 2 — tooling** | **BUILT AND TESTED. AWAITING YOUR REVIEW.** Not merged |
| **State 3 — production GO / NO-GO** | **NOT AUTHORISED.** Unchanged by this work |
| `SESSION_DRIVER` in production | **still `file`** |
| `align-session-driver.yml` | **NEVER DISPATCHED** |
| CL-11 | **Not started** |
| CL-12 | **Not started** |
| Phase 2 | **Untouched** |
