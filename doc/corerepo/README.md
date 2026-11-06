# Core Repository

This folder is the project's living memory: a compact, vendor-neutral account of where the project stands, so another developer or another AI-assisted development tool can continue without rereading the whole repository. It is written in plain language with no tool-specific instructions; `.claude/` stays the Claude-specific layer.

The `core-repo-maintainer` agent maintains it, and `infra-readiness-agent` maintains `02_INFRA_READINESS.md`. Both work under `.claude/rules/solution-development-kit.md`.

## When It Is Updated

Incrementally. `01_CURRENT_STATE.md` and `11_DECISION_CONFLICT_REGISTER.md` are the project's working-memory log, so they are updated as work proceeds: a change of focus or progress, a dated decision, a new open question. Every other file changes on accepted or verified state changes only: a task changing major lifecycle state, a structure becoming verified, a conflict being resolved, a feature being added, deferred or re-scoped, an integration or AI component changing, infrastructure readiness changing, or a handover being requested. Only the affected files and sections change. It is never regenerated after a routine edit, an intermediate code change or a read that changed nothing.

## Files

| File | Holds |
| --- | --- |
| `00_PROJECT_INDEX.md` | The entry point, naming every file in this folder |
| `01_CURRENT_STATE.md` | The current phase, task, focus, blockers and next action |
| `02_INFRA_READINESS.md` | Each infrastructure check with its status, evidence and last verified time |
| `03_FEATURE_FUNCTION_INDEX.md` | Every source feature and function with its main build workstreams and disposition |
| `04_REQUIREMENT_COVERAGE.md` | Where each Requirement ID ended up, with one disposition from the closed list |
| `05_CODE_KNOWLEDGEBASE.md` | A map of entry points, modules, services, patterns and tests, by path and symbol |
| `06_DB_MODEL.md` | The verified physical structures, their grain, keys, constraints and indexes |
| `07_ENTITY_MODEL.md` | The business entities and their relationships, apart from the physical model |
| `08_INTEGRATION_REGISTER.md` | Every integration with its direction, security and retry or idempotency mechanism |
| `09_AI_COMPONENT_REGISTER.md` | Every runtime AI component with its catalog record, prompt version, guardrails, kill switch, human approval points, owner and risk level |
| `10_TASK_STATUS.md` | A snapshot of the current phase, task and gate |
| `11_DECISION_CONFLICT_REGISTER.md` | The dated decision log, the open questions, and the conflicts and gaps awaiting a decision |
| `12_CHANGE_LOG.md` | Accepted changes and the core repo files each one touched |
| `13_HANDOVER.md` | The project-level handover |
| `14_AI_CONTEXT.md` | The shortest orientation: current task, gate, blockers, next action and what to read next |
| `PROJECT_MANIFEST.yaml` | The same current state in a small machine-readable form |

`14_AI_CONTEXT.md` and `PROJECT_MANIFEST.yaml` are read right after the root `CLAUDE.md` startup when a task begins, so keep both short.

## How It Relates To The Other Stores

Each fact is recorded once, in the store that owns it, and referenced from the others.

- It is its own store, not a knowledge-base tier, so the knowledge-base approval gate in `.claude/rules/knowledge-base.md` does not govern it. It is written as the work happens, as When It Is Updated above describes.
- It owns no task status. `10_TASK_STATUS.md` and the task fields in `PROJECT_MANIFEST.yaml` are a snapshot of `doc/tasktracking/`, which stays the record.
- It owns no requirement. `03` and `04` map to `doc/solutiondocument/`, which stays authoritative.
- `06_DB_MODEL.md` records only structures verified against the project's schema source (`SCHEMA VERIFIED`). The table dictionary rules in `.claude/rules/knowledge-base.md` still apply.
- It is where the project's working-memory log lives. `01_CURRENT_STATE.md` holds the current focus and progress, and `11_DECISION_CONFLICT_REGISTER.md` holds the dated decision log and the open questions, so no separate working-memory log is created.
- `13_HANDOVER.md` is the project-level handover. A single session's handoff still uses `/session-handoff`.
- The orientation map reads this folder as one more source and owns no fact of its own.

## What Never Goes Here

No secret, token, credential, full connection string, customer record, production row data or production value, per `.claude/rules/secret-handling.md`. Use placeholders such as `<TOKEN>` and `<DATABASE_NAME>`.
