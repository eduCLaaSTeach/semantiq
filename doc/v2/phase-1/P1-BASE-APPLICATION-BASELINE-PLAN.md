# P1-BASE — Application Baseline — PLAN

**Status:** APPROVED — 30 August 2026. Moved to DESIGN; see
`P1-BASE-APPLICATION-BASELINE-DESIGN.md`.
**Unit:** P1-BASE (approved; see `PHASE-1-PLAN.md` §2)
**Precedes:** P1-00 — Application Entry, Login & First-Run Bootstrap
**Authority:** Blueprint §3.7 order 1 — "Create the fresh v2 application baseline
on the already-established Laravel/React/MySQL/cPanel delivery platform; add CI
quality gates and no v1 dependencies."

---

## 1. Scope

Stand up an empty but real SemantIQ v2 application on the existing delivery
platform, so that the Login unit has somewhere to be built.

This unit deliberately delivers **no business behaviour, no authentication and no
authorisation**. Its value is that every later unit inherits a working build,
test, deploy and schema path.

### In scope

| Item | Detail |
| --- | --- |
| Laravel 13 / PHP 8.5 skeleton | Fresh `composer create-project`, no v1 files |
| React 19 + Vite frontend | Build wired into the Laravel asset pipeline |
| MySQL connection | Server database created, `.env` populated, migrations run |
| Modular monolith layout | Top-level module boundaries agreed and empty |
| Application shell | Sidebar clusters, header, content region, per design system |
| CI quality gates | Pint, PHPUnit, frontend build, on every PR and push to main |
| Deployment | Real deploy workflow restored, forwarder in place, health check green |
| Config validation | App fails loudly at boot on missing required configuration |

### Explicitly out of scope

- Login page, SSO, sessions, bootstrap — these are **P1-00**.
- Any user, role, domain, scope or sensitivity table — these are P1-03 and P1-05.
- Any menu item beyond the empty shell needed to prove layout.
- Any Fabric or Workplace concern.

Per the execution contract, no future-phase table, service or route is created
early. The shell renders its cluster headings and nothing beneath them.

---

## 2. Dependencies and blockers

All P1-BASE blockers are resolved. Decisions in full: `PHASE-1-PLAN.md` §4.

| ID | Decision | Outcome |
| --- | --- | --- |
| D-01 | Role and access model | **APPROVED** — blueprint model governs. No role/domain/scope/sensitivity schema in P1-BASE; module boundaries only |
| D-02 | Navigation architecture | **APPROVED** — three product areas, not the four generic clusters. Only accepted System Administration capabilities become navigable |
| D-05 | MySQL provisioning and migrations | **APPROVED** — one-time infrastructure provisioning; migrations run through the deploy workflow; migration failure fails the deployment |
| D-06 | Deploy test page | **APPROVED** — removed during P1-BASE, in the stated order, after Laravel is verified at the site root |

D-03 (bootstrap method) and D-04 (Entra registration) are **DEFERRED TO P1-00**.
They do not block P1-BASE and are not solved here.

---

## 3. Server-side changes

The server currently serves a static page from `public_html` with no forwarder and
no application. Three changes are required, and each is a real risk to the live
host.

### 3.1 Restore the front-door forwarder

Laravel deploys its whole tree into `public_html`, so only `public/` may be
web-reachable. A forwarder `.htaccess` at the document root must:

- deny dotfiles, with `.well-known/` exempted so ACME/TLS renewal keeps working;
- deny `.env`, `composer.*`, `package*.json`, `artisan`, `phpunit.xml`, `*.md`,
  `*.yml`, `*.log`, `*.sqlite` by name;
- 404 the `app`, `bootstrap`, `config`, `database`, `doc`, `resources`, `routes`,
  `storage`, `tests`, `vendor` and `deployment` directories;
- rewrite everything else into `public/`.

This file is version-controlled and staged by the deploy workflow. It is written
fresh from the v2 requirements above, not copied from v1.

**Verification is part of this unit, not assumed:** after deploy, each denied path
is requested over HTTPS and asserted to be non-200.

### 3.2 Restore a real deploy workflow

`deploy.yml` is currently parked. It is restored with automatic triggers in the
same change that introduces `composer.json`, and reviewed before it runs. Two
points need care:

- **`--delete` scope.** The document root holds `.env` and `.well-known/`, neither
  in the repository. If `--delete` is used it must exclude `.env`,
  `.well-known/`, `storage/` and `public/storage`, or it will destroy
  credentials that exist nowhere else. The parked workflow already carries those
  excludes; they must be re-read and confirmed against the current server layout
  rather than trusted.
- **`deploy-test.yml`** is retired once the real deploy works, and
  `public/index.html` is removed so it cannot shadow Laravel's front controller.

### 3.3 Database

A cPanel MySQL database and user are created, and `DB_DATABASE`, `DB_USERNAME`
and `DB_PASSWORD` are filled in on the server `.env` only. Migrations run on
first release; whether that is a CI step over SSH or a manual first run is D-05.

No credential is ever written to the repository.

---

## 4. CI quality gates

`ci.yml` is restored with `pull_request` and `push: main` triggers in the same
change that adds `composer.json`. Gates, all blocking:

| Gate | Command |
| --- | --- |
| Formatting | `vendor/bin/pint --test` |
| Tests | `php artisan test` |
| Frontend build | `npm ci && npm run build` |
| Deploy prerequisites | Assert every file `deploy.yml` stages actually exists |

The last gate exists because a missing `deployment/public_html.htaccess` once
caused seven consecutive silent deploy failures. That check is cheap and is kept.

The suite runs against SQLite in memory; the production driver stays MySQL. Any
schema construct that MySQL rejects but SQLite accepts must be caught — a known
prior failure mode was an index identifier exceeding MySQL's limit. A guard test
for identifier length is included in this unit.

---

## 5. Acceptance criteria

P1-BASE is accepted when all of the following are demonstrated with real
evidence, not assertions:

1. `composer install && npm ci && npm run build && php artisan test` passes on a
   clean checkout.
2. CI is green on a pull request and on `main`.
3. The application deploys through the restored pipeline and answers at
   `https://semantiq.claas2saas.com/` with a Laravel-rendered response.
4. Every path listed in §3.1 is requested over HTTPS and returns non-200.
5. `.env` and `.well-known/` still exist on the server after a deploy.
6. `php artisan migrate` runs against MySQL and the migration table is present.
7. The health check reports real state — database reachable, config present — and
   fails when a required value is missing rather than reporting success.
8. The shell renders with the approved design system tokens, brand assets and
   responsive behaviour, in light and dark themes.
9. No file in the repository originates from SemantIQ v1.

---

## 6. Test plan

| Type | Cases |
| --- | --- |
| Build | Clean install, asset build, no dev dependencies in the production install |
| Schema | Migration runs; identifier lengths within MySQL limits |
| Config | Missing required config fails at boot with a safe message and no secret |
| Health | Reports failure when the database is unreachable; never hard-codes success |
| Web exposure | Each denied path in §3.1 returns non-200 over HTTPS |
| Deploy safety | `.env` and `.well-known/` survive a deploy |
| UI | Shell renders in both themes; responsive at the design system's breakpoints |

Negative cases are the point of the exposure and config tests. A health check
that cannot fail is not a health check.

---

## 7. Files expected to change

Indicative, for review scale — not a commitment to an exact tree.

```text
composer.json, composer.lock, package.json, package-lock.json
artisan, phpunit.xml, vite.config.js, .env.example, .gitignore, .editorconfig
app/            bootstrap/      config/         database/migrations/
public/index.php                resources/js/   resources/views/
routes/web.php                  tests/Feature/  tests/Unit/
deployment/public_html.htaccess
.github/workflows/ci.yml        (triggers restored)
.github/workflows/deploy.yml    (triggers restored, excludes confirmed)
.github/workflows/deploy-test.yml (retired)
public/index.html               (removed)
```

Schema impact: Laravel's framework tables only — migrations, cache, jobs,
sessions if the session driver requires them. **No domain tables in this unit.**

---

## 8. Risks specific to this unit

| Risk | Handling |
| --- | --- |
| Restored `deploy.yml` prunes `.env` | Confirm excludes by reading the workflow against the real server layout before the first run; verify `.env` present afterwards |
| Forwarder wrong, application tree exposed | Each denied path asserted over HTTPS as an acceptance gate |
| Forwarder wrong, site 404s | Deploy verified by an HTTPS read-back, as the current deploy test already does |
| SQLite-versus-MySQL divergence | Identifier-length guard test; migrations run against real MySQL before acceptance |
| Test page shadows the front controller | Removed in this unit; deploy verified afterwards |

---

## 9. Stop point

This plan stops here for approval. On approval it moves to
`P1-BASE-APPLICATION-BASELINE-DESIGN.md`. No application code is written before
that design is approved.
