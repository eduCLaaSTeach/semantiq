# Deployment Layout Amendment — Front Controller at `public_html`

**Status:** DRAFT — awaiting product-owner approval. **Nothing is implemented.**
**Supersedes:** the D-08A discussion, now closed
**Affects:** `deployment/`, `.github/workflows/deploy.yml`, `bootstrap/app.php`, tests

---

## 1. Hosting decision — recorded as final

```
SemantIQ cPanel document root : public_html
SemantIQ deployment root      : public_html
D-08A                         : CLOSED / NOT TO BE PURSUED
D-08B                         : APPROVED PERMANENT HOSTING MODEL
```

The product owner has fixed this permanently. The hosting provider will **not** be
asked to repoint the document root, and future design work must not assume
`public_html/public` is the document root. Any document that implies otherwise is
to be corrected, not re-argued.

---

## 2. Current versus proposed

**Current — deployed and passing every gate**

```
public_html/.htaccess
      │  deny rules (never observed to fire)
      │  RewriteRule ^(.*)$ public/$1 [L]     <- rewrites EVERY request into public/
      ▼
public_html/public/index.php
```

**Proposed**

```
public_html/.htaccess
      │  deny rules  <- now the ONLY protection
      │  RewriteCond !-f !-d  →  index.php
      ▼
public_html/index.php
```

---

## 3. The finding that decides this amendment

> **Read this before approving. It is the reason the migration is not a
> file-moving exercise.**

Today, the line doing the protecting is:

```apache
RewriteRule ^(.*)$ public/$1 [L]
```

Every request — including `/app/Console.php`, `/.env`, `/vendor/autoload.php` — is
rewritten **into `public/`**, where those files do not exist. Laravel then returns
404. The real files sitting in `public_html/app/`, `public_html/vendor/` and
`public_html/.env` are never addressable, because no request is ever allowed to
resolve against the deployment root.

**That is why the live suite shows 29 × 404 and 0 × 403.** The deny rules are not
firing; the forwarder is doing all of it, and doing it comprehensively.

The proposed layout **removes that forwarder.** Under `RewriteCond %{REQUEST_FILENAME} !-f`,
a request for an existing file is served rather than rewritten. So:

| Request | Today | After migration, if the deny rules do not fire |
| --- | --- | --- |
| `/.env` | 404 (rewritten into `public/`) | **Served as plain text — full credential disclosure** |
| `/vendor/…/*.php` | 404 | **Executed as PHP, out of application context** |
| `/app/…/*.php` | 404 | **Executed as PHP, out of application context** |
| `/composer.lock` | 404 | **Served** |

The migration therefore transfers the security boundary **from a mechanism proven
by 29 live tests to a mechanism never once observed to work.** We have no positive
evidence any deny rule in `deployment/public_html.htaccess` has ever produced a
403 on this host.

This does not block the decision. It changes the order of work: **the deny rules
must be proven to fire before the forwarder is removed**, not afterwards.

### Prerequisite gate — proposed

Before any layout change reaches production, one deployment must demonstrate a
**403 originating from Apache**. Concretely: add a probe file whose only possible
protection is a deny rule, deploy, and observe 403 rather than 404. If it returns
404, the deny rules are inert on this host and the migration must not proceed until
that is understood.

This is cheap, runs through the normal pipeline, and changes no runtime behaviour.

---

## 4. Design answers to the twelve required points

### 4.1 Where `index.php` resides

`public_html/index.php`, staged at deploy time from a dedicated
`deployment/public_html-index.php`. The repository keeps `public/index.php`
untouched for local development and the test suite.

Two front controllers is a drift risk, mitigated by a test asserting the deployed
variant boots the same framework files as the stock one.

### 4.2 How `vendor/autoload.php` and `bootstrap/app.php` resolve

The stock controller resolves upward:

```php
require __DIR__.'/../vendor/autoload.php';        // public/ → base
$app = require_once __DIR__.'/../bootstrap/app.php';
```

At the deployment root, `vendor/` and `bootstrap/` are **siblings**, so the root
variant resolves in place:

```php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
```

The maintenance-mode check at the top of the file moves the same way
(`__DIR__.'/storage/framework/maintenance.php'`).

### 4.3 Where Vite assets are deployed

`npm run build` continues to emit to `public/build`. The deploy stages
`public/build` → `public_html/build`.

`@vite` generates URLs through `asset('build/…')`, which produces `/build/…` —
correct at the root without change.

### 4.4 favicon, robots and public assets

Staged from `public/` to the deployment root alongside `index.php`, by the same
step. The repository remains the source of truth for their content.

### 4.5 Does repository `public/` remain?

**Yes — as a build and source directory only.** It is not removed.

Removing it would break `npm run build`, `php artisan serve`, and the feature tests
that render `@vite`, for no benefit: the product-owner requirement concerns the
*server* layout, not the repository. Keeping it also makes rollback a configuration
revert rather than a file-restoration exercise.

On the server, `public_html/public/` is left in place during transition and is not
deleted as part of this amendment.

### 4.6 How GitHub Actions stages web-facing files

After `npm run build`, and before rsync, the workflow copies the web-facing set from
`public/` to the workspace root:

```
public/index.php    →  ./index.php      (replaced by deployment/public_html-index.php)
public/build/       →  ./build/
public/favicon.ico  →  ./favicon.ico
public/robots.txt   →  ./robots.txt
```

This mirrors how `deployment/public_html.htaccess` is already staged to `.htaccess`
today, so the pattern is established rather than new.

### 4.7 How `.htaccess` changes

From "deny, then forward everything into `public/`" to "deny, then serve the root
front controller":

```apache
# Deny rules first — unchanged in content, but now load-bearing.
RewriteRule ^\.well-known/ - [L]
RewriteRule (^|/)\. - [F,L]
RewriteRule (^|/)(composer\.(json|lock)|package(-lock)?\.json|artisan|phpunit\.xml|vite\.config\.js|[^/]*\.(md|ya?ml|log|sqlite))$ - [F,L]
RewriteRule ^(app|bootstrap|config|database|deployment|doc|node_modules|resources|routes|storage|tests|vendor)(/|$) - [F,L]

# Laravel's standard front-controller handling.
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [L]
```

The `!-f` condition is exactly what makes §3 dangerous: existing files are served,
not rewritten away.

### 4.8 How protected paths stay inaccessible

By the deny rules above and nothing else. There is no second mechanism once the
forwarder is gone — which is why §3's prerequisite gate exists. `FilesMatch` is
retained as an independent module, but it has also never been observed to fire.

### 4.9 How `.well-known/` stays usable

Matched and passed through **first**, before the dotfile rule that would otherwise
catch it. Unchanged from today, and asserted by the live suite on every deployment.

### 4.10 How rsync preserves server-managed state

Unchanged. `deployment/rsync-protected-paths.txt` continues to hold `.env`,
`.well-known/`, `storage/`, `public/storage`, enforced by the pre-flight on the
runner and by `RsyncExclusionContractTest` in CI.

**One addition required:** the newly staged root files (`index.php`, `build/`,
`favicon.ico`, `robots.txt`) must not be excluded, and the contract test should
assert they are transferred rather than silently dropped.

### 4.11 Rollback

Revert the amendment commit and redeploy. Because `public/` is retained in the
repository and `public_html/public/` is retained on the server, the previous
forwarder-based layout is restored by the `.htaccess` alone — the files it needs
never left.

Rollback is therefore a configuration revert through the normal pipeline, with no
manual server step.

### 4.12 Availability during transition

The deployment already runs `artisan down` / `artisan up` around the sync on the
EXISTING path. The cutover is a single `.htaccess` replacement inside that window,
so no request observes a half-migrated layout.

---

## 5. A Laravel convention is being deliberately adapted

Laravel's documented deployment model is that the web root points at `public/`.
This amendment departs from it, and two mechanisms depend on that assumption.

**`public_path()`** resolves to `base_path('public')`. After migration the web
files are at `base_path()` itself, so `public_path()` would point at the wrong
directory.

**This already breaks something concrete in P1-BASE.** `HealthInspector` checks:

```php
is_file(public_path('build/manifest.json'))
```

Under the new layout the manifest is at `public_html/build/manifest.json` while
`public_path()` returns `public_html/public/build/manifest.json`. The asset check
would report a missing manifest on a perfectly healthy deployment, and
`semantiq:health` would fail the deployment.

**Resolution.** Set the public path once, in `bootstrap/app.php`, so both the web
front controller *and* the Artisan CLI agree:

```php
->usePublicPath(env('APP_PUBLIC_PATH') ?: base_path('public'))
```

with `APP_PUBLIC_PATH` set in the **server** `.env` only. Local development and CI
keep Laravel's default. Setting it in the front controller alone would fix the web
path and leave `semantiq:health` — which runs under the CLI — still wrong.

Other conventions to re-check during implementation: `storage:link` (unused today,
no `public/storage` in service) and any future package that writes into
`public_path()`.

---

## 6. Report

### 6.1 Files and workflows requiring change

| File | Change |
| --- | --- |
| `deployment/public_html-index.php` | **New** — root front controller with sibling paths |
| `deployment/public_html.htaccess` | Forwarder replaced by root front-controller handling |
| `.github/workflows/deploy.yml` | Stage `index.php`, `build/`, `favicon.ico`, `robots.txt` to the root |
| `bootstrap/app.php` | `usePublicPath()` driven by `APP_PUBLIC_PATH` |
| Server `.env` | Add `APP_PUBLIC_PATH` — **product-owner action, one line, no secret** |
| `tests/Architecture/` | Front-controller parity test; staged-file transfer assertions |
| `doc/v2/phase-1/*` | D-08 decision recorded as final |

### 6.2 Security impact

**Materially negative until the prerequisite gate passes.** Protection moves from a
mechanism proven by 29 live tests to one never observed to fire. Worst case, if the
deny rules are inert, is plaintext disclosure of `.env` — database credentials and,
from P1-00, the Microsoft client secret — plus arbitrary execution of application
PHP outside its intended entry point.

After the gate passes, the posture is comparable to today's, with the same live
suite enforcing it on every deployment.

### 6.3 Migration sequence

1. **Prove a 403 is achievable** (§3 prerequisite). Deploy a probe; observe 403.
2. If 403 is not achievable → **stop and report**. Do not proceed.
3. Add `APP_PUBLIC_PATH` to the server `.env`.
4. Implement §6.1 in one PR; CI green; product-owner approval.
5. Merge → deploy on the EXISTING path, inside the maintenance window.
6. Run the full live exposure suite plus `/`, `/up`, `/console`, health.
7. Record observed results. Only then treat the layout as adopted.

### 6.4 Rollback sequence

Revert the commit → merge → redeploy. The `.htaccess` returns to forwarding into
`public/`, which is still present on the server and in the repository. No manual
server action, no data movement.

### 6.5 Tests required

- Front-controller parity: the deployed variant requires the same framework files.
- `usePublicPath` resolves to the deployment root when `APP_PUBLIC_PATH` is set, and
  to `base_path('public')` when it is not.
- Health asset check passes under both path configurations.
- Exclusion contract still holds, and the newly staged root files are transferred.
- Full live exposure suite — unchanged and non-negotiable.

### 6.6 Risks

| Risk | Severity | Handling |
| --- | --- | --- |
| Deny rules inert; `.env` served in plaintext | **Critical** | §3 prerequisite gate, before any layout change |
| Application PHP executed out of context | **High** | Same gate |
| `public_path()` breaks the health check and fails deploys | Medium | `APP_PUBLIC_PATH`, tested both ways |
| Two front controllers drift | Low | Parity test |
| Stale `public_html/public/` confuses future readers | Low | Retained deliberately for rollback; remove only after the layout is accepted |

---

## 7. Recommendation

The hosting decision is settled and this amendment implements it. My recommendation
concerns **sequencing only**:

**Approve the design, and authorise step 1 of §6.3 — the 403 probe — as a separate,
tiny change before anything else.** It is one file and one deployment, it changes no
runtime behaviour, and it answers the one question this migration genuinely rests
on.

If a 403 can be produced, the rest is routine. If it cannot, we would otherwise have
removed the only working protection from a directory holding a database password.

**Nothing is implemented. Awaiting approval.**
