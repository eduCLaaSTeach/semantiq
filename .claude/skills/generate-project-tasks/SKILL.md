---
name: generate-project-tasks
description: Generate the detailed project task/subtask backlog only after the standards-aware Phase Plan has explicit human approval. Converts the approved phases, no more than five, into phase/module-or-menu/task/subtask records with stable Task IDs, proves every requirement, feature, and function has a disposition, states the technical workstreams including indexing and idempotency on every task, maps requirements and applicable Claude standards/gates, initializes traceability and progress tracking, updates the doc/corerepo snapshot, and enables event-based timing and user acceptance logging. Does not implement application code.
---

# Generate Project Tasks

1. Read `.claude/rules/solution-development-kit.md`.
2. Follow the current root `CLAUDE.md` and task-gated `.claude` standards. Do not rely only on the earlier planning snapshot.

## Mandatory Gate
Read the approvals from the Solution Planning And Task Tracking section of `.claude/PROJECT-CONTEXT.md`, which records who approved each document and when. Confirm explicit human approval is recorded there for:
- `doc/phasedocument/02_Gap_Conflict_Report.md`
- `doc/phasedocument/MASTER_PHASE_PLAN.md`

If either reads `not approved`, or names no approver and date, stop. A status line inside the document itself is not approval.

## Standards Drift Check
Compare the current standards relevant to task generation with `doc/phasedocument/PROJECT_STANDARDS_BASELINE.md`.
If a material standard changed in a way that can alter architecture, scope, security, data, UI, AI, API, deployment, or acceptance criteria, stop and request a Phase Plan refresh before generating or executing affected tasks.

## Workflow
1. Delegate backlog decomposition to `task-planner`.
2. Validate every task maps to an approved phase and, where applicable, requirement IDs.
3. Organise by `Phase -> Module/Menu or Workstream -> Task -> Subtask`, keeping no more than five phases, `P01` to `P05`.
4. Record applicable standards/gates per task where relevant.
5. Make every task state Frontend, Backend, Database, Integration/API, AI, Security, Tests/Verification, Acceptance/UAT, the indexing requirement, and the idempotency requirement, or `Not Applicable - Verified` with the reason.
6. Prove every active source requirement, feature, and function has a disposition and, where applicable, a task mapping. Nothing disappears silently.
7. Keep Task IDs stable. Never renumber or delete a published ID; map a legacy ID to its macro phase instead.
8. Initialize `doc/tasktracking/task-events.jsonl` as an append-only event log if absent.
9. Initialize `doc/tasktracking/COMPLETION_LOG.md` if absent.
10. Run consistency review: no orphan tasks, duplicate IDs, unsupported scope, missing acceptance criteria, missing testing expectations, missing workstreams, or unrepresented dependencies.
11. Stop. Do not write production code.

## Outputs
- `doc/tasktracking/TASK_REGISTER.md`
- `doc/tasktracking/TASK_BOARD.md`
- `doc/tasktracking/TRACEABILITY_MATRIX.md`
- `doc/tasktracking/task-events.jsonl`
- `doc/tasktracking/COMPLETION_LOG.md`
- `doc/corerepo/03_FEATURE_FUNCTION_INDEX.md`, `04_REQUIREMENT_COVERAGE.md`, `10_TASK_STATUS.md`, and `PROJECT_MANIFEST.yaml`. The first two map to `doc/solutiondocument/`; the task status and the manifest's task fields are a snapshot of `doc/tasktracking/`, which stays the record

## Completion Semantics
A task is project-complete only when it reaches `Accepted` following explicit user testing approval.
