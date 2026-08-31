# Daily Development Handover — 31 August 2026

**Project:** SemantIQ v2.2
**Current phase:** Phase 1 — P1-01 Organisation (implemented, awaiting acceptance)
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
P1-BASE ACCEPTED — 31 August 2026
```

Accepted by the Product Owner against the verified production baseline at
`3d075bfbf80392651577abe256526575632b3e73`. **P1-BASE is closed.**

The D-08B observation recorded in §8 is **resolved**, not outstanding — see §8
for the correction and §11 for the evidence. The forwarder was never the only
protection; the deny rules had been firing all along and only their reported
status was wrong.

---

## 11. P1-BASE final acceptance evidence

The delivery ran to four pull requests after PR #36. All are recorded, including
the deployment that failed.

### The pull requests

| PR | Purpose | Merge SHA | CI | Deployment |
| --- | --- | --- | --- | --- |
| #38 | Apache denial-capability matrix (authorised diagnostic) | `d44f8e0` | [33349778947](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33349778947) | [33349882458](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33349882458) — success |
| #39 | PR 1 — diagnostics removed, boundary hardened | `e4830ee` | [33353084363](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33353084363) | [33353179847](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33353179847) — success |
| #40 | PR 2 — final `public_html` root layout | `da7ddea` | [33354172485](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33354172485) | [33354267648](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33354267648) — **FAILED** |
| #41 | PR 2a — corrective: prove ACME end to end | `3d075bf` | [33354599929](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33354599929) | [33354691876](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33354691876) — success, 27/27 steps |

### PR #40's failed deployment — kept, not smoothed over

The layout migration itself succeeded. **One check failed, and the check was
wrong.**

`Options -Indexes` turned `/.well-known/` from a 200 directory listing into a
403, and the gate asserted "not 403" — so it failed the deployment on a
certificate path that was working correctly.

**This defect was mine.** `-Indexes` was added in PR #40 without revisiting a
gate written against the old behaviour.

TLS renewal was never at risk, and one measurement settles it: a *missing*
challenge token returns **404, not 403**. The request resolves to the filesystem
and only the file is absent; an existing file fails the `!-f` condition and is
served directly. Let's Encrypt writes one file and reads it back — it never
lists the directory.

The check could have been relaxed to accept 403. That would have concealed the
fact that renewal had never actually been verified, only a proxy for it. PR #41
replaced the proxy with the mechanism: write a token, fetch it over HTTPS,
require 200 with the exact body, remove it under a `trap`.

> **A gate that fails on a healthy system gets ignored, which is worse than not
> having it.**

Two further defects were caught by rehearsal **before** reaching production: a
`grep && exit` guard that would have aborted assembly under `set -e` on its
*passing* path, and an asset check reading manifest paths in the wrong form,
which would have failed every deployment unconditionally.

### The 17-point acceptance evidence

| # | Item | Result |
| --- | --- | --- |
| 1–2 | PR 1 (#39) `e4830ee` | CI and deployment success |
| 3 | Strengthened 403 gate | **26/26 paths 403**, 9-byte bodies, zero leaks |
| 4–5 | PR 2 (#40) `da7ddea`, PR 2a (#41) `3d075bf` | final deployment success, 27/27 steps |
| 6 | Root front controller | `/index.php` **200**; SHA-256 verified; resolves siblings |
| 7 | No `public_html/public` dependency | `SEMANTIQ_PUBLIC_ABSENT`; `/public/index.php` **403**; site 200 |
| 8 | Apache exposure | all 26 protected paths **403**, no secret, path, source or trace |
| 9 | Checksums | `.htaccess` **MATCH**, `index.php` **MATCH** |
| 10 | `.well-known` / ACME | file written, fetched, **200 exact body**; missing token 404; listing 403; token removed |
| 11 | APP_KEY | **preserved** — `EXISTING` path, never regenerated |
| 12 | `.env` | **preserved** — excluded from transfer and deletion |
| 13 | MySQL / migrations | ran against production, success |
| 14 | Application health | `semantiq:health` **5/5 OK** over SSH |
| 15 | Assets | `/build/assets/*.css` and `*.js` **200**; `/build/` listing **403** |
| 16 | Unresolved risk | **none outstanding** |
| 17 | Recommendation | READY FOR ACCEPTANCE → **ACCEPTED** |

### Final hosting architecture, in force

```
cPanel document root          = public_html
deployment root               = public_html
production front controller   = public_html/index.php
public_html/public            = not a required production layer
```

Full detail: `doc/v2/phase-1/HOSTING-ARCHITECTURE.md`.

Test suite at acceptance: **64 tests, 185 assertions.**

---

## 12. P1-00 — Login, Microsoft Entra SSO and first-run bootstrap

Delivered, deployed and verified against the real eduCLaaS Entra tenant on
31 August 2026. **Not accepted** — acceptance is the Product Owner's.

### Lifecycle

| Stage | Evidence |
| --- | --- |
| PLAN | `P1-00-LOGIN-BOOTSTRAP-PLAN.md` — approved, decisions D-03, D-04, D-09, D-10, D-11, D-12 |
| DESIGN | `P1-00-LOGIN-BOOTSTRAP-DESIGN.md` — approved, D-03.1 and D-13 |
| EXECUTE | PR [#45](https://github.com/eduCLaaSTeach/semantiq/pull/45) · merge `6aa4ab0` |
| Correction | PR [#46](https://github.com/eduCLaaSTeach/semantiq/pull/46) · merge `08d7bd2` |
| Tooling fix | PR [#47](https://github.com/eduCLaaSTeach/semantiq/pull/47) · merge `72b3f4d` |

### The production defect — kept, not erased

The first live sign-in **failed**. The server log carried one line:

```
auth.login.refused.protocol {"result":"refused","reason":"token_signature_invalid"}
```

**Root cause.** `JWK::parseKeySet($jwks)` was called without a default
algorithm. Microsoft's real Entra JWKS omits the per-key `alg` field, and
php-jwt throws `UnexpectedValueException: JWK must contain an "alg" parameter`
for such a key when no default is given. The whole key set failed to parse, so
**no signature was ever checked**. The rotation-retry path did not engage — it
only refetches when the error message mentions `kid` — so the failure fell
through to `token_signature_invalid`, naming the wrong cause.

**Why CI was green while production was broken.** The test JWKS included `alg`.
The fixture was more helpful than the real thing, so it tested the fixture
rather than the system. That is the lesson worth keeping from this unit.

**Correction.** Keys are filtered before parsing — RSA only, `use: sig` when
present, `alg: RS256` when present, `kid`/`n`/`e` required — then parsed with
RS256 as the explicit default. The fixture now mirrors Entra exactly, and the
pre-fix validator **fails seven tests against it**.

The first bootstrap grant was issued before this defect was found and could not
be redeemed. It was **not reused** and **no database record was edited**; it was
left to expire, and a fresh grant was issued after the fix.

A second, smaller defect: the read-only verification workflow failed its first
run with `Permission denied (publickey)`. The cPanel deploy key is
passphrase-protected and the workflow only wrote the key file instead of
loading it into an agent. A workflow defect, not a production one.

### Live verification — 31 August 2026

**Microsoft Entra authentication succeeded end to end.** The nominated first
System Administrator signed in through Entra and reached `/console`.

Server state, from the read-only verification workflow
([33371191488](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33371191488)),
counts only — no identity, grant or secret read:

```json
{"bootstrap_state":"CONFIGURED","active_system_admins":1,"users_total":1,
 "grants_total":2,"grants_consumed":1,"grants_still_open":0}
```

| Claim | Evidence |
| --- | --- |
| Bootstrap closed | `bootstrap_state = CONFIGURED` |
| Exactly one administrator | `active_system_admins = 1`, `users_total = 1` |
| The successful grant was consumed | `grants_consumed = 1` |
| No usable grant remains | `grants_still_open = 0` — the failed first grant expired unused |
| No self-registration | `users_total = 1`; only the bootstrapped administrator exists |

| Check | Result |
| --- | --- |
| `/` · `/up` · `/console` anonymous | 200 · `ok` · 302 to login |
| `/console` anonymous, JSON client | **401** |
| `GET /auth/logout` | **405** — POST only |
| `POST /auth/logout` without session | **419** — CSRF enforced |
| Invented 64-character grants (×2) | 302 to closed; no administrator creatable |
| Six refusal states | 200, no trace, framework internals, token, nonce or role mapping |
| 17 protected paths | **all 403** |
| ACME challenge path | **404** (resolves, file absent) · `.well-known/` 403 (listing off) |
| `semantiq:health` | **green** on the deployment |
| Secret hygiene | no token, grant, code, nonce, verifier or administrator identity in any public body or workflow output |

**Note on what cannot be proven from outside.** `/first-run/<grant>` answers
identically whether bootstrap is closed, the grant is unknown, or it has
expired. That indistinguishability is deliberate anti-probing, which is exactly
why closure is proven from the server state above rather than inferred from an
HTTP response.

### Status

```
P1-00 ACCEPTED — 31 August 2026
```

Accepted by the Product Owner against the verified production baseline.
**P1-00 is closed.** Full evidence: `doc/v2/phase-1/P1-00-LOGIN-BOOTSTRAP-VERIFICATION.md`.

Test suite at acceptance: **121 tests, 464 assertions.**

---

## 13. P1-01 — Organisation

### Lifecycle

| Stage | Outcome |
| --- | --- |
| PLAN | **APPROVED** — D-14 corrected to optional many-to-many, D-15 `users` only |
| DESIGN | **APPROVED** — including **D-16**, `users.organisation_id` |
| EXECUTE | Complete, merged and deployed |
| TEST | 170 tests, 1703 assertions; 28 mutations applied across the unit, 28 caught |
| VERIFY | **UNFINISHED** — Product Owner live verification in progress |
| ACCEPT | **NOT ACCEPTED** |

### The pull requests, in order

| PR | Subject | Merge |
| --- | --- | --- |
| [#50](https://github.com/eduCLaaSTeach/semantiq/pull/50) | P1-01 implementation | `9afe33d` |
| [#51](https://github.com/eduCLaaSTeach/semantiq/pull/51) | Read-only verification workflow | `84463d4` |
| [#52](https://github.com/eduCLaaSTeach/semantiq/pull/52) | Production evidence | `579c25c` |
| [#53](https://github.com/eduCLaaSTeach/semantiq/pull/53) | Management-cycle live-verification correction and carried gate | `8f65647` |
| [#54](https://github.com/eduCLaaSTeach/semantiq/pull/54) | Aggregate verification counters | `44fa84b` |
| [#55](https://github.com/eduCLaaSTeach/semantiq/pull/55) | Baseline and real-production-data constraint | `b1a75e2` |
| [#56](https://github.com/eduCLaaSTeach/semantiq/pull/56) | `/console` shell and navigation integration defect | `4f99c46` |
| [#57](https://github.com/eduCLaaSTeach/semantiq/pull/57) | Verification record correction | `7b3f445` |
| [#58](https://github.com/eduCLaaSTeach/semantiq/pull/58) | UI presentation and professional-quality correction | **`1b4c6384719b66048d97d00f5911a4279ecf6646`** |

### The decisions

**D-14 — Business Unit ↔ Legal Entity: optional many-to-many via a junction.**
The many-to-one model was proposed and **rejected by the Product Owner** as
self-contradictory: the plan itself stated the two axes do not align. The
junction carries the two keys and nothing else.

**D-15 — `users` only.** No `people` table.

**D-16 — `users.organisation_id`**, nullable, FK to `organisations`, indexed,
owned by P1-01. Raised because the same-organisation rule could not be
implemented honestly: `users` carried no SemantIQ organisation key, and the
nearest available column — Entra `tenant_id` — would have made every test pass
for the wrong reason. Populated at exactly one place, Company Profile creation.
No seed, no backfill, no manual database write, no bootstrap change.

### Defects found during P1-01, kept rather than smoothed over

**1. A directory-enumeration oracle (found by the anonymous route sweep).**
Route-model binding ran before the session gate, so an anonymous request to a
protected record answered **302 when the record existed and 404 when it did
not** — letting an unauthenticated visitor map the organisation by probing
identifiers. Authentication and authorisation now run ahead of
`SubstituteBindings`. Verified live: ids `1`, `999999` and `2147483647` all
answer 302.

**2. `NavigationRegistry` rejected every valid node.** Routes are named
fluently, so the collection's name lookup is stale until refreshed. P1-BASE
registered zero nodes, so nothing had exercised the guard.

**3. Organisation was unreachable after sign-in (found by Product Owner live
review, PR #56).** Two causes. `/console` still rendered the P1-00 standalone
card, never moved onto `AppShell`. Worse and hidden by the first,
`SystemAdministratorNavigationAuthorizer` took `Request` through its
constructor while `NavigationRegistry` is a singleton — so it read
`semantiq_user` from a request captured at construction. **Every node was
denied and `productAreas` resolved to `[]` on every page, the Organisation
screens included.** Their sidebars were empty too; nobody had looked.

**4. The sidebar exposed the raw internal icon key `building` (found by Product
Owner live review, PR #58).**

- `AppShell` rendered `{node.icon}` directly, so a **registry key became
  visible user-facing text**.
- Every automated test passed: the node was registered, the route resolved, the
  authorisation was correct. Nobody had asked what the screen actually said.
- Replaced by the approved implementation: one central inline-SVG registry
  (`resources/js/Components/Icon.jsx`) per the shared standard — 24px viewBox,
  2px stroke, round caps and joins, outline, `i-<concept>` ids, rendered at
  `1em` and sized by `font-size`. Organisation declares `i-sitemap`.
- **An unknown key now renders nothing**, never its own name.
- **Additional obvious P1-01 UI-polish defects were found and corrected in the
  same pass:** a primary button stretched the full width of its card;
  add-forms had no heading, so a row of inputs was explained only by its button;
  the actions column header was empty; a two-character country field was 590px
  wide.

Defects 3 and 4 were both found by the Product Owner looking at the screen, not
by any test. That is the reason for the new delivery rules in section 15.

### Carried gate to P1-03

The **live multi-user management-cycle check** is deferred to **P1-03**.
Self-management is refused before the chain walk runs, so a genuine cycle needs
at least two SemantIQ users; production has one, and P1-03 provisions the
second. It was **not** solved by inserting a user, reopening bootstrap, writing
to MySQL, building P1-03 early, or weakening the rule. P1-01 keeps its
mutation-proven automated coverage as the evidence for the rule. Recorded in
`PHASE-1-PLAN.md` §10.

---

## 14. Production state at end of day — READ THIS BEFORE RESUMING

Read from the server by `verify-organisation.yml` run
[33385154456](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33385154456)
at day end. Read-only; aggregates only.

```json
{"row_counts":{"organisations":1,"legal_entities":0,"business_units":1,
 "business_unit_legal_entity":0,"departments":0,"teams":0,
 "team_memberships":0,"management_relationships":0},
 "d16_column_exists":true,"d16_column_nullable":true,
 "d16_foreign_key_target":"organisations",
 "users_total":1,"users_with_organisation":1,
 "team_memberships_current":0,"team_memberships_ended":0,
 "business_units_with_multiple_legal_entities":0,
 "legal_entities_with_multiple_business_units":0,
 "business_units_active":0,"business_units_inactive":1,
 "department_moved_events":0,"organisation_delete_routes":0}
```

> **CORRECTION TO THE DAY-END BRIEF.** The day-end instruction stated that no
> Organisation or business-structure data had been entered and that the
> verification baseline therefore remained valid. **The server says otherwise,
> and the server is authoritative.** Recording the stated position would have
> put a false statement into the permanent record.

**The zero baseline captured in run
[33381020970](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33381020970)
is SUPERSEDED. Do not compare against it again.** The reading above is the new
reference point.

### What the numbers actually prove — three checks passed live

| Check | Evidence | Result |
| --- | --- | --- |
| 2 — System Administrator reaches Organisation | Product Owner screenshot of `/console/organisation` | **PASS** |
| 3 — Create the organisation | `organisations: 1`, `business_units: 1` | **PASS** |
| **5a — D-16 association** | `users_with_organisation` **0 → 1** while `users_total` stays **1** | **PASS** |

`users_with_organisation = 1` is the direct live proof of D-16's population
rule: creating the Company Profile associated the administrator who created it,
in the same transaction. No seed, no backfill, no manual write.

`business_units_active: 0` with `business_units_inactive: 1` means a business
unit was created and then deactivated. It had **no departments**, so this was a
**leaf deactivation, which the design permits** (§3: *deactivate a leaf —
permitted*). **It is not evidence for check 7**, which requires deactivating a
business unit that still has an **active department** and observing the refusal.

### Still outstanding for P1-01

| # | Check | State |
| --- | --- | --- |
| 7 | Deactivate a business unit **with an active department** → refused, children named | **Not yet observed** |
| 4 | D-14 both directions | Conditional on the real organisation having that shape |
| 9 | Move a department between business units | Conditional on a legitimate move existing |
| 5 | Add a team member, then remove | Conditional on the membership being factually correct |
| 6 | Live management cycle | **Carried to P1-03** |

---

## 15. New permanent delivery rules — `CLAUDE.md`

`CLAUDE.md` was created today and now holds the durable delivery protocol. It
references the existing authoritative documents rather than restating them.
**These rules are mandatory for all future work:**

1. **Every completed feature, corrective task or delivery unit must ship with a
   Product Owner Test Script before acceptance is requested** — twelve required
   parts, written for the Product Owner, including a warning wherever test data
   is permanent.
2. **Green CI alone is never sufficient for handover.**
3. **UI work requires a professional-polish review** before handover — the full
   inspection list, ending with: *would a professional SaaS product team be
   comfortable showing this exact screen to a customer?* It is a quality gate,
   **not** licence to expand scope.
4. **UI work requires actual visual verification in a real browser**, at desktop
   and small-screen width, recording what was actually observed.
5. **Internal or developer terminology must never leak into user-facing UI** —
   icon keys, enum values, route names, debug text. Enforced by
   `NavigationPresentationTest`.
6. **Automated test evidence must never be reported as observed production
   evidence.** A passing test is a different claim.
7. **The Product Owner must never be asked to create inaccurate or misleading
   permanent business data solely to satisfy a test.** Where a check cannot be
   exercised on real data, mark it **NOT CURRENTLY OBSERVABLE WITH REAL
   PRODUCTION DATA**, keep its automated evidence, and carry the live
   observation forward.

---

## 16. End-of-day lifecycle state

```
P1-BASE   ACCEPTED — 31 August 2026 — closed
P1-00     ACCEPTED — 31 August 2026 — closed
P1-01     NOT ACCEPTED — Product Owner live verification pending
P1-02     NOT STARTED — locked
```

**P1-00 detail:** real Microsoft Entra login and first System Administrator
bootstrap verified live. The PR #46 production JWKS defect is retained in this
document's history at §12. Bootstrap is CONFIGURED and closed.

---

## 17. What must NOT happen next

P1-BASE and P1-00 are both accepted and closed. **P1-01 is implemented and
deployed but NOT ACCEPTED. P1-02 is locked.**

The lifecycle is unchanged: `PLAN → APPROVE → DESIGN → APPROVE → EXECUTE →
TEST → VERIFY → ACCEPT`.

- Do **not** start P1-02. It unlocks only on explicit P1-01 acceptance.
- Do **not** create roles, permissions, business domains, scopes, sensitivity or
  the access engine — those are P1-04 and P1-05.
- Do **not** build user or group administration — that is P1-03.
- Do **not** implement Fabric, Power BI, AI or Workplace.
- Do **not** issue another bootstrap grant. Bootstrap is CONFIGURED and closed.
- Do **not** perform speculative refactoring.

### Settled baseline decisions — do not reopen

D-07 (Laravel → Inertia → React 19 → Vite) · D-08B (`public_html` permanent
document and deployment root) · root `public_html/index.php` front controller ·
GitHub Actions → SSH → cPanel deployment · APP_KEY preservation rules ·
deployment-controlled MySQL migrations · the approved Apache 403 exposure
boundary.

Settled unless a verified technical impossibility appears.

---

## Resume Here Next Session

### DO NOT START P1-02.

**The next session begins by completing P1-01 Product Owner live verification.**

1. **Read this daily update first**, especially §14 — the zero baseline is
   superseded and real data now exists.
2. Read `CLAUDE.md` — the mandatory delivery rules created today.
3. Read `doc/v2/phase-1/P1-01-ORGANISATION-VERIFICATION.md`.
4. `main` should be at or after `1b4c638`.

### The first Product Owner test

1. **Sign in.**
2. **Inspect `/console`.**
3. **Confirm `SYSTEM ADMINISTRATION → Organisation` is present.**
4. **Confirm a real icon is shown and the word `building` is absent.**

**Only after that passes** should the Product Owner proceed with real
Organisation data — and only data that is genuinely true, because P1-01 has no
hard delete and everything created is permanent.

Then the remaining checks in §14, of which **check 7** is the one that can be
done today with the structure already present.

**P1-01 must receive explicit Product Owner acceptance before P1-02 unlocks.**

---

## 18. Evidence references

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
| PR #58 — UI presentation correction | https://github.com/eduCLaaSTeach/semantiq/pull/58 |
| PR #58 merge commit | `1b4c6384719b66048d97d00f5911a4279ecf6646` |
| PR #58 CI | [33384567666](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33384567666) — success |
| PR #58 deployment | [33384679794](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33384679794) — success |
| End-of-day production state | [33385154456](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33385154456) — read-only |
| Superseded zero baseline | [33381020970](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33381020970) |
| P1-01 plan | `doc/v2/phase-1/P1-01-ORGANISATION-PLAN.md` |
| P1-01 design | `doc/v2/phase-1/P1-01-ORGANISATION-DESIGN.md` |
| P1-01 verification | `doc/v2/phase-1/P1-01-ORGANISATION-VERIFICATION.md` |
| Delivery protocol | `CLAUDE.md` |

No credential, `APP_KEY` value, database credential, SSH material, `.env` content or
other secret appears in this document, and none may be added to it.

---

## 19. Daily update convention

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
