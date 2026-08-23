# Claude Code Notes - SemantIQ Repository Execution Protocol

Project-specific guidance for this repository. Read it before making changes.

## What This Is

SemantIQ is a Business Decision Intelligence application with a privileged control plane that automates Microsoft Fabric and related Microsoft data/AI services. The confirmed repository baseline is:

- Backend: Laravel 13 on PHP 8.5
- Frontend: React 19
- Database: MySQL hosted through cPanel
- Build toolchain: Composer plus Node.js 24/npm for frontend assets
- Architecture: modular monolith
- Deployment: GitHub Actions builds and deploys to cPanel over SSH

See `README.md` for the current repository/deployment picture and `doc/` for the design and specification set.

Application code has not landed yet at the time of this baseline. There is no `composer.json` or `package.json`, so the current build workflow cannot pass until the Laravel/React application is scaffolded. Re-verify this statement before relying on it.

## Current Deployment Mode And Product Target

The current application deployment baseline is one SemantIQ application instance for one customer organisation / Microsoft Entra tenant.

The product architecture must remain multi-tenant-ready because SemantIQ is intended to be productised for customers that bring their own Fabric environment. Do not implement cross-customer SaaS tenancy, shared-customer data access, or multi-tenant Entra sign-in unless a later approved requirement explicitly enables it.

For customer-owned configuration, metadata, Fabric resource IDs, audit data and policy records, preserve an explicit organisation/tenant context so a future multi-tenant service can isolate customers without a redesign. Cross-organisation access is denied by default.

## Mandatory Reading Order Before A Material Change

1. `CLAUDE.md`
2. `README.md`
3. `IMPLEMENTATION_STATUS.md`
4. `doc/MASTER_IMPLEMENTATION_PLAN.md`
5. The one current phase file under `doc/phases/`
6. `doc/MENU_STRUCTURE.md` and `doc/ROLE_MODEL.md` for navigation, business experience and authorization
7. `doc/reference/DATA_PROTECTION_SOVEREIGNTY_STANDARD.md`
8. `doc/context/CONTEXT_INDEX.md` and the context registers relevant to the change
9. Relevant entries in `doc/reference/SEMANTIQ_SRS_BASELINE.md`, `REQUIREMENT_TRACEABILITY.md`, `API_REGISTER.md`, and `HELP_TOPIC_INDEX.md`
10. For UI work, `doc/design-system/ui-and-ux-layout-template-shared.md`
11. For AI/conversational work, `doc/reference/AI_CONVERSATIONAL_TECHNOLOGY_GUIDE.md`

If a referenced file does not exist, say so. Do not invent its contents.

## Source-Of-Truth And Conflict Rule

Use this precedence when documents disagree:

1. Explicit user approval or an approved decision record for the current change.
2. `CLAUDE.md` for repository execution, safety and phase-gate rules.
3. `doc/design-system/ui-and-ux-layout-template-shared.md` for UI layout, structure, theme and brand rules.
4. `IMPLEMENTATION_STATUS.md` plus the active phase/master plan for implementation sequence.
5. The formal SRS/reference documents for functional requirements.
6. Existing code, migrations and tests for the implemented state, after verifying they are not stale or incorrect.

If a material conflict remains, stop the affected implementation, record it under `doc/execution/decisions/`, explain it to the user and obtain approval. Never silently choose a side.

## Ask, Do Not Assume

Confirm before acting when hosting, schema shape, external permissions, validation commands or intent is unclear. Verify repository claims by reading files, running commands or checking the branch. Label unverified claims as unverified.

The technology stack above is approved as the current application baseline, but still inspect the repository before coding because scaffolding may change. Do not replace Laravel, React, MySQL, cPanel deployment or the modular-monolith architecture without explicit approval.

## Hard Phase Gate

Only one phase can be active. Passing tests does not unlock the next phase.

For the current phase:

1. Inspect the actual repository.
2. Create or update `doc/execution/PHASE-XX-PLAN.md`.
3. Present the plan and wait for user approval before material implementation.
4. Implement only approved current-phase scope.
5. Run available automated and manual validation.
6. Create or update `doc/execution/PHASE-XX-VERIFICATION.md` with real evidence.
7. Ask for the exact completion phrase in the phase document.
8. Only after the user supplies that phrase may `IMPLEMENTATION_STATUS.md` be updated and the next phase unlocked.

Never fabricate or infer approval.

## Plan-Before-Code Requirements

Each phase plan must include:

- verified current repository and stack observations;
- requirement, screen, API, help-topic and acceptance IDs in scope;
- files/components to change;
- database/migration changes;
- external APIs, identity, permissions and tenant settings;
- data classification, retention, storage geography and processing geography;
- data-protection and sovereignty implications;
- security controls and threat/abuse considerations where applicable;
- affected code/data/validation/configuration context-register entries;
- tests and verification strategy;
- rollback/recovery approach;
- assumptions, blockers and user decisions required.

## Never Commit A Secret

Never put a token, key, password, private key, client-secret value, bearer token, production connection string, `.env` value, production row data or customer data extract into a committed file, log, screenshot or chat summary.

Use placeholders such as `<DATABASE_NAME>`, `<TOKEN>` and `<APP_BASE_URL>` in documentation.

Runtime application/database credentials belong in the server `.env` or an approved secret manager. Deployment credentials that the GitHub Actions workflow actually requires belong in GitHub Environment/Actions secrets, not in committed files. Do not place database credentials in GitHub secrets unless a specific approved workflow genuinely needs them.

If a real credential is discovered in source control, remove/replace it with a placeholder, report it and state that the credential must be rotated.

Browser code must never receive a Fabric automation client secret, certificate private key or backend bearer token.

## Branching And Deployment

`main` is the only long-lived branch and the production deploy trigger. There is no permanent DEV/QA/STAG/PROD Git branch chain.

Use short-lived work/phase branches for pull requests when changes are being developed or reviewed. Merge into `main` only after review and explicit approval because a push/merge to `main` triggers the live deployment workflow.

Do not run an actual deployment, production database migration, destructive Fabric operation or production configuration change without explicit user approval for that action.

Routine Git/GitHub commands on non-deploying work branches need no per-action confirmation, but before any Git/GitHub write or remote action state the source branch, target branch, files and command/action. Any action that merges/pushes to `main` or otherwise deploys production still requires explicit approval. Follow the global commit-identity policy resolved through:

`git config --global --get ai-commit-identity.policy-path`

Never attribute commits to AI/bots/noreply identities and remove `Co-authored-by:` trailers.

The current workflow is reported as bound to a GitHub environment named `development` even though `main` deploys the live site. Treat this as a naming/control mismatch to be corrected only through an approved deployment change. Do not rename the environment or move secrets silently.

## Schema And Persistence

Laravel migrations under `database/migrations/` are the source of truth once application code exists.

- Pair every forward migration with a working `down()` unless an approved irreversible migration is explicitly documented.
- Never edit an already merged/applied migration; add a new migration.
- Do not invent table/column names when migrations already exist.
- Use one clear meaning per row, atomic values rather than stored totals, codified reference lists rather than uncontrolled free text, and state-change/event rows when history matters.
- Customer-owned configuration/data records that require isolation must carry organisation/tenant scope where appropriate.
- Retention must be policy/configuration driven, not buried as magic constants in code.

## Product Experience Boundary

SemantIQ has two explicit experience layers:

1. Business Decision Intelligence Experience - the default experience for business users.
2. Platform Control Plane - privileged administration used to automate Fabric, data engineering, governance, AI and operations.

Business users must not be forced to understand Microsoft Fabric. Use business language such as Sales Intelligence, Finance Intelligence, People Intelligence, Risks, Opportunities, Forecast and Ask SemantIQ. Do not surface capacities, Lakehouses, Dataflows, semantic models, tenant settings, Fabric Data Agents or similar implementation concepts in normal business navigation unless the requirement genuinely calls for a technical/admin view.

Administrators should remain in SemantIQ for setup whenever a supported API can perform the operation safely. If Microsoft requires a portal-only/manual action, build a guided Help workflow with prerequisites, exact steps, permissions, tenant/environment scope, security/sovereignty impact and a SemantIQ validation step afterward.

`doc/MENU_STRUCTURE.md` is the functional navigation authority. `doc/ROLE_MODEL.md` is the role/domain authorization authority. The design-system template remains the visual/layout authority.

Role-aware business data access is the intersection of platform role, organisation/tenant context, business-domain entitlement, data scope, object/field security and data-protection policy. Hiding a menu item is never sufficient authorization. Backend policies must enforce every protected action.

System/platform administrators do not automatically receive unrestricted Sales, Finance, People or other sensitive business data. Business-domain access must be separately entitled.

## User Interface

`doc/design-system/ui-and-ux-layout-template-shared.md` is the single authority for layout, structure and theme.

Read it before generating or changing a screen, component, layout or stylesheet. Do not introduce a second design system, theme, component-library skin or ad hoc visual language.

Do not invent token values, colours, fonts, spacing steps, shell dimensions or icon styles. Never modify, recolour, regenerate or substitute the logo/favicon/brand assets in `doc/design-system/assets/`.

Never ask about or offer alternative themes, colours, fonts, logos or favicons. Those are settled by the design-system authority. Only app-specific values requested by its App Definition may be raised. `BRAND_ASSETS_PATH` remains a developer/user decision and must not be chosen silently.

Where `doc/04-UI-Specification.md` or mockups under `doc/mockups/` disagree with the design-system template, the template wins.

Follow its ENFORCED versus PRINCIPLED distinction. ENFORCED rules are not deviable. A PRINCIPLED deviation requires the standard pattern, proposed deviation, rationale, domain context and trade-offs to be documented and approved before implementation. Record the approved exception; never apply it silently.

For new UI generation follow the template order: navigation/access configuration, role/policy layer, shell, tokens/themes, then each page from the matching archetype. Cover success, empty, loading, validation, permission-denied, error and small-screen states, not only the happy path.

## Code

Match the repository's conventions, formatting and naming. Keep changes small and tied to approved phase scope.

Do not add another runtime, framework, queue, cache, database, external service or major dependency without approval. This also applies to AI sidecar services written in .NET, Python or Node.js: they are not part of the confirmed Laravel/PHP/React runtime unless separately approved.

Use PHPDoc and TSDoc for declarations where useful, explaining intent, invariants, external dependencies and security/sovereignty assumptions rather than restating syntax.

Validate external input at boundaries. Use Laravel/framework safe APIs and parameterised database access. Give outbound calls bounded timeouts, retry only where safe, and preserve correlation/request IDs. Handle empty/loading/error states for data-driven views.

## Microsoft Integration Freshness Rule

The API register is a baseline, not permission to assume an endpoint still behaves the same. Before implementing a Microsoft integration, verify current Microsoft documentation for:

- endpoint/API version;
- supported identities;
- scopes/roles and tenant settings;
- stable versus preview status;
- long-running-operation and retry behaviour;
- region/capacity prerequisites and published limits.

If current Microsoft documentation materially differs from the baseline, stop the affected task, create a decision record and obtain user approval before changing the implementation approach.

## AI And Conversational Technology Rule

For AI, LLM, RAG, Fabric Data Agent, conversational UI, Copilot Studio, Microsoft Foundry, agent orchestration, MCP, model hosting, multi-agent or autonomous-action work:

- read `doc/reference/AI_CONVERSATIONAL_TECHNOLOGY_GUIDE.md`;
- prefer Fabric Data Agent for governed structured Fabric analytics when it meets the use case;
- evaluate Copilot Studio for Microsoft 365/Teams/low-code channels;
- compare a meaningful Microsoft-first option with an open-source alternative when appropriate;
- respect the confirmed primary application stack: Laravel/PHP/React;
- if the best agent framework requires .NET/Python or a separate model server, treat it as a separately deployable sidecar/service and obtain explicit architecture approval before adding it;
- create/update `doc/execution/AI-TECHNOLOGY-DECISION.md` and get explicit user approval before material AI implementation;
- keep model/runtime/retrieval/channel dependencies behind replaceable adapters.

Never use an LLM as the direct execution engine for deterministic Fabric provisioning/configuration. AI may recommend or draft; validated SemantIQ workflows and approved APIs execute changes.

## Data Protection, Sovereignty And Context Preservation

Before planning or changing code that touches customer data, Fabric, identity, AI, logging, configuration or external services:

- read `doc/reference/DATA_PROTECTION_SOVEREIGNTY_STANDARD.md`;
- determine data classification, owner, retention, access policy, storage geography and processing geography;
- default cross-geo processing/storage and cross-geo AI/conversation-history settings to OFF;
- do not activate production resources outside an approved geography without an explicit documented exception;
- evaluate Private Link/managed private endpoints, public-access blocking, Purview classification/labels/DLP and workspace CMK when required by customer policy or data classification;
- minimise data stored in the SemantIQ control plane and prefer resource IDs/metadata over business payload copies;
- redact observability by default and never log secrets or unrestricted customer payloads;
- update the context registers in the same change when code, data, validation, configuration or sovereignty behaviour changes.

The current repository records a seven-year retention policy for operational data, audit/compliance logs and backups. Treat seven years as the current project policy baseline, not a universal legal rule and not a hard-coded constant. Confirm customer/regulatory requirements and allow policy-driven overrides where approved.

No legal privacy regime has yet been formally determined in the repository. Engineering must still apply privacy-by-design, least privilege and sovereignty controls. Legal applicability, including Singapore PDPA or other customer/regional regimes, must be confirmed before production acceptance rather than assumed by code.

## Required Context Registers

Keep these current:

- `doc/context/CODE_CONTEXT_REGISTER.md`
- `doc/context/DATA_CONTEXT_REGISTER.md`
- `doc/context/VALIDATION_RULES_REGISTER.md`
- `doc/context/CONFIGURATION_REGISTER.md`
- `doc/context/DATA_SOVEREIGNTY_REGISTER.md`
- `doc/context/SECURITY_PRIVACY_DECISIONS.md`

Document why a component exists, what data it touches, validation/config dependencies, permissions, errors, tests and sovereignty impact. Missing/stale context for a behaviour-changing change is a verification failure.

## Done Means Verified

Do not claim complete without running the available validation and reporting the real result. If validation cannot run, say why and give the exact follow-up command.

A phase verification report must include:

- automated tests/results;
- manual workflow checks;
- redacted request/correlation evidence where applicable;
- migration/config validation;
- security/privacy/sovereignty checks;
- confirmation context registers match implementation;
- known issues/deferred items;
- rollback/recovery notes;
- explicit pass/fail against exit criteria.

Never report tests passed unless they were run.

## Writing Style

Use plain ASCII in committed Markdown/text, code comments, commit messages and pull-request text. No em dash, en dash, curly quotes or Unicode ellipsis. Use plain hyphens/quotes and ASCII punctuation.

## UI/UX Workflow

For all UI/UX, frontend layout, styling, component, and design work:

1. Read `.claude/reference-template/` first.
2. Follow the design system, patterns, components, spacing, typography, and conventions defined there.
3. Reuse existing patterns before introducing new ones.
4. Keep implementation consistent with the reference templates.
5. Treat `.claude/reference-template/` as the source of truth for UI/UX decisions.

