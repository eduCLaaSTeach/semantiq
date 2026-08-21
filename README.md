# SemantIQ

A control plane for Microsoft Fabric, covering the 80-step end-to-end provisioning
and governance procedure as a single web application.

- Live site: https://semantiq.claas2saas.com/
- Repository: https://github.com/eduCLaaSTeach/semantiq
- Design and specification set: [doc/README.md](doc/README.md)

## Stack

| Layer | Choice |
| --- | --- |
| Backend | Laravel 13 on PHP 8.5 |
| Frontend | React 19 |
| Database | MySQL, cPanel-hosted |
| Build toolchain | Composer, and Node.js 24 with npm for frontend assets only |
| Architecture | Modular monolith |

Node.js is a build-time dependency on the CI runner. It is not required on the
server.

## Repository Layout

| Path | Holds |
| --- | --- |
| `doc/` | Solution architecture, requirement scoping, functional, workflow and UI specifications, plus a self-contained HTML mockup and Word exports |
| `.github/workflows/` | The deployment pipeline |
| `deployment/` | The versioned cPanel front-door `.htaccess` |

Application code has not landed yet. There is no `composer.json` or
`package.json` in the repository, so the pipeline's build steps cannot pass until
the Laravel application is scaffolded.

## Branching

One branch, `main`. It is the GitHub default and the deploy trigger. There is no
`DEV`, `QA`, `STAG` or `PROD` branch and no promotion chain.

> [!WARNING]
> Every push to `main` triggers a deployment to the live site. There is no
> pre-production branch and no approval gate in front of it. Open a pull request
> and review before merging rather than pushing directly.

## Deployment

GitHub Actions builds the release on the runner, then transfers the built tree to
cPanel over SSH. Defined entirely in
[.github/workflows/deploy.yml](.github/workflows/deploy.yml).

1. Check out, set up PHP 8.5, `composer validate`, `composer install --no-dev`.
2. Set up Node.js 24, `npm ci`, `npm run build`, then remove `node_modules`.
3. Copy `deployment/public_html.htaccess` to `.htaccess` at the deployment root so
   the document root forwards every request into `public/`.
4. Load the passphrase-protected deploy key into `ssh-agent` through an askpass
   helper, then verify SSH and `rsync` on the target.
5. `rsync -az --delete` to the deploy path.
6. Create the Laravel runtime directories if missing and make them writable.

`vendor/` and `public/build` are transferred already built. Excluded from
transfer: `.git/`, `.github/`, `deployment/`, `node_modules/`, `tests/`,
`phpunit.xml`, `README.md`, `.editorconfig`, `.gitignore`, `.gitattributes`,
`.env`, `.env.example`, `storage/`, `public/storage`.

> [!CAUTION]
> `rsync --delete` mirrors the source. Anything in the deploy path that is not in
> the build and not on the exclude list is deleted. There is no dry-run guard.

### Required GitHub secrets

Names only. Values are entered in the GitHub UI and never committed.

| Secret | Purpose |
| --- | --- |
| `CPANEL_HOST` | SSH host |
| `CPANEL_PORT` | SSH port, defaults to `22` |
| `CPANEL_USER` | SSH and cPanel account user |
| `CPANEL_DEPLOY_PATH` | Deploy path, the account document root |
| `CPANEL_SSH_PRIVATE_KEY` | Passphrase-protected deploy key |
| `CPANEL_SSH_KEY_PASSPHRASE` | Passphrase for that key |

The job is bound to a GitHub environment named `development`, which is where a
branch policy and environment-scoped secrets would be configured.

## Database

MySQL, created through cPanel. The application connects directly through
Laravel's MySQL driver.

Laravel migrations in `database/migrations/` are the source of truth for the
schema. Pair every forward migration with a working `down()`. Never edit a
migration that has been merged or applied; add a new one instead.

Connection settings live in `.env` on the server, which the pipeline excludes from
transfer and therefore never creates or overwrites. Keys: `DB_CONNECTION`,
`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.

> [!IMPORTANT]
> No credential, connection string or `.env` value belongs in this repository, in
> a GitHub secret, or in any committed file. Use placeholders such as
> `<DATABASE_NAME>` and `<DATABASE_USER>` in documentation.

Applying a migration against any environment is a deployment action. It is not
performed as part of a normal change.

## Roles And Tenancy

Single-tenant. Organisation hierarchy is Administrator at the org root, then
Business Unit, then Team. The System Administrator sits above the org root and
owns platform configuration rather than records.

| Role | Record scope |
| --- | --- |
| System Administrator | All records, plus platform and integration configuration |
| Administrator | All records; permanently deletes application records |
| Collaborator | Own records plus records in their Business Unit |
| Contributor | Own or directly assigned records only |
| Viewer | Read-only |

## Interface Conventions

Brand is CLaaS2SaaS. Palette: Midnight Blue `#193E6B`, Green Gold `#B3A125`,
Avocado `#5F8025`, Sunray `#E9AC53`, Violet-Red `#991547`, Jelly Bean `#448E9D`,
Cadmium Violet `#7F3F98`. Montserrat for headings, Source Sans 3 for body, compact
density, WCAG AA.

Four navigation clusters in fixed order: Workspace, Compliance, Application
Administration, System Administration. Light and dark themes with a System option.

List screens sort and filter server-side over the whole result set, with state in
the URL query using `q`, `sort`, `dir`, `page`, `size` plus one parameter per
facet.

Full detail is in [doc/04-UI-Specification.md](doc/04-UI-Specification.md).

## Data Governance

Retention is 7 years for operational data, audit and compliance logs, and backups.
No privacy regime has been determined as applicable. Recovery objectives, restore
cadence and failover procedure are not yet defined.

## Open Items

- Scaffold the Laravel application so the pipeline's build steps can pass.
- Confirm whether the cPanel host serves MySQL or MariaDB, and verify `DB_HOST`
  and `DB_PORT`.
- Set the cPanel PHP selector to 8.5.
- Create the `development` GitHub environment, move the `CPANEL_*` secrets onto
  it, and restrict its deployment branch policy to `main`.
- Pin the SSH host key instead of trusting it live on every run, and add a
  sentinel or dry-run guard in front of `rsync --delete`.
- Provision a queue worker and a scheduler. The pipeline configures neither, so no
  queued job or scheduled task currently runs.
- Add post-deploy `migrate` and cache steps to the pipeline.
- Brand assets (logos and favicons) are no longer in the working tree. Recover
  them from git history or re-source them, and decide where they live in the app.
