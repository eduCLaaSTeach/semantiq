# Phase 1 Closeout — WS-1: Session Driver Alignment Runbook

## CL-10 · `SESSION_DRIVER=file` → `SESSION_DRIVER=database`

> # ⛔ RUNBOOK READY — PRODUCTION CHANGE NOT AUTHORISED YET
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
| Status | **RUNBOOK READY. NOT EXECUTED.** |
| **Blocking finding** | **A tooling gap exists — §5.3. No existing mechanism can perform this change safely.** |

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

**`user_id` already exists on the sessions table and nothing reads it.** The
table was prepared for per-user revocation; **the control does not exist.** This
runbook does not create it.

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
| **P-5** | Sessions table exists | Read-only check on the server | Present |
| **P-6** | Database connectivity | `php artisan migrate:status` over SSH | Runs and returns; connection healthy |
| **P-7** | Migration status clean | Same command | **Nothing pending.** A pending migration means an unrelated change is mid-flight |
| **P-8** | **Identity source is still `env`** | Dispatch **`verify-identity`** (read-only) | **`env`.** CL-08 is deferred; if this reads `store`, an unauthorised cutover has happened and **this change must not proceed** |
| **P-9** | Microsoft sign-in configuration present | The deployment's identity step, or `verify-identity` | Present. **Without it, a signed-out user cannot sign back in** |
| **P-10** | **Server access for rollback is available and tested** | SSH reaches the deploy path **before** the change | Confirmed. **Never start a change whose rollback path is unverified** |
| **P-11** | No unrelated deployment or change in progress | GitHub Actions — no running deploy; no open production change | Clear |

### Backup and recovery posture

**This is a configuration-only change to a single key.** It writes no row,
drops no table and alters no schema.

| | |
| --- | --- |
| What could be lost | **The one `.env` line being changed**, if the write went wrong |
| Why the posture is sufficient | The change mechanism (§5) **preserves every other byte**, writes atomically, and **verifies mode and ownership after the rename**. The prior value is `file` — a known constant recorded in this runbook, not something that needs recovering from a backup |
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

### 5.2 The proven mechanics that must be reused

`ensure-session-lifetime.sh` already solved every hazard of editing a live
secrets file, and its reasoning is recorded in the script itself. **Any
mechanism for CL-10 must reuse all of it:**

| Hazard | How it is already solved |
| --- | --- |
| **The rename swaps the inode**, so the new file carries the *temporary* file's mode and owner | Mode and ownership are **recorded first, restored before the rename, and VERIFIED after it** |
| A temporary copy of `.env` is a second copy of every secret | Created under **`umask 077`**, removed by a **trap on every exit path** |
| A run killed by an untrappable signal leaves that copy behind | A **sweep of stale temporaries before writing a new one**, bounding exposure to a single interrupted run — *found by deliberately killing the script mid-write, not by reading it* |
| A non-atomic replace can truncate the file | **Same-directory `mv -f`** — a rename across filesystems is not atomic |
| The edit might not land, or might move something else | The result is **verified** before the rename |
| Secrets could leak into logs | **No value from `.env` is ever printed** |
| The script becoming a general `.env` editor | *"This script changes ONE key. It is not an .env editor and must never become one."* |

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

**Recommended shape, for a separate approval:**

| | |
| --- | --- |
| **What** | `deployment/ensure-session-driver.sh` — modelled on `ensure-session-lifetime.sh`, changing **one key**, preserving every other byte, with the same mode/ownership/atomicity/umask/trap/sweep/verify mechanics |
| **Plus** | A **manual-dispatch** workflow to run it, modelled on `verify-session-store.yml`'s SSH handling (the deploy key is passphrase-protected and needs ssh-agent with askpass — writing the key file alone yields *"Permission denied (publickey)"*) |
| **Target value** | Literal `database`. **Not** derived from `.env.example` — reading a repository file and reporting it as deployment reality is precisely how the original wrong claim was made |
| **Why not the deployment workflow** | `deploy.yml` runs on **every push to `main`**. A driver switch must happen **once, in a chosen window, behind a go/no-go** — not on every documentation merge |
| **Why not a manual SSH edit** | It has none of §5.2's guarantees. Hand-editing the file that holds every secret, with no atomicity and no mode/ownership verification, is the failure `ensure-session-lifetime.sh` was written to prevent |

**Rejected: a second production mutation path.** The instruction is to reuse
existing safe mechanics rather than invent a parallel one, and the proposal
above is deliberately a **sibling** of the existing script, not a new pattern.

### 5.4 Cache handling after the change

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
| **1** | **Pre-change checks completed** | P-1 … P-11 recorded with values and timestamps | ☐ |
| **2** | **Agreed maintenance / sign-out window** | Window agreed in advance and recorded | ☐ |
| **3** | **Rollback procedure ready** | §7 rehearsed; **P-10 server access verified before starting** | ☐ |
| **4** | **Real post-change Microsoft sign-in succeeds** | **Product Owner signs in for real.** Not a simulation, not an automated check | ☐ |
| **5** | **Production observed reporting `database`** | `verify-session-store` reports `Effective session driver : database` | ☐ |
| **6** | **The `sessions` table is actually being used** | §9 — real row behaviour | ☐ |

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

## 9. Database-session behavioural proof

### The rule

**Prove behaviour without exposing session contents.** A session row contains a
serialised payload and an identifier that is, in effect, a live credential.

| Never | Always |
| --- | --- |
| `SELECT *` from `sessions` | Aggregates, and bounded non-sensitive columns only |
| Print or log `payload` | Ignore it entirely |
| Print or log a session `id` | Count rows, don't identify them |
| Copy a row anywhere | Read in place, read-only |

### The proof

**A real authenticated session must create and update a row.** The sequence
proves behaviour rather than configuration:

| Step | Read-only observation | What it proves |
| --- | --- | --- |
| **A** | `SELECT COUNT(*) FROM sessions;` **before** the Product Owner signs in | The starting point, established rather than assumed |
| **B** | Product Owner signs in with Microsoft (proof 4) | A real authenticated session now exists |
| **C** | `SELECT COUNT(*) FROM sessions;` again | **Count increased** — the store is being **written** |
| **D** | `SELECT COUNT(*) FROM sessions WHERE user_id IS NOT NULL;` | **At least one authenticated row** — not merely an anonymous visitor row |
| **E** | Product Owner navigates a console screen, then re-read `SELECT MAX(last_activity) FROM sessions;` | **`last_activity` advanced** — the store is being **read and updated**, not just written once |

**Step E is what makes this a behavioural proof.** A row appearing could be a
single write; a row whose `last_activity` moves as the user navigates proves the
application is genuinely round-tripping the session through the database.

**Run these as read-only statements**, via the established SSH path. **No
`payload`, no `id`, no `ip_address`, no `user_agent` is selected, printed or
recorded** — the counts and the timestamp are sufficient and carry nothing
sensitive.

### A built-in cross-check that already exists

`verify-session-store` **already fails** if the driver is `database` while the
P1-09 System Health row still reads *Not checked* — and fails in the other
direction too. **It is a guard, not a narration:** after the change it must
report `database` *and* a consistent System Health row, or it errors. That
existing assertion is part of the evidence for proofs 5 and 6 and needs nothing
new built.

---

## 10. Success criteria

**All seven. Any one failing means the change has not succeeded.**

| | |
| --- | --- |
| **1** | Production reports **`SESSION_DRIVER=database`** |
| **2** | **A real Microsoft sign-in succeeds** |
| **3** | The **`sessions` table reflects live session behaviour** — §9 A–E |
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
| **Before** | `verify-session-store` output showing **`file`**; `verify-identity` showing **`env`**; the full P-1 … P-11 results |
| **After** | `verify-session-store` showing **`database`**; `/up`; `/`; the console route sweep |
| **Behavioural** | The §9 A–E counts and `last_activity` values — **counts and timestamps only** |
| **Product Owner** | **Their own statement that a real Microsoft sign-in succeeded**, and that the console behaved normally |
| **Rollback** | If triggered: what was observed, when rollback ran, and the restored-state evidence |

> **NO SECRETS IN THE EVIDENCE.** No `.env` line, no `APP_KEY`, no database
> host/name/user/password, no Microsoft tenant, client ID or secret, no session
> `id`, no session `payload`, no cookie. **The driver name is a configuration
> choice, not a credential — which is why it is the one value recorded verbatim.**

---

## 13. Product Owner GO / NO-GO

> # RUNBOOK READY
> # ⛔ PRODUCTION CHANGE NOT AUTHORISED YET

**Nothing here has been executed.** Production still runs `SESSION_DRIVER=file`.
No `.env` was read for modification or written. No session was affected. No
maintenance window was opened.

**Two things are required before step 1 of §6:**

1. **Resolution of the §5.3 gap.** No existing mechanism can safely change
   `SESSION_DRIVER`. The recommended `deployment/ensure-session-driver.sh` plus
   a manual-dispatch workflow **has not been written** and needs its own
   approval. **A GO alone does not make this change performable.**
2. **This explicit authorisation:**

```
Product Owner GO / NO-GO: ______

Agreed window (date and time):  ______
Approved to resolve the §5.3 gap first (yes / no):  ______
```

**No execution until GO is supplied.** Approval of the Closeout PLAN was not
this authorisation, and neither is approval of this runbook.

---

## 14. Status

**WS-1 PREPARATION COMPLETE. NOT EXECUTED.**

| | |
| --- | --- |
| `SESSION_DRIVER` | **still `file`** |
| Production switch | **NOT performed** |
| §5.3 gap | **Identified, NOT implemented** |
| CL-11 | **Not started** |
| CL-12 | **Not started** |
| Phase 2 | **Untouched** |
