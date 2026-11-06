# Claude Solution Development Kit - V1.4.1

Note: this kit is part of the gateway. Its rule, `.claude/rules/solution-development-kit.md`, is the authority for requirement intake, planning, task tracking, and per-task execution, superseding the overlapping parts of `phased-workflow.md`, the sprint checklist, and the EOD task-status parts. Every other gateway rule (database schema, review gates, secure coding, UI, semantic data model) still binds exactly as before. Downstream projects get the kit as part of the gateway's `.claude/`, `doc/`, and `scripts/` folders, copied together with the root `CLAUDE.md`, so there is nothing to install separately. The Safe Installation section below describes Salil's standalone package.

A reusable, technology-neutral governance package for solution planning, task tracking, and controlled task execution in Claude Code Desktop and other AI-assisted development workflows.

## V1.4.1 Principles
- `/doc/solutiondocument/` defines what the approved solution must do.
- The repository's current root `CLAUDE.md` plus the `.claude/` settings it loads through task gates define how development is performed.
- `/doc/corerepo/` keeps compact, vendor-neutral, living project knowledge, so another developer or AI tool can continue without rereading the whole repository.
- Planning uses no more than five macro phases. Detailed work belongs in tasks and subtasks.
- Every feature and function accounts for Frontend, Backend, Database, Integration/API, AI, Security, Tests, UAT, Indexing, and Idempotency, or marks one `Not Applicable - Verified` with a reason.
- No requirement, feature, or function disappears silently. Gaps and conflicts are surfaced and block the affected work until resolved.
- Progressive loading and event-driven documentation keep AI usage low.

The kit never assumes its own copied standards are newer than the repository. It evaluates the current project standard every time planning or task generation runs.

## Safe Installation Into An Existing Project
This applies to Salil's standalone package. Merge it into the project repository. Do **not** overwrite the organisation's existing root `CLAUDE.md` or wholesale-replace its `.claude/` folder.

Add/merge:
- `.claude/rules/solution-development-kit.md`
- kit agents under `.claude/agents/`
- kit skills under `.claude/skills/`
- `doc/corerepo/`
- `doc/solutiondocument/`, `doc/phasedocument/`, and `doc/tasktracking/` templates where missing
- `scripts/`

When upgrading an existing repository, use `integration/PRE_UPGRADE_CHECKLIST.md`, `integration/V1_4_1_MIGRATION.md`, and `integration/POST_UPGRADE_VALIDATION.md`. See `integration/README.md`.

## Requirement Intake
Place approved requirements under `/doc/solutiondocument/`. The recommended current categories are:
- `1_Solution Scope Document-Final/`
- `2_Engagement App Workflow Specs/`
- `3_Engagement App Data Points Specs/`
- `4_Analytics Specifications/`
- `5_Agent Specs/`
- `6a_Engagement App UI Specs/`
- `6b_Engagement App Mockup Screens/`
- `7_Data Modelling Specs/`
- `Archive/`

These names are not hard-coded. Agents recursively discover the actual active structure. `Archive` is ignored by default.

## Stage 1 - Solution Planning
1. Add/update requirements under `/doc/solutiondocument/`.
2. Open the repository in Claude Code Desktop.
3. Invoke `/generate-phase-plan`.
4. Claude loads the current project standards through the root gateway, then reads all active requirement material.
5. Review `/doc/phasedocument/02_Gap_Conflict_Report.md`.
6. Review `/doc/phasedocument/MASTER_PHASE_PLAN.md`.
7. Resolve material gaps/conflicts.
8. Explicitly approve both files.

Planning writes `PROJECT_STANDARDS_BASELINE.md` so the project records which live standards were used. Who approved each planning document and when is recorded in the Solution Planning And Task Tracking section of `.claude/PROJECT-CONTEXT.md`; a status line inside the document is not approval.

## The Five Macro Phases
1. Phase 1 - Platform, Infrastructure & Master Foundation
2. Phase 2 - Core Application, Login, Administration & AI Foundation
3. Phase 3 - Feature / Function Design & Dependency Approval
4. Phase 4 - Feature / Function Build & Technical Verification
5. Phase 5 - UAT, Acceptance & Release Readiness

A small solution may use fewer phases where one is genuinely not applicable, never more than five. Phase IDs are `P01` to `P05`. The five phases replace the earlier per-phase `PLAN -> DESIGN -> IMPLEMENT -> TEST -> VERIFY -> ACCEPT` lifecycle, because the phases now are those stages.

In Phase 2, login, session, and logout follow the application's authentication model as recorded in `.claude/PROJECT-CONTEXT.md`. Authorization means the application's own per-feature authorization: the feature registry, roles as sets of grants, and enforcement at all four layers. The shared AI foundation is the AI model catalog per `.claude/rules/ai-model-catalog.md`, with its governance per `.claude/rules/ai-agent-governance.md` and its guardrails per `.claude/rules/ai-guardrails.md`.

Inside a Phase 4 task the build order is: database verified, then backend, backend tests, frontend, integration and AI, then end-to-end validation, followed by the code and security review passes.

## Stage 2 - Project Task Generation
After approval, invoke `/generate-project-tasks`.

Claude checks for material standards drift, then creates:
- `doc/tasktracking/TASK_REGISTER.md`
- `doc/tasktracking/TASK_BOARD.md`
- `doc/tasktracking/TRACEABILITY_MATRIX.md`
- `doc/tasktracking/task-events.jsonl`
- `doc/tasktracking/COMPLETION_LOG.md`

Tasks are organised as:
`Phase -> Module/Menu or Workstream -> Task -> Subtask`

## Feature And Task Workstreams
Every task records:
- Frontend
- Backend
- Database
- Integration/API
- AI
- Security
- Tests/Verification
- Acceptance/UAT
- Indexing decision
- Idempotency decision

This does **not** mean separate frontend, backend, or database phases. Delivery stays feature-oriented and end-to-end.

Indexing is evaluated per persisted structure from its approved access patterns, never on every column, and verified wherever the schema source exposes it, per `.claude/rules/database-schema.md` and `.claude/rules/semantic-data-model.md`. Idempotency is required where a retry or a duplicate causes a harmful side effect, with the mechanism recorded and duplicate and retry behaviour tested, per `.claude/rules/resilience.md`. It is not forced onto simple reads.

## Requirement Coverage And Conflicts
Maintain:
- `/doc/corerepo/03_FEATURE_FUNCTION_INDEX.md`
- `/doc/corerepo/04_REQUIREMENT_COVERAGE.md`
- `/doc/corerepo/11_DECISION_CONFLICT_REGISTER.md`

Allowed dispositions: `Covered`, `Duplicate`, `Conflict`, `Gap`, `Out of Scope - Explicit`, `Deferred - Approved`, `Not Applicable - Verified`. They record where each source item ended up, beside the classification labels (`CONFIRMED REQUIREMENT`, `GAP`, `CONFLICT`, `STANDARD CONFLICT`, and the rest) that classify each finding.

A material unresolved `Conflict` or `Gap` blocks the affected planning and build work.

## Infrastructure Readiness
`infra-readiness-agent` maintains `/doc/corerepo/02_INFRA_READINESS.md` in Phase 1 and whenever infrastructure materially changes. It checks only what the solution actually requires, such as repository, runtime and build, backend health, hosting target, database connectivity and the schema source, sign-in reachability where an external provider is used, integrations, and AI connectivity. It never claims READY without evidence, and a schema source that cannot be read blocks database-dependent work.

## Persistent Core Repository
`core-repo-maintainer` updates `/doc/corerepo/` incrementally on accepted or verified state changes:
- project and current state;
- infrastructure readiness;
- feature and requirement coverage;
- code knowledge base;
- database and entity models;
- integration and AI registers;
- task status;
- decisions and conflicts;
- accepted change log;
- handover;
- compact AI context and manifest.

It is its own store and records each fact once. It owns no task status (a snapshot of `doc/tasktracking/`) and no requirement (mapped to `doc/solutiondocument/`). Its database model holds only `SCHEMA VERIFIED` structures. It is where the working-memory log lives, in `01_CURRENT_STATE.md` and `11_DECISION_CONFLICT_REGISTER.md`, which are updated as work proceeds rather than only on accepted changes; its handover is the project-level one, and it never holds a secret or a production value. See `doc/corerepo/README.md`.

## AI Usage And Credit Discipline
Do not reread everything on every task. For a task, begin with:
1. root `CLAUDE.md` startup requirements;
2. `/doc/corerepo/14_AI_CONTEXT.md`;
3. `/doc/corerepo/PROJECT_MANIFEST.yaml`;
4. the selected Task ID and its linked requirements;
5. only the applicable `.claude` gates;
6. only the affected code and files.

Re-read all solution documents only for fresh intake, a full re-baseline, a material scope change, or a traceability or conflict need. Invoke specialist agents only when relevant. Update core documentation only on meaningful state changes.

## Task Lifecycle
`Planned -> Ready -> In Progress -> Implementation Complete -> User Testing -> Accepted`

Exceptional states: `Blocked`, `Rework Required`, `Cancelled`. A task the user rejects in testing goes to `Rework Required`, logged as `USER_TESTING_REJECTED`; a dropped task goes to `Cancelled`.

Only `Accepted` counts as final completed progress, and only after explicit user testing approval.

## Time Logging
`scripts/task-event.sh` appends timestamped lifecycle events to `doc/tasktracking/task-events.jsonl`. Time values are calculated from real recorded timestamps, never invented.

## Boundary
The kit supports planning, task tracking, and controlled single-task execution. It never grants autonomous project-wide coding permission. Every task remains subject to the repository's live Claude gateway, human approval gates, schema controls when persistence is involved, review/security requirements, and UAT acceptance.

## Controlled Task Execution

After Phase Plan approval and task generation, developers start implementation one Task ID at a time with:

`/execute-approved-task <TASK-ID>`

The kit enforces:

1. task readiness and dependency check;
2. task-specific plan, design and evaluation;
3. human approval before implementation;
4. logical data/field proposal when persistence is needed;
5. human approval of the logical proposal;
6. physical table/field/index/constraint proposal against the project's schema source, with reuse checks;
7. human approval of the physical proposal;
8. the migration authored through the confirmed migration tool;
9. schema self-check and `SCHEMA VERIFIED`;
10. explicit human coding authorisation;
11. coding, testing, code/security review and implementation-complete logging;
12. user testing and explicit acceptance before the task counts complete.

Steps 6 to 9 follow `.claude/rules/database-schema.md`.

Claude never executes DDL as part of this workflow and never codes against a proposed-but-not-live schema.

## Safe Kit Upgrade
Never upgrade the kit in the middle of an active coding change, database migration, deployment, or UAT defect fix. Prefer an accepted task or another controlled checkpoint. In a gateway project this applies whenever the project takes a newer copy of the gateway's `.claude/`, `doc/`, and `scripts/` folders. Before the upgrade, create a clean Git checkpoint and a working branch off `DEV`, and inventory the current standards, requirements, phase and task history, accepted decisions, task-event timestamps, schema verification records, and core-repo state.

The upgrade is non-destructive by default:
- preserve root `CLAUDE.md` and the live organisation and project `.claude/` standards;
- preserve `/doc/solutiondocument/`;
- preserve published Task IDs, task-event history, completion timestamps, and UAT and acceptance evidence;
- preserve accepted phase and task history, approved database proposals, and schema verification records;
- never renumber a historical Task ID merely to fit the newer macro-phase model; map it to its macro phase instead;
- never reset `/doc/corerepo/` or the tasktracking history;
- run no application feature coding, DDL, migration, or deployment as part of a kit upgrade.

After the upgrade, run `integration/POST_UPGRADE_VALIDATION.md`. Do not merge the upgrade branch until the validation records `PASS - SAFE TO MERGE`, proving there is no requirement loss, task or history loss, standards weakening, unintended schema change, or broken execution sequence. See `integration/VERSIONING_POLICY.md` for when to use a patch, minor, or major kit version.

## Companion Developer Documents
A copy/paste prompt for upgrading an existing V1.3 or V1.4 repository is at `integration/V1_3_TO_V1_4_1_UPGRADE_PROMPT.md`.

## Core Commands
- **`/generate-phase-plan`** - analyse all active requirements and the current project standards, prove complete coverage, run the infrastructure baseline checks, generate a Phase Plan of at most five phases, then stop for human approval.
- **`/generate-project-tasks`** - after planning approval, generate the stable project Task/Subtask backlog and tracking files with explicit technical workstreams.
- **`/execute-approved-task <TASK-ID>`** - execute exactly one Ready task through Plan/Design/Evaluate, optional data/schema gates, explicit coding authorisation, implementation, UAT and acceptance.
