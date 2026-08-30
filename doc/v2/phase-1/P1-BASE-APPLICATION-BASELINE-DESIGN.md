# P1-BASE — Application Baseline — DESIGN

**Status:** APPROVED WITH AMENDMENTS — 30 August 2026. Cleared for EXECUTE.
**Unit:** P1-BASE — PLAN → DESIGN → EXECUTE
**Plan:** `P1-BASE-APPLICATION-BASELINE-PLAN.md`
**Decisions:** `PHASE-1-PLAN.md` §4 (D-01, D-02, D-05, D-06 approved; D-03, D-04
deferred). D-07 and D-08 decided at design approval — see §21.

Four mandatory corrections were made at approval and are incorporated in place:

| # | Correction | Section |
| --- | --- | --- |
| 1 | First deployment differs from subsequent deployment; no remote `artisan` exists yet | §11 |
| 2 | The static test page is removed in the implementation commit, not after a verify step | §12, §18 |
| 3 | `--delete` exclusions asserted by a pre-flight check, not documented and trusted | §12 |
| 4 | Configuration validation distinguishes P1-BASE requirements from future-phase ones | §6 |

Implementation is authorised strictly within this design. Anything outside it —
Microsoft SSO, bootstrap, users, roles, domains, Fabric, Workplace — is out of
scope for P1-BASE.

---

## 1. Laravel 13 / PHP 8.5 baseline architecture

Fresh `laravel/laravel` skeleton via `composer create-project`. Nothing is copied
from SemantIQ v1.

| Concern | Decision | Why |
| --- | --- | --- |
| Starter kit | **None.** No Breeze, Jetstream or Fortify | Every starter kit ships password authentication, registration and reset. SemantIQ has no local passwords and no self-registration; P1-00 is SSO-only. Removing scaffolding later leaves routes and views behind, which is exactly the accidental surface the blueprint forbids |
| PHP | 8.5, matching the runner and the cPanel runtime | Divergence between CI and production hides failures until deploy |
| Auth scaffolding | Laravel's `auth` config and session guard retained, **no driver wired** | P1-00 supplies the identity provider. The guard exists and denies from day one |
| Queue | `sync` | No worker on cPanel today. A real driver is a Phase 1 Gate 6 concern, not P1-BASE |
| Cache | `file` | No Redis on the host |
| Session | **`database`** | Session revocation on privilege change is a P1-00 requirement. A file-driver session cannot be invalidated server-side by user id. The `sessions` table is Laravel framework baseline, not business schema |

The session-driver choice is the one place P1-BASE deliberately looks ahead. It
creates a framework table, not a SemantIQ table, and reversing it later would
mean rewriting session handling during the security unit.

---

## 2. React 19 + Vite integration

Vite is the build tool, `laravel-vite-plugin` the bridge, `@vitejs/plugin-react`
the transform. Assets build to `public/build` with a manifest, which Blade reads
through `@vite`.

**`public/build` is gitignored and must be present at runtime.** It is built in
CI and in deploy, and shipped by rsync. A prior failure mode was feature tests
rendering `@vite` views on a clean runner with no manifest, producing a 500 that
reads like an application bug. CI builds assets before running tests.

The integration pattern is **Inertia + React**, approved as D-07 (§21). Laravel
owns routing, session, authorisation and refusal states; React renders. There is
no separate SPA authentication surface and no token auth for Release 1.

---

## 3. Modular monolith structure

```text
app/
  Modules/
    Platform/                  <- the ONLY module in P1-BASE
      Http/Controllers/
      Http/Middleware/
      Providers/
      Support/
      Health/
  Shared/
    Navigation/                <- product areas, registry, contracts
    Support/
```

Module rules, enforced by review and by an architecture test:

- A module exposes behaviour through its own contracts. No module reaches into
  another module's models or internals.
- Shared concerns live in `app/Shared`. Anything placed there needs a stated
  reason; "two modules might want it" is not one.
- Each module registers itself through a service provider. Adding a module is
  adding a directory and a provider, not editing a central switch.

**P1-BASE creates exactly one module, `Platform`** — shell, health, configuration.
Identity, Organisation, Access, Audit and the rest are created by the units that
own them. No empty directories are pre-created to "reserve" them.

Per D-01, no role, domain, scope or sensitivity schema appears here. What P1-BASE
establishes is the boundary those later units plug into: a navigation registry
whose nodes carry a policy key, and a guard contract that resolves it. In
P1-BASE the resolver denies everything.

---

## 4. SemantIQ three-product-area shell architecture

Per D-02, the top level is three product areas:

```text
System Administration   Fabric Configuration   SemantIQ Workplace
```

Not the four generic clusters. Audit, Access Reviews and Security Status sit
inside System Administration.

### Navigation is data, resolved server-side

```text
ProductArea ── has many ──> NavigationNode
                              ├─ label
                              ├─ icon
                              ├─ route (leaf) or children (group)
                              └─ policyKey        <- REQUIRED on every node
```

The sidebar is rendered from the registry filtered by the current user's
effective access. Per the blueprint, navigation is generated from effective
access, and menu hiding is never the control — every route re-authorises.

### The honest-empty rule

P1-BASE registers the three areas and **zero navigable nodes**. There is nothing
implemented to navigate to yet.

Consequently the shell must render a truthful empty state rather than a menu of
links to nothing. Fabric Configuration and SemantIQ Workplace are represented in
the architecture but contribute no visible menu, in either P1-BASE or the rest of
Phase 1, until their phases deliver real screens. An area with no authorised,
implemented nodes does not render.

This is a design rule with teeth: a node cannot be registered without a route
that resolves and a policy key that a guard evaluates. Placeholder screens are
unrepresentable rather than merely discouraged.

### What is reachable in P1-BASE

Because authentication is P1-00, there is no way to hold a session in P1-BASE.
The blueprint requires that no protected shell is returned before identity
verification, so:

| Route | P1-BASE behaviour | Replaced by |
| --- | --- | --- |
| `/` | Pre-authentication entry page: brand mark, product name, no menu, no shell, no business metadata | P1-00 Login page |
| `/app/*` | Guarded. The guard resolves no identity and **fails closed**, redirecting to `/` | P1-00 authenticated landing |
| `/up` | Liveness probe, see §13 | — |

The shell layout and navigation registry are built and covered by tests, but no
authenticated route can be reached until P1-00 supplies an identity. The
deny-by-default path is therefore exercised from the first unit, before there is
anything to protect — which is the right order.

---

## 5. Shared CLaaS2SaaS design-system integration

The design system governs presentation. Per D-02 it does not govern information
architecture.

| Element | Approach |
| --- | --- |
| Tokens (§4) | Colour, spacing, radius, elevation and motion as CSS custom properties, defined once, consumed by React |
| Themes | Light and dark from the same token contract. Both are first-class; neither is an afterthought |
| Type scale (§4) | Enforced. Arbitrary font sizes are a review failure |
| Brand assets | `doc/design-system/assets/` copied into `resources/`, referenced by token, not by hard-coded path |
| Shell dimensions (§4) | Sidebar and header dimensions taken from the standard |
| Auth archetype (§5.7) | The `/` entry page uses it: standalone centred card, no shell, brand mark, trust footer |
| Accessibility | Contrast, focus order, keyboard navigation and reduced-motion honoured as the standard requires |

Only what P1-BASE needs is built: the shell layout and the auth archetype. Other
page archetypes are built by the units that first need them.

Both approved deviations — the seven-role model and the three product areas — are
recorded in a short `doc/v2/DESIGN-SYSTEM-DEVIATIONS.md`, as the standard asks
for documented per-app reasons.

---

## 6. Environment and configuration strategy

| Layer | Contents | Committed |
| --- | --- | --- |
| `.env.example` | Every key the application reads, with empty or safe defaults | Yes |
| Server `.env` | Real values, including `DB_*` and later `MICROSOFT_*` | **Never** |
| `config/semantiq.php` | SemantIQ-specific configuration, read from env at boot | Yes |
| GitHub environment secrets | Deployment credentials only, never application secrets | n/a |

**Boot-time validation, scoped to this unit (Correction 4).** The validator
asserts only what P1-BASE actually needs to run. Future-phase configuration is
declared but not required, and its absence is not a boot failure.

| Class | Keys | P1-BASE behaviour |
| --- | --- | --- |
| **Required now** | `APP_KEY`, `APP_ENV`, `APP_URL`, `DB_CONNECTION`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Missing or malformed ⇒ refuse to serve |
| **Required with a safe default** | `APP_DEBUG` (false in production), `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` | Defaulted; a production boot with `APP_DEBUG=true` is a validation failure |
| **Declared, not yet required** | `MICROSOFT_TENANT_ID`, `MICROSOFT_CLIENT_ID`, `MICROSOFT_CLIENT_SECRET`, `MICROSOFT_REDIRECT_URI` | Present in `.env.example` as empty. **Absence is not an error in P1-BASE.** They become required when P1-00 activates Microsoft authentication |

The requirement set is declared as data, so P1-00 promotes the Microsoft keys
from *declared* to *required* by moving them between lists — no validator rewrite,
and no chance of the promotion being forgotten.

**No placeholder secrets.** Fake Microsoft values are never created to satisfy
validation. An empty value that is not yet required stays empty; a fake one would
survive into P1-00 and fail at the identity provider instead of at boot.

On failure the application refuses to serve and returns a generic error. The
specific missing key goes to the log, never to the response. The failure mode this
guards against is a deploy that succeeds while the application quietly misbehaves.

No secret is ever logged, rendered, or included in an error payload or health
response.

---

## 7. MySQL connection and migration lifecycle

### One-time provisioning prerequisite (D-05)

Performed once by infrastructure administration, outside the application:

1. Create the cPanel MySQL database.
2. Create a dedicated database user and grant it on that database only.
3. Set `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` in the server `.env`, and
   leave **`APP_KEY=` empty**.

Never built into application code. Never stored in the repository or in GitHub.

**Verification:** `php artisan db:show` over SSH reports the connection, and the
health check (§13) reports the database reachable. Both are recorded as evidence.

### APP_KEY bootstrap — INITIAL deployment only

The provisioning above writes `.env` by hand, and a Laravel application key
cannot reasonably be written by hand. Before the first deployment there is no
remote `artisan` to generate one either. A deployment that refused to proceed on
an empty `APP_KEY` would therefore never reach the point where it could fix
that — a deadlock in the first version of this design.

The key is generated exactly once, by `deployment/ensure-app-key.sh`, piped to
the server over stdin after the sync, when **all four** hold:

1. the server `.env` exists;
2. `APP_KEY` is absent, empty or malformed;
3. the application has been transferred — `artisan` and `vendor/autoload.php`
   both present;
4. the detected state is **INITIAL**.

| State | Key | Behaviour |
| --- | --- | --- |
| INITIAL | absent / empty / malformed | Generate once, then verify `.env` holds a `base64:` key |
| INITIAL | valid | Leave untouched |
| UPDATE | valid | Leave untouched |
| UPDATE | absent / empty / malformed | **Fail the deployment** with an explicit message |

**Never regenerated on an UPDATE.** Rotating `APP_KEY` on a running system
invalidates every encrypted cookie and session, and anything else encrypted with
it — an outage that looks like data loss, caused by a deployment trying to help.

**The key value never leaves the server.** `key:generate` output is discarded so
nothing carrying it can reach the workflow log whatever a future Laravel version
decides to print. It is never echoed, never returned to GitHub and never written
to repository configuration. Only the *presence* of a key is ever reported.

`APP_KEY=base64:` with nothing after it counts as missing, not present. Matching
the shape rather than the prefix is what makes that true.

The logic lives in a script rather than inline SSH shell so it can be executed
against fixtures. `tests/Architecture/AppKeyBootstrapTest.php` runs the real
script with a stubbed `php` for each row of the table above, plus the
no-`.env` and no-Laravel refusals, and asserts the generated key appears in
neither stream. Deleting the UPDATE guard fails two of those tests, which is how
the coverage is known to be real rather than decorative.

### Migration lifecycle

Migrations run through the deployment workflow over SSH:

```bash
php artisan migrate --force --no-interaction
```

Manual execution is not the normal production path. **Migration failure fails the
deployment.** The sequencing in §11 keeps the application in maintenance mode
across the migration, so a failed migration never leaves a running application on
an inconsistent schema.

P1-BASE creates **framework baseline tables only**: `migrations`, `sessions`
(§1), and cache/job tables only if the configured drivers require them. No
SemantIQ business tables, role tables, domain tables, Fabric tables or
future-phase schema.

---

## 8. cPanel document root and `public/` forwarding

The document root is `public_html`, and Laravel deploys its whole tree into it.
Only `public/` may ever be reachable.

```text
public_html/                 <- DOCUMENT ROOT, and the whole Laravel tree
├── .htaccess                <- forwarder: guards, then rewrite into public/
├── .env                     <- server-managed secret, MUST NOT be served
├── .well-known/             <- server-managed, MUST stay reachable (ACME/TLS)
├── app/ bootstrap/ config/ database/ resources/ routes/ tests/ vendor/
│                            <- MUST NOT be served
├── storage/                 <- server-managed runtime state, MUST NOT be served
├── artisan                  <- MUST NOT be served
└── public/                  <- the ONLY web-reachable directory
    ├── index.php            <- front controller
    └── build/               <- compiled assets
```

Request flow:

```text
GET /admin/users
      │
      ▼
public_html/.htaccess
      │
      ├── path is .well-known/*        ──> serve (TLS renewal)
      ├── path is a dotfile            ──> 403
      ├── name matches a denied file   ──> 403     guard one
      ├── path is a denied directory   ──> 404
      │
      └── otherwise rewrite ──> public/admin/users
                                     │
                                     ▼        guard two
                               public/.htaccess ──> public/index.php
```

### D-08 precedence — preferred architecture first

**Preferred.** If cPanel allows `semantiq.claas2saas.com` to use
`public_html/public` as its document root, that architecture is used. `.env`,
`vendor/`, `app/`, `config/`, `database/`, `storage/`, `routes/` and the rest sit
**physically outside** the HTTP document root, which is materially stronger than
relying on rewrite rules. Laravel's normal `public/.htaccess` then handles routing.

The root-level forwarder may remain as defence against an accidental future
document-root change, but it is **not** the primary boundary in this mode.

**Fallback.** If the document root cannot be changed, the hardened
`public_html/.htaccess` architecture above stands and is load-bearing.

**Neither mode weakens the §16 exposure tests.** Even with `public_html/public`
configured, `/.env`, `/composer.json`, `/vendor/`, `/storage/` and `/.git/config`
must still return 403 or 404 and must never redirect into application
authentication.

**This does not block implementation.** The application code is identical either
way. Before the first deployment the actual cPanel capability is checked and the
result recorded in the verification document as exactly one of:

- **D-08A** — `public_html/public` supported and configured
- **D-08B** — document-root change unavailable; hardened forwarder used

The result is established by inspection, never guessed.

---

## 9. `.htaccess` security design

Two independent guards. Either alone would suffice today; together, neither one
failing silently exposes a credential.

**Guard one — refuse by name and by directory.**

- `.well-known/` exempted first, before any dotfile rule. Blocking it breaks TLS
  renewal weeks later in a way nobody connects back to this file.
- All other dotfiles denied.
- Denied by filename: `.env*`, `composer.json`, `composer.lock`, `package.json`,
  `package-lock.json`, `artisan`, `phpunit.xml`, `vite.config.js`, `*.md`,
  `*.yml`, `*.yaml`, `*.log`, `*.sqlite`.
- Denied by directory: `app`, `bootstrap`, `config`, `database`, `doc`,
  `resources`, `routes`, `storage`, `tests`, `vendor`, `deployment`.

Matching by name as well as by directory means a file that moves does not quietly
lose its guard.

**Guard two — rewrite everything else into `public/`.**

No `RewriteBase`, no domain and no absolute path, so the same file works across
domains and subdomains.

The file is version-controlled at `deployment/public_html.htaccess` and staged to
the document root by the deploy workflow. It is written fresh from the
requirements above; no v1 file is copied.

**Correct-looking configuration is not proof.** §16 defines the negative tests
that must actually pass.

---

## 10. GitHub Actions CI architecture

`ci.yml` is restored with `pull_request` and `push: main` triggers in the same
change that introduces `composer.json`.

| Step | Purpose |
| --- | --- |
| Checkout | — |
| PHP 8.5 + `pdo_sqlite`, `mbstring` | Match production PHP |
| `composer install` (with dev) | Pint and PHPUnit live in dev |
| Node 24, `npm ci` | — |
| `npm run build` | Feature tests render `@vite`; without the manifest they 500 |
| `cp .env.example .env && php artisan key:generate` | A throwaway key per run; no fixed key is ever committed |
| **MySQL service + `php artisan migrate`** | See §17 — migrations are proven against real MySQL, not only SQLite |
| `vendor/bin/pint --test` | Formatting |
| `php artisan test` | Tests |
| Deploy-prerequisite check | Assert every file `deploy.yml` stages exists |

The last step exists because a missing `deployment/public_html.htaccess` once
produced seven consecutive silent deploy failures. It is cheap and it stays.

CI is read-only, holds no deployment secret and never touches the server.

---

## 11. GitHub Actions deployment architecture

`deploy.yml` restored with `push: main` and `workflow_dispatch`, bound to the
`development` environment for environment-scoped secrets.

### Two deployment states (Correction 1)

The sequence cannot assume a remote `artisan`. On the first P1-BASE deployment
there is no Laravel installation on the server at all, so `php artisan down`
would fail and abort the pipeline **before** it could install the application
that would have made the command work.

**Detection is explicit and deterministic.** A directory existing is not
evidence of a working installation. The deployment probes for all of:

```text
$CPANEL_DEPLOY_PATH/artisan                    exists
$CPANEL_DEPLOY_PATH/vendor/autoload.php        exists
$CPANEL_DEPLOY_PATH/bootstrap/app.php          exists
php artisan --version                          exits 0
```

All four ⇒ **EXISTING**. Anything else ⇒ **INITIAL**. A missing *or broken*
prior installation must never block installing the new one, so a failed probe
resolves to INITIAL rather than to an error.

#### Initial deployment

```text
 1  checkout
 2  composer install --no-dev --optimize-autoloader
 3  npm ci && npm run build
 4  remove node_modules            (not a production runtime need)
 5  stage deployment/public_html.htaccess -> .htaccess
 6  configure SSH (agent, askpass, keyscan)
 7  verify SSH authentication
 8  rsync exclusion pre-flight     (§12 — fails before any transfer)
 9  detect installation state      -> INITIAL
10  (no artisan down — nothing to put into maintenance)
11  rsync (§12)
12  ensure storage/ and bootstrap/cache exist and are writable
13  verify server .env exists and required configuration is present
14  php artisan migrate --force --no-interaction
15  php artisan optimize:clear
16  health verification            (§13)
17  HTTPS verification             (§16)
18  exposure-negative tests        (§16)
```

Step 13 matters on this path specifically: the server `.env` is created by the
one-time provisioning of D-05, and the first deployment is where a missing or
unconfigured `.env` would otherwise surface as a confusing migration error.

#### Subsequent deployments

```text
 1-8  as above
 9    detect installation state    -> EXISTING
10    php artisan down             <- maintenance window opens
11    rsync (§12)
12    ensure runtime directories
13    php artisan migrate --force --no-interaction
14    php artisan optimize:clear
15    health verification
16    php artisan up               <- maintenance window closes
17    HTTPS verification
18    exposure-negative tests
```

**Why the window spans sync and migration.** New code lands at step 11 and the
schema catches up at step 13. In between, the application would run new code
against an old schema. Maintenance mode makes that interval invisible.

### Migration failure

If the migration fails on either path the job fails and the deployment is not
completed. On the EXISTING path the application **stays in maintenance**:

- `php artisan up` does **not** run after a failed migration, on any path or
  condition;
- the failure is visible as a failed GitHub Actions job;
- no rollback migration runs automatically — a destructive automatic `down()` on
  a half-applied migration can lose data that the failure itself did not.

A visible maintenance page is better than a live application on an inconsistent
schema. Operator recovery is in §15.

---

## 12. rsync — source-controlled versus persistent paths

Every path is classified. Nothing is left to assumption.

| Class | Paths | Deployment treatment |
| --- | --- | --- |
| **Source-controlled** | `app/`, `bootstrap/` (less `cache/`), `config/`, `database/`, `public/` (less `storage`), `resources/`, `routes/`, `vendor/`, `artisan`, `composer.*` | Replaced on every deploy |
| **Generated build artefact** | `public/build/` | Built in CI, shipped, replaced |
| **Secrets / configuration** | `.env` | **Server-managed. Never sent, never deleted** |
| **Server-managed** | `.well-known/` | **Never sent, never deleted** — TLS renewal |
| **Persistent runtime data** | `storage/` (logs, sessions, cache, uploads), `public/storage` | **Never sent, never deleted** |
| **Repository-only** | `.git/`, `.github/`, `doc/`, `tests/`, `phpunit.xml`, `*.md`, `.editorconfig`, `.gitignore`, `.gitattributes`, `node_modules/`, `deployment/` | Excluded — not needed at runtime, and §9 denies them anyway |

`--delete` is retained so stale files do not accumulate, and every path in the
bottom four classes is an explicit `--exclude`. An exclude protects a path from
deletion as well as from transfer, so the classification above *is* the safety
mechanism.

### Enforced exclusion contract (Correction 3)

Documentation and comments are not protection. The mandatory exclusions are
declared once, as data, in `deployment/rsync-protected-paths.txt`:

```text
.env
.well-known/
storage/
public/storage
```

Two mechanisms enforce it, so that weakening it later is hard rather than merely
discouraged:

1. **Deployment pre-flight.** Before any transfer, the workflow asserts that
   every path in the contract appears as an `--exclude` in the rsync command it
   is about to run. A missing entry **fails the job before rsync starts**. This
   runs on every deployment, not only the first.
2. **CI test.** A repository test parses `.github/workflows/deploy.yml` and
   asserts the same thing, so a pull request that drops an exclusion goes red
   without needing a deployment to discover it.

Any further server-generated persistent path found during implementation is added
to the contract file, and both mechanisms pick it up with no other change.

**Three independent protections for `.env`:** excluded from rsync, asserted by
the pre-flight, and denied over HTTP by §9. Losing it is unrecoverable — it is
the only copy of the database password and, from P1-00, the Microsoft client
secret.

After deployment, acceptance asserts every protected path still exists (§18).
Verified, not assumed, because the cost of being wrong is a credential that
exists nowhere else.

### Retiring the static test page (Correction 2)

The deploy-test workflow copies the repository's `public/index.html` to the
server document root. It is not a Laravel file, and it must never sit beside
`public/index.php`, where Apache's `DirectoryIndex` could serve it instead of the
front controller.

The removal therefore happens **in the implementation commit**, not after a
verification step. Verifying Laravel first and removing the page afterwards would
require a window in which both exist — exactly the shadowing risk being avoided.

In the commit that introduces the real application:

- `public/index.html` is removed from the repository;
- `deploy-test.yml` is retired;
- `public/index.php` is introduced;
- the real `deploy.yml` is restored.

The static page may remain **on the server** until the first real deployment,
which is what keeps the existing proof intact until the moment it is replaced.
The first deployment then removes the obsolete root `index.html` as part of the
controlled transition: it is not in the repository, so it is not transferred, and
it is not in the exclusion contract, so `--delete` removes it.

---

## 13. Health-check design

Two surfaces, deliberately split by audience.

**`GET /up` — public liveness.** Returns `200` or `503` and a single status word.
Nothing else.

It must never reveal Laravel or PHP versions, database names, hostnames,
usernames, environment values, stack traces, migration names, filesystem paths or
secrets. A test asserts the response body matches a strict allowlist rather than
merely "does not contain a secret" — an allowlist cannot be outgrown by a future
edit that adds a field.

**Availability during maintenance is implemented, not assumed.** Laravel's
maintenance mode intercepts requests in middleware before routing, so `/up` is
*not* automatically exempt. The exemption is explicit — the maintenance
middleware carries `/up` in its URI exemption list — and it is **tested**, by
putting the application into maintenance and asserting `/up` still answers while
an ordinary route returns 503. Without that test, a deploy-time probe could be
reporting on maintenance mode rather than on application health.

**`php artisan semantiq:health` — operator diagnostics, over SSH.** Reports:

| Check | Real assertion |
| --- | --- |
| Database | Opens a connection and runs a trivial query |
| Migrations | No pending migrations |
| Configuration | Every required key present and well-formed |
| Storage | `storage/` and `bootstrap/cache` writable |
| Assets | `public/build/manifest.json` exists |

Every check performs a real operation. **A check that cannot fail is not a
check** — hard-coded success is a defect, and P1-BASE tests prove each check
fails when its dependency is broken (§18).

No secret, connection string or business payload appears in either surface. The
richer surface is reachable only over SSH, so it is bounded by server access
rather than by a web permission that does not exist yet.

`/up` returns `503`, not `200`, when a dependency is down, so a monitor sees the
failure. This route is exempt from maintenance mode; a deploy in progress reports
unhealthy rather than lying.

---

## 14. Failure behaviour

| Failure | Behaviour |
| --- | --- |
| Required config missing | Refuse to serve. Generic error to the client; specifics to the log only |
| Database unreachable | `/up` returns 503; error pages carry no connection detail |
| Migration fails during deploy | Deploy job fails, application stays in maintenance mode (§11) |
| Vite manifest missing | Fails at boot with a clear operator message, not a 500 mid-render |
| Unhandled exception in production | Generic error page. `APP_DEBUG=false` enforced; a debug-enabled production boot is a configuration validation failure |
| Guard cannot resolve identity | **Deny.** Redirect to `/`. Never fall through to content |

The last row is the one that matters most and is exercised from P1-BASE onward,
before there is any real identity to resolve.

---

## 15. Deployment rollback

rsync deployment is not versioned, so rollback is a forward operation.

| Scenario | Recovery |
| --- | --- |
| Bad code, schema unchanged | `workflow_dispatch` the deploy at the previous good commit |
| Bad code, schema changed | Re-deploy previous commit; reverse the migration only if it has a tested `down()` |
| Migration failed mid-deploy | App is already in maintenance (§11). Fix forward and re-deploy, or restore the database from backup |
| Server left in maintenance | `php artisan up` over SSH once the cause is resolved |

**Migrations are forward-only by default.** A `down()` is written only where it is
genuinely safe and tested; a `down()` that silently loses data is worse than none.
Destructive migrations are called out for review at the unit that introduces
them — P1-BASE has none.

Database backup and restore are cPanel platform responsibilities. P1-BASE
documents the restore path; it does not implement backup.

---

## 16. Web-exposure negative-test design

Run against the real deployed site over HTTPS, as an acceptance gate. Not
simulated, not asserted from configuration.

| Path | Required | Not acceptable |
| --- | --- | --- |
| `/.env` | 403 or 404 | 200; **any redirect**; any response containing `APP_KEY` or `DB_` |
| `/composer.json`, `/composer.lock` | 403 or 404 | 200 |
| `/package.json`, `/package-lock.json` | 403 or 404 | 200 |
| `/artisan`, `/phpunit.xml`, `/vite.config.js` | 403 or 404 | 200 |
| `/app/`, `/bootstrap/`, `/config/`, `/database/` | 403 or 404 | 200 |
| `/resources/`, `/routes/`, `/tests/`, `/vendor/` | 403 or 404 | 200 |
| `/storage/logs/laravel.log` | 403 or 404 | 200 |
| `/deployment/public_html.htaccess` | 403 or 404 | 200 |
| `/doc/`, `/README.md` | 403 or 404 | 200 |
| `/.git/config` | 403 or 404 | 200 |
| `/.well-known/` | reachable | 403 or 404 |
| `/` | 200, the pre-auth entry page | a directory listing |

**A redirect to a Login page is not sufficient proof for a filesystem path.** A
`302` means the request reached the application and was handled; the requirement
is that these files are never served at all. Tests assert the status code
directly and additionally assert that no response body contains a known secret
marker.

Requests are cache-busted. A cached `200` from an earlier state would otherwise
read as a pass — a mistake already made once in this repository, against the
deploy test itself.

---

## 17. MySQL / SQLite compatibility

Tests run on SQLite in memory for speed; production is MySQL. The gap is real and
is closed rather than hoped away.

| Risk | Mitigation |
| --- | --- |
| Identifier longer than MySQL's 64 characters | A guard test asserts every index, key and constraint name is within the limit. A prior release failed in production on exactly this |
| SQLite accepts a construct MySQL rejects | **CI runs migrations against a real MySQL service container**, in addition to the SQLite suite |
| Type or default differences | Migrations use portable column types; anything vendor-specific is called out at its unit |
| Charset / collation | Set explicitly in the connection config, not left to server defaults |

The MySQL service step is what turns this from a convention into a gate: a
migration that MySQL will reject fails CI rather than the deploy.

---

## 18. P1-BASE acceptance and verification procedure

Every item produces recorded evidence in
`P1-BASE-APPLICATION-BASELINE-VERIFICATION.md`. Assertions without evidence do not
count.

**A — Local**
1. Clean checkout: `composer install && npm ci && npm run build && php artisan test` passes.
2. `vendor/bin/pint --test` passes.

**B — CI**
3. CI green on the pull request.
4. CI green on `main`.
5. Migrations pass against the MySQL service container.

**C — Provisioning (D-05 prerequisite)**
6. Database and user exist; `php artisan db:show` succeeds over SSH.
7. Server `.env` carries `DB_*`; no credential in the repository.

**D — Deployment**
8. Deploy completes; migrations run; maintenance mode opens and closes.
9. `https://semantiq.claas2saas.com/` returns the Laravel pre-auth entry page.
10. `.env` still present after deploy.
11. `.well-known/` still present and reachable after deploy.
12. `storage/` contents preserved across deploy.

**E — Web exposure**
13. Every §16 negative test passes, cache-busted, against the live site.
14. No response body contains a secret marker.

**F — Health and failure**
15. `/up` returns 200 when healthy.
16. `/up` returns 503 with the database made unreachable — proven, not assumed.
17. `semantiq:health` reports each check accurately.
18. Boot with a required config key removed refuses to serve and leaks nothing.

**G — Deploy test retirement (Correction 2 order)**
19. Existing static deployment proof known good before rollout.
20. `public/index.html` and `deploy-test.yml` removed in the implementation
    commit, before the first real deployment.
21. First real deployment removes the obsolete server root `index.html`.
22. Laravel is the site root; HTTPS read-back proves a Laravel response.
23. No deploy-test artefact remains capable of shadowing `public/index.php`.

**J — D-08 document root**
24. Actual cPanel capability checked and recorded as **D-08A** or **D-08B**.
    Never guessed.

**H — UI**
23. Shell renders in light and dark with design-system tokens and brand assets.
24. Responsive behaviour at the standard's breakpoints.
25. Entry page follows the §5.7 Auth archetype.

**I — Boundaries**
26. No file originates from SemantIQ v1.
27. No role, domain, scope or sensitivity schema exists.
28. No Phase 2 or Phase 3 menu is navigable; no placeholder screens exist.
29. `/app/*` denies and redirects, having no identity to resolve.

---

## 19. Files and directories introduced

```text
composer.json  composer.lock  package.json  package-lock.json
artisan  phpunit.xml  vite.config.js  .env.example
.gitignore  .editorconfig  .gitattributes

app/Modules/Platform/{Http,Providers,Support,Health}/
app/Shared/{Navigation,Support}/
bootstrap/  config/  config/semantiq.php
database/migrations/          <- framework baseline only
public/index.php
resources/js/  resources/css/  resources/views/  resources/brand/
routes/web.php
tests/Feature/  tests/Unit/  tests/Architecture/

deployment/public_html.htaccess

.github/workflows/ci.yml            (triggers restored, MySQL service added)
.github/workflows/deploy.yml        (triggers restored, excludes confirmed)
.github/workflows/deploy-test.yml   (retired at step G)
public/index.html                   (removed at step G)

doc/v2/DESIGN-SYSTEM-DEVIATIONS.md
```

Schema: `migrations`, `sessions`, and cache/job tables only if the configured
drivers require them. Nothing else.

---

## 20. Boundary confirmation

**No SemantIQ v1 reuse.** No v1 application code, schema, migration, permission
model, authorisation code, workflow, business logic, API contract, test or menu
logic. The pre-reset history remains reachable through `refs/pull/*/head` and is
consulted for lessons only, never as a source implementation. Acceptance item 26
checks this.

**No prebuilt future functionality.** No Phase 2 or Phase 3 code, routes, tables
or menus. Within Phase 1, no unit beyond P1-BASE is anticipated in code: no
identity tables, no role or domain or scope or sensitivity schema, no admin
screens. The navigation registry is an empty structure with no nodes.

**The single deliberate exception** is the `database` session driver (§1), which
creates a Laravel framework table so that P1-00 can revoke sessions. It is
recorded here rather than left for a reviewer to discover.

---

## 21. Decisions taken at design approval

### D-07 — React integration — **APPROVED: Inertia + React**

```text
Laravel  ->  Inertia  ->  React 19  ->  Vite
```

Laravel remains authoritative for routes, authentication and session context,
authorisation, effective-access resolution, navigation authorisation, protected
data requests, and redirects and refusal states. React is the presentation layer.

No separate SPA authentication architecture for Release 1, and no JWT or local
token authentication introduced merely to serve the frontend. P1-00 integrates
Microsoft Entra SSO into the normal Laravel application session.

**Inertia is not authorisation.** Every protected controller, action and service
still enforces backend policy. React must never decide whether a user may
retrieve protected information. Navigation filtering is UX only; backend
authorisation is mandatory. This is written into the navigation contracts: a node
carries a policy key, and the route it points at re-authorises independently, so
filtering the menu and authorising the request are separate code paths that
cannot be collapsed into one by accident.

If a mobile application, external API or third-party consumer is needed later, a
dedicated API contract is created at that time against the same effective-access
engine. No speculative API is designed now.

### D-08 — cPanel document root — **APPROVED with precedence**

Preferred: document root at `public_html/public`, putting the application tree
physically outside the web root. Fallback: the hardened forwarder of §8–§9.
Neither mode weakens the §16 exposure tests. Full precedence and the recording
requirement are in §8; the outcome is recorded as D-08A or D-08B in the
verification document, established by inspection rather than assumed.

## 22. Stop point

This design stops here for approval.

On approval, P1-BASE moves to EXECUTE and the first SemantIQ v2 application code
is written, scoped to §19 and no further. Until then: no application code, no
migration, no server modification, and no P1-00 work.
