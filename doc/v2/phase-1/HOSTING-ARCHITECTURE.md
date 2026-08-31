# SemantIQ Hosting Architecture — final

**Status:** IN FORCE. Product Owner decision, 31 August 2026. Permanent.

```text
cPanel document root            = public_html
deployment root                 = public_html
production front controller     = public_html/index.php
public_html/public              = NOT a required production layer
```

`public_html/public/` was only an early pre-project Git-to-cPanel
synchronisation test location. It is not part of the intended production
architecture, it is removed from the server, and the document root will not be
repointed to it. **D-08A is closed permanently.**

The repository keeps its normal Laravel `public/` directory, because Vite,
`artisan serve` and the test suite expect it. That is a repository concern and
implies nothing about the server: production does not serve through it.

---

## 1. Repository layout versus server layout

They are deliberately different. The deploy workflow assembles the production
tree on the runner and transfers that, rather than transferring the repository
and fixing it up afterwards.

| Repository | Server |
| --- | --- |
| `deployment/public_html.index.php` | `index.php` |
| `deployment/public_html.htaccess` | `.htaccess` |
| `public/build/` | `build/` |
| `public/favicon.ico` | `favicon.ico` |
| `public/robots.txt` | `robots.txt` |
| `public/index.php`, `public/.htaccess` | *(absent — not the production entry)* |
| `app/`, `bootstrap/`, `config/`, `vendor/`, … | same, all denied over HTTP |

Assembling on the runner is what lets `rsync --delete` remove the old
`public/` directory by itself: a path absent from the source is a path
`--delete` prunes. The deployment then removes any residue explicitly and
**proves `public_html/public` does not exist** — if it is absent and the site
answers, nothing can be depending on it.

## 2. Why the front controller is not named `index.php` in the repository

`DeploymentLayout` decides the layout by whether a front controller sits at the
base path. A base-path `index.php` in the repository would make every developer
machine and CI run believe it was production, and resolve `public_path()` to the
repository root where no assets live. A test enforces its absence.

## 3. How `public_path()` is resolved

Set once, in `bootstrap/app.php`, from `DeploymentLayout::publicPath()`.

This was the substantive design question, and the two obvious answers are both
wrong:

- **Set it in the front controller.** Correct for every web request and silently
  wrong for everything else. `semantiq:health` checks for the Vite manifest
  under `public_path()` and runs under the CLI during deployment, which never
  loads `index.php`.
- **Add `APP_PUBLIC_PATH` to the server `.env`.** Works, but puts the value that
  determines whether the application can find its own assets into the one file
  that is hand-maintained, unversioned, and exists in a single copy. Getting it
  wrong is a broken deployment with no diff to point at.

**No new server environment variable is required.** The layout is derived from
the layout itself: a front controller at the base path *is* the root layout.
`bootstrap/app.php` is loaded by `index.php` and `artisan` alike, so HTTP,
Artisan, `semantiq:health` and Vite manifest lookup agree by construction, with
nothing to configure and nothing to keep in sync.

Verified against a real assembled tree, not asserted:

| Check | Root layout | Repository layout |
| --- | --- | --- |
| `public_path()` | deployment root | `base_path('public')` |
| Vite manifest | found at `build/manifest.json` | found at `public/build/manifest.json` |
| `artisan --version` | works | works |
| `semantiq:health` | all five checks OK | all five checks OK |
| Page asset references | `/build/assets/…` | `/build/assets/…` |

## 4. The storage-link collision

Under the root layout `public_path('storage')` resolves to
`public_html/storage` — **the application's real storage directory**, holding
logs, cache, sessions and compiled views. `storage:link` would try to replace
live runtime state with a symlink to a subset of itself.

`config/filesystems.php` therefore declares no links under the root layout, and
the conventional link only for the repository layout where it is correct. A test
also asserts the deployment never runs `storage:link`. The route that would have
served those files was already disabled for the same collision; any later need
to serve user files goes through an authorised controller, which the security
model requires regardless.

## 5. Security under this layout

The forwarder used to make a failed denial harmless: every request was rewritten
into `public/`, where `app/`, `vendor/` and `.env` do not exist. **That safety
net is gone.** A root front controller serves any file that exists, so `/.env`
would be served as text if the deny rules did not fire.

They do fire. PR #38 measured four independent mechanisms at this document root
and all four returned 403. The deny rules precede every serving rule, in one
module so written order is the order they run, and `<Files>` + `Require all
denied` remains as a second module.

The gate is observed behaviour, not configuration review:

- every protected path must return **403** — a 404 means the request reached
  Laravel and fails the deployment;
- `ErrorDocument 403` must stay a **quoted literal**, or a denial is re-served
  through the application and masked as a 404;
- the denial body must reveal no framework, path, trace or configuration;
- `.htaccess` **and** `index.php` are SHA-256 verified against the repository on
  every deployment;
- `.well-known/` must stay reachable, or TLS renewal fails silently.

## 6. Deployment

```text
GitHub main → GitHub Actions → SSH/rsync → public_html
```

Preserved across every deployment: `.env`, `APP_KEY`, `.well-known/`, and
persistent `storage/` runtime state, by the exclusion contract in
`deployment/rsync-protected-paths.txt`, enforced twice — on the runner before
any transfer, and by a CI test on every pull request.

No manual cPanel deployment. No manual production file editing. No manual
migrations as the normal path.
