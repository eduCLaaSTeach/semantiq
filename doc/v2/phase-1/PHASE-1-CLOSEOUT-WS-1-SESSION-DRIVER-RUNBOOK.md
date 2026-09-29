# Phase 1 Closeout — WS-1: Session Driver Alignment Runbook

## CL-10 · `SESSION_DRIVER=file` → `SESSION_DRIVER=database`

> # ✅ RUNBOOK APPROVED — ⛔ PRODUCTION CHANGE NOT AUTHORISED
>
> **Nothing in this document has been executed.** Production still runs
> `SESSION_DRIVER=file`. The change requires an explicit Product Owner
> **GO** — §13.

| | |
| --- | --- |
| Workstream | **WS-1** — the first active item of Phase 1 Closeout |
| Register item | **CL-10** only |
| Baseline | `main` = **`43c280b25f58c98b898f817938063de4a02d1ca2`** |
| Authority | `PHASE-1-CLOSEOUT-PLAN.md` §3 CL-10, §3A, §5 — Gate A approved |
| Status | **RUNBOOK APPROVED by the Product Owner. NOT EXECUTED.** |
| **Tooling gap** | **Confirmed — §5.3. No existing mechanism can change `SESSION_DRIVER`. APPROVED TO BUILD, NOT APPROVED TO EXECUTE** |
| Amendment | **Round 3 — final** — two evidence-wording corrections: §2A probe reclassified as supporting corroboration; §9 B-0/B-2 restated as *observed absence of movement*, not proof of inertness. Rounds 1 and 2 fully retained |
| **Identity finding** | **`sessions.user_id` will be NULL for every SemantIQ session — §2A. Established from source; corroborated by a transient local probe that was not retained.** The previous §9 proof rested on it and was impossible. **CL-10's goal is unaffected; CL-11 gains a new design constraint — §9A** |

---

## 1. Purpose and scope

**This runbook covers CL-10 and nothing else.** It aligns the production session
store with the approved baseline target: `SESSION_DRIVER=database`.

**Explicitly out of scope — none of these is touched, prepared or implied:**

| | |
| --- | --- |
| **CL-11** — per-user session revocation | **Not implemented, not designed.** It is a separate Gate B item that *depends* on this one, and the dependency runs one way only: CL-10 does not build any part of CL-11 |
| **CL-08** — Entra `env` → encrypted store cutover | **DEFERRED by explicit Product Owner decision.** Not performed, not prepared |
| **CL-12** — Organisation Administrator navigation | Separate Gate B item, unrelated to sessions |
| Every other register item | Untouched |

**Why CL-10 comes first, and why it matters beyond tidiness.** It breaks no
delivered control today. **It will matter the day CL-11 is built:** on the
`file` driver a per-user revocation control would **silently fail rather than
error** — the worst of the three outcomes, because a security control that is
believed and does not work is worse than one that is absent. **WS-1 must
complete and be verified before WS-2 goes live.**

---

## 2. Current verified state

Every row below is **established from the repository or from production**, with
its source. Nothing is assumed.

| Fact | Value | How it is known |
| --- | --- | --- |
| **Production session driver** | **`file`** | Read-only SSH verification — workflow `verify-session-store`, run `35453840140` |
| **Configuration is not cached** | **Not cached** | Same run. **This matters:** the reported value is live, not a stale compiled artefact |
| **Sessions table migration** | **Already exists** | `database/migrations/0001_01_01_000000_create_sessions_table.php` |
| **Schema change required** | **NONE** | The migration is already applied — the most recent deployment reported `INFO  Nothing to migrate.` |
| **Sessions table shape** | `id` (pk), `user_id` (nullable, indexed), `ip_address`, `user_agent`, `payload`, `last_activity` (indexed) | The migration above |
| **Application health** | **Healthy** | `GET /up` → `ok`; deploy run `35684070208` ran `semantiq:health` over SSH |
| **Identity authority** | **`env` — unchanged** | CL-08 deferred; `identity_source` untouched |
| **Microsoft sign-in configuration** | **Present** | The deployment's *"Verify identity configuration is present on the server"* step passes on every run and is a hard gate |
| **`.env` is never transferred** | **Guaranteed** | `deployment/rsync-protected-paths.txt` lists `.env`; the deployment asserts the exclusion contract **before any transfer**, and a CI test asserts it against the workflow file |

**`user_id` already exists on the sessions table, nothing reads it — and under
SemantIQ's identity architecture nothing writes a usable value into it either.** That second half was missing from this runbook and from the
assumptions carried toward CL-11. **§2A establishes it from source and from a
local run.**

---

## 2A. `sessions.user_id` will be NULL — established, not assumed

> **This invalidated the previous §9 proof.** An earlier draft made
> `COUNT(*) WHERE user_id IS NOT NULL` a **mandatory** proof. **That column will
> never be populated by a SemantIQ sign-in**, so the proof could only ever have
> failed — or, worse, been quietly reinterpreted until it passed.

### The sign-in path, traced end to end

| Step | What actually happens | Source |
| --- | --- | --- |
| Microsoft returns | `CallbackController::issueSession()` runs | `app/Modules/Platform/Http/Controllers/Auth/CallbackController.php:83` |
| The session is issued | `$request->session()->put(EnsureSessionIsCurrent::SESSION_USER_ID, $user->id)` — i.e. the key **`auth.user_id`** | same file, line 109 |
| **Laravel Guard login** | **NEVER CALLED** | — |
| Each request re-checks | `EnsureSessionIsCurrent` reads **`auth.user_id`** and resolves the model itself | `EnsureSessionIsCurrent.php:40,50` |
| The resolved principal | Placed on the request as `semantiq_user`, which every authorising path reads | e.g. `RequireActionClass.php:52` |

**SemantIQ authentication is custom, session-key based.** It does not use
Laravel's authentication Guard at any point.

### Exhaustive search — no Guard binding exists

| Searched across `app/` | Hits |
| --- | --- |
| `Auth::login`, `Auth::setUser`, `loginUsingId`, `Auth::guard`, `->login(` | **0** |
| `Session::extend`, custom `SessionHandlerInterface`, `DatabaseSessionHandler` subclass | **0** |
| Any write to the `sessions` table | **0** — the only reference is P1-09's `SessionStoreCheck`, a round-trip health probe |

**`App\Modules\Platform\Models\User` is a plain `Model`** — `final class User
extends Model`. It does **not** implement `Authenticatable`.
**`config/auth.php` still carries `'model' => null`** for the `users` provider,
with the original comment: *"No user model exists in P1-BASE."*

### What Laravel does with that

`Illuminate\Session\DatabaseSessionHandler` populates the column like this:

```php
protected function addUserInformation(&$payload)
{
    if ($this->container->bound(Guard::class)) {
        $payload['user_id'] = $this->userId();
    }
    return $this;
}

protected function userId()
{
    return $this->container->make(Guard::class)->id();
}
```

**`Guard::class` IS bound** — it is a core container alias — so the branch runs.
**But `Guard::id()` has no authenticated user**, because nothing ever logged one
in. It returns `null`, and `null` is written.

### Established from source; corroborated by a transient local probe

**The two classes of evidence are not equal, and this runbook does not pretend
they are.**

| Class | What it is | Status |
| --- | --- | --- |
| **Authoritative** | **The source analysis above** — the traced sign-in path, the zero-hit search, the `User` class declaration, `config/auth.php`, and Laravel's `DatabaseSessionHandler`. **Every line of it is in the repository and can be re-read, re-run and re-checked by anyone at any time** | **Reproducible. This is the evidence the finding rests on** |
| **Supporting** | A **transient local probe** — written, executed once, and **deliberately deleted.** It configured `session.driver=database`, issued a session exactly as `issueSession()` does, and read the row back | **Local corroboration only. NOT retained as an artifact, therefore NOT reproducible or auditable from the repository** |

The probe reported:

```
session rows written: 1
user_id = NULL | last_activity set = yes
Guard::id() = NULL
Guard bound  = true
```

> **A deleted, uncommitted probe is not durable acceptance evidence**, and must
> not be cited as though it were. It agreed with the source analysis, which is
> worth recording — but **the source analysis is what carries the finding.** If
> this conclusion is ever challenged, it is re-established by re-reading the code,
> not by trusting a transcript of a run nobody can repeat.

### The three conclusions

| | |
| --- | --- |
| **1** | **`sessions.user_id` must NOT be treated as populated.** It will be `NULL` for every SemantIQ session unless an explicit integration is built |
| **2** | **The driver switch is still safe.** The `bound()` guard means no exception: a row **is** written, and **`last_activity` is set and usable.** CL-10's goal is unaffected |
| **3** | **Nothing is being fixed here.** Adding Laravel Auth, making `User` implement `Authenticatable`, or altering the middleware would be **security-architecture changes**. They are out of scope for WS-1 and belong, if needed, to the CL-11 DESIGN — see §9A |

> **DO NOT ADD LARAVEL AUTH TO MAKE A PROOF PASS.** Changing the identity model
> so that a measurement succeeds is the inverse of evidence.

---

## 3. Expected user impact

> ### EVERY SIGNED-IN USER WILL BE SIGNED OUT.

**This is the defining effect of the change, and it is accepted.** The Product
Owner ruled: *"Everyone being signed out is understood and accepted as an
expected effect of this controlled change."*

| | |
| --- | --- |
| **What happens** | The application starts reading sessions from a different store. Existing `file` sessions are not migrated and are not readable from the new store |
| **What users see** | They are returned to sign-in. Their next Microsoft sign-in works normally |
| **What is lost** | Session state only. **No user record, role, assignment, organisation, domain, review, audit entry or configuration value is affected** |
| **Is this a defect?** | **No.** It is the expected, understood and accepted consequence. A runbook that promised otherwise would be wrong |
| **Who should know first** | Anyone signed in during the window. §6 places that notice before the point of no return |

**Rollback signs everyone out a second time** — §7. That is a reason to choose
the window carefully, not a reason to avoid rollback if it is needed.

---

## 4. Pre-change checks

**All eleven must pass and be recorded before the change begins.** A check that
was not run is a check that failed.

| # | Check | How | Expected |
| --- | --- | --- | --- |
| **P-1** | Application health | `GET /up` | `ok` |
| **P-2** | Site responds | `GET /` | `200` |
| **P-3** | **Current session driver** | Dispatch **`verify-session-store`** (read-only) | **`file`** — confirming the starting point, not assuming it |
| **P-4** | Configuration is not cached | Same workflow | **Not cached.** If it *is* cached, the reported value may not be live and the change plan must be re-examined before proceeding |
| **P-5** | Sessions table exists | Dispatch **`verify-session-table-evidence`** (read-only) | **Present — PASS, DIRECTLY OBSERVED.** `session_table_exists: True`, runs `36391980170` and `36392333552`, 28 September 2026. **This was previously INFERRED from the migration set**; see §4A |
| **P-6** | Database connectivity | `php artisan migrate:status` over SSH | Runs and returns; connection healthy |
| **P-7** | Migration status clean | Same command | **Nothing pending.** A pending migration means an unrelated change is mid-flight |
| **P-8** | **Identity source is still `env`** | Dispatch **`verify-identity`** (read-only) | **`env`.** CL-08 is deferred; if this reads `store`, an unauthorised cutover has happened and **this change must not proceed** |
| **P-9** | Microsoft sign-in configuration present | The deployment's identity step, or `verify-identity` | Present. **Without it, a signed-out user cannot sign back in** |
| **P-10** | **Server access for rollback is available and tested** | SSH reaches the deploy path **before** the change | Confirmed. **Never start a change whose rollback path is unverified** |
| **P-11** | No unrelated deployment or change in progress | GitHub Actions — no running deploy; no open production change | Clear |

### 4A. P-5 and the Fact B B-0 baseline — DIRECTLY OBSERVED, 28 September 2026

> **THIS REPLACES THE EARLIER "FACT B BASELINE NOT COLLECTED" POSITION.**
> At the first go/no-go the pack was incomplete for two reasons that were the
> same reason: **P-5 was inferred, not observed**, and **no mechanism existed
> to collect the B-0 baseline at all.** `verify-session-store` reports table
> existence **only when the effective driver is already `database`** — and
> production is `file`, so that branch had never run. Run `36392412993`, taken
> today, still prints `Session table : not reported - the driver is not
> database`, which is the gap itself, on the record.
>
> **`verify-session-table-evidence.yml` was built to close exactly that**
> (PR #144, merged `86760940c9633da1cd406488b11ba2937a954cb1`). It is
> **READ-ONLY EVIDENCE TOOLING.** It is not a driver change and merging it was
> not a GO.

**Both readings were taken from `main`, on head `8676094`, with production
untouched between them.**

| | **Baseline Reading 1** | **Baseline Reading 2** |
| --- | --- | --- |
| **Workflow run** | **`36391980170`** | **`36392333552`** |
| **Head SHA** | `86760940c9633da1cd406488b11ba2937a954cb1` | `86760940c9633da1cd406488b11ba2937a954cb1` |
| **Sample taken (UTC)** | **07:30:24** | **07:34:12** |
| **Result** | **SUCCESS** | **SUCCESS** |
| `effective_session_driver` | **`file`** | **`file`** |
| `configuration_is_cached` | **`False`** | **`False`** |
| `session_table_exists` | **`True`** | **`True`** |
| `session_row_count` | **`0`** | **`0`** |
| `max_last_activity` | **`null`** — the table is empty | **`null`** — the table is empty |

| | |
| --- | --- |
| **Interval between samples** | **3 minutes 48 seconds** (07:30:24 → 07:34:12 UTC) |
| **Row count unchanged?** | **Yes — `0` in both readings** |
| **Most recent activity unchanged?** | **Yes — `null` in both readings** |
| **Competing `cpanel-deploy` operation in the interval?** | **None.** The deployment for this merge (run `36391676485`) completed at **07:28:38**, before Reading 1 began. The only other run in the window was CI `36391676633`, which uses the `ci-<ref>` concurrency group and never touches the server |

#### The conclusion, stated exactly

> **No session-table movement was observed during the controlled pre-change
> interval.**

**That is the whole of the claim.** It **does not mathematically prove that no
write or delete occurred between the two samples** — a write and a compensating
delete, or a write to an already-counted row, would not show. It is an
observation over an interval, not a proof of inertness. That limitation is
§9 B-0's own wording and it is repeated here rather than quietly dropped now
that the readings agree.

#### Table state: **EMPTY AND STABLE**

`session_row_count` is **`0` in both readings**, so the additional Product
Owner safety rule resolves to **EMPTY AND STABLE**. There are no pre-existing
database-session rows to account for, and therefore **no set of rows that could
be silently treated as harmless when the database driver is activated**.

**Had the count been greater than zero**, this section would report the count
and the latest activity timestamp **only**, and the driver switch would remain
unauthorised pending Product Owner review. No row was exposed, none was
deleted, and no payload, user or session identifier was inspected — the
workflow cannot read them, and its output allow-list is enforced on the report
itself.

### Backup and recovery posture

**This is a configuration-only change to a single key.** It writes no row,
drops no table and alters no schema.

| | |
| --- | --- |
| What could be lost | **The one `.env` line being changed**, if the write went wrong |
| Why the posture is sufficient | The change mechanism (§5.3) writes atomically and, **before the rename**, proves the target line, the line count, that no other content moved, and that mode and ownership match — **aborting with the original `.env` untouched if any fails** (V-1 … V-4). The prior value is `file`, a known constant recorded here, not something needing recovery from a backup |
| **No `.env` backup is taken, deliberately** | **A backup of a secrets file is a second secrets file.** The existing `ensure-session-lifetime.sh` states this and takes none; this runbook follows the same rule. The rename is already atomic, so a backup would buy nothing and cost a duplicate of every secret on disk |
| Database backup | **Not required for this change** — no data is written or migrated |

---

## 5. Change mechanism

### 5.1 The only two `.env` mutation paths that exist today

Both are **single-key, purpose-built, and neither can set `SESSION_DRIVER`:**

| Script | Key | Scope |
| --- | --- | --- |
| `deployment/ensure-app-key.sh` | `APP_KEY` | **Initial deployment only**, and only when absent |
| `deployment/ensure-session-lifetime.sh` | `SESSION_LIFETIME` | D-31. Value sourced from `artisan semantiq:session-policy --idle-minutes` |

**`.env` is otherwise never written by the deployment at all**, and never
transferred: it is the first line of `deployment/rsync-protected-paths.txt`.

### 5.2 What the existing script actually proves — stated precisely

> **CORRECTED, Product Owner review.** An earlier draft of this section said the
> existing mechanics already prove *"the edit might not land, or might move
> something else — the result is **verified** before the rename."* **That
> overstated what the code does**, and the distinction matters because the whole
> point of CL-10's mechanism is what it guarantees at runtime on a live secrets
> file. The corrected reading is below.

**`ensure-session-lifetime.sh` solved most of the hazards of editing a live
secrets file, and its reasoning is recorded in the script itself.** But its
guarantees fall into **two different classes**, and they must not be conflated:

#### (a) Runtime-verified — the script checks these while it runs

| Hazard | Runtime guarantee |
| --- | --- |
| A temporary copy of `.env` is a second copy of every secret | Created under **`umask 077`**; a **trap** removes it on every exit path it can catch |
| A run killed by an **untrappable** signal leaves that copy behind | A **sweep of stale temporaries before writing a new one**, bounding exposure to a single interrupted run — *found by deliberately killing the script mid-write, not by reading it* |
| A non-atomic replace can truncate the file | **Same-directory `mv -f`** — a rename across filesystems is not atomic |
| **The target line did not land** | `grep -qE "^SESSION_LIFETIME=<value>$"` on the temporary file, **before** the rename |
| Mode or ownership silently changed | `stat` comparison — **but only AFTER the rename.** See §5.2(c) |
| Secrets leaking into logs | **No value from `.env` is ever printed** |

#### (b) Test evidence, NOT a runtime check

**That only one line changes is established by the script's behaviour and its
tests — it is NOT verified at runtime before the rename.**

The single pre-rename check is:

```sh
if ! grep -qE "^SESSION_LIFETIME=${approved}\$" "$tmp"; then
```

**That proves the target line EXISTS in the temporary file. It proves nothing
about any other byte.** A `sed` that also mangled an unrelated line would pass
it.

**The script's own comment promises more than the code delivers**, and this is
worth recording rather than glossing:

> *"The edit must have landed, and nothing else may have moved. **A line count
> that changed by anything other than the one appended line** means the rewrite
> did something it was not asked to."*

**No line count is ever taken** — `wc -l` does not appear in the script. The
comment describes a check that was intended and not implemented. **This is not a
live defect** (the `sed` substitution is sound and its behaviour is tested), but
it is precisely the shape of thing this project keeps finding: **a comment
asserting a guarantee the code does not provide.**

#### (c) A genuine ordering weakness — not to be copied

```sh
chown "$owner" "$tmp" 2>/dev/null || true   # failure is swallowed
mv -f "$tmp" .env                           # original replaced HERE
# ... ownership compared only now, after the fact
```

**A failed ownership restoration is detected only after the original `.env` has
already been replaced.** The check is real and worth having, but by the time it
fires the damage is done and the operator is told to *"restore them before
deploying again"* — recovery, not prevention.

**The new script must be stronger — §5.3.**

> **CL-10 does not refactor `ensure-session-lifetime.sh`.** The weakness above is
> recorded, not fixed here. That script is a separate, accepted, working D-31
> control, and **absorbing an unrelated refactor into a session-driver change
> would widen a controlled production alignment into something else.** If it
> should be hardened, that is its own justified change.

### 5.3 ⚠️ THE GAP — no existing mechanism can perform this change

> **STOPPING POINT. This is the finding the Product Owner's brief asked for.**

**`ensure-session-lifetime.sh` cannot be pointed at `SESSION_DRIVER`.** It is
key-specific by construction: the key name is hard-coded throughout, and the
value it writes is **derived from `artisan semantiq:session-policy
--idle-minutes`**, a source that has no meaning for a driver name. It takes only
a deploy path as an argument. **It is not parameterised, and its own docblock
forbids making it so.**

**Therefore, performing CL-10 safely requires a repository change**, and per the
approved instruction **it is identified here and NOT implemented.**

> ### PRODUCT OWNER RULING — APPROVED TO BUILD, NOT APPROVED TO EXECUTE
>
> The tooling below **may be implemented in a separate PR** once this runbook is
> approved and merged. **That authorises repository tooling and automated tests
> ONLY.** It does **not** authorise dispatching the workflow, changing `.env`,
> opening the maintenance window, touching any production session, or setting
> `SESSION_DRIVER=database`. See §5.4.

**Two artefacts. Neither exists yet.**

#### A. `deployment/ensure-session-driver.sh`

**Purpose-built for `SESSION_DRIVER`. Not a general `.env` editor — the same
prohibition the existing script places on itself.**

| | Requirement |
| --- | --- |
| **Arguments** | **Exactly two:** the deployment path, and a target restricted to **`database` or `file`**. **No arbitrary key. No arbitrary value.** Anything else is refused |
| **Forward and rollback** | **The same script**, differing only in the target argument. Rollback is therefore not a separate, never-exercised path |
| **Target source** | The **literal** value passed in. **Never derived from `.env.example`** — reading a repository file and reporting it as deployment reality is exactly how the original wrong claim was made |

**Runtime preconditions — refuse rather than guess:**

| # | Precondition | On failure |
| --- | --- | --- |
| **R-1** | `.env` **exists** | Abort |
| **R-2** | **Exactly one** `SESSION_DRIVER=` entry | Abort |
| **R-3** | **Zero entries → ABORT.** Do not append | **Never invent deployment configuration.** An absent key means the deployment is not in a state this script understands |
| **R-4** | **Duplicate entries → ABORT** | Ambiguous: which one is live is a question the script must not answer by guessing |
| **R-5** | **Current value is `file` or `database`** | Abort on anything else — **refuse an unexpected state rather than overwriting it** |
| **R-6** | Target is **exactly** `file` or `database` | Abort |
| **R-7** | **Idempotent** — current already equals target | **Exit 0 without writing**, as `ensure-session-lifetime.sh` does |

**Runtime verification BEFORE the rename — all four, on the temporary file:**

| # | Must be proven before `mv` |
| --- | --- |
| **V-1** | The `SESSION_DRIVER` line is **exactly** the intended target |
| **V-2** | **Line count is unchanged** — the check the existing script's comment promises and does not perform (§5.2(b)) |
| **V-3** | **All content other than the `SESSION_DRIVER` value is unchanged** — a normalised comparison or checksum computed **locally on the server**, printing no `.env` content and creating **no persistent second copy** |
| **V-4** | **Mode AND ownership of the temporary file match the original** — see below |

> #### V-4 IS THE CORRECTION THAT MATTERS MOST
>
> **The existing script sets ownership with `|| true`, renames, and only then
> compares.** A failed `chown` is therefore discovered **after the original
> `.env` has already been replaced** — recovery, not prevention.
>
> **The new script must verify mode and ownership on the temporary file BEFORE
> the rename, and abort while the original `.env` is still untouched.**
> It must **also** verify after the rename, keeping the existing check rather
> than replacing it. **Before-and-after, not after-only.**

**Retained from §5.2(a) — all mandatory:** same-directory temporary · `umask 077`
before creation · trap cleanup on every catchable exit · **stale-temp sweep** for
untrappable termination · **atomic same-directory rename** · **no `.env`
backup** (a backup of a secrets file is a second secrets file) · **no secret
output, ever**.

#### B. `.github/workflows/align-session-driver.yml`

| | Requirement |
| --- | --- |
| **Trigger** | **`workflow_dispatch` ONLY.** Never on push. **Never wired into `deploy.yml`** |
| **Ref guard** | **Hard refuse unless the selected ref is `main`** |
| **Target input** | A **choice** of exactly `database` or `file` — not free text |
| **Confirmation input** | A **deliberate confirmation** so an accidental dispatch cannot mutate production |
| **SSH** | **Reuse the exact established pattern** from the read-only production workflows — ssh-agent with askpass, because the deploy key is passphrase-protected and writing the key file alone yields *"Permission denied (publickey)"* |
| **Credentials** | **Reuse the repository's existing GitHub Environment and secrets.** Do not create a second credentials model |
| **Output** | **Never display `.env`, credentials or session contents** |

**Why not `deploy.yml`:** it fires on **every push to `main`**. CL-10 is a
**one-time controlled alignment behind a Product Owner GO / NO-GO** — it must not
ride on a documentation merge.

**Why not a manual SSH edit:** it has none of §5.2(a)'s guarantees. Hand-editing
the file that holds every secret, with no atomicity and no mode/ownership
verification, is the failure `ensure-session-lifetime.sh` was written to prevent.

**Rejected: a second production mutation path.** These are deliberately a
**sibling** of the existing script and workflow patterns, not a new one.

### 5.4 Three separate states — do not collapse them

**These are three distinct approvals. Holding one is not holding the next.**

| # | State | Status |
| --- | --- | --- |
| **1** | **Runbook approval** — this document is correct and authoritative | **APPROVED** — Product Owner, amendment round 3 |
| **2** | **Tooling implementation approval** — the §5.3 script and workflow may be written and tested | **APPROVED TO BUILD.** Repository code and automated tests only |
| **3** | **Production GO / NO-GO** — the change may actually be made | **NOT AUTHORISED** — §13 |

**State 2 does not imply state 3.** Building the tool is not permission to run
it, and a merged runbook is not a GO.

### 5.5 Cache handling after the change

**Clear only what is necessary.** The deployment already runs
`php artisan optimize:clear`; that is the established command and this runbook
uses it rather than inventing a different one.

**Configuration is currently *not* cached (P-4)**, so the new value takes effect
without it — but the clear is run anyway, because "should already be live" is an
assumption and clearing is cheap and safe.

**Never run `config:cache` as part of this change.** It would freeze the
configuration and defeat the live verification in §8 and §9.

---

## 6. Window procedure

**No step in §6 may begin before a Product Owner GO (§13).**

| Step | Action | Checkpoint |
| --- | --- | --- |
| **1** | Confirm the agreed window has started and **P-1 … P-11 all pass and are recorded** | **STOP if any failed** |
| **2** | Notify anyone signed in that they will be signed out | Before anything changes |
| **3** | Re-confirm driver is still `file` and identity source still `env` | Guards against drift since step 1 |
| **4** | **Open the maintenance window** — `php artisan down`, the deployment's own mechanism | Site shows maintenance |
| **5** | **▸ POINT OF NO RETURN ▸** Change the single key `SESSION_DRIVER` from `file` to `database` | **Every existing session becomes unreadable from here.** Forward and rollback both now cost a sign-out |
| **6** | `php artisan optimize:clear` | Compiled state cleared |
| **7** | **Close the maintenance window** — `php artisan up` | Site live |
| **8** | `GET /up` → `ok`, `GET /` → `200` | **Roll back if not** — §7 |
| **9** | Dispatch `verify-session-store` | Must report **`database`** — proof 5 |
| **10** | **Product Owner performs a real Microsoft sign-in** | Proof 4. **Roll back if sign-in fails** |
| **11** | Confirm the `sessions` table is genuinely in use — §9 | Proof 6 |
| **12** | Walk the accepted console routes | §10 |
| **13** | Capture evidence — §12 | |

**On the point of no return.** Before step 5 the change costs nothing to
abandon. After it, *both* directions sign everyone out — so rollback is still
entirely available, it simply is no longer free. **That is a reason to decide
before step 5, not a reason to press on through a failure.**

---

## 7. Rollback

| | |
| --- | --- |
| **The change** | Set the single key `SESSION_DRIVER` back to **`file`**, by the same mechanism, then `php artisan optimize:clear` |
| **Trigger** | **Any** of: Microsoft sign-in fails · sessions cannot be persisted or read · `/up` unhealthy · 500s or a redirect loop · session instability · unexpected configuration drift |
| **Decision** | Roll back rather than debug forward on a live deployment. **The prior state is known-good and was working minutes earlier** |
| **Cost** | **A second sign-out.** Everyone signed in since the change is signed out again. Accepted — it is smaller than leaving the deployment in a failed state |

### What rollback provably does not touch

| | |
| --- | --- |
| **Identity authority** | `identity_source` is a different key and is not read or written. **Sign-in continues to use the `env` Microsoft configuration throughout** — the ability to sign back in never depended on this change |
| **`APP_KEY`** | A different key, never touched. `ensure-app-key.sh` writes it **only when absent**, and it is present |
| **Every other `.env` value** | Preserved byte for byte by the §5.2 mechanics — the same property that makes the forward change safe makes the reverse safe, because it is the same operation with a different value |
| **Schema and data** | Nothing was migrated forward, so nothing is migrated back. Rows written to `sessions` while the database driver was live simply stop being read; they are session state, not records |

**Rollback is symmetric with the forward change**, which is the strongest thing
that can be said for it: it is not a special recovery path exercised for the
first time under pressure.

---

## 8. The six mandatory proofs

**From the approved PLAN, CL-10. All six are required. None may be inferred.**

| # | Proof | Evidence | Status |
| --- | --- | --- | --- |
| **1** | **Pre-change checks completed** | P-1 … P-11 recorded with values and timestamps | **P-1 … P-11 ALL PASS**, P-5 now **directly observed** (§4A). Re-confirm immediately before the window |
| **2** | **Agreed maintenance / sign-out window** | Window agreed in advance and recorded | ☐ |
| **3** | **Rollback procedure ready** | §7 rehearsed; **P-10 server access verified before starting** | ☐ |
| **4** | **Real post-change Microsoft sign-in succeeds** | **Product Owner signs in for real.** Not a simulation, not an automated check | ☐ |
| **5** | **Production observed reporting `database`** | `verify-session-store` reports `Effective session driver : database` | ☐ |
| **6** | **The `sessions` table is actually being used** | §9 **Fact B**, B-0 → B-3. **Read §9's stated limitation before recording this** — it is a bounded correlation, not a unique identification | **B-0 DONE** (§4A). B-1 … B-4 ☐ — they require the change, which is not authorised |

> ### WHY PROOF 6 IS NOT PROOF 5
>
> **A deployment can report `database` while nothing has ever written a row.**
> Proof 5 is a claim about **configuration**; proof 6 is a claim about
> **behaviour**. Configuration that is correct and behaviour that is broken look
> identical from proof 5 alone — which is exactly the shape of the original
> defect, where a repository file was read and reported as deployment reality.
>
> **Both are required, and proof 6 is the one that would catch a real failure.**

---

## 9. Proving the switch worked — two separate facts

> **REBUILT, Product Owner review round 2.** The previous §9 rested on
> `COUNT(*) WHERE user_id IS NOT NULL`. **§2A proves that column is always
> NULL**, so that proof was not merely weak — **it was impossible.** One weak
> proof must not be swapped for another, so what follows states exactly what it
> establishes and exactly what it does not.

**Two facts, proven separately. Neither substitutes for the other.**

### Fact A — authentication works after the switch

| | |
| --- | --- |
| **A-1** | **The Product Owner performs a genuine Microsoft / SemantIQ sign-in.** Not simulated |
| **A-2** | **`/console` loads**, and **one authenticated console screen** loads |
| **What it proves** | The custom `auth.user_id` session path survives the driver change end to end — sign-in, session issue, `EnsureSessionIsCurrent` re-resolution, authorisation |
| **Why it stands alone** | It needs **no database observation at all.** A successful authenticated page load is itself the evidence |

### Fact B — Laravel is persisting sessions through the database

**The controlled window is what makes this defensible**, and it exists anyway:
the switch invalidates every pre-existing `file` session, so there is a natural
moment when **no authenticated session exists at all**.

| Step | Observation | What it establishes |
| --- | --- | --- |
| **B-0** | **Before the change:** `COUNT(*)` and `MAX(last_activity)`, taken **twice, minutes apart**. **COLLECTED — see §4A.** Runs `36391980170` and `36392333552`, 3m48s apart, both `0` / `null` | **Two identical readings establish that NO TABLE MOVEMENT WAS OBSERVED during the controlled pre-change interval.** This is the baseline the rest is measured against. **It does not mathematically prove that no write or delete could have occurred between the two samples** — a write and a compensating delete, or a write to an already-counted row, would not show. It is an observation over an interval, not a proof of inertness |
| **B-1** | **General users are instructed not to sign back in yet.** The Product Owner is the **first** to sign in | Bounds the window |
| **B-2** | **After A-1:** `COUNT(*)` and `MAX(last_activity)` again | **A table that showed no movement during the controlled pre-change interval now shows movement after the driver change and the controlled sign-in / request window.** The switch took effect in behaviour, not only in configuration |
| **B-3** | Product Owner makes **one controlled authenticated request** at a noted time; `MAX(last_activity)` read immediately before and after | **The store is READ and UPDATED**, not written once. This is what distinguishes a live session store from a one-off insert |
| **B-4** | Product Owner records **which screen and at what time** | Supplies the human half of the correlation |

### What this proves — and what it does not

| | |
| --- | --- |
| **Proven** | **Laravel is persisting and round-tripping sessions through the database.** That is CL-10's actual claim, and B-0 → B-3 establishes it |
| **NOT proven** | **That any particular row is the Product Owner's.** It cannot be, and no amount of care changes that: **`user_id` is NULL**, and session `id`, `payload`, `ip_address` and `user_agent` are all off-limits |
| **The residual gap, stated plainly** | An **anonymous visitor** reaching the sign-in page during the window also creates a row. The controlled window makes this unlikely; **it does not make it impossible.** So B-2 and B-3 are **a tightly-bounded correlation, not a unique identification** |

**That gap is acceptable for CL-10 and it is named rather than hidden.** CL-10
claims *"session persistence has moved to the database"* — a statement about the
**store**, which B-0's observed-no-movement baseline followed by observed movement
does establish. It never claimed *"this row belongs to this person"*, and after §2A
it could not.

**Closing the residual gap would require a committed diagnostic** — an endpoint
or command that reports a session's own row. **That is not built, not proposed
here, and would need its own approval.** Inventing one to reach certainty would
widen this pull request and add a production surface for the sake of a
measurement.

### Safety rules for every observation above

| Never | Always |
| --- | --- |
| `SELECT *` from `sessions` | Bounded aggregates and one timestamp |
| Session `id`, `payload`, `ip_address`, `user_agent`, cookie | `COUNT(*)`, `MAX(last_activity)` |
| Copying a row anywhere | Read in place, read-only, over the established SSH path |

**`WHERE user_id IS NOT NULL` is removed from this runbook entirely** — it would
return zero and, given §2A, could only mislead whoever ran it next.

---

## 9A. CL-11 / WS-2 design constraint — created by this finding

> **CL-11 DESIGN IS NOT STARTED.** This records a constraint it must satisfy; it
> does not begin it or choose its mechanism.

**CL-11 must establish a durable, authoritative mapping between each database
session and the SemantIQ principal whose privileges that session carries.**

| | |
| --- | --- |
| **The assumption this replaces** | *"The sessions table has a `user_id` column, so sessions are linked to users."* **The column exists; the link does not.** §2A |
| **The constraint** | CL-11 **must not assume Laravel's default `sessions.user_id` is that mapping.** Per-user revocation cannot target rows by a column that is always NULL |
| **The mechanism** | **Undecided, and deliberately so.** Binding SemantIQ identity to the Laravel Guard, writing the principal through a custom session handler, or maintaining a separate mapping are all candidates with different security consequences. **The Gate B DESIGN decides** |
| **Not a CL-10 blocker** | CL-10 moves session **persistence** to the database. That remains correct, necessary and unchanged — **it is still the prerequisite for CL-11**, because on the `file` driver there is nothing to target at all |

**This finding makes CL-11 larger than "read `sessions.user_id` and delete
rows".** Discovering that at Gate B design time is the point of having found it
now.

---

## 10. Success criteria

**All seven. Any one failing means the change has not succeeded.**

| | |
| --- | --- |
| **1** | Production reports **`SESSION_DRIVER=database`** |
| **2** | **A real Microsoft sign-in succeeds** |
| **3** | The **`sessions` table reflects live session behaviour** — §9 **Fact B**, steps B-0 → B-3 |
| **4** | **`GET /up`** → `ok` |
| **5** | **`GET /`** → `200` |
| **6** | **Every previously accepted console route still available** — `/console`, `/console/administration`, `/console/organisation`, `/console/people/users`, `/console/people/groups`, `/console/domains`, `/console/access`, `/console/security`, `/console/security/exceptions`, `/console/security/events`, `/console/access-reviews`, `/console/access-reviews/overdue`, `/console/system-health`, `/console/integrations`, `/console/audit`. **Same status codes as before the change** |
| **7** | **No identity or configuration regression** — `identity_source` still `env`, Microsoft configuration present, `APP_KEY` unchanged, no other `.env` value altered |

---

## 11. Failure criteria — roll back

**Any one of these triggers §7 immediately. Do not debug forward on production.**

| | |
| --- | --- |
| **Sign-in fails** — the Product Owner cannot complete a Microsoft sign-in |
| **A database session cannot be persisted or read** — no row appears, or `last_activity` never advances |
| **HTTP 500**, a **redirect loop**, or visible **session instability** (users signed out repeatedly, sessions not holding) |
| **Unexpected configuration drift** — any `.env` value other than `SESSION_DRIVER` differs, or file mode/ownership changed |
| **Health check failure** — `/up` not `ok`, or `semantiq:health` failing |

**"It will probably settle" is not a criterion.** If one of these is observed,
roll back and investigate with production restored.

---

## 12. Evidence capture

| What | Detail |
| --- | --- |
| **Timestamps** | Window start; change applied; window closed; each proof observed |
| **Change reference** | The workflow run ID (or operator session reference) that performed it |
| **Before** | `verify-session-store` output showing **`file`**; `verify-identity` showing **`env`**; the full P-1 … P-11 results. **ALREADY CAPTURED — §13.1**, with the B-0 baseline in §4A |
| **After** | `verify-session-store` showing **`database`**; `/up`; `/`; the console route sweep |
| **Fact A** | The Product Owner's own statement that a real Microsoft sign-in succeeded and that `/console` plus one authenticated screen loaded |
| **Fact B** | The **two identical B-0 baseline readings** taken before the change (establishing that no table movement was observed over that interval), the B-2 readings after sign-in, and the B-3 `MAX(last_activity)` either side of one controlled request, with B-4's note of which screen and when — **`COUNT(*)` and `MAX(last_activity)` only** |
| **Stated limitation** | The evidence record must carry §9's residual gap: this is **a bounded correlation, not a unique identification**, because `sessions.user_id` is NULL and no identifier may be exposed |
| **Product Owner** | **Their own statement that a real Microsoft sign-in succeeded**, and that the console behaved normally |
| **Rollback** | If triggered: what was observed, when rollback ran, and the restored-state evidence |

> **NO SECRETS IN THE EVIDENCE.** No `.env` line, no `APP_KEY`, no database
> host/name/user/password, no Microsoft tenant, client ID or secret, no session
> `id`, no session `payload`, no cookie. **The driver name is a configuration
> choice, not a credential — which is why it is the one value recorded verbatim.**

---

## 13. Product Owner GO / NO-GO

> # ✅ RUNBOOK APPROVED · ✅ TOOLING BUILT, TESTED, MERGED · ✅ EVIDENCE PACK COMPLETE
> # ⛔ FINAL PRODUCT OWNER DECISION: NO-GO FOR THE PRODUCTION MUTATION AT THIS MOMENT
>
> **This is not a technical rejection of CL-10.** The implementation, the
> rollback tooling, the evidence tooling, P-1 … P-11 and the empty/stable
> session-table baseline are **ready**. One control item remains open and it is
> not a code item: **no maintenance window has been agreed** (§13.3).

**Nothing in §6 has been executed.** Production still runs
`SESSION_DRIVER=file`. No `.env` was read for modification or written. No
session was affected. No maintenance window was opened for this change.
`align-session-driver.yml` **has never been dispatched — the Actions API
reports 0 runs for it.**

### 13.1 The pre-change evidence pack — COMPLETE as of 28 September 2026

**The gap that blocked the first go/no-go is closed.** P-5 is observed rather
than inferred, and the Fact B B-0 baseline exists. **Proof 6 was not waived
and nothing in it was fabricated**; the measurement that was missing was built
(PR #144), reviewed, merged and run.

| Item | Status | Evidence |
| --- | --- | --- |
| **P-1** application health | **PASS** | `/up` = `ok`, deployment run `36391676485` |
| **P-2** site responds | **PASS** | `/` = `200`, same run |
| **P-3** current driver is `file` | **PASS** | `verify-session-store` run **`36392412993`** |
| **P-4** configuration not cached | **PASS** | same run — `configuration_is_cached: False` |
| **P-5** sessions table exists | **PASS — DIRECTLY OBSERVED** | **§4A**, runs `36391980170` / `36392333552` — `session_table_exists: True`. **Previously INFERRED; no longer** |
| **P-6** database connectivity | **PASS** | both evidence runs reached the configured connection and returned aggregates |
| **P-7** migration status clean | **PASS** | deployment run `36391676485` — `INFO Nothing to migrate.` |
| **P-8** identity source is `env` | **PASS** | `verify-identity` run `35825123806` — `identity_source: env` |
| **P-9** Microsoft sign-in configuration present | **PASS** | deployment run `36391676485` — "All four Microsoft identity settings are present and non-empty. No value was read." |
| **P-10** rollback server access tested | **PASS** | every evidence run reaches the deploy path over the same SSH path a rollback uses |
| **P-11** no unrelated change in progress | **PASS** | no competing `cpanel-deploy` operation in the sampling window — §4A |
| **Fact B, B-0 baseline** | **COLLECTED** | **§4A** — two readings 3m48s apart, `0` / `null` both times |
| **Table state** | **EMPTY AND STABLE** | `session_row_count: 0` in both readings |

**Rollback readiness is unchanged and still stands.** Rollback is the **same
workflow and the same script** with `target: file` (§7). It was never a
separate code path, and P-10 is confirmed above.

### 13.2 Dispatch values — RECORDED, NOT EXECUTED

**These are written down so the operator does not have to compose them under
time pressure. Writing them here is not authorisation to type them.**

> ### ⚠️ CORRECTED — Product Owner final review, 29 September 2026
>
> **The earlier version of this table had a single "Rollback" row with the
> takeover field blank.** That is correct **only while production is still
> LIVE**. The merged workflow **refuses a `database` → `file` rollback when
> production is already in maintenance** unless the separate takeover
> confirmation is supplied — so the operator would have followed this runbook,
> been refused, and had to work out why **during an incident**, which is the
> worst possible moment to discover a runbook is wrong.
>
> **The workflow was right and is unchanged.** This is a runbook correction
> only. **The maintenance state is half of the decision**, and the table now
> says so.

**FOUR CASES. THE DRIVER ALONE DOES NOT DECIDE WHICH — read the maintenance
state first** (§5.4). If you do not know whether production is LIVE or in
MAINTENANCE, **establish it before dispatching anything**; the workflow reads
it too and will refuse rather than guess.

| # | Case | Production state | `target` | `confirmation` | `rollback_takeover` |
| --- | --- | --- | --- | --- | --- |
| **1** | **Forward change** | **LIVE** | `database` | `ALIGN SESSION DRIVER` | *(leave empty)* |
| **2** | **Rollback from a live `database` deployment** | **LIVE** | `file` | `ALIGN SESSION DRIVER` | *(leave empty)* |
| **3** | **Rollback while production is ALREADY in MAINTENANCE** — the driver is still `database` and a change failed mid-window | **MAINTENANCE** | `file` | `ALIGN SESSION DRIVER` | **`TAKE OVER MAINTENANCE FOR SESSION ROLLBACK`** |
| **4** | **Rollback completion** — the driver is already `file` but production is still down, which is where a rollback stops when its `.env` rewrite succeeded and a later step did not | **MAINTENANCE** | `file` | `ALIGN SESSION DRIVER` | **`TAKE OVER MAINTENANCE FOR SESSION ROLLBACK`** |

> ## ⛔ NEVER SUPPLY THE TAKEOVER PHRASE WITH TARGET `database`.
>
> **It exists only to recover, and recovery is always towards `file`.** The
> workflow refuses this combination before it reaches the server, and no
> situation makes it correct. If production is in maintenance and you believe
> you need to go forward to `database`, **the answer is to finish or undo the
> operation that owns that window first** — not to take it over.

**Why cases 3 and 4 both require the phrase.** In both, the window was opened
by somebody or something else, and `php artisan up` releases **whatever**
maintenance mode is in effect — including a window that belongs to a
deployment or to a person mid-operation. The phrase is the operator stating,
in words that cannot be produced by a stray click on a repeated dispatch,
that they intend to assume control of that window. **Case 4 rewrites
nothing**: no script runs, no `.env` is touched, no cache is cleared; the run
verifies the driver, checks health and brings production back up.

**What the workflow refuses, so the operator is not surprised by it:**

| Attempt | Result |
| --- | --- |
| Forward change (`database`) while production is in MAINTENANCE | **Refused, whatever is typed.** A forward alignment never assumes control of an existing window |
| Takeover phrase supplied with `target: database` | **Refused before any connection is made** |
| Driver already `database`, production in MAINTENANCE, target `database` | **Refused.** This is neither a no-op nor a completion — the recovery direction is always `file` |
| Rollback (`file`) while production is in MAINTENANCE, phrase **missing or misspelt** | **Refused.** Nothing on the server is written |
| Driver already `file`, production **LIVE**, target `file` | **No-op.** No window, no script, no cache clear, no interruption |

### 13.3 ⛔ BLOCKER — no maintenance window has been agreed

**Proof 2 of §8 requires an agreed maintenance / sign-out window, recorded in
advance. There is none.** That is the outstanding item, and it is the reason
the final decision is **NO-GO AT THIS MOMENT** rather than a technical
objection to CL-10.

| | |
| --- | --- |
| **Status** | **NOT AGREED** |
| **Who supplies it** | **The Product Owner, and nobody else.** Not the delivery team, not this runbook, not a default |
| **What must not happen** | **No date or time may be invented, assumed, proposed as a placeholder, or inferred from convenience.** A window nobody agreed to is not a window |
| **Why it gates everything** | The change **signs every user out**. Choosing when that happens is a business decision about real people's working day, not a deployment detail |

**Until the Product Owner supplies an exact window, nothing in §6 may be
dispatched — regardless of how complete the evidence pack is.**

### 13.4 The execution-day sequence, once a window exists

**Nothing below has been performed. It is the agreed order for the day, so it
does not have to be recalled under pressure.** The evidence in §4A and §13.1
was collected on 28 September 2026 and **is a record, not a substitute** — it
is re-taken on the day, because a pre-change baseline that is a day old is a
baseline of a different day.

| # | Step | Requirement |
| --- | --- | --- |
| **1** | Fresh **P-1 … P-11** | All eleven PASS, recorded with values and timestamps |
| **2** | Fresh **`verify-session-store`** | **`file`**, configuration **not cached** |
| **3** | Fresh **`verify-identity`** | **`env`** |
| **4** | Fresh session-table evidence **Reading 1** | `verify-session-table-evidence` — SUCCESS |
| **5** | **Wait at least 3 minutes**, with **no competing `cpanel-deploy` operation** | Confirmed from the Actions history, not assumed |
| **6** | **Reading 2** | SUCCESS |
| **7** | Compare | **Table still exists** and remains **EMPTY AND STABLE** |
| **8** | Confirm **no unrelated production change** is in flight | Clear |
| **9** | **Notify users** | Before the window opens, not during it |
| **10** | **Explicit production GO**, then dispatch | §13.5. **Step 10 is a separate decision from steps 1-9 passing** |

**If step 7 shows the table is no longer empty**, the driver switch is **not**
authorised by this pack: report the count and the latest activity timestamp
only, expose no row, delete nothing, inspect no payload, user or session
identifier, and **stop for Product Owner review** (§4A).

### 13.5 What is still required

**One thing now, not two.** The §5.3 tooling **exists, is tested and is
merged** — `deployment/ensure-session-driver.sh` and
`.github/workflows/align-session-driver.yml`, merged at
`2a70eeedf82f4c716d18d863c254411fd10999a3`, with the read-only evidence
workflow merged at `86760940c9633da1cd406488b11ba2937a954cb1`. **That closes
§5.4 state 2. It is not permission to run any of it.**

**What remains is the agreed window (§13.3) and the authorisation itself:**

```
Product Owner GO / NO-GO: ______

Agreed window (date and time):  ______
§5.3 tooling implemented, tested and merged (yes / no):  ______
```

**No execution until GO is supplied.** Approval of the Closeout PLAN was not
this authorisation. Neither is approval of this runbook, approval of the
tooling, approval of the read-only evidence workflow, or the fact that the
evidence pack is now complete. **A complete evidence pack is what makes the
decision possible; it is not the decision.**

---

## 14. Status

**WS-1 RUNBOOK APPROVED. TOOLING BUILT, TESTED AND MERGED. EVIDENCE PACK
COMPLETE. PRODUCTION CHANGE NOT EXECUTED AND NOT AUTHORISED.**

| | |
| --- | --- |
| `SESSION_DRIVER` | **still `file`** — confirmed live, `verify-session-store` run **`36392412993`**, 28 September 2026. Configuration **not cached**. Earlier confirmations retained: runs `35825117167` (23 September) and `35684301122` (at runbook approval) |
| Production switch | **NOT performed.** No `.env` read for modification or written; no session affected; no window opened for this change |
| `align-session-driver.yml` | **NEVER DISPATCHED — 0 runs** |
| **State 1 — runbook** | **APPROVED** — Product Owner, after amendment round 3 (§2A evidence reclassification, §9 B-0/B-2 restatement). Rounds 1 and 2 fully retained |
| **State 2 — tooling** | **CLOSED.** `ensure-session-driver.sh` and `align-session-driver.yml` merged at `2a70eeedf82f4c716d18d863c254411fd10999a3`; `verify-session-table-evidence.yml` merged at `86760940c9633da1cd406488b11ba2937a954cb1` |
| **Evidence pack** | **COMPLETE — §13.1.** P-1 … P-11 all PASS with P-5 **directly observed**, and Fact B **B-0 collected** (§4A). Proof 6 was **not waived and not fabricated** |
| **Table state** | **EMPTY AND STABLE** — `session_row_count: 0` in both baseline readings |
| **State 3 — production change** | **NOT AUTHORISED — final Product Owner decision, 29 September 2026: NO-GO AT THIS MOMENT.** Not a technical rejection; the one open item is the **un-agreed maintenance window** (§13.3) |
| **Maintenance window** | **NOT AGREED.** To be supplied by the Product Owner. **No date or time may be invented or assumed** |
| **§13.2 dispatch table** | **CORRECTED** — four operational cases, because a `database` → `file` rollback **while production is already in maintenance** requires the takeover phrase and the earlier three-row table did not say so |
| CL-11 | **Not started** |
| CL-12 | **Not started** |
| Phase 2 | **Untouched** |
