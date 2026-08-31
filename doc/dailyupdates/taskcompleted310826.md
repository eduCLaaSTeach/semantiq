# Daily Development Handover — 31 August 2026

**Project:** SemantIQ v2.2
**Current phase:** Phase 1 — P1-BASE Application Baseline
**Session date as recorded by the product owner:** 31 August 2026

> **Timestamp note.** Every GitHub Actions run and commit referenced below carries a
> UTC timestamp of **2026-08-30**. The session is recorded here as 31 August at the
> product owner's direction. If a future reader compares this header against the run
> logs and finds a one-day difference, that is the reason — the evidence IDs, not the
> header, are authoritative for correlating runs.

This is a technical handover and restart checkpoint, not a meeting summary. Failures
are retained even where they were subsequently fixed, because the failures are the
evidence that the gates work.

---

## 1. Starting state

| Item | State at start of session |
| --- | --- |
| P1-BASE PLAN | Approved |
| P1-BASE DESIGN | Approved with four mandatory corrections |
| Implementation | PR #34 open, CI green, not merged |
| Backend | Laravel 13 / PHP 8.5 |
| Frontend | Inertia + React 19 + Vite (D-07) |
| Document root | Unknown at start; **D-08B confirmed during the session** |
| MySQL provisioning (D-05) | Completed by the product owner |
| APP_KEY | Already present on the server and independently verified valid |
| P1-00 | **LOCKED** |

The server already held a valid Laravel `APP_KEY` from the D-05 provisioning. That
fact changed the deployment behaviour materially — see §3 and §7.

---

## 2. Product-owner decisions made today

| Decision | Outcome |
| --- | --- |
| **D-08** | **D-08B confirmed.** cPanel does not offer the required `public_html/public` arrangement |
| Document root | Remains **`public_html`** |
| Consequence | Hardened root-forwarder architecture is **mandatory**, and is the security boundary rather than a defence-in-depth extra |
| Source of truth | GitHub `main`. The server never becomes the source of truth |
| Deployment model | **GitHub → GitHub Actions → SSH/rsync → cPanel**, exclusively |
| Manual server edits | Not permitted as normal deployment practice. No cPanel File Manager uploads, no manual copying outside the workflow, no bypassing Actions because SSH exists |
| APP_KEY | **Must be preserved.** Never regenerated |
| APP_KEY bootstrap | INITIAL deployment may generate a key **only** when absent, empty or malformed. **That path was not required** — the server key was already valid, so the preservation branch ran on both deployments |
| P1-00 | Remains **blocked** pending product-owner acceptance of P1-BASE |

If an SSH diagnostic ever finds a defect: fix in Git → test → PR → merge → redeploy
through Actions. Never repair production by hand and leave GitHub behind.

---

## 3. PR #34 — P1-BASE implementation

**Scope:** Laravel 13 baseline, Inertia + React 19 + Vite, the `Platform` module,
three-product-area shell architecture with zero navigation nodes, deny-by-default
authenticated area, framework-only schema, configuration validation, health checks,
CI with a MySQL 8.4 migration gate, and the deployment workflow with INITIAL/EXISTING
detection and an enforced rsync exclusion contract.

| | |
| --- | --- |
| Merge commit | `996cd9f238551020dc1aac037b07f9600a8280fa` |
| CI on `main` | [33322144289](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33322144289) — success |
| Deployment run | [33322144286](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33322144286) — **failure** |
| Detected state | **INITIAL** |

**Succeeded (23 of 24 steps):** rsync exclusion pre-flight · SSH authentication ·
state detection · maintenance window correctly **skipped** on the INITIAL path ·
sync to cPanel · runtime directories · environment and APP_KEY (**preserved**) ·
`migrate --force --no-interaction` · `optimize:clear` · `semantiq:health` over SSH ·
HTTPS site verification.

**Failed (1 step):** **Web-exposure negative tests.** `/app/` returned **302** where
the gate requires 403 or 404.

**The gate worked correctly and prevented false acceptance.** The deployment was
otherwise entirely successful, Laravel was live and serving, and every other check
passed. Without this gate the release would have been reported as complete while a
security criterion was unmet.

---

## 4. First deployment — defect analysis

Three findings. All are retained as security lessons.

### 4.1 `/app` namespace collision

The authenticated application boundary had been mounted at `/app`, while `app/` is
also a protected Laravel source directory. Under D-08B the whole tree is deployed
inside the document root, so the forwarder is **obliged** to refuse `/app/` to protect
the source on disk — and a URL prefix of that name can therefore never be served.

The application route conflicted with the filesystem security boundary. They cannot
coexist under D-08B.

**Nothing was exposed.** Verified during the investigation: every file beneath
`/app/` returned 404 with zero source bytes, including
`app/Modules/Platform/Http/Controllers/EntryController.php`,
`app/Providers/AppServiceProvider.php` and
`app/Modules/Platform/Support/ConfigurationValidator.php`. `/app/Modules/` and
`/app/anything` also returned 404. The 302 came from the application's own
deny-by-default redirect, not from directory serving.

### 4.2 `/storage` collision

Laravel's built-in `/storage/{path}` serving route conflicts with the protected
`storage/` deployment-root path. Under D-08B it is equally unreachable.

This was **not** found by inspection — it was found by the new regression test the
moment it was written, having gone unnoticed until then.

### 4.3 `.htaccess` defence-depth finding

The live deployment showed protected paths returning **Laravel 404** responses rather
than **Apache 403** denials.

This demonstrated that the forwarding layer was doing the actual protection and the
expected secondary Apache denial behaviour was **not** being observed. The file
claimed two independent guards; only one was doing any work.

**This finding is unresolved.** See §8.

---

## 5. PR #35 — correction

**Purpose:** make the D-08B exposure gate pass without weakening it.

| Change | Detail |
| --- | --- |
| Authenticated boundary | `/app` → **`/console`** |
| Laravel storage serving | Disabled (`config/filesystems.php`, `'serve' => false`) |
| `RoutePrefixCollisionTest` | New architecture regression guard — walks the real route table and fails any route beginning with a directory the forwarder blocks. A companion test asserts the protected list still matches the forwarder |
| `.htaccess` | All denials rewritten as `mod_rewrite` rules placed **before** the catch-all forwarder, so ordering is deterministic within one module. `FilesMatch` retained afterwards as an independent mechanism |
| Tests | **34 → 44** |

| | |
| --- | --- |
| Merge commit | `6f68a62475790eb03d28af4a101069ca8c098d95` |
| CI on `main` | [33322650103](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33322650103) — success |
| Deployment run | [33322650107](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33322650107) — success |
| Steps | **All 24 passed** |

---

## 6. Production verification completed

Observed against the live deployment, cache-busted, after the PR #35 redeployment.

| Item | Observed |
| --- | --- |
| Deployment state | **EXISTING** |
| Maintenance mode | Opened and closed correctly around sync and migration |
| APP_KEY | **Preserved.** Preservation branch taken; value never printed |
| Production MySQL migration | Successful |
| Schema | **`migrations` and `sessions` only** |
| `.env` | Survived |
| `.well-known/` | Survived — 200, ACME renewal intact |
| `storage/` | Survived; runtime directories writable |
| `/` | 200 — Laravel Inertia pre-auth page |
| `/up` | 200, body `ok` |
| `/console` (browser) | 302 → `/` — deny-by-default |
| `/console` (JSON) | 401, `{"message":"Unauthenticated."}` — no protected payload |
| `/app` and `/app/` | **404** — protected, no longer application routes |
| Old static test page | Removed |
| Navigation / shell leakage | **Zero** |
| Exposure tests | **29 / 29 passed** |
| Secret markers, source, server paths, stack traces | **None observed** |

Both deployment paths of the state machine are now proven: INITIAL skipped the
maintenance window because there was nothing to place into it; EXISTING opened and
closed it around the sync and migration.

---

## 7. Other defects found and corrected earlier in the session

Retained because each was silent and each was found by allowing a check to fail.

1. **The rsync exclusion guard did not guard anything.** Deleting the `.env`
   exclusion left the test green, because `--exclude ".env.example"` satisfied a
   pattern searching for `".env"` — it had no trailing boundary. Found by
   deliberately breaking the workflow. A guard that cannot fail is worse than none,
   because it is trusted. Now anchored; breaking it fails, restoring it passes.
2. **`/up` returned a 500 stack trace when the database was unreachable.** It sat
   inside the `web` middleware group, whose session driver is `database`, so the
   liveness route could not answer at the one moment a monitor needs it. Moved
   outside the group; tests assert 503-not-500 and that no cookie is set.
3. **Six architecture tests had never executed.** `phpunit.xml` declared only the
   Unit and Feature suites, so `tests/Architecture` was skipped silently — which is
   why defect 1 survived being written.
4. **The APP_KEY runbook contained a deadlock.** The environment check refused an
   empty `APP_KEY`, but before the first deployment there is no remote `artisan` to
   generate one. Resolved by generating exactly once on the INITIAL path after the
   application has been transferred, and never on an UPDATE.

---

## 8. RESOLVED — SECURITY FOLLOW-UP (superseded 31 August 2026)

> **STATUS: RESOLVED by PR #38. The finding recorded below is wrong.**
>
> It is kept, not deleted, because the observation was accurate and only the
> inference drawn from it was mistaken — and because the way it was mistaken is
> worth remembering.
>
> **Observed:** all 29 protected paths returned Laravel 404; none returned an
> Apache 403. **Inferred:** the Apache deny rules were not executing, and the
> forwarder was the only working protection.
>
> **Actually true:** the deny rules executed every time. Apache serves a denial
> through its `ErrorDocument` chain, and the inherited path-valued
> `ErrorDocument` re-entered the rewrite engine, met the forwarder, reached
> Laravel and returned 404. The refusal always happened; the reported status did
> not survive the round trip.
>
> A quoted-literal `ErrorDocument 403` — answered from memory, with no internal
> redirect — made the true status visible. Four mechanisms then measured **403**
> in one request cycle: mod_rewrite `[R=403,L]`, mod_rewrite `[F,L]`, mod_alias
> `RedirectMatch 403`, and `<Files>` + `Require all denied`. A guarded real file
> returned 403 while its unguarded sibling returned 200, proving the file was
> reachable and the directive refused it. `.env`, `/vendor/` and `/app/` now
> return **403**.
>
> **Answering the three options below:** none was needed. `AllowOverride` was
> never the cause — the caution in refusing to state it as fact was justified.
> D-08A stays closed. And the forwarder is not a single boundary: four
> mechanisms deny, and the `.htaccess` checksum control means the boundary
> cannot be edited on the server without failing the deployment.
>
> **Nothing was ever exposed** — no protected path returned anything but a
> refusal in any run. The cost was three weeks of being unable to tell a working
> boundary from a broken one, which is why the exposure gate now **requires 403**
> and a fall-through to Laravel fails the deployment.
>
> Full evidence: `doc/v2/phase-1/APACHE-DENIAL-CAPABILITY-DIAGNOSTIC.md`.

<details>
<summary>Original finding, retained as written (superseded)</summary>

> **Do not lose this. It is not a gate failure. It is an unresolved weakness.**

All 29 exposure tests passed. **All 29 returned Laravel 404 responses; none returned
an Apache 403 denial.**

Therefore the currently observed D-08B protection appears to depend primarily on the
forwarding behaviour rather than on two independently demonstrated server-side
layers.

PR #35 rewrote the `.htaccess` denials into a single `mod_rewrite` block specifically
to correct this, on the reasoning that mixing modules had made ordering unreliable.
**That prediction was wrong** — the live result after redeployment was identical:
still zero 403 responses.

Possible causes include hosting-level `AllowOverride` or other Apache configuration
restrictions. **This has not been proven and must not be stated as established
fact.** It cannot be established from outside the hosting panel.

**Why it matters:** under D-08B the application tree sits inside the document root.
If the forwarding rewrite were ever broken or removed, requests would map directly
onto real files, with no second mechanism demonstrated to stop them.

### Possible future choices — none selected

1. Ask the hosting provider / cPanel support to confirm the applicable Apache
   `AllowOverride` configuration for this domain.
2. Investigate whether **D-08A** can be made available by hosting configuration.
3. Explicitly accept the current hardened forwarder as a **single change-controlled
   security boundary**, backed by the automated live exposure tests that run on every
   deployment.

**No decision has been made. Do not silently select one.**

</details>

---

## 9. PR #36

**Documentation and evidence only. It contains no application functionality.**

At the time of this handover it holds the observed P1-BASE deployment verification
evidence, and this daily update document.

**Do not merge it as part of the daily-update task unless separately authorised.**

---

## 10. Current P1-BASE status

```
P1-BASE BUILD VERIFIED — READY FOR PRODUCT OWNER ACCEPTANCE
```

**but:**

```
P1-BASE HAS NOT YET RECEIVED FINAL PRODUCT OWNER ACCEPTANCE.
```

The unresolved D-08B defence-depth observation in §8 remains for product-owner
review. A successful deployment is not acceptance.

---

## 11. What must NOT happen next

- Do **not** start P1-00.
- Do **not** implement Microsoft Entra SSO.
- Do **not** implement first-admin bootstrap.
- Do **not** create users or groups.
- Do **not** create organisation schema.
- Do **not** create roles, domains, scopes or sensitivity.
- Do **not** implement Fabric.
- Do **not** implement Power BI.
- Do **not** implement AI.
- Do **not** implement Workplace.
- Do **not** perform speculative refactoring.
- Do **not** change deployment or security architecture without a product-owner
  decision.

---

## Resume Here Tomorrow

1. **Read this daily update first.**
2. Read `doc/v2/phase-1/P1-BASE-APPLICATION-BASELINE-VERIFICATION.md`.
3. Check the current state of `main` — it should be at `6f68a62` unless something
   has changed since.
4. Check the status of **PR #36** (documentation only — merged, open, or closed).
5. **Do not assume product-owner acceptance has been given.** It had not been given
   when this handover was written.
6. Present the unresolved **D-08B defence-depth observation** from §8, with its three
   options, and do not choose among them.
7. **Wait for the product owner's decision.**
8. Only after an explicit `P1-BASE ACCEPTED` may planning for **P1-00** begin. P1-00
   planning starts by bringing back **D-03** (first-administrator bootstrap method)
   and **D-04** (Microsoft Entra ID app registration), both of which were deferred
   and remain undecided.

---

## 12. Evidence references

| Item | Reference |
| --- | --- |
| PR #34 — P1-BASE implementation | https://github.com/eduCLaaSTeach/semantiq/pull/34 |
| PR #34 merge commit | `996cd9f238551020dc1aac037b07f9600a8280fa` |
| PR #34 CI on main | [33322144289](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33322144289) — success |
| PR #34 deployment (INITIAL) | [33322144286](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33322144286) — **failure**, exposure gate |
| PR #35 — exposure gate correction | https://github.com/eduCLaaSTeach/semantiq/pull/35 |
| PR #35 merge commit | `6f68a62475790eb03d28af4a101069ca8c098d95` |
| PR #35 CI on main | [33322650103](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33322650103) — success |
| PR #35 deployment (EXISTING) | [33322650107](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33322650107) — success, 24/24 |
| PR #36 — documentation and evidence | https://github.com/eduCLaaSTeach/semantiq/pull/36 |
| Verification document | `doc/v2/phase-1/P1-BASE-APPLICATION-BASELINE-VERIFICATION.md` |
| Phase 1 plan and decision register | `doc/v2/phase-1/PHASE-1-PLAN.md` |
| P1-BASE design | `doc/v2/phase-1/P1-BASE-APPLICATION-BASELINE-DESIGN.md` |
| Architecture baseline | `doc/SemantIQ_v2.2_Ground_Zero_Architecture_Reset_Three_Phase_Blueprint.md` |
| Phase 1 authority | `doc/SemantIQ_v2_PHASE_1_System_Administration.md` |
| UI standard | `doc/design-system/ui-and-ux-layout-template-shared.md` |

No credential, `APP_KEY` value, database credential, SSH material, `.env` content or
other secret appears in this document, and none may be added to it.

---

## 13. Daily update convention

From now on, at the end of every working day or session, maintain exactly one file:

```
doc/dailyupdates/taskcompletedddmmyy.md
```

`dd` day · `mm` month · `yy` two-digit year.

If multiple work sessions occur on the same day, **update that same day's file** —
never create numbered duplicates.

Each daily file captures: work completed · decisions · PRs and commits · CI and
deployment · defects discovered · fixes · verification evidence · unresolved issues ·
blockers · product-owner decisions still required · current acceptance status · the
exact resume point.

This convention is permanent for the SemantIQ project unless the product owner
explicitly changes it. The daily update **supplements** the architecture and
verification documents; it does not replace them.
