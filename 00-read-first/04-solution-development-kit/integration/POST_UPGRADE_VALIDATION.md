# Post-Upgrade Validation

Run after applying a kit upgrade and before merging the upgrade branch.

## A. Standards Integrity
- [ ] Root `CLAUDE.md` is preserved and remains authoritative.
- [ ] Existing organisation and project `.claude/` rules were merged, not wholesale replaced.
- [ ] No existing hard stop, security rule, schema rule or approval gate was weakened.

## B. Requirement And Feature Integrity
- [ ] All active source documents remain present.
- [ ] A feature and function coverage comparison shows no silent loss.
- [ ] Every prior requirement disposition still exists or has an explicitly approved update.
- [ ] Unresolved conflicts and gaps remain visible and blocking where applicable.

## C. Task And History Integrity
- [ ] Published Task IDs are unchanged.
- [ ] Task and subtask relationships are preserved.
- [ ] `task-events.jsonl` history is preserved.
- [ ] Historical timestamps are unchanged.
- [ ] UAT approvals and `Accepted` records are preserved.
- [ ] The current task resumes at the same state and gate.

## D. Database And Schema Integrity
- [ ] The upgrade itself executed no DDL or migration.
- [ ] Approved physical proposals and schema verification evidence remain present.
- [ ] Database model and current-state documentation was not rewritten from memory.
- [ ] Any refreshed database facts came from live authoritative schema evidence.

## E. Application Integrity
- [ ] No application feature code changed as part of the kit upgrade unless separately and explicitly approved.
- [ ] No deployment occurred as part of the upgrade.
- [ ] The existing build and test state was not falsely rewritten.

## F. Kit Workflow Validation
- [ ] `/generate-phase-plan` resolves and still stops for planning approval.
- [ ] `/generate-project-tasks` preserves stable IDs and history.
- [ ] `/execute-approved-task <TASK-ID>` still follows Plan/Design/Evaluate -> approvals -> data/schema gates -> explicit coding authorisation -> implementation -> UAT.
- [ ] The maximum of five macro phases is enforced for new or rebased planning, while historical phase and task IDs are preserved.
- [ ] The gateway's resolutions survived the upgrade: no `Rejected` status (a user rejection goes to `Rework Required`), the `SCHEMA VERIFIED` path in `.claude/rules/database-schema.md`, and planning approvals read from `.claude/PROJECT-CONTEXT.md`.
- [ ] `/doc/corerepo/` remains compact, vendor-neutral and current.

## Result
Record one outcome:
- `PASS - SAFE TO MERGE`
- `FAIL - DO NOT MERGE`

On `FAIL - DO NOT MERGE`, document the mismatch, restore or reconcile from the pre-upgrade checkpoint, and repeat the validation.
