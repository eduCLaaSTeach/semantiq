# Project Context

Durable, non-secret project facts Claude reads before making changes. Field meanings, examples, and who answers each live in `developer-handbook/reference/PROJECT-CONTEXT-UNDERSTANDING.md`. Leave unknown fields as `<ASK_DEVELOPER>`; never guess.

Never store secrets here. Use placeholders such as `<HOSTING_RESOURCE>`, `<DATABASE_NAME>`, `<DATABASE_USER>`, `<MCP_ALIAS>`, and `<CLICKUP_URL>`.

## Required Intake Status

- Solution Cluster: `<ASK_DEVELOPER>`
- Module: `<ASK_DEVELOPER>`
- ClickUp URL: [ClickUp Workspace](<CLICKUP_URL>)
- Business purpose: `<ASK_DEVELOPER>`
- Primary users: `<ASK_DEVELOPER>`
- Application type: `<ASK_DEVELOPER>`
- Backend stack and version: Laravel 13 on PHP 8.5
- Frontend stack and version: React 19
- Database engine and version: MySQL (exact version `<ASK_DEVELOPER>`; confirm whether the cPanel host serves MySQL or MariaDB, since Laravel driver and DDL behavior differ)
- Package manager/runtime versions: PHP 8.5 with Composer; Node.js 24 with npm (build-time only, per `.github/workflows/deploy.yml`)
- Authentication model: `<ASK_DEVELOPER>`
- Data sensitivity/PII: `<ASK_DEVELOPER>`

## Team

Who works on this project. Claude reads this to label the EOD status report in `docs/eod/`, per `.claude/rules/eod-reporting.md`. Ask for it during intake and keep it current; a developer who is not listed here cannot be logged.

Role codes: `TL` Technical Lead, `PL` Project Lead, `LD` Lead Developer, `TD` Tech Developer, `TA` Tech Associate.

| Name | Email | Role | Work start | Work end | Timezone | From | To |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `<ASK_DEVELOPER>` | `<ASK_DEVELOPER>` | `<TL/PL/LD/TD/TA>` | `<09:00>` | `<18:00>` | `<GMT+6>` | `<17 August 2026>` | |

- Name is what the developer types when Claude asks whose session this is, so use the name they will actually give.
- Work start, work end, and timezone are the developer's declared daily working window, not a measurement of any day. Claude never records hours worked, session counts, or activity.
- From and To are the developer's period on this project. Leave To empty while they are still on it, and fill it the day they leave or move to another project.
- A developer gets a section in a day's EOD report only when that date falls inside their From and To window, so someone who left in July stops appearing in August while every report they were part of stays exactly as it was.
- Row order is section order in every daily EOD report. Keep it stable.

## Current Sprint Tasks

### Sprint: `<ASK_DEVELOPER>`

#### Cluster: `<ASK_DEVELOPER>`

##### Module: `<ASK_DEVELOPER>`

###### Feature: `<ASK_DEVELOPER>`

- [ ] `<ASK_DEVELOPER>`
  - Validation commands: `<ASK_DEVELOPER>`
  - Success criteria: `<ASK_DEVELOPER>`
- [ ] `<ASK_DEVELOPER>`
  - Validation commands: `<ASK_DEVELOPER>`
  - Success criteria: `<ASK_DEVELOPER>`

###### Feature: `<ASK_DEVELOPER>`

- [ ] `<ASK_DEVELOPER>`
  - Validation commands: `<ASK_DEVELOPER>`
  - Success criteria: `<ASK_DEVELOPER>`
- [ ] `<ASK_DEVELOPER>`
  - Validation commands: `<ASK_DEVELOPER>`
  - Success criteria: `<ASK_DEVELOPER>`

##### Module: `<ASK_DEVELOPER>`

###### Feature: `<ASK_DEVELOPER>`

- [ ] `<ASK_DEVELOPER>`
  - Validation commands: `<ASK_DEVELOPER>`
  - Success criteria: `<ASK_DEVELOPER>`
- [ ] `<ASK_DEVELOPER>`
  - Validation commands: `<ASK_DEVELOPER>`
  - Success criteria: `<ASK_DEVELOPER>`

###### Feature: `<ASK_DEVELOPER>`

- [ ] `<ASK_DEVELOPER>`
  - Validation commands: `<ASK_DEVELOPER>`
  - Success criteria: `<ASK_DEVELOPER>`
- [ ] `<ASK_DEVELOPER>`
  - Validation commands: `<ASK_DEVELOPER>`
  - Success criteria: `<ASK_DEVELOPER>`

## Scope And Non-Goals

- Declared non-goals / out-of-scope feature classes: `<ASK_DEVELOPER>`
- Deferred work pushed to a later phase: `<ASK_DEVELOPER>`

## System Boundaries

- What the system does: `<ASK_DEVELOPER>`
- What the system does NOT do: `<ASK_DEVELOPER>`
- External integration seams: `<ASK_DEVELOPER>`
- Module / layer map: `<ASK_DEVELOPER>`
- Allowed dependency direction: `<ASK_DEVELOPER>`

## Stack And Dependency Decisions

- Framework & stack choice rationale: `<ASK_DEVELOPER>`
- Approved libraries: `<ASK_DEVELOPER>`
- Banned / disallowed libraries: `<ASK_DEVELOPER>`
- Formatter / linter configuration: `<ASK_DEVELOPER>`
- Dependency version-pinning / lockfile policy: `<ASK_DEVELOPER>`
- Data-access layer / pattern: `<ASK_DEVELOPER>`

## UI Application Definition

### Approved standard values

- Company name: CLaaS2SaaS
- Company logos, per theme: `logo-full-light.png` / `logo-full-dark.png` (expanded), `logo-short-light.png` / `logo-short-dark.png` (collapsed), in `.claude/skills/ui-ux-design/assets/`
- Favicon, per theme: `favicon-light.ico` / `favicon-dark.ico`
- Design tokens, palette, surfaces: `.claude/skills/ui-ux-design/reference/design-tokens.md`; brand palette Midnight Blue `#193E6B`, Green Gold `#B3A125`, Avocado `#5F8025`, Sunray `#E9AC53`, Violet-Red `#991547`, Jelly Bean `#448E9D`, Cadmium Violet `#7F3F98`
- Fonts: Montserrat headings + Source Sans 3 body
- Density: compact
- Icon style: central inline-SVG registry (24px viewBox, 2px stroke, outline)
- Accessibility target: WCAG AA
- Theme switcher: on (System / Dark / Light, profile menu Appearance section, persisted)
- Sidebar nav filter: on; Sidebar collapsible icon rail: on
- Navigation clusters (top-to-bottom): Workspace, Compliance, Application Administration, System Administration

### App identity (per project)

- App name: `<ASK_DEVELOPER>`
- Browser / document title-bar name: `<ASK_DEVELOPER>`
- Tagline: `<ASK_DEVELOPER>` `[optional]`
- Brand-assets destination path: `<BRAND_ASSETS_PATH>` = `<ASK_DEVELOPER>`

### UI stack (per project)

- UI stack: React 19 (Laravel 13 integration layer, for example Inertia or a standalone SPA against the API: `<ASK_DEVELOPER>`)
- Charting library: `<ASK_DEVELOPER>`

### Feature toggles (confirmed defaults)

- Customizable dashboard: On
- Notifications: On (in-app, top-bar bell)
- SSO / external IdP: On - Microsoft Entra ID, direct or via the ECC -> SCC module (confirm which path per app)
- Recycle bin (soft-delete + restore): On
- Audit log: On

### List behavior (per project)

Every list / index screen sorts and filters, per `.claude/rules/ui-ux-quality.md`. What varies is the naming.

- List query parameter convention: `q`, `sort`, `dir`, `page`, `size`, plus one per facet - replace only if the project already has a convention
- Server-side sort and filter for paginated lists: required

### Step-by-step form drafts (per project)

Every multi-step form saves a resumable draft on each `Continue`, per `.claude/rules/ui-ux-quality.md`. What varies is where it is stored, not whether it exists.

- Draft storage shape (a separate draft table, or the record's own table with a draft state): `<ASK_DEVELOPER>`
- Where the draft state / status value comes from (the platform's existing status dimension, or a new one): `<ASK_DEVELOPER>`
- Drafts in flight per user per flow (one resumable draft, or several): one
- Fields excluded from a saved draft (credentials, keys, tokens, passwords): all of them, always
- Autosave beyond the per-step save (interval, on blur, on close): `<ASK_DEVELOPER>`

### Navigation tree (config-driven, sidebar-only)

- Workspace: `<ASK_DEVELOPER>`
- Compliance: `<ASK_DEVELOPER>`
- Application Administration: `<ASK_DEVELOPER>`
- System Administration: `<ASK_DEVELOPER>`
- Access policies: `<ASK_DEVELOPER>`

### Entities

#### Entity: `<ASK_DEVELOPER>` (plural: `<ASK_DEVELOPER>`)

- Database table(s): `<ASK_DEVELOPER>`
- Columns: `<ASK_DEVELOPER>`
- Transaction / child table(s): `<ASK_DEVELOPER>`
- Field -> column mapping notes: `<ASK_DEVELOPER>`
- Default list sort (column + direction): `<ASK_DEVELOPER>`
- List filter facets (the dimensions this list is narrowed by): `<ASK_DEVELOPER>`

### Roles & tenancy

| Role | UI-standard tier | Record scope |
| --- | --- | --- |
| System Administrator | system admin | ALL records, plus platform and integration configuration; the only role that reaches System Administration |
| Administrator | admin | ALL records; the role that permanently deletes application records |
| Collaborator | team/collaborator | OWN records plus records in their Business Unit |
| Contributor | self/contributor | OWN or directly assigned records only |
| Viewer | self-view/read-only | Read-only; scope OWN, TEAM, or Business Unit per app: `<ASK_DEVELOPER>` |
| Custom | per app, optional | Define scope and cluster grants before use: `<ASK_DEVELOPER>` |

Cluster grants per role:

| Cluster | Roles granted |
| --- | --- |
| Workspace | Viewer, Contributor, Collaborator, Administrator, System Administrator |
| Compliance | Collaborator, Administrator, System Administrator |
| Application Administration | Administrator, System Administrator |
| System Administration | System Administrator only |

- Tenancy: single-tenant
- Team/org hierarchy: Administrator (org root) -> Business Unit -> Team. System Administrator sits above the org root and owns platform configuration rather than records.

## Security And Identity Conventions

- Secret manager / secret store: `<ASK_DEVELOPER>`
- CI credential model: `<ASK_DEVELOPER>`
- Token signing scheme: `<ASK_DEVELOPER>`
- Authorization scope convention: `<ASK_DEVELOPER>`
- Password hashing algorithm: `<ASK_DEVELOPER>`
- Token / session lifetime policy: `<ASK_DEVELOPER>`
- Encryption in transit policy: `<ASK_DEVELOPER>`
- Encryption at rest policy: `<ASK_DEVELOPER>`
- Key / secret rotation schedule: `<ASK_DEVELOPER>`
- Field / column-level encryption targets: `<ASK_DEVELOPER>`
- AI/LLM feature conventions: `<ASK_DEVELOPER>`
  - Prompt store location + versioning: `<ASK_DEVELOPER>`
  - Model routing / adapter + fallback: `<ASK_DEVELOPER>`
  - Max-tokens / temperature defaults: `<ASK_DEVELOPER>`
  - Per-agent token budget + cost ceiling: `<ASK_DEVELOPER>`
  - Golden-set location + pass-score bar: `<ASK_DEVELOPER>`
  - Eval-as-merge-gate expectation: `<ASK_DEVELOPER>`

## AI Model Catalog (Only If The App Calls A Model At Runtime)

- Does the application call an AI model / LLM at runtime: `<ASK_DEVELOPER>`
- Catalog storage location (database table, versioned config file, or settings provider): `<ASK_DEVELOPER>`
- Providers and model ids in use: `<ASK_DEVELOPER>`
- Environment source `{{env.NAME}}` resolves against: `<ASK_DEVELOPER>`
- Project-wide per-call timeout and connect timeout: `<ASK_DEVELOPER>`
- Retry policy and dedupe-key strategy for model calls: `<ASK_DEVELOPER>`
- Cost currency (one project-wide constant) and display rounding precision: `<ASK_DEVELOPER>`
- Who may manage the catalog (role, defaults to System Administrator): `<ASK_DEVELOPER>`
- Documented exceptions to the approved catalog pattern: none

## Migrations And Data Change Workflow

- File-based migration tool: Laravel migrations (Artisan), the confirmed source of truth for the MySQL application schema. Authoring a migration is not applying it: running `migrate` against any environment stays a deployment action needing explicit approval per `.claude/rules/deployment.md`.
- Migration file naming convention: Laravel default, `database/migrations/YYYY_MM_DD_HHMMSS_description.php`. Pair every forward migration with a working `down()`. Never edit a migration already merged or applied; add a new one. When two branches collide on the timestamp ordering key, the second to merge re-stamps it.

## Analytics And Semantic Model

- Does the solution feed a semantic model, BI layer, or reporting product: `<ASK_DEVELOPER>`
- Semantic model / BI tool and who owns it: `<ASK_DEVELOPER>`
- How reporting reads the data (operational tables, read replica, extract, warehouse): `<ASK_DEVELOPER>`
- Reporting time zone and business-day boundary: `<ASK_DEVELOPER>`
- First day of week and fiscal year start: `<ASK_DEVELOPER>`
- Timestamp storage convention: `<ASK_DEVELOPER>`
- Surrogate / business key convention: `<ASK_DEVELOPER>`
- Conformed dimensions the platform already has (source, status, reason, owner, currency): `<ASK_DEVELOPER>`
- History / event / snapshot retention: `<ASK_DEVELOPER>`
- Where each feature's analytics question set is recorded: `<ASK_DEVELOPER>`
- ERD and data-model deliverable location and format: `<ASK_DEVELOPER>`
- Approved materialized aggregates (and how each is rebuilt): none
- Documented exceptions to the analytics-ready data rules: none

## Quality, Observability, And Operability

- Minimum test coverage bar: `<ASK_DEVELOPER>`
- Gating mechanism for incomplete work in the live path: `<ASK_DEVELOPER>`
- Structured logging format: `<ASK_DEVELOPER>`
- Metrics / tracing destination: `<ASK_DEVELOPER>`
- Health / readiness endpoint conventions: `<ASK_DEVELOPER>`
- Per-environment config keys that differ across environments: `<ASK_DEVELOPER>`

## Resilience Thresholds

- Outbound call timeout(s) per dependency type: none yet
- Retry policy for idempotent operations: none yet
- Circuit-breaker trip / reset thresholds: none yet
- Dead-letter / replay location for failed async work: none yet
- Idempotency / dedupe key strategy: `Idempotency-Key` header documented, not yet enforced

## Data Lifecycle Governance

- Data sensitivity classification scheme: none
- Retention period per data class: 7 years
- Audit / compliance log retention: 7 years
- Abandoned step-by-step form draft retention: `<ASK_DEVELOPER>`
- Applicable privacy regime: none
- Backup retention window: 7 years
- Restore-test cadence: none
- Recovery-point objective (RPO) per environment: none
- Recovery-time objective (RTO) per environment: none
- Failover procedure and disaster communications: none

## Operations And Incident Response

- Service-level objectives (SLOs) per critical service: none yet
- Alert thresholds: none yet
- Incident severity taxonomy and per-severity response time: none yet
- On-call / escalation path: none yet
- Top failure modes and their runbook locations: none yet

## API / Interface Conventions

- Interface paradigm: `<ASK_DEVELOPER>`
- Resource / operation naming style: `<ASK_DEVELOPER>`
- Error contract format: `<ASK_DEVELOPER>`
- Version scheme: `<ASK_DEVELOPER>`
- Standard header names: `<ASK_DEVELOPER>`
- Pagination style and maximum page-size cap: `<ASK_DEVELOPER>`
- Authoritative contract spec tool and location: `<ASK_DEVELOPER>`

## Configuration Contract

- Config example-file path: `<ASK_DEVELOPER>`
- Config library / loader: `<ASK_DEVELOPER>`
- Config precedence order: `<ASK_DEVELOPER>`
- Enumerated config variables (names + placeholders only): `<ASK_DEVELOPER>`

## Git And Review Knobs

- Commit signing policy: not required
- Merge strategy: not fixed (chosen per Pull Request)
- Working-branch lifetime cap: none set
- Rebase cadence: none set
- Required approver count: one superior approval by convention (not branch-protected)
- CODEOWNERS / path-ownership file location: none
- Review first-response SLA window: none set

## Deployment Policy

- Deployment target: DEV environment at [Development Site](https://semantiq.claas2saas.com/), on cPanel shared hosting. Files are deployed over SSH into the document root that the `CPANEL_DEPLOY_PATH` secret points at, and the committed forwarder rewrites every request into `public/`. That target is the cPanel account's main document root, not a subdomain-specific one, which is why the forwarder is needed at all.
- Deployment method: GitHub Actions builds the release, then `rsync -az --delete` over SSH to the target path held in the `CPANEL_DEPLOY_PATH` repository secret. No FTP, no cPanel Git Version Control, no container registry.
- Source control provider: GitHub, remote over SSH using the local host alias `gp` (`git@gp:eduCLaaSTeach/semantiq.git`)
- GitHub repository URL: [GitHub Repository](https://github.com/eduCLaaSTeach/semantiq)
- CI/CD or deployment pipeline: GitHub Actions, `.github/workflows/deploy.yml` (`Deploy DEV to cPanel (SSH)`), job `deploy` on `ubuntu-latest`, bound to the `development` GitHub environment, concurrency group `cpanel-deploy-dev` with `cancel-in-progress: false`, workflow-level `permissions: contents: read`. Trigger: push to `DEV` plus `workflow_dispatch`. All third-party actions are pinned to full commit SHAs with a version comment. Required secrets, names only: `CPANEL_HOST`, `CPANEL_PORT` (defaults to `22`), `CPANEL_USER`, `CPANEL_DEPLOY_PATH` at job scope, and `CPANEL_SSH_PRIVATE_KEY`, `CPANEL_SSH_KEY_PASSPHRASE` scoped to the Configure SSH step only.
- Branch model reconciliation: resolved. The project follows the gateway model in `.claude/rules/git-branching-release.md` (`DEV` -> `QA` -> `STAG` -> `PROD`). The old `main` branch was renamed to `DEV` and the pipeline deploys from `DEV` to the DEV environment. Only `DEV` is wired so far; `QA`, `STAG`, and `PROD` have no workflow or hosting target yet. Outstanding: the GitHub remote default branch is still `main` and needs an org admin to move it to `DEV` and delete `main`.
- Hosting provider/platform: cPanel-managed hosting; provider/company name `<ASK_DEVELOPER>`
- Runtime/build/deploy model: build on the runner, ship the built tree. PHP 8.5 via `shivammathur/setup-php@v2`, `composer validate --no-check-publish`, `composer install --no-dev --prefer-dist --optimize-autoloader`, Node.js 24 via `actions/setup-node@v4` with npm cache, `npm ci`, `npm run build`, then `node_modules` removed before transfer. `deployment/public_html.htaccess` is copied to `.htaccess` at the deployment root so `public_html` forwards every request into `public/`. `vendor/` and `public/build` are transferred; `.git/`, `.github/`, `deployment/`, `node_modules/`, `tests/`, `phpunit.xml`, `README.md`, `.editorconfig`, `.gitignore`, `.gitattributes`, `.env`, `.env.example`, `storage/`, and `public/storage` are excluded from rsync. A post-sync SSH step creates `storage/framework/cache/data`, `storage/framework/sessions`, `storage/framework/views`, `storage/logs`, and `bootstrap/cache` when missing and applies `chmod -R ug+rwX`.
- Infrastructure-as-code tool and location: none; the workflow file is the only deployment definition in the repository
- Local development support: `<ASK_DEVELOPER>`
- Environment site URLs:
  - `DEV`: [Development Site](https://semantiq.claas2saas.com/)
  - `QA`: [QA Site](<QA_SITE_URL>)
  - `STAG`: [Staging Site](<STAG_SITE_URL>)
  - `PROD`: [Production Site](<PROD_SITE_URL>)
- Production deployment action: Claude must not deploy, migrate, publish, upload, change hosting settings, or edit production environment values without explicit developer approval for that exact action.

## Required Deployment Details To Ask For

- Hosting target/provider: cPanel shared hosting over SSH; provider/company name `<ASK_DEVELOPER>`
- Source control and pipeline: GitHub plus GitHub Actions (`.github/workflows/deploy.yml`)
- Hosting account/project/resource placeholder: `<CPANEL_USER>` on `<CPANEL_HOST>`, port `<CPANEL_PORT>`, deploy path `<CPANEL_DEPLOY_PATH>`
- Production URL and subdirectory: [Production Site](<PROD_SITE_URL>), subdirectory: `<ASK_DEVELOPER>`
- Web root, artifact path, startup command, service entry point, or container image: the web root is the cPanel account's main document root, held in the `CPANEL_DEPLOY_PATH` secret and kept out of this file because the pipeline treats it as one. Application files land directly in it, and the committed `deployment/public_html.htaccess` is deployed as `.htaccess` to rewrite every request into `public/`, where Laravel's front controller runs. No startup command and no container image; requests are served by the host's PHP handler.
- Runtime/platform version: target runtime PHP 8.5 (confirm the cPanel PHP selector matches). Node.js 24 is build-time only on the runner and is not required on the target.
- Package manager/build availability on target: none needed. Composer and npm run on the runner only, and `vendor/` plus `public/build` are transferred already built. `rsync` must exist on the target and the workflow verifies it in the SSH authentication step.
- Background processing/scheduler support: not configured by the pipeline. Queue worker and scheduler (cron) support on the target: `<ASK_DEVELOPER>`
- Deployment workflow owner and steps: owner `<ASK_DEVELOPER>`. Steps: checkout, set up PHP 8.5, `composer validate`, `composer install --no-dev`, set up Node.js 24, `npm ci`, `npm run build`, remove `node_modules`, stage the `public_html` forwarder, load the passphrase-protected key into `ssh-agent` through an askpass helper and `ssh-keyscan` the host, test SSH authentication, `rsync -az --delete` to the deploy path, then ensure the Laravel runtime directories exist and are writable. Manual GitHub steps the developer must perform, none of which Claude can do: add the six `CPANEL_*` secrets; create the `development` environment and move those secrets onto it; set its deployment branch policy to `DEV` only; leave required reviewers off unless an approval prompt on every DEV push is wanted. Manual cPanel steps: create the `semantiq` subdomain and note its document root, set the PHP selector to 8.5, and place the DB credentials in the server `.env`.
- Rollback and monitoring/log access: the workflow defines no rollback step, and `rsync --delete` overwrites the target in place. Rollback mechanism, known-good reference, and log/monitoring access: `<ASK_DEVELOPER>`. Open security follow-ups carried from the review, both Medium and both needing developer action: the host key is trusted live by `ssh-keyscan` on every run rather than pinned to a `CPANEL_HOST_KEY` secret, and `rsync --delete` runs against a secret-supplied path with no sentinel-file or dry-run guard.
- Required `.env` keys using placeholders only: `.env` and `.env.example` are excluded from rsync, so the target `.env` is server-managed and never written by the pipeline. Database keys: `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`. Remaining key list (app, mail, cache, session, queue): `<ASK_DEVELOPER>`

## Required Validation Details To Ask For

- Syntax/type/static checks: `<ASK_DEVELOPER>`
- Lint/format commands: `<ASK_DEVELOPER>`
- Unit/integration/e2e commands: `<ASK_DEVELOPER>`
- Build/package commands: `<ASK_DEVELOPER>`
- Deployment smoke test: `<ASK_DEVELOPER>`
- Known validation limitations: `<ASK_DEVELOPER>`

## Schema MCP (Database Metadata Source Of Truth)

- Database/schema source of truth: Laravel migrations in `database/migrations/` define and version the MySQL application schema. The `schema` MCP server serves SQL Server metadata and is out of scope for this database, so its tools are not used to verify this app's tables and no `mcp__schema__*` proposal workflow applies here. Schema review happens through the migration diff in the Pull Request instead.
- SQL Server schema name: not applicable; this project has no SQL Server database in scope
- Table name prefix convention: `<ASK_DEVELOPER>`
- Table/column naming casing convention: `<ASK_DEVELOPER>`
- Resulting table name pattern: `[<SCHEMA>].<PREFIX><table_base_name>`
- Database/schema MCP server name and alias: `schema`
- Database/schema MCP tools: `mcp__schema__list_tables`, `mcp__schema__describe_table`, `mcp__schema__find_existing_tables_for_concept`, `mcp__schema__list_pending_proposals`, `mcp__schema__propose_table_change`, `mcp__schema__get_proposal`
- Connection, transport, URL, and credentials: in Claude Code's MCP configuration only; never recorded or printed here
- Auth and token refresh: per `.claude/docs/MCP-USAGE.md`; refresh via `.claude/commands/mcp-token-refresh.md`
- Availability: verified by session preflight (`/mcp-health-check`); if unavailable, stop schema work and alert the developer
- Tables/schemas relevant to the current task: discovered and verified live through the MCP tools; never from memory

## Application Database Connection (Only If The App Connects To A Database Directly)

- Does the application connect to a database directly: yes, over Laravel's MySQL driver
- Database engine the application connects to: MySQL
- Database host/service placeholder: `<DATABASE_HOST>` (cPanel-local MySQL; confirm whether the app connects over `localhost`/socket or a remote host)
- Database name placeholder: `<DATABASE_NAME>`
- Database user placeholder: `<DATABASE_USER>`
- Schema/database owner: the cPanel-created database user `<DATABASE_USER>`, granted ALL PRIVILEGES on `<DATABASE_NAME>`
- Where connection settings/secrets live (env keys, placeholders only): the target server's `.env`, which rsync excludes so the pipeline never writes or overwrites it. Keys: `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`. No database credential belongs in a GitHub repository secret, in the repo, or in this file.

## Knowledge Base Policy

- Claude must read existing knowledge-base files before editing when they exist.
- Claude must read this file before editing and update it with confirmed non-secret facts after developer answers.
- After each completed implementation, Claude must ask the developer whether to create or update the knowledge base now or defer it, and must write knowledge-base files only after the developer has verified, validated, and explicitly approved the update, per `.claude/rules/knowledge-base.md`.
- When approved, Claude must ask whether the work is a solution, a module, or a feature and where it sits in the hierarchy, then update the write-up and the knowledge-base README index.
- Claude must update the table dictionary after confirmed work that verifies or changes table/schema knowledge; when a change alters the schema, the table-dictionary update travels in the same change unit as the schema change.
