# Deployment Check

Use before delivering deployment-related work or instructions.

Input: `[DEPLOYMENT_TASK]`

Process:

1. Read `.claude/PROJECT-CONTEXT.md`, `.claude/rules/deployment.md`, `.claude/rules/enterprise-governance.md`, and `.claude/rules/secret-handling.md`.
2. Confirm source control provider, CI/CD pipeline, hosting target, and deployment method before giving provider-specific steps.
3. Confirm stack, runtime versions, build system, artifact/output path, startup command or entry point, and required environment placeholders.
4. Confirm database/MCP, migrations, data safety, rollback, logs, monitoring, and post-deploy validation.
5. Do not deploy, upload, publish, migrate, change hosting settings, or alter production environment values without explicit approval.
6. Provide manual/provider steps with placeholders only after the provider and deployment path are confirmed.
7. When the change requires them, surface the manual server-side or control-panel steps the developer must perform (environment variables, cron/scheduled jobs, SSL/TLS, DNS, database creation, file permissions, extensions, workers, restart), provider-neutral until the provider is confirmed and with placeholders only. If none are needed, say so.

Return:

- Deployment readiness status
- Source control and pipeline status
- Hosting target and method
- Required developer-provided values
- Files/layout/runtime impact
- Commands to run, if approved
- Provider/manual steps, if approved
- Manual server-side/control-panel steps for the developer, if any (or none needed)
- Rollback or mitigation steps
