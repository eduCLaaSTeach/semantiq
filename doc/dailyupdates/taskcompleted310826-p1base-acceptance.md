# P1-BASE Final Acceptance Report — 31 August 2026

**Recommendation: READY FOR P1-BASE ACCEPTANCE**

Acceptance is the Product Owner's alone. This records what was observed.

---

## 1–3. PR 1 — diagnostics removed, boundary hardened

| | |
| --- | --- |
| PR | [#39](https://github.com/eduCLaaSTeach/semantiq/pull/39) |
| Merge SHA | `e4830ee8a1f43883464b7006cfc38067bcd22baa` |
| CI | [33353084363](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33353084363) — success |
| Deployment | [33353179847](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33353179847) — success |

Every synthetic probe, marker and diagnostic step removed. `ErrorDocument 403`
kept as a **quoted literal** with a bare `Forbidden` body — the control that
keeps a denial observable instead of masked into a Laravel 404.

**Strengthened 403 gate:** requires **403**, never 403-or-404, on 26 protected
paths. A 404 now means the request fell *through* Apache into Laravel and fails
the deployment. Body scanning extended to PHP source, stack traces and framework
internals.

## 4–5. PR 2 — final `public_html` root layout

| | |
| --- | --- |
| PR | [#40](https://github.com/eduCLaaSTeach/semantiq/pull/40) |
| Merge SHA | `da7ddea8040996846488ccf1de2b1c7c6afc8f04` |
| CI | [33354172485](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33354172485) — success |
| Deployment | [33354267648](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33354267648) — **failed on one check** (see §16) |

| | |
| --- | --- |
| PR | [#41](https://github.com/eduCLaaSTeach/semantiq/pull/41) — corrective |
| Merge SHA | `3d075bfbf80392651577abe256526575632b3e73` |
| CI | [33354599929](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33354599929) — success |
| Deployment | [33354691876](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33354691876) — **success, all 27 steps** |

## 6. Root front-controller evidence

`/index.php` → **200**. `public_html/index.php` is SHA-256 verified against
`deployment/public_html.index.php` on every deployment; the run asserted a match.
It resolves `vendor/autoload.php`, `bootstrap/app.php` and
`storage/framework/maintenance.php` as siblings, enforced by test and by a
pre-transfer assertion.

## 7. No production dependency on `public_html/public`

| Evidence | Result |
| --- | --- |
| Server-side existence probe | `SEMANTIQ_PUBLIC_ABSENT` |
| `/public/index.php` | **403** |
| `/public/` | **403** |
| Site serving with it absent | `/` **200** |

The directory does not exist and the application answers. Nothing can depend on it.

## 8. Apache exposure — all 26 paths **403**, bodies 9 bytes, zero leaks

`.env` · `.env.example` · `.gitignore` · `.git/config` · `composer.json` ·
`composer.lock` · `package.json` · `package-lock.json` · `artisan` ·
`phpunit.xml` · `vite.config.js` · `app/` · `bootstrap/` · `config/` ·
`database/` · `resources/` · `routes/` · `storage/` · `tests/` · `vendor/` ·
`deployment/` · `doc/` · `public/` · `README.md` ·
`deployment/public_html.htaccess` · `storage/logs/laravel.log`

No `APP_KEY`, DB credential, Microsoft secret, connection string, server path,
PHP source or stack trace in any body.

## 9. `.htaccess` and front-controller checksums

Both **MATCH**; a mismatch fails the deployment. Permanent controls, not diagnostics.

## 10. `.well-known` / TLS renewal

| Check | Result |
| --- | --- |
| ACME challenge file written, fetched, body read back | **200, exact body** |
| `/.well-known/acme-challenge/<missing>` | **404** — resolves, only absent |
| `/.well-known/` listing | **403** — deliberate; ACME never lists |
| Self-test token after cleanup | **404** — removed |

## 11–14. Key, environment, database, health

| | |
| --- | --- |
| APP_KEY | preserved — `EXISTING` path, never regenerated |
| `.env` | preserved — excluded from transfer and deletion |
| Migrations | ran against production MySQL, success |
| `semantiq:health` | passed over SSH — database, migrations, configuration, storage, assets |
| Storage | runtime directories writable |

## 15. Assets

`/build/assets/app-B7L28IJq.css` **200** · `/build/assets/app-bHOReTcR.js`
**200** · `/build/` listing **403**. The page references `/build/assets/…` at the
root, not through any `public/` layer.

## 16. Unresolved risk

**None outstanding.** One item arose and was resolved:

The PR #40 deployment failed its `.well-known/` check. `Options -Indexes` turned
the directory from a 200 listing into a 403, and the gate asserted "not 403" — so
it failed on a certificate path that was working correctly. **This was my defect:
I added `-Indexes` without revisiting a gate written against the old behaviour.**

TLS renewal was never at risk. A missing challenge token returned **404, not
403** — the request resolves to the filesystem and only the file is absent; an
existing file fails the `!-f` condition and is served directly.

Rather than relax the check, PR #41 replaced the proxy with the real mechanism: a
token is written, fetched over HTTPS, required to return 200 with the exact body,
and removed under a `trap`. The lesson recorded: *a gate that fails on a healthy
system gets ignored, which is worse than not having it.*

Two smaller defects were caught by rehearsal **before** reaching production: a
`grep && exit` guard that would have aborted assembly under `set -e` on its
passing path, and an asset check reading manifest paths in the wrong form, which
would have failed every deployment unconditionally.

## 17. Recommendation

**READY FOR P1-BASE ACCEPTANCE.**

Scope respected: no P1-00 capability, no business schema, no login, SSO, users,
roles, domains, scopes or sensitivity. P1-00 remains **LOCKED**.

Final hosting architecture:

```text
cPanel document root          = public_html
deployment root               = public_html
production front controller   = public_html/index.php
public_html/public            = not a required production layer
```

Only the Product Owner issues **P1-BASE ACCEPTED**.
