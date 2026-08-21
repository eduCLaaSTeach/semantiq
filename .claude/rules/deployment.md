# Deployment Rules

Claude must treat deployment as unknown until the developer confirms the hosting target and release process.

## Deployment Neutrality

Do not assume any source control provider, hosting provider, deployment target, pipeline, local workflow, artifact model, infrastructure model, runtime, or operational service. Do not assume any language, framework, package manager, frontend/backend stack, database, queue, worker, scheduler, storage, mail, auth provider, build tool, container model, or cloud service. Ask and verify from the developer and project files.

## Required Intake

Before deployment-related edits or instructions, confirm or ask for:

- hosting provider/target and environment names
- source control provider and CI/CD or deployment pipeline; deployment method and who runs it
- application stack, runtime versions, framework, build system, package manager
- artifact/output path, web root, startup command, service entry point, or container image details
- domain/base URL, networking, routing, security certificate, cache/CDN, proxy details when applicable
- environment variables (placeholders only)
- database engine if applicable, Schema MCP `schema` readiness, migrations policy, data safety rules
- storage, messaging, auth/integration, background processing, scheduling, cache, search, other operational requirements
- logs, monitoring, health checks, rollback, post-deployment validation

Treat source control, CI/CD, build, artifact storage, hosting, database, and runtime as separate choices. None implies the others.

## Conditional Provider Rules

Apply provider-, runtime-, framework-, database-, or deployment-specific rules only after the developer confirms those choices. Ask for the exact provider/service/tool names and required release steps. Do not use examples as defaults.

## Manual Server-Side Steps

Some changes work only after the developer performs manual, out-of-band actions Claude cannot perform. Surface them explicitly instead of assuming the environment is prepared.

- When applicable, give an explicit, ordered checklist of manual steps on the server or in the provider's control panel/portal. Common cases: setting env vars or app settings; creating or scheduling a cron/background job; provisioning a domain, DNS records, or an SSL/TLS certificate; creating a database or database user; adjusting file/directory permissions; enabling a runtime extension or module; configuring a worker, queue, cache, or service binding; restarting the service.
- Keep steps provider-neutral until the provider is confirmed. Name a specific provider only after confirmation, per Conditional Provider Rules, and use placeholders such as `<APP_BASE_URL>` and `<DATABASE_NAME>` per `.claude/rules/secret-handling.md`; never put real values in steps.
- Treat any production-impacting manual step as requiring explicit approval; do not perform it yourself.
- If no manual server-side steps are needed, say so.

## When To Ask First

Ask before editing or writing instructions if: the deployment target or method is unknown; the source control provider, CI/CD pipeline, or artifact flow is unknown; the change touches production, hosting settings, environment variables, domains, SSL, DNS, CI/CD, containers, infrastructure, migrations, or persistent data; a provider-specific file would be created or overwritten; or a new runtime, build system, service, queue, cache, worker, cloud resource, or dependency would be introduced.

## CI Quality Gates

When the project defines CI quality gates (linting, tests, build, security scanning), treat them as merge-blocking and deploy-blocking. The gate tools, commands, and pass criteria are confirmed facts; ask when unknown.

- Honor every defined gate. Do not promote, merge, or deploy past a failing or skipped required gate.
- Do not disable, bypass, weaken, or mark a gate optional without explicit approval.
- Run the confirmed gate commands where they can run and report each gate's status with exact results.
- If a gate cannot run here, state why and provide the exact follow-up command or pipeline step rather than treating the work as validated.

A passing gate is a precondition for promotion, not a formality. The promotion path itself is defined in `.claude/rules/git-branching-release.md`; this section governs the gates guarding each promotion.

## Per-Environment Configuration

Configuration differs across environments. Keep it separated per environment, confirm which keys differ, and never leak one environment's configuration or secrets into another. Environment names and the set of varying keys are confirmed facts.

- Confirm which keys differ per environment and which are shared before changing or generating configuration.
- Keep each environment's values isolated; do not copy one environment's endpoints, connection targets, feature flags, or secrets into another.
- Reference configuration through the confirmed mechanism; use placeholders such as `<APP_BASE_URL>` and `<DATABASE_NAME>` per `.claude/rules/secret-handling.md`.
- Treat a value intended for one environment as unsafe to reuse in another until the developer confirms it is genuinely shared.

## Infrastructure As Code

Applies only when the project defines infrastructure as code in the repository. Govern that definition like application code. The infrastructure tool, language, file layout, and environment names are confirmed facts; ask when unknown.

- Review infrastructure changes through the same Pull Request flow as application code, per `.claude/rules/git-branching-release.md`. Do not promote outside the confirmed promotion path.
- Parameterize per-environment differences rather than hardcoding them; keep each environment's values isolated and use placeholders in docs and examples.
- Plan and preview the change before applying, and surface the planned change set for review.
- Applying infrastructure or running a migration is a production-impacting action requiring explicit approval, like a production deploy, per the approval gate in `.claude/rules/enterprise-governance.md` and `.claude/rules/production-readiness.md`. Promoting the definition through Git does not trigger that gate; applying it does.

## Final Reporting

For deployment work, include: files changed; hosting/deployment impact; runtime/build impact; environment placeholders needed; CI quality gate status and exact results, including any blocked or skipped gates; per-environment configuration impact and any keys that differ; infrastructure-as-code impact, the planned/previewed change set, and whether an apply or migration was approved; commands run and validation results; manual deployment or provider steps if approved; manual server-side or control-panel steps the developer must perform (or a note that none are needed); rollback or mitigation notes; security impact and remaining exposure risk.
