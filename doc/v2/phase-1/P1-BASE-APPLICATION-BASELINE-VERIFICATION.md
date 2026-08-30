# P1-BASE — Application Baseline — VERIFICATION

**Status:** PARTIAL — build, tests, CI and UI verified. Deployment and live
web-exposure verification are **NOT VERIFIED**, blocked on the D-05 one-time
database provisioning. See §7.

**Unit:** P1-BASE
**Design:** `P1-BASE-APPLICATION-BASELINE-DESIGN.md` (approved with four corrections)
**Head verified:** `8d01db3` — see §2 for the full run ledger and the currency rule
**Date:** 30 August 2026

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

Every CI run on this branch is listed, newest first, so the record shows which
run belongs to which head rather than a single claim that ages badly.

| Head | Run | Conclusion | Contents |
| --- | --- | --- | --- |
| `8d01db3` | [33321421385](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33321421385) | **success**, 13/13 steps | Gate 4 path list completed; evidence ledger |
| `bb8c765` | [33321180460](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33321180460) | success, 13/13 | Verification document added |
| `e096b62` | [33321086991](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33321086991) | success, 13/13 | CI and deployment workflows |

**Currency rule.** A green run on a head that is no longer current proves
nothing about the head being accepted, so the ledger above is kept rather than
overwritten. The commit that adds each ledger entry is itself documentation-only
and changes no application file, which is why the entry can be trusted about the
code it describes.

The definitive pre-merge evidence is the CI run on the **final** head at merge
time, confirmed in the acceptance report rather than predicted here. This
document cannot cite the SHA of the commit that creates it.

All three runs above executed the same 13 steps, including migrations against
MySQL 8.4:

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
| B4 | CI green on `main` | **NOT VERIFIED** — the branch is not merged |
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

## 7. NOT VERIFIED

Each item below could not be executed. None is assumed to pass.

### D — Deployment (D8–D12)

**NOT VERIFIED.** Blocked on the **D-05 one-time database provisioning**, which
is an infrastructure administration action outside this repository and outside
what this session can perform. The server `.env` must carry `DB_DATABASE`,
`DB_USERNAME` and `DB_PASSWORD` before a deployment can migrate.

The deployment workflow fails deliberately and clearly in that state: the
"Verify the server environment file" step reports *"No .env on the server.
Complete the one-time provisioning (D-05) first"* rather than surfacing a
confusing migration error.

The branch is also not merged, and `deploy.yml` fires on push to `main`.

Unverified: D8 deploy completes · D9 Laravel answers at the site root ·
D10 `.env` survives · D11 `.well-known/` survives · D12 `storage/` preserved.

### E — Live web-exposure tests (E13, E14)

**NOT VERIFIED.** These must run against the deployed application. Until
Laravel is on the server there is nothing to test: the checks would pass
trivially against the static page and prove nothing.

The tests are implemented in `deploy.yml` and run automatically on the first
deployment. They cover the Gate 4 minimum list in full, treat any status other
than 403/404 as failure, explicitly reject a redirect as proof, cache-bust every
request, and assert `.well-known/` stays reachable. Each response body is also
scanned for `APP_KEY`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`,
`MICROSOFT_CLIENT_SECRET`, connection strings and server paths - a 403 that
still returns a body carrying one of those is a failure.

**Current live server state**, observed 30 August 2026 (cache-busted), *before*
any P1-BASE deployment:

| Path | Status |
| --- | --- |
| `/` | 200 — still the static deploy-test page |
| `/up` | 404 — Laravel not deployed |
| `/.env` | 403 |
| `/vendor/` | 404 |
| `/.well-known/` | 200 |

### G — Deploy-test retirement (G19–G23)

**PARTIAL.**

| Item | Result |
| --- | --- |
| G19 static proof known good before rollout | **PASS** — run 33317821620 green, page served the merge commit |
| G20 `public/index.html` and `deploy-test.yml` removed in the implementation commit | **PASS** — commits `1f30760` and `e096b62` |
| G21 first deployment removes the obsolete server root `index.html` | **NOT VERIFIED** — no deployment yet |
| G22 Laravel is the site root | **NOT VERIFIED** |
| G23 no artefact can shadow `public/index.php` | **NOT VERIFIED** on the server; **PASS** in the repository |

### J — D-08 document root (J24) — **RESOLVED: D-08B**

**D-08B CONFIRMED** by the product owner, 30 August 2026, from cPanel.

| | |
| --- | --- |
| Document root | `public_html` |
| `public_html/public` available | No — cPanel does not offer the required arrangement for this deployment |
| Architecture in force | Hardened root forwarder, `deployment/public_html.htaccess` |

**This raises the stakes on two gates rather than lowering them.** Under D-08A
the application tree would sit physically outside the web root and no rewrite
rule could expose it. Under D-08B the tree is *inside* `public_html`, so the
root `.htaccess` is the only thing standing between a request and `.env`,
`vendor/`, `config/` and the rest.

Consequently, for P1-BASE acceptance:

- the root `.htaccess` security boundary is a **mandatory** acceptance gate, not
  a defence-in-depth extra;
- the complete live web-exposure test suite (§E) is **mandatory** and must be
  executed against the deployed application, not inferred from the `.htaccess`
  source.

Correct-looking Apache configuration is not evidence. Only observed HTTPS
responses are.

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
