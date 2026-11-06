---
name: execute-approved-task
description: Execute exactly one human-approved project Task ID through compact progressive context loading, controlled plan/design evaluation with explicit Frontend/Backend/Database/Integration-API/AI/Security/Tests/Acceptance/Indexing/Idempotency sections, optional logical data proposal, physical schema proposal against the project's schema source, migration and schema self-check, explicit coding authorisation, ordered implementation/testing, and user acceptance, updating only the affected doc/corerepo state. Use when a developer is ready to begin work on a generated task. Never skips human approval gates and never invents database schema.
---

# Execute Approved Task

1. Read `.claude/rules/solution-development-kit.md`.
2. Follow the current root `CLAUDE.md` startup/gate-loading rules.
3. Delegate orchestration to `task-execution-orchestrator`.

## Required Argument
A single Task ID such as `P03-M02-T004`. If absent or ambiguous, ask for the Task ID and stop.

## Context Loading
Load progressively: the root gateway startup -> `doc/corerepo/14_AI_CONTEXT.md` -> `doc/corerepo/PROJECT_MANIFEST.yaml` -> the Task ID -> its linked requirements -> the applicable gates -> the affected code only. Re-read all solution documents only for fresh intake, a full re-baseline, a material scope change, or a traceability or conflict question. Invoke specialist agents only when their scope is relevant.

## Gate Sequence
`READY CHECK -> PLAN/DESIGN/EVALUATE -> HUMAN APPROVAL -> [LOGICAL DATA -> HUMAN APPROVAL -> PHYSICAL PROPOSAL -> HUMAN APPROVAL -> MIGRATION -> SCHEMA SELF-CHECK] -> HUMAN CODING AUTHORISATION -> CODE/TEST/REVIEW -> IMPLEMENTATION COMPLETE -> USER TESTING -> ACCEPTED OR REWORK`

The bracketed data/schema sequence is mandatory only when the task creates, changes, maps to, or depends on persisted structures, and it ends in `SCHEMA VERIFIED`, per `.claude/rules/database-schema.md`.

The plan/design states Frontend, Backend, Database, Integration/API, AI, Security, Tests/Verification, Acceptance/UAT, Indexing, and Idempotency, each as work or `Not Applicable - Verified` with the reason. Coding runs in this order: Database verified -> Backend -> Backend tests -> Frontend -> Integration/AI -> end-to-end validation, then the code and security review passes.

At `Implementation Complete` and at `Accepted`, update only the affected `doc/corerepo/` sections. The task status there is a snapshot of `doc/tasktracking/`, which stays the record.

## Hard Stops
Stop when:
- task is not Ready;
- dependencies are incomplete;
- an unresolved material `Gap` or `Conflict` blocks the task;
- plan/design is not explicitly approved;
- logical data proposal requires approval;
- physical schema proposal requires approval;
- the schema source cannot be read, or the schema does not match the approved proposal;
- coding has not been explicitly authorised;
- a Critical/High review finding remains unresolved unless the repository's approved exception process is followed;
- user testing has not been explicitly approved.

Never start another Task ID automatically, and never execute DDL.
