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

**Expected:** **exactly two inputs** — the driver `target` (a dropdown of
`database` and `file` only) and the `confirmation` phrase. **No key field, no
value field, no free-text setting name.** This is not an environment editor and
there is nowhere for it to become one.

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
| `SessionDriverAlignmentWorkflowTest` | 21 | Assert the workflow's safety contract with its comments stripped out first |

**The pull request body lists 25 deliberate mutations** — 10 against the script,
15 against the workflow — **each one recorded with the test that caught it.**
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
| **1** | Your PASS / FAIL for steps 1 – 9 |
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
| **That the failure path leaves the correct state** | It would require deliberately failing a production run | **NOT CURRENTLY OBSERVABLE WITH REAL PRODUCTION DATA.** Manufacturing a production failure to watch the recovery is not a test worth its cost. The logic is asserted structurally and the failure message names the explicit rollback |
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
