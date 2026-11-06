# Claude Solution Development Kit V1.4.1 - Final Release Manifest

Note: this kit is part of the gateway. Its rule, `.claude/rules/solution-development-kit.md`, is the authority for requirement intake, planning, task tracking, and per-task execution. In this gateway, the root `CLAUDE.md`, `.claude/`, `doc/`, and `scripts/` ship together, so downstream projects receive every runtime file below with the gateway and there is nothing to merge by hand. The paths below are as they sit in this repository.

This release contains the governance package for solution planning, task tracking, controlled task execution, and persistent project knowledge.

## Requirement Source
`/doc/solutiondocument/` contains active solution requirements. Current recommended categories are provided, but agents discover the live structure dynamically. `Archive/` is ignored by default.

## Standards Source
The repository's current root `CLAUDE.md` and task-gated `.claude/` configuration remain authoritative for how work is planned and delivered. The kit rule owns its own domain and weakens none of the other rules.

## Core Kit
- `.claude/rules/solution-development-kit.md`
- `.claude/agents/`: `standards-reader`, `solution-reader`, `requirement-analyst`, `gap-auditor`, `solution-architect`, `dependency-planner`, `phase-planner`, `qa-reviewer`, `phase-orchestrator`, `task-planner`, `progress-tracker`, `acceptance-gate`, `task-execution-orchestrator`, `data-design-reviewer`, `infra-readiness-agent`, `core-repo-maintainer`
- `.claude/skills/generate-phase-plan/`, `.claude/skills/generate-project-tasks/`, `.claude/skills/execute-approved-task/`
- `doc/solutiondocument/`
- `doc/phasedocument/`
- `doc/tasktracking/`
- `doc/corerepo/`
- `scripts/task-event.sh`

The kit version is V1.4.1, recorded in this manifest and in the kit README. The gateway's own version is in `.claude/CHANGELOG.md`.

## Developer Guides
- `00-read-first/04-solution-development-kit/README.md`

## Upgrade And Integration Safety
All in `00-read-first/04-solution-development-kit/integration/`:
- `README.md`
- `PRE_UPGRADE_CHECKLIST.md`
- `V1_4_1_MIGRATION.md`
- `V1_4_MIGRATION.md`
- `V1_3_MIGRATION.md`
- `V1_1_MIGRATION.md`
- `V1_3_TO_V1_4_1_UPGRADE_PROMPT.md`
- `V1_3_TO_V1_4_UPGRADE_PROMPT.md`
- `POST_UPGRADE_VALIDATION.md`
- `VERSIONING_POLICY.md`
- `OPTIONAL_CLAUDE_MD_NOTE.md`

## Core Commands
1. **`/generate-phase-plan`**
2. Human approval of `02_Gap_Conflict_Report.md` and `MASTER_PHASE_PLAN.md`, recorded in `.claude/PROJECT-CONTEXT.md`
3. **`/generate-project-tasks`**
4. **`/execute-approved-task <TASK-ID>`**

## Controlled Execution Sequence
`Ready -> Plan/Design/Evaluate -> Human Approval -> [Logical Data -> Approval -> Physical Table Proposal -> Approval -> Migration -> Schema Self-Check] -> Explicit Coding Authorisation -> Code/Test/Review -> Implementation Complete -> User Testing -> Accepted or Rework Required`

The bracketed data/schema sequence applies only when the task creates, changes, maps to, or depends on persisted structures, and it ends in `SCHEMA VERIFIED`, per `.claude/rules/database-schema.md`.

## Release Safety
This is a full kit. An existing project takes it non-destructively. Historical Task IDs, lifecycle events, timestamps, UAT and acceptance evidence, active requirements, approved database and schema verification evidence, current project standards, and core-repo history are all preserved.
