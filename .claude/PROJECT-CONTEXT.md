# Project Context

Durable, non-secret project facts Claude reads before making changes. Field meanings, examples, and who answers each live in `developer-handbook/reference/PROJECT-CONTEXT-UNDERSTANDING.md`. Every value below was read from this repository's own code, configuration, workflows and documents on 1 October 2026; where the repository cannot answer a field, the field says so in plain words rather than guessing.

Never store secrets here. Name the configuration key that holds a secret (for example `.env`, `DB_PASSWORD`) and never its value.

Where this file and the project's own documents disagree, the project documents win: `CLAUDE.md` names them, and `doc/v2/phase-1/PHASE-1-PLAN.md` is the authoritative delivery status register.

## Required Intake Status

- Business purpose: SemantIQ v2 is a secure Business Decision Intelligence control plane for Microsoft Fabric (`composer.json`). It is built in three phases: Phase 1 System Administration (identity, organisation, roles, domain security, platform controls), Phase 2 Fabric Configuration (connect, govern, model and secure data and make it AI-ready), Phase 3 SemantIQ Workplace (personalised intelligence, conversational AI, insights, decisions and Power BI). Phase 1 is delivered: all thirteen units are accepted and Phase 1 closeout (carried gates such as the production session-driver alignment) is in progress per `doc/v2/phase-1/PHASE-1-PLAN.md`.
- Primary users: the organisation's people under seven platform roles (`app/Modules/Access/Support/RoleCode.php`): System Administrator, Organisation Administrator, Executive, Domain Owner / Director, Manager, Business User and Auditor. In the delivered Phase 1 the screens serve System Administrators, Organisation Administrators and Auditors; Executives, Domain Owners, Managers and Business Users are the audience of the Phase 3 Workplace, which is not built yet.
- Application type: server-rendered web application, a Laravel modular monolith (`app/Modules/*`) serving React pages through Inertia, single-tenant today with organisation boundaries designed to be multi-tenant-ready.
- Backend stack and version: Laravel 13 (`laravel/framework` ^13.17) on PHP 8.5 in CI and deployment (`composer.json` allows ^8.3), with `inertiajs/inertia-laravel` ^3.3 and `firebase/php-jwt` ^7.1 for Entra ID token validation.
- Frontend stack and version: React 19 with `@inertiajs/react` ^2.0, built by Vite 8 (`laravel-vite-plugin` ^3.1, `@vitejs/plugin-react` ^5.0.4); hand-written CSS design tokens in `resources/css/app.css`, no CSS framework.
- Database engine and version: MySQL on cPanel in production; CI proves the migrations against MySQL 8.4. The automated test suite runs on SQLite in memory (`phpunit.xml`), and the People, Setup, Domains, Access, Security, Reviews, Audit, System Health and Administration suites are re-run on MySQL in CI.
- Package manager/runtime versions: Composer (`composer.lock` committed) and npm (`package-lock.json` committed, `.npmrc` sets `ignore-scripts=true` and `audit=true`); PHP 8.5 and Node.js 24 in `.github/workflows/ci.yml` and `deploy.yml`.
- Authentication model: Microsoft Entra ID through OpenID Connect, implemented in-house rather than with Socialite: `RedirectController` and `CallbackController` under `app/Modules/Platform/Http/Controllers/Auth`, `EntraProvider`, `EntraDiscovery` and `IdTokenValidator` (RS256 only, issuer and tenant checked) under `app/Modules/Platform/Identity/Microsoft`. A verified identity must match an existing active `users` row (provider, external subject, tenant); unknown, inactive and wrong-tenant identities fail closed. The resulting session is a Laravel database-backed session cookie, re-checked on every `/console` request by `EnsureSessionIsCurrent` (12-hour absolute limit) with a 60-minute idle limit from `SESSION_LIFETIME`. Before SSO exists, a single local Bootstrap Administrator (email and hashed password, `bootstrap_administrators`) can perform first-run platform setup; it is closed once SSO is verified and a permanent System Administrator has signed in. Privileged access changes require an Entra step-up re-authentication (`pending_step_ups`).
- Data sensitivity/PII: personal data of the organisation's staff: name, work email, Entra object and tenant ids, team and management relationships, group membership, role and entitlement history. The `sessions` table holds IP address and user agent (Laravel default columns); the audit trail deliberately stores neither. Integration secrets (Entra client secret, SMTP, AI and Fabric credentials) are stored encrypted. Business domains carry a sensitivity classification of Standard, Confidential or Restricted; no business data is read or stored in Phase 1.

## Team

Who works on this project. Claude reads this to label the EOD status report in `docs/eod/`, per `.claude/rules/eod-reporting.md`. Ask for it during intake and keep it current; a developer who is not listed here cannot be logged.

Role codes: `TL` Technical Lead, `PL` Project Lead, `LD` Lead Developer, `TD` Tech Developer, `TA` Tech Associate.

| Name | Email | Role | Work start | Work end | Timezone | From | To |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Mehedi Hassan Nayeem | github.mehedihassan.0955.0602@gmail.com | LD | 07:00 | 15:00 | GMT+6 | 30 August 2026 | |
| eduCLaaSTeach | 161204863+eduCLaaSTeach@users.noreply.github.com | TD | 09:00 | 18:00 | GMT+8 | 30 August 2026 | |

- Name is what the developer types when Claude asks whose session this is, so use the name they will actually give.
- Work start, work end, and timezone are the developer's declared daily working window, not a measurement of any day. Claude never records hours worked, session counts, or activity.
- From and To are the developer's period on this project. Leave To empty while they are still on it, and fill it the day they leave or move to another project.
- A developer gets a section in a day's EOD report only when that date falls inside their From and To window, so someone who left in July stops appearing in August while every report they were part of stays exactly as it was.
- Row order is section order in every daily EOD report. Keep it stable.
- The rows come from the commit history. `eduCLaaSTeach` is the GitHub account that merges the pull requests (it has also committed as `claude.ai@educlaas.com`); commits authored by Claude itself are not listed.

## Current Sprint Tasks

No sprint tasks are tracked in this repository yet.

## Scope And Non-Goals

- Declared non-goals / out-of-scope feature classes: no reuse of SemantIQ v1 code, schema, migrations, permissions, workflows, tests or API contracts (only the CLaaS2SaaS UI/UX standard is reused); no re-evaluation of the fixed platform (Laravel 13, PHP 8.5, React 19, MySQL on cPanel, GitHub Actions to cPanel over SSH/rsync, modular monolith); no pre-building of future menus, tables or services; no fake or placeholder Phase 2 or Phase 3 screens; no self-registration; authentication alone never grants business-domain access, and a System Administrator receives no business-domain access by default. Phase 1 performs no AI inference and reads no Fabric business data.
- Deferred work pushed to a later phase: Phase 2 Fabric Configuration (data sources, discovery, classification, ingestion, data quality, business and semantic models, security mapping, AI readiness, pipelines, Power BI publication, monitoring) and Phase 3 SemantIQ Workplace (personalised intelligence, Ask SemantIQ, insights, recommendations, decisions and alerts, reports and dashboards). Their menu entries exist only as locked nodes in `app/Shared/Navigation/ApprovedMenu.php`. Phase 1 carried gates still open are listed in `doc/v2/phase-1/PHASE-1-PLAN.md` section 10 and may not move to Phase 2 without a recorded Product Owner decision.

## Orientation Map

The one-page snapshot Claude keeps for whoever owns this project, per `.claude/rules/orientation-map.md`. It answers Position, Surface, and Open, is rebuilt from the repository when a trigger fires, and holds no fact of its own. Ask for these during intake; the page is not built until the path and its reader are confirmed.

- Map file path: `docs/orientation-map.html`
- Who the map is written for (the owner, by role): the Product Owner, who approves each unit's plan and design and gives final acceptance.
- What counts as a milestone being formally accepted here: explicit Product Owner acceptance of a delivery unit after its Product Owner Test Script passes, recorded in the unit's acceptance or verification document under `doc/v2/phase-1/` and in the status register of `PHASE-1-PLAN.md`. A green CI run is not acceptance.
- Where the project's status file, active plan, and decision records live: `doc/v2/phase-1/PHASE-1-PLAN.md` (status register, decision register D-01 onward, carried gates), the per-unit `P1-*-PLAN.md`, `-DESIGN.md`, `-VERIFICATION.md` and `-ACCEPTANCE.md` files beside it, and `PHASE-1-CLOSEOUT-PLAN.md`.
- Directories that make up the configuration surface: `.github/workflows/`, `deployment/`, `config/`, `bootstrap/app.php`, `.env.example`, `composer.json`, `package.json`, `vite.config.js`, `phpunit.xml`, `.claude/` and `CLAUDE.md`.
- Regeneration triggers beyond the rule's defaults: none
- Design identity the page follows: the approved `ui-ux-design` tokens, standalone rather than in the app shell

## System Boundaries

- What the system does: in Phase 1, controls who can enter SemantIQ and what they may reach: Microsoft Entra sign-in and first-run bootstrap, the organisation model (company profile, legal entities, business units, departments, teams, management hierarchy), users and groups, business domains and their owners, roles with domain entitlements, scopes and sensitivity ceilings plus an Access Simulator, Security Status, Access Reviews, a hash-chained Audit log, System Health, Platform Integrations (identity, email, AI and Fabric connections with encrypted secrets) and an Administration Home roll-up.
- What the system does NOT do: it does not yet read, model or present any business data, call an AI model for inference, query Fabric data, or publish to Power BI; it does not create database users or databases; it does not let an identity provider grant access on its own.
- External integration seams: Microsoft Entra ID (OpenID Connect sign-in, discovery and step-up through `login.microsoftonline.com`); SMTP for email (test email from Platform Integrations); an AI service, OpenAI or Azure OpenAI, contacted only for a credential metadata check; Microsoft Fabric REST API (`api.fabric.microsoft.com`), contacted only for one configured workspace's metadata; GitHub Actions to cPanel over SSH/rsync for deployment.
- Module / layer map: `app/Modules/Platform` (entry, authentication, sessions, first-run setup, integrations, deployment layout, health), `Identity` (Identity & SSO administration), `Organisation`, `People`, `Domains`, `Access` (roles, entitlements, access engine, step-up), `Security` (posture and event catalogue), `Reviews`, `Audit`, `SystemHealth`, `Administration`; shared navigation and lifecycle code in `app/Shared`; React pages in `resources/js/Pages/<Module>` with shared components and the three layouts in `resources/js/Components` and `resources/js/Layouts`.
- Allowed dependency direction: one way, enforced by the architecture tests in `tests/Architecture` (for example `ReviewsConsumeAccessTest`, `AccessBoundaryTest`, `PeopleBoundaryTest`, `AuditBoundaryTest`, `P1BoundaryTest`): feature modules consume Access and Platform services, Access names nothing from Reviews, and Administration Home only projects facts that other modules own.

## Stack And Dependency Decisions

- Framework & stack choice rationale: fixed by the product owner as the established platform baseline (blueprint section 0.1 and Phase 1 document section 1); not a redesign topic. Server-driven Inertia pages were chosen over a separate SPA with a JSON API (decision D-07 in the P1-BASE design).
- Approved libraries: exactly those in `composer.json` and `package.json`: `laravel/framework`, `laravel/tinker`, `inertiajs/inertia-laravel`, `firebase/php-jwt`, `react`, `react-dom`, `@inertiajs/react`, `vite`, `laravel-vite-plugin`, `@vitejs/plugin-react`; development: `phpunit/phpunit`, `laravel/pint`, `mockery/mockery`, `fakerphp/faker`, `nunomaduro/collision`, `laravel/pail`, `laravel/pao`. A shared dependency needed by more than one unit is raised for Product Owner approval before it is introduced.
- Banned / disallowed libraries: any SemantIQ v1 package or code; a second icon set or icon library beside `resources/js/Components/Icon.jsx`. No other ban is written down in the repository.
- Formatter / linter configuration: Laravel Pint with its default Laravel preset (no `pint.json`), checked in CI with `vendor/bin/pint --test`; `.editorconfig` sets UTF-8, LF line endings and 4-space indentation (2 for YAML). No JavaScript linter is configured.
- Dependency version-pinning / lockfile policy: `composer.lock` and `package-lock.json` are committed; CI and deployment install with `composer install` and `npm ci` from the lockfiles; deploy workflow actions are pinned to commit SHAs.
- Data-access layer / pattern: Eloquent models per module (`app/Modules/*/Models`) with module service classes and read-only projections (`*/Projection`) for screens that summarise other modules.

## UI Application Definition

### Approved standard values

- Company name: CLaaS2SaaS
- Company logos, per theme: `logo-full-light.png` / `logo-full-dark.png` (expanded), `logo-short-light.png` / `logo-short-dark.png` (collapsed), in `.claude/skills/ui-ux-design/assets/`; the application serves its own copies from `resources/brand/`
- Favicon, per theme: `favicon-light.ico` / `favicon-dark.ico`
- Design tokens, palette, surfaces: `.claude/skills/ui-ux-design/reference/design-tokens.md`; brand palette Midnight Blue `#193E6B`, Green Gold `#B3A125`, Avocado `#5F8025`, Sunray `#E9AC53`, Violet-Red `#991547`, Jelly Bean `#448E9D`, Cadmium Violet `#7F3F98`. In this repository the tokens are implemented in `resources/css/app.css` from the project's own copy of the standard, `doc/design-system/ui-and-ux-layout-template-shared.md`.
- Fonts: Montserrat headings + Source Sans 3 body, loaded from Google Fonts in `resources/views/app.blade.php`
- Density: compact
- Icon style: central inline-SVG registry (24px viewBox, 2px stroke, outline), `resources/js/Components/Icon.jsx`
- Accessibility target: WCAG AA
- Theme switcher: on (System / Dark / Light), `resources/js/Components/ThemeSwitcher.jsx`
- Sidebar nav filter: not built; Sidebar collapsible icon rail: on (`resources/js/Layouts/AppShell.jsx`)
- Navigation: SemantIQ does not use the standard's four generic clusters. It has three product areas, rendered in this order: SemantIQ Workplace, Fabric Configuration, System Administration (decisions D-02 and D-23, an approved SemantIQ-specific deviation). Audit, Access Reviews and Security Status sit inside System Administration.

### App identity (per project)

- App name: SemantIQ
- Browser / document title-bar name: `SemantIQ`, with page titles rendered as `<page> · SemantIQ` by `resources/js/app.jsx`
- Tagline: none set in the application. The blueprint's descriptor is "Business Decision Intelligence control plane for Microsoft Fabric".
- Brand-assets destination path: `resources/brand/` for the logos and `public/` for the favicons

### UI stack (per project)

- UI stack: React 19 pages rendered through Inertia (`resources/js/Pages`), hand-written CSS with design tokens (`resources/css/app.css`), built by Vite
- Charting library: none; no chart is rendered in Phase 1

### Feature toggles (confirmed defaults)

- Customizable dashboard: not built; Administration Home (`/console/administration`) is a fixed, read-only roll-up
- Notifications: not built; there is no in-app notification bell
- Authentication: On - per the authentication model recorded above; this application owns every role, entitlement, scope and sensitivity ceiling. See the Authentication And Authorization section below
- Recycle bin (soft-delete + restore): not used. Records are deactivated and reactivated through an `active` / `inactive` status, and permanent removal is a guarded purge (decision D-24) recorded in the audit trail
- Audit log: On, `/console/audit`, four categories: user access, admin changes, security events, configuration changes

### List behavior (per project)

Every list / index screen sorts and filters, per `.claude/rules/ui-ux-quality.md`. What varies is the naming.

- List query parameter convention: `search` (Access Reviews use `q`), one parameter per facet (`status`, `group`, `organisation`, `kind`, `owner`, `role`, `state`, `period`, `event`) and Laravel's `page`. Sorting is fixed per list on the server; no `sort` or `dir` parameter exists yet.
- Server-side sort and filter for paginated lists: required; lists paginate 25 rows per page on the server.

### Step-by-step form drafts (per project)

Every multi-step form saves a resumable draft on each `Continue`, per `.claude/rules/ui-ux-quality.md`. What varies is where it is stored, not whether it exists.

- Draft storage shape (a separate draft table, or the record's own table with a draft state): no draft table exists. The only multi-step flow, First-Run Platform Setup, saves each step to its own record (`integration_configurations`, `integration_secrets`, `bootstrap_administrators`) as the step is completed, and an identity change is held in `staged_integration_changes` until it is confirmed.
- Where the draft state / status value comes from (the platform's existing status dimension, or a new one): the `status` column of `integration_configurations` and the setup state derived by `app/Modules/Platform/Setup`; no separate draft status exists.
- Drafts in flight per user per flow (one resumable draft, or several): one
- Fields excluded from a saved draft (credentials, keys, tokens, passwords): all of them, always
- Autosave beyond the per-step save (interval, on blur, on close): none in the code

### Navigation tree (config-driven, sidebar-only)

The tree is defined in code, `app/Shared/Navigation/ApprovedMenu.php`, and filtered per user by `NavigationRegistry`; only accepted System Administration capabilities are navigable.

- SemantIQ Workplace: locked, Phase 3 (Home, My Intelligence, Explore, Ask SemantIQ, Insights, Risks & Opportunities, Recommendations, Decisions & Alerts, Reports & Dashboards, My Workspace, Help)
- Fabric Configuration: locked, Phase 2 (Overview, Data Sources, Connect Source, Discovery, Data Classification, Ingestion, Data Quality, Business Model, Security Mapping, Semantic Model, AI Readiness, Pipelines & Refresh, Power BI Publication, Monitoring)
- System Administration: Administration Home, Organisation, Users & Groups, Roles & Access, Business Domains, Identity & SSO, Security Status, Access Reviews, Audit, System Health, Platform Integrations
- Access policies: each node and route declares an action class (`platform_admin`, `org_admin`, `access_admin`, `evidence_read`, `business_data`) enforced by `RequireActionClass`; a route that declares none fails closed. An Organisation Administrator's System Administration navigation is exactly `Administration Home` (ruling PO-R2).

### Entities

#### Entity: Organisation (plural: Organisations)

- Database table(s): `organisations`
- Columns: `name`, `legal_name`, `country`, `timezone`, `status`, primary legal entity
- Transaction / child table(s): `legal_entities`, `business_units`, `business_unit_legal_entity`, `departments`, `teams`, `team_memberships`, `management_relationships`
- Field -> column mapping notes: business units, departments and teams also carry a `code`; memberships and management relationships are dated rows (`joined_at` / `left_at`, `effective_from` / `effective_to`) rather than overwritten values
- Default list sort (column + direction): `name` ascending on the legal entity, business unit, department and team lists
- List filter facets (the dimensions this list is narrowed by): none; these lists are neither paginated nor filtered today

#### Entity: User (plural: Users)

- Database table(s): `users`
- Columns: `provider`, `external_subject`, `tenant_id`, `email`, `display_name`, `status`, `organisation_id`, `last_signed_in_at`
- Transaction / child table(s): `groups`, `group_memberships`, `team_memberships`, `role_assignments`
- Field -> column mapping notes: the identity key is unique on (`provider`, `external_subject`, `tenant_id`); the old `platform_role` column was migrated into `role_assignments` and dropped
- Default list sort (column + direction): `display_name` ascending
- List filter facets (the dimensions this list is narrowed by): search, status, group, organisation

#### Entity: Business Domain (plural: Business Domains)

- Database table(s): `business_domains`
- Columns: `code`, `name`, `description`, `kind`, `status`, `access_expectation`, `organisation_id`
- Transaction / child table(s): `business_domain_owners`
- Field -> column mapping notes: a domain names and owns part of the intelligence estate and grants access to nothing on its own
- Default list sort (column + direction): `kind` then `name`, ascending
- List filter facets (the dimensions this list is narrowed by): search, kind, status, owner

#### Entity: Role Assignment (plural: Role Assignments)

- Database table(s): `role_assignments`
- Columns: `user_id`, `organisation_id`, `role_code`, `assigned_at`, `ended_at`, `assigned_by_user_id`, `ended_by_user_id`
- Transaction / child table(s): `domain_entitlements`, `entitlement_scopes`, `entitlement_ceilings`, `pending_step_ups`
- Field -> column mapping notes: grants are never updated in place; a revocation stamps `ended_at` and `ended_by_user_id`
- Default list sort (column + direction): `role_code` ascending, then `assigned_at` descending
- List filter facets (the dimensions this list is narrowed by): search, role, state

#### Entity: Access Review (plural: Access Reviews)

- Database table(s): `access_review_cycles`, `access_review_items`
- Columns: cycles carry `organisation_id`, `started_at`, `due_at`, `started_by_user_id`; items carry `kind`, `subject_user_id`, `role_code`, `state`, `due_at`, `decided_at`, `decided_by_user_id`, `decision_basis`, `self_review`
- Transaction / child table(s): `access_review_items`
- Field -> column mapping notes: each item records the composition it reviewed (`composition`, `composition_fingerprint`) so a later grant change supersedes it
- Default list sort (column + direction): `due_at` ascending, then `id`
- List filter facets (the dimensions this list is narrowed by): `q`, state

#### Entity: Audit Event (plural: Audit Events)

- Database table(s): `audit_events`, `audit_chain_head`
- Columns: `sequence`, `occurred_at`, `event`, `category`, actor, subject, target, `outcome`, `reason`, access context (`role`, `domain_id`, `scope`, `sensitivity`), `previous_hash`, `row_hash`
- Transaction / child table(s): none
- Field -> column mapping notes: no IP address, user agent or free text; no foreign key to users, so evidence survives a user purge
- Default list sort (column + direction): `occurred_at` descending, then `sequence` descending
- List filter facets (the dimensions this list is narrowed by): category tab, event

#### Entity: Integration (plural: Integrations)

- Database table(s): `integration_configurations`, `integration_secrets`, `staged_integration_changes`, `platform_settings`
- Columns: `family` (identity, email, ai, fabric), `settings`, `status`, `last_tested_at`; secrets carry `name`, `ciphertext`, `key_version`
- Transaction / child table(s): `integration_secrets`
- Field -> column mapping notes: one secret per name per family, encrypted with Laravel `Crypt`, never returned to the browser
- Default list sort (column + direction): fixed family order
- List filter facets (the dimensions this list is narrowed by): none

### Roles & tenancy

SemantIQ does not use the standard's five-tier model for authorisation (decision D-01, an approved SemantIQ-specific deviation). Effective access is Identity + Platform Role + Business Domain + Scope + Sensitivity + Organisation / Team / Ownership relationship + Policy.

| Role | Action classes | Record scope |
| --- | --- | --- |
| System Administrator | platform_admin, org_admin, access_admin, evidence_read | Platform-scoped; administers everything but receives no business-domain data by default |
| Organisation Administrator | org_admin, access_admin, evidence_read | Its organisation; may grant every role except System Administrator |
| Executive | business_data | Per domain entitlement, scope and sensitivity ceiling |
| Domain Owner / Director | business_data | Per domain entitlement, scope and sensitivity ceiling |
| Manager | business_data | Per domain entitlement, scope and sensitivity ceiling |
| Business User | business_data | Per domain entitlement, scope and sensitivity ceiling |
| Auditor | evidence_read | Read-only evidence: Security Status, Access Reviews, Audit |

Scope types: own, team, business unit, domain, organisation. Sensitivity levels: Standard, Confidential, Restricted.

- Roles are a fixed catalogue in code (`RoleCode`, `RoleCatalogue`); assignments, entitlements, scopes and ceilings are data, changed in the UI under Roles & Access. Domain, scope and sensitivity are independent dimensions, and scope is never derived from a role tier.
- Tenancy: single-tenant deployment, with organisation boundaries designed to be multi-tenant-ready
- Team/org hierarchy: Organisation -> Business Unit -> Department -> Team, with legal entities associated to business units and a dated management hierarchy between users.

## Authentication And Authorization

Authentication follows the authentication model recorded under Required Intake Status above. This application owns every role, entitlement, scope and sensitivity ceiling. Never record a secret here.

### Session And Sign-In

- Local session technology: Laravel session cookie; the approved driver is `database` (`sessions` table, `.env.example`), while production still runs `file` until the carried session-driver alignment is completed (`PHASE-1-PLAN.md` section 10)
- Local session lifetime and expiry behavior: 60 minutes idle (`SESSION_LIFETIME`) and 12 hours absolute (`EnsureSessionIsCurrent::ABSOLUTE_HOURS`); both are compared with the approved policy in `SessionPolicy` and shown on the Session Policy screen. Expiry lands on `/auth/session-expired`.
- Where a sign-in attempt's outcome is recorded (a durable record in the security audit trail; a structured log stream may mirror it, never replace it): `SecurityEventLogger` writes a structured log entry and records it through `EvidenceRecorder` into `audit_events`
- The closed set of sign-in outcome values, and where it is codified: `auth.login.succeeded`, `auth.login.refused.unknown_identity`, `auth.login.refused.inactive`, `auth.login.refused.tenant`, `auth.login.refused.protocol`, plus `auth.logout` and `auth.session.expired`, and the `bootstrap.signin.*` events for the local Bootstrap Administrator; codified in `app/Modules/Platform/Security/SecurityEventLogger.php` and catalogued in `app/Modules/Security/Catalogue/EventCatalogue.php`
- Retention and classification of the sign-in outcome record (the audit retention unless a different one is confirmed): the audit retention below; no purge of audit rows exists in the application
- Where a failed sign-in lands: a standalone state page under `/auth/`: `access-not-assigned`, `account-inactive`, `access-denied` or `sign-in-unavailable`
- Local session identifier reissued when the session is created: yes - `CallbackController` regenerates the session on sign-in

### Authorization (This Application's Own)

- Feature registry storage location: there is no feature registry stored as data. Capabilities are declared in code: the navigation tree in `app/Shared/Navigation/ApprovedMenu.php` and the action class each route requires (`RequireActionClass`)
- Roles, grants, and assignment storage location: `role_assignments`, `domain_entitlements`, `entitlement_scopes`, `entitlement_ceilings`; the role catalogue itself is `app/Modules/Access/Support/RoleCatalogue.php`
- Shared action vocabulary: the five action classes `platform_admin`, `org_admin`, `access_admin`, `evidence_read`, `business_data` (`ActionClass`); only `business_data` requires a complete grant path of role, entitlement, scope and ceiling
- Default role provisioned on first login: none. An identity with no matching active user record is refused at sign-in (`access-not-assigned`); users are created in Users & Groups before they can sign in
- Where the configured default role does not resolve: not applicable, because no default role is configured
- Where the default is no access, the page a new user lands on: `/console`, the confirmation page reachable with a session and no role
- How the framework's own authorization helpers are handled (bridged to the application's identity, or bypassed for explicit policy calls - never left half-wired): not used; authorisation runs through `RequireActionClass`, `RequireOrganisation` and the `AccessEngine`, ordered before route-model binding in `bootstrap/app.php`
- How the tenant is resolved where the app is multi-tenant: single-tenant; the user's `organisation_id` decides the organisation, and the Entra tenant id is checked at sign-in
- Tier that manages roles, grants, and user assignment: System Administrator and Organisation Administrator (`access_admin`)
- Tier that manages the feature registry: no data-driven registry exists; the code catalogue changes only through a reviewed change
- Grant-change audit location (part of the security audit trail): `audit_events`, admin changes category
- Grant ceiling: an Organisation Administrator can never grant System Administrator (`RoleCatalogue::grantableBy`); granting oneself a domain entitlement requires an Entra step-up re-authentication; sensitivity ceilings cap each domain entitlement
- A role or grant above the acting administrator's authority: not grantable by that administrator; the role list offered comes from `RoleCatalogue::grantableBy`
- Every active, granted feature resolves to a real route before it renders, so a product-area heading never appears above nothing (`NavigationRegistry` refuses a node whose route does not resolve)
- Grant resolution: evaluated at the backend on every protected request by `RequireActionClass` and the `AccessEngine`; no cross-request grant cache exists in the code
- Documented exceptions to the feature-authorization rules: the D-01 and D-02 deviations from the shared UI standard's role tiers and clusters, recorded in `doc/v2/phase-1/PHASE-1-PLAN.md` section 4

## Security And Identity Conventions

- Secret manager / secret store: the server `.env` for platform secrets (`APP_KEY`, `DB_PASSWORD`, `MICROSOFT_CLIENT_SECRET`); integration secrets entered in Platform Integrations are stored encrypted in `integration_secrets`; deployment credentials are GitHub Actions secrets (`CPANEL_HOST`, `CPANEL_PORT`, `CPANEL_USER`, `CPANEL_DEPLOY_PATH`, `CPANEL_SSH_PRIVATE_KEY`, `CPANEL_SSH_KEY_PASSPHRASE`)
- CI credential model: CI holds no deployment secret and never touches the server; only `deploy.yml` reads the cPanel secrets, from the GitHub `development` environment
- Token signing scheme: SemantIQ issues no tokens of its own. It validates Entra ID tokens signed RS256 against the tenant's published keys (`IdTokenValidator`)
- Authorization scope convention: OpenID Connect sign-in scopes for Entra; inside the application, the scope types own, team, business unit, domain and organisation
- Password hashing algorithm: Laravel's default hasher (bcrypt) for the single local Bootstrap Administrator; no other passwords exist, because everyone else signs in through Entra
- Token / session lifetime policy: 60 minutes idle, 12 hours absolute; bootstrap grants, recovery tokens and step-up requests carry their own expiry
- Encryption in transit policy: HTTPS on the live site (the deployment gate fetches over HTTPS and proves ACME renewal works); outbound calls to Entra, Fabric and AI services are HTTPS
- Encryption at rest policy: integration secrets encrypted with Laravel `Crypt` (AES-256-CBC keyed by `APP_KEY`); session data is not encrypted (`SESSION_ENCRYPT` defaults to false); database-level encryption is not recorded in the repository
- Key / secret rotation schedule: none recorded; `key_version` is stored on each secret but no rotation tooling exists (asserted by `NoKeyRotationToolingTest`)
- Field / column-level encryption targets: `integration_secrets.ciphertext` and `staged_integration_changes.ciphertext`
- AI/LLM feature conventions: no runtime inference exists in Phase 1. Conversational AI is Phase 3 scope.
  - Prompt store location + versioning: none, no prompts exist
  - Model routing / adapter + fallback: none; the AI connection is a single configured provider (OpenAI or Azure OpenAI) used only for a credential check
  - Max-tokens / temperature defaults: none, no inference call exists
  - Per-agent token budget + cost ceiling: none, no inference call exists
  - Golden-set location + pass-score bar: none
  - Eval-as-merge-gate expectation: none

## Secure Coding Conventions

The facts `.claude/rules/secure-coding.md` depends on. Mechanisms and names only, never a secret.

- Templating engine and its automatic output escaping: React (JSX escapes by default) through Inertia, with one Blade root view, `resources/views/app.blade.php`
- HTML sanitizer for rich content (none means rich content renders as text): none; rich content renders as text
- CSRF protection mechanism: Laravel's web middleware group CSRF token, used by Inertia requests; `/up` is outside the web group and changes nothing
- Standing CSRF exemptions: signature-verified inbound webhook routes; any other is listed under Documented exceptions below
- Session cookie attributes (`HttpOnly`, `Secure` over HTTPS, host-only, and `SameSite=Lax` unless a reason for `Strict` is recorded): `HttpOnly` on, `SameSite=lax`, host-only (no `SESSION_DOMAIN` set), `Secure` taken from `SESSION_SECURE_COOKIE` in the server `.env`
- Content security policy and security headers: none set by the application; the production `.htaccess` (`deployment/public_html.htaccess`) denies protected paths with a literal 403 and disables directory listing
- Origins allowed to read responses cross-origin (none means same origin only): none
- Does the server fetch URLs a user or administrator supplies (webhooks, imports, AI model catalog endpoints, connection tests): yes. Platform Integrations connection tests call the administrator-entered AI endpoint and SMTP host; Entra and Fabric calls go to fixed Microsoft hosts
  - Internal destinations it may reach, and the environment configuration key that names them: none
- Does the application accept file uploads: no
  - Allowed types and maximum size: not applicable, no upload exists
  - Storage location and how files are served: not applicable; `storage:link` is never run in production because of the root-layout collision (`HOSTING-ARCHITECTURE.md` section 4)
  - Malware scanner: not applicable
- Production error display: debug output off; an unexpected error shows a generic message and a correlation id, with detail in the log only
- Security audit trail location (the sign-in outcome record and the grant-change audit are part of it): `audit_events`, with the chain head in `audit_chain_head`, shown at `/console/audit`
- How the audit trail is kept append-only (a grant of insert and select with no update or delete on the store, or by convention only until that grant is confirmed): by convention in the application: no route, controller action or service updates or deletes a row (`AuditImmutabilityTest`), and each row is SHA-256 hash-chained to the previous one so tampering is detectable (`AuditChainVerifier`). No database grant restriction is recorded.
- Audited actions beyond the rule's defaults, including any record whose reads are audited: none
- Dependency vulnerability audit tool and command: `npm audit` runs on every npm install (`.npmrc` `audit=true`); `composer audit` is available but is not run in CI
- Advisory that blocks adding or updating a dependency: critical, high, or unrated; take the fixed version where one exists, otherwise only with the developer's explicit acceptance recorded under Documented exceptions below
- Compliance frameworks the organization pursues, any recorded mapping of controls to their criteria, and who owns the evidence (code supports a control; it never makes the application compliant): none recorded in the repository
- Documented exceptions to the secure coding and dependency vulnerability rules (a CSRF exemption, a loosened content security policy, an accepted advisory, an audited action shipped before the trail exists), each with who accepted it and when, the reason, the follow-up and its owner, and when it is revisited: none

## AI Model Catalog (Only If The App Calls A Model At Runtime)

- Does the application call an AI model / LLM at runtime: no inference. Platform Integrations stores one AI connection, and its Test button makes a single metadata read (OpenAI `/v1/models` or the Azure OpenAI deployments list) with a 10 second timeout; any other provider is reported as Not checked (`AiConnectionTester`)
- Catalog storage location (database table, versioned config file, or settings provider): `integration_configurations` (family `ai`) with the API key in `integration_secrets`; there is no multi-model catalog
- Providers and model ids in use: none in use; the connection accepts OpenAI or Azure OpenAI and no model id is called
- Environment source `{{env.NAME}}` resolves against: not used by this application
- Project-wide per-call timeout and connect timeout: 10 seconds for the AI connection test; no inference timeout exists
- Retry policy and dedupe-key strategy for model calls: none, no model call exists
- Cost currency (one project-wide constant) and display rounding precision: none, no cost is tracked
- Who may manage the catalog (role, defaults to System Administrator): System Administrator (`platform_admin`), and the Bootstrap Administrator during first-run setup
- Documented exceptions to the approved catalog pattern: none

## Migrations And Data Change Workflow

- File-based migration tool: Laravel migrations in `database/migrations`, applied by the deploy workflow with `php artisan migrate --force --no-interaction`; a failed migration fails the deployment (decision D-05)
- Migration file naming convention: Laravel timestamp prefix `YYYY_MM_DD_NNNNNN_` followed by a snake_case description, for example `2026_09_21_000002_create_integration_configurations_table.php`

## Analytics And Semantic Model

- Does the solution feed a semantic model, BI layer, or reporting product: not in Phase 1. Phase 2 and Phase 3 connect to Microsoft Fabric and Power BI semantic models; SemantIQ's own administration tables feed no BI layer today
- Semantic model / BI tool and who owns it: Microsoft Fabric and Power BI, owned by the customer organisation, in Phase 2 and Phase 3 scope
- How reporting reads the data (operational tables, read replica, extract, warehouse): the in-app screens read the operational MySQL tables directly through module projections
- Reporting time zone and business-day boundary: the application stores and computes in UTC (`config/app.php`); each organisation records its own `timezone`, and no business-day boundary is defined
- First day of week and fiscal year start: not defined in the repository
- Timestamp storage convention: UTC; audit `occurred_at` is stored as an exact microsecond string so it can be hashed
- Surrogate / business key convention: auto-increment big integer `id` surrogate keys, with business codes (`code` columns) and the external identity key (`provider`, `external_subject`, `tenant_id`) as unique business keys
- Conformed dimensions the platform already has (source, status, reason, owner, currency): the `active` / `inactive` status shared by every organisational record, role codes, scope types, sensitivity levels, audit categories and the outcome and reason codes in the security event catalogue
- History / event / snapshot retention: memberships, management relationships, ownership and every grant are dated rows that are ended, not overwritten; audit rows are never deleted by the application
- Where each feature's analytics question set is recorded: not recorded; the per-unit design documents under `doc/v2/phase-1/` are the place a feature records its design
- ERD and data-model deliverable location and format: no ERD is committed; table shapes are documented in the per-unit `P1-*-DESIGN.md` files and the migrations
- Approved materialized aggregates (and how each is rebuilt): none
- Documented exceptions to the analytics-ready data rules: none

## Quality, Observability, And Operability

- Minimum test coverage bar: no percentage bar is set. Every guard must be proven non-vacuous by a deliberate mutation (recorded in the `P1-*-MUTATIONS.md` files), negative tests are mandatory per unit, and the suite has Unit, Feature and Architecture suites (`phpunit.xml`)
- Gating mechanism for incomplete work in the live path: undelivered menu entries are locked navigation nodes, and a route without a declared action class fails closed; there is no feature-flag system
- Structured logging format: Laravel logging, `stack` channel over `single` by default; security events go through `SecurityEventLogger` with a fixed, redacted context shape
- Metrics / tracing destination: none configured
- Health / readiness endpoint conventions: `/up` liveness outside the web middleware group and exempt from maintenance mode (503 when impaired); `php artisan semantiq:health` runs during deployment; the System Health screen at `/console/system-health`
- Per-environment config keys that differ across environments: `APP_ENV`, `APP_DEBUG`, `APP_URL`, `DB_*`, `SESSION_DRIVER`, `SESSION_LIFETIME`, `SESSION_SECURE_COOKIE`, `LOG_LEVEL` and the `MICROSOFT_*` keys

## Resilience Thresholds

- Outbound call timeout(s) per dependency type: 10 seconds for every outbound call in the code: Entra discovery and key retrieval (`EntraDiscovery`), and the AI, email and Fabric connection tests (`TIMEOUT_SECONDS` in `app/Modules/Platform/Setup/Connections`)
- Retry policy for idempotent operations: none yet
- Circuit-breaker trip / reset thresholds: none yet
- Dead-letter / replay location for failed async work: none; the queue runs `sync`
- Idempotency / dedupe key strategy: none yet; single-use tokens (bootstrap grants, recovery tokens, step-up references) are consumed atomically

## Data Lifecycle Governance

- Data sensitivity classification scheme: business domains are classified Standard, Confidential or Restricted (`Sensitivity`); no scheme is recorded for the administration data itself
- Retention period per data class: not recorded in the repository
- Audit / compliance log retention: indefinite in the application, which never deletes an audit row; no retention period is recorded
- Abandoned step-by-step form draft retention: not applicable, no draft records exist
- Applicable privacy regime: not recorded in the repository
- Backup retention window: not recorded in the repository; backups are a hosting concern on cPanel
- Restore-test cadence: none
- Recovery-point objective (RPO) per environment: none
- Recovery-time objective (RTO) per environment: none
- Failover procedure and disaster communications: none

## Operations And Incident Response

- Service-level objectives (SLOs) per critical service: none yet
- Alert thresholds: none yet
- Incident severity taxonomy and per-severity response time: none yet
- On-call / escalation path: none yet
- Top failure modes and their runbook locations: `deployment/AUDIT-ROLLBACK.md` (audit rollback) and `doc/v2/phase-1/PHASE-1-CLOSEOUT-WS-1-SESSION-DRIVER-RUNBOOK.md` (session-driver alignment)

## API / Interface Conventions

- Interface paradigm: server-driven Inertia pages over web routes; there is no public JSON API (`/api/*` is only used to decide JSON error rendering)
- Resource / operation naming style: plural kebab-case resource paths under `/console` with route names in dot notation, for example `organisation.business-units.update`; state changes use `PATCH .../deactivate`, `.../reactivate`, `.../revoke`, and permanent removal uses `DELETE`
- Error contract format: Laravel validation errors returned to Inertia forms; JSON is rendered for requests that expect JSON; refusals go to the standalone `/auth/` state pages
- Version scheme: none, no versioned API exists
- Standard header names: Laravel and Inertia defaults (`X-Inertia`, `X-XSRF-TOKEN`); no custom header
- Pagination style and maximum page-size cap: page-number pagination with `page`, 25 rows per page, fixed on the server
- Authoritative contract spec tool and location: none; the routes in `routes/web.php` and the per-unit design documents are the contract

## Configuration Contract

- Config example-file path: `.env.example`
- Config library / loader: Laravel configuration in `config/*.php` reading the server `.env`; the identity provider settings live in `config/identity.php`, and after the identity cutover they come from Platform Integrations (`platform_settings.identity_source`)
- Config precedence order: real environment variables, then `.env`, then the defaults in `config/*.php`; for identity, the database-held integration once cut over
- Enumerated config variables (names + placeholders only): `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL`, `LOG_CHANNEL`, `LOG_LEVEL`, `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `SESSION_DRIVER`, `SESSION_LIFETIME`, `CACHE_STORE`, `QUEUE_CONNECTION`, `MICROSOFT_TENANT_ID`, `MICROSOFT_CLIENT_ID`, `MICROSOFT_CLIENT_SECRET`, `MICROSOFT_REDIRECT_URI`

## Git And Review Knobs

- Commit signing policy: not required
- Merge strategy: not fixed (chosen per Pull Request); recent pull requests are squash-merged with the PR number in the title
- Working-branch lifetime cap: none set
- Rebase cadence: none set
- Required approver count: Product Owner approval at each gate by convention (not branch-protected)
- CODEOWNERS / path-ownership file location: none
- Review first-response SLA window: none set

## Deployment Policy

- Deployment target: one cPanel account, document root and deployment root `public_html` (decision D-08B, permanent)
- Deployment method: `.github/workflows/deploy.yml` on push to `main` or manual dispatch: build on the runner, assemble the production tree, rsync over SSH with the exclusion contract in `deployment/rsync-protected-paths.txt`, then migrate, clear caches and run `semantiq:health`
- Source control provider: GitHub
- GitHub repository URL: [GitHub Repository](https://github.com/eduCLaaSTeach/semantiq)
- CI/CD or deployment pipeline: GitHub Actions: `ci.yml` (Pint, PHPUnit, MySQL migration and MySQL suite re-runs) on pull requests and `main`; `deploy.yml`; and the `verify-*.yml`, `initialise-domains.yml`, `align-session-driver.yml` and `verify-session-*.yml` operator workflows
- Hosting provider/platform: cPanel shared hosting with Apache, PHP 8.5 and MySQL
- Runtime/build/deploy model: Composer install `--no-dev` and `npm run build` on the GitHub runner; only the built tree is transferred, and no build runs on the server
- Infrastructure-as-code tool and location: none; the server is fixed infrastructure, and the repository carries only the deployment files in `deployment/`
- Local development support: `composer setup` (install, copy `.env.example`, key generate, migrate, npm build) and `composer dev`; tests with `composer test` or `php artisan test`
- Environment site URLs:
  - `DEV`: not defined in the repository
  - `QA`: not defined in the repository
  - `STAG`: not defined in the repository
  - `PROD`: [Production Site](https://semantiq.claas2saas.com), the one live site, deployed from `main` through the GitHub environment named `development`
- Production deployment action: Claude must not deploy, migrate, publish, upload, change hosting settings, or edit production environment values without explicit developer approval for that exact action.

## Required Deployment Details To Ask For

- Hosting target/provider: cPanel, document root `public_html`
- Source control and pipeline: GitHub, `eduCLaaSTeach/semantiq`, GitHub Actions `deploy.yml`
- Hosting account/project/resource placeholder: held in the GitHub Actions secrets `CPANEL_HOST`, `CPANEL_USER` and `CPANEL_DEPLOY_PATH`; not recorded in the repository
- Production URL and subdirectory: [Production Site](https://semantiq.claas2saas.com), subdirectory: none, the site is served from the domain root
- Web root, artifact path, startup command, service entry point, or container image: web root `public_html`, front controller `public_html/index.php` (from `deployment/public_html.index.php`), `.htaccess` from `deployment/public_html.htaccess`, built assets in `public_html/build/`
- Runtime/platform version: PHP 8.5 on the server
- Package manager/build availability on target: not needed; dependencies and assets are built on the runner
- Background processing/scheduler support: none used; the queue connection is `sync` and no scheduled command is registered
- Deployment workflow owner and steps: the `deploy.yml` workflow, run on merge to `main`; the steps are documented in `doc/v2/phase-1/HOSTING-ARCHITECTURE.md` section 6
- Rollback and monitoring/log access: no automated rollback; `deployment/AUDIT-ROLLBACK.md` covers the audit tables, and logs are in `storage/logs` on the server through cPanel and SSH
- Required `.env` keys using placeholders only: `APP_KEY`, `APP_URL`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `SESSION_DRIVER`, `SESSION_LIFETIME` and the `MICROSOFT_*` keys, with values held only in the server `.env`

## Required Validation Details To Ask For

- Syntax/type/static checks: `composer validate --no-check-publish` in the deploy workflow; no static analyser is configured
- Lint/format commands: `vendor/bin/pint --test`
- Unit/integration/e2e commands: `php artisan test` (Unit, Feature and Architecture suites on SQLite), and in CI the same feature suites again on MySQL, for example `php artisan test tests/Feature/Access`
- Build/package commands: `npm ci && npm run build`; `composer install --no-dev --optimize-autoloader` for deployment
- Security checks (dependency audit, static security analysis, secret scan) and where they run: `npm audit` on every npm install; the deploy workflow scans the live response body for leaked configuration (`APP_KEY`, `DB_*`, `MICROSOFT_CLIENT_SECRET`) and asserts that protected paths return 403; no static security analyser or secret scanner is configured
- Deployment smoke test: `php artisan semantiq:health`, the HTTPS read-back of the deployed build, the protected-path 403 checks, the ACME challenge round trip and the `.htaccess` and `index.php` SHA-256 checks, all in `deploy.yml`
- Known validation limitations: the main suite runs on SQLite, so MySQL-only behaviour is covered only by the suites CI re-runs on MySQL; browser checks are manual; some live observations need real production data and are carried as gates in `PHASE-1-PLAN.md` section 10

## Database Schema (Source Of Truth)

- Database/schema source of truth (the database's own metadata, or the committed migrations and schema definition), per `.claude/rules/database-schema.md`: the committed migrations in `database/migrations`, checked against the live database by the `verify-*.yml` workflows
- SQL Server schema name: not applicable; the database is MySQL and uses the database's default schema
- Table name prefix convention: none
- Table/column naming casing convention: snake_case, plural table names, singular pivot names (`business_unit_legal_entity`)
- Resulting table name pattern: plain snake_case plural, for example `business_units`
- Tables/schemas relevant to the current task: verified against the schema source in the current session; never from memory

## Application Database Connection (Only If The App Connects To A Database Directly)

- Does the application connect to a database directly: yes
- Database engine the application connects to: MySQL (`DB_CONNECTION=mysql`)
- Database host/service placeholder: `DB_HOST` in the server `.env`
- Database name placeholder: `DB_DATABASE` in the server `.env`
- Database user placeholder: `DB_USERNAME` in the server `.env`
- Schema/database owner: the cPanel account; the database and user are created once by hosting administration, never by application code (decision D-05)
- Where connection settings/secrets live (env keys, placeholders only): the server `.env`, keys `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`

## Knowledge Base Policy

- Claude must read existing knowledge-base files before editing when they exist.
- Claude must read this file before editing and update it with confirmed non-secret facts after developer answers.
- After each completed implementation, Claude must ask the developer whether to create or update the knowledge base now or defer it, and must write knowledge-base files only after the developer has verified, validated, and explicitly approved the update, per `.claude/rules/knowledge-base.md`.
- When approved, Claude must ask whether the work is a solution, a module, or a feature and where it sits in the hierarchy, then update the write-up and the knowledge-base README index.
- Claude must update the table dictionary after confirmed work that verifies or changes table/schema knowledge; when a change alters the schema, the table-dictionary update travels in the same change unit as the schema change.
