# Project Task Tracking

Generated only after explicit human approval of the Phase Plan.

Task hierarchy:
`Phase -> Module/Menu or Workstream -> Task -> Subtask`

Phase IDs are limited to `P01` to `P05`, one per macro phase in the approved plan.

Every task should trace to:
- approved phase;
- requirement IDs where applicable;
- applicable current Claude standards/gates where relevant;
- dependencies;
- acceptance criteria;
- user testing requirement.

Every task also records a decision for each technical workstream: Frontend, Backend, Database, Integration/API, AI, Security, Tests/Verification, Acceptance/UAT, Indexing and Idempotency. Use `Not Applicable - Verified` with a reason rather than omitting a workstream. Delivery stays feature-oriented and end-to-end; the workstreams are not separate phases.

Files:
- `TASK_REGISTER.md` - detailed backlog and status.
- `TASK_BOARD.md` - management progress snapshot.
- `TRACEABILITY_MATRIX.md` - requirement -> phase -> task -> acceptance traceability.
- `task-events.jsonl` - append-only event timestamps.
- `COMPLETION_LOG.md` - accepted task history and timing metrics.

Task status:
`Planned -> Ready -> In Progress -> Implementation Complete -> User Testing -> Accepted`

Exceptional states: `Blocked`, `Rework Required`, `Cancelled`. A task the user rejects in testing goes to `Rework Required`, logged as `USER_TESTING_REJECTED`; a dropped task goes to `Cancelled`.

This folder is the record for task status. A compact snapshot of the current status is also kept in `/doc/corerepo/10_TASK_STATUS.md` and `/doc/corerepo/PROJECT_MANIFEST.yaml`, and follows this folder rather than leading it.

Preserve all history. Never delete or renumber a published Task ID; map a legacy ID to its macro phase instead. Never rewrite a recorded event, timestamp, UAT approval or acceptance record.

If current `.claude` standards materially change after planning approval, perform the standards drift check before generating or executing affected tasks.

Final completion means `Accepted` after explicit user testing approval.

## Controlled Task Execution
Start exactly one Ready task with **`/execute-approved-task <TASK-ID>`**. Claude must begin in Plan/Design/Evaluate mode and stop for human approval. If persistence/schema is involved, the task must pass logical data approval, physical table proposal approval, the migration and the schema self-check recorded as `SCHEMA VERIFIED`, and explicit coding authorisation before code is written.

A task with an approved `NO DATA MODEL CHANGE REQUIRED` determination skips the logical/physical/schema self-check gates, but still requires explicit coding authorisation, current CODE/review/security gates, internal validation and UAT approval.
