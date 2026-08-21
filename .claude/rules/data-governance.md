# Data Governance Rules

Claude must govern data across its lifecycle whenever a change stores, retains, backs up, or recovers data.

Conditional: applies only when the project stores data beyond transient request state. It does not override `.claude/rules/secret-handling.md`, `.claude/rules/schema-mcp.md`, `.claude/rules/production-readiness.md`, or the DDL restrictions.

## Assume No Storage Model

- Do not assume the project persists data. Confirm from `.claude/PROJECT-CONTEXT.md` or ask before applying these bars.
- Do not assume or mandate any sensitivity scheme, classification labels, retention periods, recovery objectives, privacy regime, backup window, or storage engine. Those are confirmed facts; ask when unknown. Treat each as unknown until the developer confirms it.

## Classification Drives Handling

- Classify every persisted data element into the confirmed sensitivity scheme, and let encryption, access control, logging, and retention be driven by class rather than applied uniformly.
- Record each table's and field's classification alongside its table-dictionary metadata, per `.claude/rules/knowledge-base.md`. Metadata only; never store row data or production values.
- Apply least-privilege access per class, consistent with `.claude/rules/enterprise-governance.md`.

## Retention And Purge

- Give each class a confirmed retention period; do not act on an unconfirmed one.
- Purge or anonymize expired data on a defined schedule once retention elapses.
- Audit and compliance logs may carry a distinct, often longer retention. Keep it separate from the operational data class and record it as a confirmed fact.
- History, event, attempt, and snapshot tables carry their own confirmed retention. They are usually the largest and longest-lived tables a feature has, and purging them changes numbers that have already been reported, so confirm the period rather than assuming either that they are kept forever or that they may be pruned. See `.claude/rules/semantic-data-model.md`.

## Privacy Regime

When personal data falls under a confirmed applicable privacy regime recorded in `.claude/PROJECT-CONTEXT.md`:

- Collect only the data the stated purpose needs; do not retain fields beyond that purpose.
- Provide a data-subject access and correction path.
- Enforce the confirmed retention limits for personal data.
- Define a breach-notification path (who is notified, on what trigger).

The applicable regime, the personal-data fields it covers, and its obligations are confirmed facts. Do not assume a regime applies or infer its rules.

## Backup And Recovery

- Maintain backups for stateful data with a confirmed retention window.
- Periodically test restores. An untested backup is not a backup; restore verification is part of the definition of done for backup work.
- Define recovery-point and recovery-time objectives per environment as confirmed facts.
- Document the failover procedure, who may declare a disaster, and the disaster-communications path.

## No Production Data Outside Production

- Never place real production or personal data in non-production environments, tests, fixtures, prompts, or logs.
- Use synthetic or anonymized data for non-production needs, with placeholders per `.claude/rules/secret-handling.md`.

## Final Reporting

For data-governance work, report: which data elements were classified and where; the retention periods, purge/anonymization schedule, and any distinct audit-log retention applied; the privacy-regime obligations addressed, if a confirmed regime applies; backup retention, restore-test status, recovery objectives, and failover/disaster decisions documented; confirmation that no real production or personal data was placed in non-production, tests, prompts, or logs.

Final rule: if the storage model, sensitivity scheme, classification labels, retention periods, applicable privacy regime, backup window, or recovery objectives are unclear, do not guess. Ask and record the confirmed facts first.
