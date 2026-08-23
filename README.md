# SemantIQ

SemantIQ is a control plane for Microsoft Fabric, intended to turn the end-to-end Fabric data-intelligence setup and governance journey into one guided web application.

- Live site: https://semantiq.claas2saas.com/
- Repository: https://github.com/eduCLaaSTeach/semantiq
- Existing design/specification index: `doc/README.md`
- Phase implementation plan: `doc/MASTER_IMPLEMENTATION_PLAN.md`

## Confirmed Application Stack

| Layer | Choice |
| --- | --- |
| Backend | Laravel 13 on PHP 8.5 |
| Frontend | React 19 |
| Database | MySQL, cPanel-hosted |
| Build toolchain | Composer plus Node.js 24/npm for frontend assets |
| Architecture | Modular monolith |
| Hosting/deployment | cPanel, deployed by GitHub Actions over SSH/rsync |

Node.js is a build-time dependency on the CI runner. It is not required as a production application runtime unless a later approved architecture decision adds a Node service.

At this repository baseline, application code has not landed yet. There is no `composer.json` or `package.json`, so the build workflow cannot currently pass until the Laravel/React application is scaffolded. Re-check this statement before acting because it will become stale as soon as Phase 00 scaffolding lands.

## Current Deployment Mode And Product Direction

The current hosted application baseline is single-customer/single-tenant per SemantIQ application instance.

The product is being designed to become reusable for customers that bring their own Microsoft Fabric environment. Therefore customer-owned metadata/configuration must preserve organisation/tenant boundaries and deny cross-organisation access by default, but Release 1 must not silently introduce shared multi-customer SaaS tenancy or multi-tenant Entra sign-in. Those require an explicit approved architecture decision.

This resolves the earlier difference between the existing repository's single-tenant deployment statement and the SRS product ambition for a multi-tenant-ready control plane.

## Repository Layout

| Path | Holds |
| --- | --- |
| `CLAUDE.md` | Repository execution, safety, phase-gate, security, sovereignty and coding rules |
| `IMPLEMENTATION_STATUS.md` | Single authoritative phase state |
| `doc/` | Existing solution/design specs plus the phased implementation documentation |
| `doc/design-system/` | Sole authority for UI layout, structure, theme and brand assets |
| `doc/phases/` | Claude Code implementation scope, one phase at a time |
| `doc/reference/` | SRS mirror, API/help/traceability, AI and data-protection standards |
| `doc/context/` | Code/data/validation/configuration/sovereignty context registers |
| `doc/execution/` | Phase plans, verification evidence and architecture/user decisions |
| `doc/templates/` | Phase-plan, verification and decision templates |
| `.github/workflows/` | Existing deployment pipeline; this kit does not overwrite it |
| `deployment/` | Existing cPanel front-door `.htaccess`; this kit does not overwrite it |

The new package intentionally uses `doc/`, not `docs/`, because the existing repository already uses `doc/` and the design system references that path.

## Documentation Precedence

- UI: `doc/design-system/ui-and-ux-layout-template-shared.md` is the single authority for layout, theme and brand rules.
- Execution/safety: root `CLAUDE.md`.
- Phase state: root `IMPLEMENTATION_STATUS.md`.
- Implementation order: `doc/MASTER_IMPLEMENTATION_PLAN.md` and the active `doc/phases/PHASE-XX-*.md` file.
- Functional requirements: `doc/reference/SEMANTIQ_SRS_BASELINE.md` and formal Word SRS under `doc/reference/word/`.

If material requirements still conflict, Claude Code must stop, record a decision and ask the user rather than silently selecting an interpretation.

## Phase-Gated Delivery

SemantIQ is implemented in ten controlled phases:

| Phase | Scope | Initial state |
| --- | --- | --- |
| 00 | Engineering foundation and control-plane skeleton | READY_FOR_PLAN |
| 01 | Organisation/tenant onboarding, SSO and Fabric automation identity | LOCKED |
| 02 | Fabric readiness, capacity and workspace provisioning | LOCKED |
| 03 | Source connectivity, gateway and schema discovery | LOCKED |
| 04 | Ingestion, Lakehouse and medallion data foundation | LOCKED |
| 05 | Data quality, standardisation and business modelling | LOCKED |
| 06 | Semantic model, security and governance | LOCKED |
| 07 | AI readiness, Fabric Data Agent and validation | LOCKED |
| 08 | Deployment, operations, help centre and lifecycle | LOCKED |
| 09 | End-to-end UAT, go-live and handover | LOCKED |

Claude Code must plan, get approval, implement, verify, present evidence and wait for the exact user completion phrase before unlocking the next phase.

## Branching And Deployment

`main` is the only long-lived branch and the live deployment trigger. There is no permanent Git DEV/QA/STAG/PROD promotion chain.

Use short-lived feature/phase branches for pull requests where review is required. A merge/push to `main` triggers the live deploy, so it must not be treated as a harmless source-control action.

The current GitHub Actions workflow builds the release and transfers it to cPanel over SSH/rsync. The repository documentation reports these steps:

1. Set up PHP 8.5 and run Composer validation/install.
2. Set up Node.js 24, run npm install/build and remove `node_modules`.
3. Copy the versioned cPanel front-door `.htaccess` to the deployment root.
4. Load the passphrase-protected deployment key into `ssh-agent`.
5. Verify SSH/rsync.
6. `rsync -az --delete` the built release to the target.
7. Create required Laravel runtime directories if missing.

The existing workflow transfers built `vendor/` and `public/build`. Its documented exclusions include `.git/`, `.github/`, `deployment/`, `node_modules/`, `tests/`, `phpunit.xml`, `README.md`, `.editorconfig`, `.gitignore`, `.gitattributes`, `.env`, `.env.example`, `storage/` and `public/storage`. In particular, server `.env` is excluded and is not created or overwritten by the deployment. Re-verify the actual workflow before relying on this list.

The existing `rsync --delete` behaviour is high impact because files present only at the target can be removed. A sentinel/dry-run/release-directory guard remains an open hardening item.

### GitHub Deployment Secrets

Values are configured in the GitHub UI and never committed. The current workflow expects names including:

- `CPANEL_HOST`
- `CPANEL_PORT`
- `CPANEL_USER`
- `CPANEL_DEPLOY_PATH`
- `CPANEL_SSH_PRIVATE_KEY`
- `CPANEL_SSH_KEY_PASSPHRASE`

Deployment credentials needed by CI belong in GitHub Environment/Actions secrets. Runtime database credentials normally belong in the server `.env` or an approved secret store and should not be copied into GitHub secrets unless an approved workflow genuinely requires them.

The current workflow is reported as using a GitHub environment named `development` even though `main` deploys the live site. This is a naming/control mismatch. The target should be a production-labelled/protected environment, but renaming it and moving secrets is a deployment change requiring explicit approval.

## Database

The planned application database is MySQL hosted through cPanel and accessed through Laravel's MySQL driver.

Once application code exists, Laravel migrations under `database/migrations/` are the schema source of truth. Do not edit an already merged/applied migration; add a new migration. Pair forward changes with a valid `down()` unless an explicitly approved irreversible migration is documented.

Runtime connection settings live in server-side environment/secret configuration, not committed files. Typical keys include `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD`.

## Roles And Access

Current organisation hierarchy is Administrator at the organisation root, then Business Unit, then Team. The System Administrator sits above the organisation root for platform/integration configuration rather than ordinary business-record ownership.

Current role baseline:

| Role | Record scope |
| --- | --- |
| System Administrator | Platform/integration configuration plus approved records |
| Administrator | Organisation-level records and administration |
| Collaborator | Own records plus records within assigned Business Unit |
| Contributor | Own/directly assigned records |
| Viewer | Read-only |

The design-system template remains authoritative for detailed role/gate rules and tier codes.

## User Interface

`doc/design-system/ui-and-ux-layout-template-shared.md` is the single authority for the application shell, page archetypes, layout, theme, tokens, brand assets and interaction contracts.

Where `doc/04-UI-Specification.md` or `doc/mockups/` conflict with the design-system template, the template wins until those older artifacts are reconciled.

## Data Protection And Data Sovereignty

Every phase must follow `doc/reference/DATA_PROTECTION_SOVEREIGNTY_STANDARD.md`.

Core engineering defaults include:

- collect/store the minimum customer business data needed by the control plane;
- prefer metadata and Fabric resource IDs over duplicating business datasets;
- capture approved storage/processing geographies before production provisioning;
- default cross-geo Fabric/AI processing, storage and conversation-history settings to OFF;
- require a documented exception before a production cross-border/cross-geo configuration is enabled;
- apply least privilege and redact logs/support exports;
- evaluate Private Link/managed private endpoints, public-access blocking, Purview controls and workspace CMK when the customer's policy/classification requires them;
- update code, data, validation, configuration, security and sovereignty context registers together with implementation changes.

The repository currently states seven-year retention for operational data, audit/compliance logs and backups. Treat that as the current project policy baseline, configurable by data class/customer/legal requirement rather than a hard-coded universal rule.

No legal privacy regime has yet been formally determined for the product in the repository. The code must still implement privacy-by-design and sovereignty controls. Legal applicability, including Singapore PDPA or other regional/customer obligations, must be confirmed before production acceptance rather than inferred by developers.

## AI And Conversational AI

Before AI implementation, read `doc/reference/AI_CONVERSATIONAL_TECHNOLOGY_GUIDE.md` and create `doc/execution/AI-TECHNOLOGY-DECISION.md`.

Technology selection must compare the actual use case, Microsoft-first options and meaningful open-source alternatives. The primary application remains Laravel/PHP/React. A .NET/Python agent framework, model server or other sidecar runtime may be selected only through a separately approved architecture decision.

Deterministic Fabric provisioning must remain validated workflow/API code, not direct LLM execution.

## Existing Open Items Carried Forward

- Scaffold Laravel 13 / React 19 so Composer/npm validation can run.
- Confirm whether the cPanel database service is MySQL or MariaDB and verify host/port.
- Confirm the cPanel PHP selector/runtime supports PHP 8.5.
- Protect the GitHub deploy environment appropriately for the live deployment and resolve the current `development` naming mismatch.
- Pin the SSH host key instead of trusting it live on each run.
- Add a safe guard/sentinel/dry-run or release strategy around `rsync --delete`.
- Provision queue-worker/scheduler support before implementing workflows that rely on them.
- Decide and approve post-deploy migration/cache behaviour.
- Record `BRAND_ASSETS_PATH` without moving/modifying brand assets prematurely.
- Complete the design-system App Definition values, including browser title, tagline, navigation tree, entity list and how React is served from Laravel.
- Reconcile older UI spec/mockups against the design-system template.
- Define list-behaviour naming and step-by-step form draft-storage behaviour.
- Define RPO, RTO, restore cadence and failover procedures.
- Confirm applicable privacy/legal regimes and customer-specific retention/sovereignty requirements before production acceptance.

## Start Here In Claude Code Desktop

Open the local clone of this repository, not a separate documentation folder. Then ask Claude Code to read `CLAUDE.md`, `README.md`, `IMPLEMENTATION_STATUS.md`, `doc/MASTER_IMPLEMENTATION_PLAN.md`, the current Phase 00 document, the data-protection standard and the existing repository. It should create the Phase 00 plan and stop for approval before coding.
