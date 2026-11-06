# Claude Solution Development Kit V1.3/V1.4 -> V1.4.1 Upgrade Prompt

Copy the prompt in the block below into Claude Code Desktop from the existing project repository. In a gateway project, the kit files arrive with a newer copy of the gateway's `.claude/`, `doc/`, and `scripts/` folders, and the prompt checks that copy against the project's history.

```text
Upgrade this repository to Claude Solution Development Kit V1.4.1 as a non-destructive governance and tooling upgrade. Do not begin application feature coding, database DDL or migration, deployment, or UAT defect implementation as part of this upgrade.

First read the current root `CLAUDE.md` and obey its startup and progressive-loading rules. Then read the existing kit migration and upgrade files if present.

## Gate 0 - Pre-Upgrade Safety
Before changing files:
1. confirm this is a safe checkpoint: no active coding change, schema migration, deployment or hotfix, or UAT defect fix is mid-flight, unless an explicit authorised exception is already recorded;
2. review `integration/PRE_UPGRADE_CHECKLIST.md` (or apply its rules if this file is not installed yet);
3. inventory and report the current root `CLAUDE.md`, `.claude/`, active `/doc/solutiondocument/`, phase outputs, tasktracking, published Task IDs, task event and timestamp history, UAT and acceptance evidence, approved database proposals and schema verification records, `/doc/corerepo/`, the current Task ID and the current gate/status;
4. verify that a Git rollback checkpoint and a working upgrade branch exist, the branch being a `chore/` or `feature/` branch off `DEV` per the gateway's Git branching rules, or stop and ask the developer to create them.

Do not continue if you cannot prove what must be preserved.

## Merge Rules
Upgrade by merge, never by wholesale replacement.
- Preserve the current root `CLAUDE.md` and the current organisation and project `.claude` standards.
- Add or update only kit-owned agents, skills, rule text, templates, integration files and developer guides.
- Keep the gateway's resolutions where a standalone kit file differs: no `Rejected` status (a user rejection goes to `Rework Required`, logged as `USER_TESTING_REJECTED`; a dropped task goes to `Cancelled`), the `SCHEMA VERIFIED` path in `.claude/rules/database-schema.md`, and planning approvals read from `.claude/PROJECT-CONTEXT.md`.
- Preserve all active solution documents.
- Preserve accepted phase and task outputs unless a drift check requires a human-approved refresh.
- Preserve every published Task ID. Never renumber historical IDs merely to fit the maximum-five-phase model; map legacy IDs to the current macro phase when necessary.
- Preserve `task-events.jsonl`, timestamps, UAT approvals, acceptance records and completion history exactly.
- Preserve approved logical and physical database proposals and schema verification evidence.
- Preserve `/doc/corerepo/`; update only the affected current-state sections incrementally.
- Never silently remove, merge away, defer or reclassify an existing requirement or feature. Run a before-and-after coverage comparison.

## V1.4.1 Controls That Must Exist After The Merge
- at most five macro phases for new or rebased planning;
- explicit Frontend, Backend, Database, Integration/API, AI, Security, Tests/Verification, Acceptance/UAT, Indexing and Idempotency workstreams;
- complete feature and requirement coverage with conflict and gap blocking;
- infrastructure readiness and the master database and schema baseline;
- `/doc/corerepo/` as persistent, vendor-neutral project knowledge;
- progressive-loading, low-credit behaviour;
- controlled single-task execution with Plan/Design/Evaluate, logical data approval, schema reuse and physical proposal, migration and schema verification, explicit coding authorisation, implementation, UAT and Accepted;
- the safe-upgrade checklists and versioning policy.

## Drift Handling
Compare the current accepted plan and tasks with the live V1.4.1 rules.
- If there is no material impact, preserve the current plan and tasks and continue from the same current Task ID and state once the upgrade is approved.
- If there is material impact to architecture, security, data, UI, AI, API, deployment or acceptance, generate a proposed refresh or mapping only. Do not replace the accepted plan or renumber tasks until a human explicitly approves the refresh.

## Post-Upgrade Validation
Run `integration/POST_UPGRADE_VALIDATION.md` and produce a concise validation report proving:
1. no requirement or feature was lost;
2. no Task ID, history, timestamp or UAT acceptance was lost or rewritten;
3. current standards were preserved and not weakened;
4. no unintended application code, schema or deployment change occurred;
5. approved database and schema verification evidence remains intact;
6. the current task can resume from the same gate and state;
7. `/generate-phase-plan`, `/generate-project-tasks`, and `/execute-approved-task <TASK-ID>` still follow the controlled sequence.

If any validation fails, report `FAIL - DO NOT MERGE`, stop, and reconcile from the pre-upgrade checkpoint. If all pass, report `PASS - SAFE TO MERGE` and wait for human approval before merging the upgrade branch.
```
