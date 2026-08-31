# P1-BASE — Application Baseline — VERIFICATION

## P1-BASE ACCEPTED — 31 August 2026

**Status:** **ACCEPTED** by the Product Owner against the verified production
baseline at `3d075bfbf80392651577abe256526575632b3e73`. **P1-BASE is closed.**

The risk recorded in §7.1 is **resolved, not outstanding.** It was never a real
weakness: the Apache deny rules had been firing throughout, and only their
reported status was wrong. Four independent mechanisms were measured returning
403, the boundary now requires 403 on every protected path, and the front
controller serves directly from `public_html`. §7.1 is retained in full, marked
superseded, because the reasoning that produced the wrong conclusion is worth
keeping.

**Unit:** P1-BASE
**Design:** `P1-BASE-APPLICATION-BASELINE-DESIGN.md` (approved with four corrections)
**Baseline accepted:** `3d075bf` — final deployment
[33354691876](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33354691876),
27/27 steps
**Head first verified:** `8d01db3` — see §2 for the full run ledger and the currency rule
**Verification dates:** 30–31 August 2026
**Acceptance evidence:** `doc/dailyupdates/taskcompleted310826.md` §11
**Hosting architecture:** `doc/v2/phase-1/HOSTING-ARCHITECTURE.md`

### Delivery history — retained, including the failures

| Event | Outcome |
| --- | --- |
| First deployment (PR #34, `996cd9f`) | **FAILED** its own exposure gate on `/app/` — a route-prefix collision, not a leak |
| PR #35 correction (`6f68a62`) | Authenticated area moved to `/console`; deployment success |
| PR #37 denial diagnostic (`662c84e`) | Probe returned 404 — reported as **NOT VERIFIED**, and that conclusion was later shown to be a false negative |
| PR #38 denial matrix (`d44f8e0`) | **All four mechanisms 403.** The deny rules had been working all along; a path-valued ErrorDocument was masking the status |
| PR #39 — PR 1 (`e4830ee`) | Diagnostics removed; gate tightened to require 403; deployment success |
| PR #40 — PR 2 (`da7ddea`) | Root layout migrated; **deployment FAILED** on a `.well-known/` check that was itself wrong |
| PR #41 — PR 2a (`3d075bf`) | ACME proven end to end; deployment success, 27/27 steps |

Two deployments failed on the way to this baseline. Both are recorded above and
in detail in the daily handover. Neither is erased: the first proved the
exposure gate works, and the second was a defect in a gate rather than in the
site — `Options -Indexes` turned a directory listing into a 403 while the check
still asserted "not 403".

Nothing below is marked PASS unless it was actually executed and its output
observed. Items that could not be executed are marked NOT VERIFIED with the
reason, never assumed.

Nothing below is marked PASS unless it was actually executed and its output
observed. Items that could not be executed are marked NOT VERIFIED with the
reason, never assumed.

---

## 1. Local build and test

| # | Item | Result | Evidence |
| --- | --- | --- | --- |
| A1 | Clean install, build, test | **PASS** | `composer install && npm ci && npm run build && php artisan test` |
| A2 | Formatting gate | **PASS** | `vendor/bin/pint --test` → `{"result":"passed"}` |

Suite: **34 tests, 34 passed, 65 assertions**.

| Suite | Result |
| --- | --- |
| Unit | 6/6 |
| Feature | 22/22 |
| Architecture | 6/6 |

The Architecture suite was not registered in `phpunit.xml` when first written.
Six tests existed and had never run. Registering the suite is part of this unit.

---

## 2. CI

Every CI run is listed, newest first, so the record shows which run belongs to
which head rather than a single claim that ages badly.

| Head | Run | Conclusion | Contents |
| --- | --- | --- | --- |
| **`6f68a62` (main, merge of #35)** | [33322650103](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33322650103) | **success** | **CI on main after the exposure fix** |
| `996cd9f` (main, merge of #34) | [33322144289](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33322144289) | success | CI on main, first P1-BASE merge |
| `a71fad3` | [33322439196](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33322439196) | success | Exposure fix, pre-merge |
| `570ddc5` | [33322074490](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33322074490) | success | D-08B recorded |
| `a38a73e` | [33321842164](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33321842164) | success | APP_KEY bootstrap |
| `0c84947` | [33321561118](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33321561118) | success | Run ledger |
| `8d01db3` | [33321421385](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33321421385) | success | Gate 4 list completed |
| `bb8c765` | [33321180460](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33321180460) | success | Verification document |
| `e096b62` | [33321086991](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33321086991) | success | CI and deployment workflows |

**Currency rule.** A green run on a head that is no longer current proves nothing
about the head being accepted, so the ledger is kept rather than overwritten. The
definitive evidence is the run on **`6f68a62`**, the merge commit now deployed to
production.

Every run executed the same 13 steps, including migrations against MySQL 8.4:

| Step | Result |
| --- | --- |
| Install PHP dependencies (PHP 8.5) | success |
| Install frontend dependencies | success |
| Build frontend assets | success |
| Check formatting (Pint) | success |
| Run tests | success |
| **Run migrations against MySQL 8.4** | **success** |
| Check the files deploy.yml depends on | success |

| # | Item | Result |
| --- | --- | --- |
| B3 | CI green on the pull request, on the current head | **PASS** — run 33321180460 on `bb8c765` |
| B4 | CI green on `main` | **PASS** — run 33322650103 on `6f68a62` |
| B5 | Migrations apply against real MySQL | **PASS** — CI step 12 |

---

## 3. Schema

| # | Item | Result | Evidence |
| --- | --- | --- | --- |
| I27 | No role/domain/scope/sensitivity schema | **PASS** | Tables after migrate: `migrations`, `sessions` only |
| — | Framework baseline only | **PASS** | One migration, `create_sessions_table` |
| — | No business schema can be added silently | **PASS** | `NoBusinessSchemaTest` fails any migration creating a forbidden table |
| — | MySQL identifier limit | **PASS** | `MigrationIdentifierLengthTest` + the CI MySQL step |

`sessions.user_id` is a nullable index, not a foreign key. There is no users
table; creating one would be P1-03's schema arriving early.

---

## 4. Application behaviour

Verified against a running server (`php artisan serve`), not only in-process.

| Route | Observed | Expected |
| --- | --- | --- |
| `/` | 200, entry page | 200 |
| `/up` | 200, body `ok`, **no cookie set** | 200 |
| `/app` | 302 → `/` | refuse |
| `/app/anything` | 404 | no route |

| # | Item | Result |
| --- | --- | --- |
| I29 | `/app/*` denies with no identity | **PASS** |
| — | Entry page leaks no protected structure | **PASS** — 0 matches for `shell-rail`, product-area names |
| — | JSON client refused 401, exact payload | **PASS** |
| F15 | `/up` 200 when healthy | **PASS** |
| F16 | `/up` 503 with the database unreachable | **PASS** — asserted 503, not 500 |
| F17 | `semantiq:health` reports accurately | **PASS** — all five checks OK; each has a failure test |
| F18 | Missing required config is refused | **PASS** — `ConfigurationValidatorTest` |
| — | Microsoft config absent is **not** a P1-BASE failure | **PASS** — correction 4 |

---

## 5. UI

Rendered in Chromium via Playwright at two viewports and both colour schemes.
Computed values read from the live DOM, not asserted from source.

| Case | Body background | Horizontal scroll | `h1` | Console errors |
| --- | --- | --- | --- | --- |
| light · 1280×800 | `rgb(232,223,208)` = `#E8DFD0` | none | 1 | none |
| dark · 1280×800 | `rgb(26,46,70)` = `#1A2E46` | none | 1 | none |
| light · 390×780 | `#E8DFD0` | none | 1 | none |
| dark · 390×780 | `#1A2E46` | none | 1 | none |

`#E8DFD0` and `#1A2E46` are the standard's light and dark **canvas** tokens,
unmodified.

| # | Item | Result |
| --- | --- | --- |
| H23 | Shell renders with design-system tokens, both themes | **PASS** |
| H24 | Responsive at the standard's breakpoints | **PASS** — no horizontal scroll at 390px |
| H25 | Entry page follows the 5.7 Auth archetype | **PASS** — centred card, no shell, brand mark |

The brand mark ships in light and dark variants and switches on
`prefers-color-scheme`; a single asset is legible on only one of the two
surfaces. Fonts are Montserrat and Source Sans 3 from the standard's own
Google Fonts URL.

---

## 6. Boundaries

| # | Item | Result |
| --- | --- | --- |
| I26 | No file originates from SemantIQ v1 | **PASS** — fresh `composer create-project`; no v1 file copied |
| I28 | No Phase 2/3 menu navigable; no placeholder screens | **PASS** — registry holds zero nodes |
| — | Exactly one module exists | **PASS** — `OnlyThePlatformModule` assertion |
| — | A node cannot exist without route, icon and policy key | **PASS** — four constructor tests |
| — | A node for an undefined route is refused | **PASS** |

---

## 7. Live deployment verification — EXECUTED

Two deployments ran, exercising both paths of the state machine.

| | Run | State | Result |
| --- | --- | --- | --- |
| Merge `996cd9f` (PR #34) | [33322144286](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33322144286) | **INITIAL** | 23/24 steps; **exposure gate FAILED** on `/app/` returning 302 |
| Merge `6f68a62` (PR #35) | [33322650107](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33322650107) | **EXISTING** | **All 24 steps succeeded** |

The first failure is kept rather than erased: it is the evidence that the gate
works. It also produced the finding in §7.2.

### Deployment steps, second run — all observed

| Step | Result |
| --- | --- |
| Rsync exclusion pre-flight | success |
| SSH authentication | success |
| Installation-state detection | success — **EXISTING** |
| Open maintenance window | success — ran, correctly, on this path |
| Sync to cPanel | success |
| Runtime directories | success |
| Environment + APP_KEY | success — **preserved, not regenerated** |
| `migrate --force --no-interaction` | success |
| `optimize:clear` | success |
| `semantiq:health` over SSH | success |
| Close maintenance window | success |
| HTTPS site verification | success |
| **Web-exposure negative tests** | **success** |

**Both deployment paths are now proven.** INITIAL skipped maintenance mode
because there was nothing to put into it; EXISTING opened and closed it around
the sync and migration. The APP_KEY step took the preservation branch on both,
because the key supplied during D-05 provisioning was already valid — the
generation branch has never fired against this server and, by design, never will
while a valid key is present.

### Application boundary, verified independently over HTTPS

| Check | Observed |
| --- | --- |
| `/` | 200, Laravel Inertia pre-auth page (`<title inertia>SemantIQ`) |
| `/up` | 200, body `ok` |
| `/console` browser | 302 → `/` (deny-by-default) |
| `/console` JSON | 401, `{"message":"Unauthenticated."}` |
| Shell / navigation leak on `/` | 0 matches |
| Old static test page | gone |

### Exposure suite — 29 paths, cache-busted, all PASS

`/.env` · `/.git/` · `/.git/config` · `/composer.json` · `/composer.lock` ·
`/package.json` · `/package-lock.json` · `/artisan` · `/phpunit.xml` ·
`/vite.config.js` · `/app` · `/app/` · a real controller file below `/app/` ·
`/bootstrap/` · `/config/` · `/database/` · `/doc/` · `/deployment/` ·
`/node_modules/` · `/resources/` · `/routes/` · `/storage/` · `/tests/` ·
`/vendor/` · `/README.md` · `/deployment/public_html.htaccess` ·
`/storage/logs/laravel.log`

Every one returned **404** with an identical 6586-byte body — Laravel's error
page — and **zero** occurrences of `APP_KEY`, `DB_DATABASE`, `DB_USERNAME`,
`DB_PASSWORD`, `MICROSOFT_CLIENT_SECRET`, a connection string, a server path, a
stack trace or `<?php`.

`/.well-known/` returned **200**: ACME renewal is intact.

### Persistence, observed

| Item | Evidence |
| --- | --- |
| `.env` survived | The environment step read it and confirmed a valid key; it exits non-zero if absent |
| **APP_KEY preserved** | Same step took the "present, leaving it untouched" branch. Value never printed |
| `.well-known/` survived | 200 over HTTPS after deployment |
| `storage/` survived | Health check reports runtime directories writable |
| Runtime permissions | Health check passed on the deployed release |

### 7.1 SUPERSEDED — the deny rules were firing all along

> **STATUS: SUPERSEDED — 31 August 2026, by PR #38.**
>
> The conclusion recorded below is **wrong**, and is retained rather than
> deleted because the reasoning that produced it was sound and the way it failed
> is the lesson.
>
> **What was observed:** all 29 protected paths returned Laravel 404 and none
> returned 403. **What was concluded:** the Apache deny rules were not executing,
> and the catch-all forwarder was the entire security boundary.
>
> **What was actually true:** the deny rules *were* executing. Apache serves a
> denial through its `ErrorDocument` chain; the inherited, path-valued
> `ErrorDocument` re-entered the rewrite engine, met the forwarder, landed in
> Laravel and returned **404**. The refusal happened every time — only the
> reported status was wrong.
>
> **How it was settled.** PR #38 added a quoted-literal `ErrorDocument 403`,
> which Apache answers from memory with no internal redirect, and measured four
> mechanisms in one request cycle against one server state:
>
> | Mechanism | Result |
> | --- | --- |
> | mod_rewrite `[R=403,L]` | **403 — verified** |
> | mod_rewrite `[F,L]` | **403 — verified** |
> | mod_alias `RedirectMatch 403` | **403 — verified** |
> | `<Files>` + `Require all denied` | **403 — verified** |
>
> The decisive control: a real guarded file returned **403** while its unguarded
> sibling in the same directory returned **200** with the diagnostic body. The
> file was genuinely reachable and the directive refused it. `.env`, `/vendor/`
> and `/app/` returned **403**, having returned 404 in every prior run, with the
> rewrite rules byte-for-byte unchanged.
>
> **Corrections to the record:**
>
> - Protection is **not** single-layered. Four independent mechanisms deny.
> - The forwarder is **not** the only thing protecting the deployment.
> - `AllowOverride` was **not** the cause. It was named below as a plausible
>   hypothesis and explicitly not as fact; that caution was warranted.
> - PR #35's rewrite was not the failure it appeared to be — its result was
>   simply unobservable, like everything else.
>
> **What this cost.** Nothing was ever exposed: no protected path returned
> anything but a refusal, in any run. The real cost was three weeks of being
> unable to distinguish a working boundary from a broken one. That is why
> `ErrorDocument 403` is now a permanent, tested control and the exposure gate
> **requires 403** rather than accepting 403-or-404: a fall-through into Laravel
> must fail the deployment instead of passing quietly.
>
> The security objection blocking the permanent `public_html` root layout is
> resolved. See `APACHE-DENIAL-CAPABILITY-DIAGNOSTIC.md`.

<details>
<summary>Original finding, retained as written (superseded)</summary>

> **Update.** The product owner has since fixed **D-08B as the permanent hosting
> model**. D-08A is closed and the hosting provider will not be asked to repoint the
> document root, so option 2 below is withdrawn. The remaining question — whether an
> Apache denial can fire at all on this host — is now more important, not less,
> because `DEPLOYMENT-LAYOUT-AMENDMENT.md` proposes removing the forwarder that is
> currently doing all of the protecting. That amendment makes proving a 403 a
> prerequisite rather than a follow-up.


**Every gate passes. This is not a gate failure. It is a weakness worth a
decision.**

Of the 29 protected paths, **0 returned 403 and 29 returned 404**. A 403 would
mean an Apache denial in `deployment/public_html.htaccess` fired. A 404 with
Laravel's error page means the request reached Laravel — the forwarder rewrote
it into `public/`, the file was not there, and the router refused it.

So the protection is real but **single-layered**. The forwarder is doing all of
it; the deny rules contribute nothing observable.

PR #35 rewrote those rules into a single `mod_rewrite` block specifically to fix
this, on the reasoning that mixing modules had made ordering unreliable. **That
prediction was wrong.** The live result is identical: still 0 × 403. The cause is
therefore something not visible from outside the panel — most plausibly
`AllowOverride` restricting which directives the host honours in `.htaccess`.

Under D-08B the tree sits inside the document root, so if the forwarder rewrite
were ever broken or removed, requests would map straight onto real files with no
second mechanism to stop them. That is the risk.

It is recorded rather than fixed because fixing it requires either host
configuration knowledge this session cannot obtain, or the D-08A document-root
change the host does not offer. **Product-owner decision.** Options: ask the host
what `AllowOverride` is set to for this domain; or re-open D-08A with the host;
or accept single-layer protection with the forwarder treated as a
change-controlled file.

</details>

### 7.2 What the first deployment's failure proved

The `/app/` 302 was a route-prefix collision, not a leak — every file beneath
`/app/` returned 404 with no source throughout. It is listed among the defects in
§8 because it was found the same way the others were: by a check that was allowed
to fail.

---

## 8. Defects found and fixed during verification

Three, all found by testing rather than review. Recorded because each was
silent.

**1. The rsync exclusion guard did not guard anything.** Deleting the `.env`
exclusion from `deploy.yml` left the test passing, because `--exclude
".env.example"` satisfied a pattern searching for `".env"` — the pattern had no
trailing boundary. A guard that cannot fail is worse than none, because it is
trusted. Found by deliberately breaking the workflow and observing a green
suite. Fixed by anchoring on the closing quote; breaking it again now fails, and
restoring it passes. The shell pre-flight was already exact.

**2. `/up` returned 500 with a stack trace when the database was down.** It was
registered inside the `web` middleware group, which starts a session; the
session driver is `database`. A liveness route that cannot answer when the
database is down is useless precisely when it matters. Found by running a real
server with no database reachable. Fixed by registering it outside the group;
two tests hold it there, one asserting 503 rather than 500 and one asserting no
cookie is set.

**3. Six architecture tests had never run.** `phpunit.xml` declared only the
Unit and Feature suites, so `tests/Architecture` was silently skipped — the
exclusion contract test among them. Found by running a filter that reported "No
tests found". Fixed by registering the suite.

---

## 9. Readiness

**Ready for acceptance:** the application baseline, its tests, CI including the
MySQL migration gate, the deployment workflow with both deployment states and
the enforced exclusion contract, and the UI shell in both themes.

**Not ready, and not claimed:** anything requiring the server. That is one
prerequisite away — the D-05 provisioning — after which the first deployment
executes the remaining items automatically and this document is completed with
their real output.

Recommended order:

1. Complete the D-05 database provisioning and set `DB_*` in the server `.env`.
2. Check the cPanel document root and record **D-08A** or **D-08B** above.
3. Merge, which triggers the first deployment on the INITIAL path.
4. Complete §7 with the observed results.
5. Then, and only then, accept P1-BASE.
