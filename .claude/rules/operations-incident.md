# Operations And Incident Response Rules

Claude must keep a production service observable, alertable, and recoverable, and follow a disciplined incident-response and rollback path whenever a change touches a running service.

Conditional: applies only when the project runs a production service. It does not override `.claude/rules/secret-handling.md`, `.claude/rules/schema-mcp.md`, `.claude/rules/production-readiness.md`, or the DDL restrictions.

## Assume No Operational Defaults

- Do not assume the project runs a production service. Confirm from `.claude/PROJECT-CONTEXT.md` or ask.
- Do not assume or mandate any service-level objective, alert threshold, severity-response time, on-call rota, escalation path, or named failure mode. Those are confirmed facts; ask when unknown.
- Reuse the confirmed metrics destination, alerting tool, paging tool, and incident tracker. Do not introduce a new observability, alerting, or paging stack without approval.

## Service-Level Objectives And Alerting

- Define a service-level objective per critical service (for example an availability target and a latency percentile) and alert on its error-budget burn so the alert fires before users notice degradation.
- Alert on conditions that precede user-visible failure: error-rate spikes, latency-percentile breaches, dead-letter growth (cross-ref `.claude/rules/resilience.md`), and cost-budget breaches.
- Track at least the golden signals (request rate, error rate, latency percentiles) at the confirmed metrics destination. Instrumentation authoring lives in `.claude/rules/production-readiness.md` Observability Authoring; honor it rather than restating it here.
- Record confirmed objective targets, alert thresholds, and budget limits in `.claude/PROJECT-CONTEXT.md`. Never bake in a specific target or threshold.

## Incident Severity And Escalation

- Adopt a severity taxonomy distinguishing at least: production-down or data-at-risk; major degradation; and minor, single-feature impact.
- Attach a response-time expectation and an escalation/on-call path to each severity (who is engaged, when it is raised). Exact times and rota are confirmed facts; ask when unknown.

Treat anything that risks data loss or corruption at the highest severity even when the service still appears available. Availability and data integrity are separate failure dimensions.

## Runbooks

- Author a runbook per top failure mode, covering the detection signal, immediate mitigation, diagnosis steps, rollback path, and communications expectation.
- Tie runbooks to confirmed, named failure modes rather than hypothetical ones. Confirm and record those failure modes.

## Rollback Classes

Treat rollback as distinct classes; the right one depends on what changed.

| Rollback class | What it reverts | How it is performed |
| --- | --- | --- |
| Application rollback | A code/runtime regression | Redeploy the prior known-good revision |
| Schema rollback | A schema change | Apply the paired down-migration; see `.claude/rules/schema-mcp.md` |
| Behavior / flag rollback | A behavior change behind a flag or release | Flip the flag off or revert to the prior released version |

- Choose the class that matches the change. A code redeploy does not undo a schema change, and flipping a flag does not undo a deployed revision.
- Confirm the actual rollback mechanism and known-good reference for each class with the developer; do not assume a deployment or migration model (see `.claude/rules/deployment.md`).

## Postmortem

- After a high-severity incident, run a blameless postmortem capturing the timeline, root cause, and what to change.
- Convert each action item into a tracked ticket in the confirmed tracker. Do not close an incident with untracked action items.

## Final Reporting

For operations/incident work, report: which service-level objectives, alerts, and golden signals were defined or changed, and where their targets came from; the severity taxonomy, response-time expectations, and escalation path touched; which runbooks were authored/updated and the failure modes they cover; the rollback class(es) prepared and the known-good reference for each; for any incident, the postmortem status and tracked action-item tickets.

Final rule: if a service-level objective, alert threshold, severity-response time, on-call rota, named failure mode, or rollback mechanism is unclear, do not guess. Ask and record the confirmed values first.
