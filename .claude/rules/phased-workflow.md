# Phased Workflow Rules

Claude must run non-trivial work through an explicit, gated lifecycle instead of jumping to code. That lifecycle is the one in `.claude/rules/solution-development-kit.md`, which is the authority for it. This rule adds only what that rule does not cover, and never a second set of phases. The lifecycle is stack-neutral; the concrete contents of each gate (reviewers, migration tool, validation commands, CI checks) are confirmed facts in `.claude/PROJECT-CONTEXT.md`, not in this rule.

## One Lifecycle

| Level | Lifecycle | Defined in `solution-development-kit.md` |
| --- | --- | --- |
| The solution | Requirement intake from `doc/solutiondocument/`, the planning workflow, and explicit approval of `doc/phasedocument/02_Gap_Conflict_Report.md` and `doc/phasedocument/MASTER_PHASE_PLAN.md` | Required Planning Workflow, Planning-Only Gate |
| The phases | At most five macro phases, `P01` to `P05`: platform and infrastructure foundation, core application, feature design, feature build, and UAT and release readiness. The phases are the delivery stages, so a phase has no lifecycle of its own, and it is accepted only when all its mandatory acceptance criteria pass | Five Macro Phases |
| A task | Gates 1 to 6 for one Task ID, and the task status model from `Planned` to `Accepted` | Approved-Task Execution Gate, Task Status Model |

- Work tracked under a Task ID runs Gates 1 to 6 in order, normally through `/execute-approved-task <TASK-ID>`, and stops at every approval boundary.
- Once `doc/tasktracking/` holds a generated backlog, application implementation runs only under a Task ID. When requested work matches no Task ID, stop and ask which task it belongs to. New scope enters the backlog from an approved Phase Plan through `/generate-project-tasks`, or through a Phase Plan refresh, never by starting it untracked.
- Non-trivial work in a project with no generated backlog runs the same gates in the same order: Gate 1 approval before any change, Gates 2 to 4 when the work creates, changes, maps to, or depends on persisted structures (through the project's schema source and confirmed migration tool, per `.claude/rules/database-schema.md`), explicit coding authorization, implementation with validation and both review passes, and explicit acceptance by the person who asked for it after they have tested it. It records no task events, because there is no Task ID to record them against.
- That never stands in for the Planning-Only Gate. Application code that implements requirements under `doc/solutiondocument/` waits for approval of both planning documents and then runs under a Task ID.

## Inside The Gates

These sit inside the gates above. None of them is a gate of its own.

- Gate 1 starts from `.claude/rules/project-intake.md`. Restate the requirement in your own words, list assumptions, open questions, and unknowns, and do not ask for approval while any requirement, stack choice, hosting/deployment detail, database detail, security boundary, validation path, or success criterion is unclear. Silence is not approval.
- When more than one workable approach exists, Gate 1 lays out the options with their tradeoffs (risk, effort, blast radius, reversibility) and states which one you recommend and why. The human picks or approves the path as part of the Gate 1 approval. When only one reasonable approach exists, say so and recommend it plainly rather than manufacturing alternatives.
- The Gate 1 design defines interfaces, contracts, and the file plan before any code, and names a rollback/mitigation strategy.
- Sensitive areas (schema, authentication, deployment surface) need the confirmed reviewers to approve at Gate 1 as well.
- During implementation, build in small, reviewable increments and evaluate each against the approved plan, the design, and the applicable rules. Do not silently expand scope; if the approach proves wrong, go back to Gate 1 and get approval again. Self-verify against `.claude/rules/production-readiness.md` and run available validation before the task reaches `Implementation Complete`.
- Promotion through Git (Pull Request, review, merge, environment promotion) follows `.claude/rules/git-branching-release.md` and `.claude/rules/deployment.md`. It is separate from task status: merging or promoting never moves a task to `Accepted`, and `Accepted` never approves a production deploy or migration.

## Gate Discipline

- At the start of each response on a tracked task, state the Task ID, its status, and the gate you are working toward. On untracked work, state the gate.
- Stop at each gate and wait for explicit human approval; do not treat your own confidence, a finished build, or a passing test as approval. Do not collapse, skip, or reorder gates.
- If asked to jump straight to building, back up to the correct earlier gate, complete it, and pass it first.
- Keep stack-specific gate contents in `.claude/PROJECT-CONTEXT.md` and reference them; do not bake specific products, tools, or counts into this workflow.

## Final Reporting

For phased work, state: the Task ID and its status, or that the work is untracked; which gates passed, with who approved each; assumptions and unknowns surfaced at Gate 1; the options weighed and the recommendation chosen; design decisions and the named rollback/mitigation strategy; the data gate outcome, `NO DATA MODEL CHANGE REQUIRED` or `SCHEMA VERIFIED` with the verified objects; the task events recorded through `scripts/task-event.sh`; validation run and results, or the documented reason it could not run; any gate re-entered and why.

Final rule: if the gate, the gate criteria, or whether a gate was actually approved is unclear, do not advance. Ask first.
