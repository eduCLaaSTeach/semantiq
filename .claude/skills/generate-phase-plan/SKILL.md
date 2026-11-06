---
name: generate-phase-plan
description: Generate a complete standards-aware implementation phase plan from active solution requirements under /doc/solutiondocument. Use for a new solution or whenever requirements or the repository's Claude standards have materially changed. It reads the current root CLAUDE.md and task-gated .claude settings, recursively reads active requirement folders, ignores Archive by default, proves every requirement, feature, and function has a coverage disposition, runs the infrastructure readiness baseline, plans no more than five macro phases, generates traceable planning artifacts, updates doc/corerepo, and stops for human approval. Planning only; never implement code.
disable-model-invocation: true
---

# Generate Phase Plan

1. Read `.claude/rules/solution-development-kit.md`.
2. Follow the repository's current root `CLAUDE.md` startup and task-gated loading discipline.
3. Use `phase-orchestrator` to generate the planning package.

## Source Intake
- Recursively inspect `/doc/solutiondocument/`.
- Do not assume fixed folder names or counts.
- Ignore any `Archive` folder by default unless an active source references it or the human requests historical comparison.
- The commonly used category folders are documented in `/doc/solutiondocument/README.md` but are not hard-coded requirements.

## Standards
- Use the standards currently present in the repository.
- Do not preload the entire `.claude/` tree.
- Record the standards actually applied in `/doc/phasedocument/PROJECT_STANDARDS_BASELINE.md`.
- If the standards version changes materially, regenerate or refresh the plan before implementation continues.

## Required Behavior
- Create all required planning files under `/doc/phasedocument/`.
- Do not permit implementation code or source changes, and never execute DDL.
- Prove every identified requirement, workflow step, menu, feature, function, report, analytic, integration, AI capability, data requirement, security rule, and acceptance condition has one disposition: `Covered`, `Duplicate`, `Conflict`, `Gap`, `Out of Scope - Explicit`, `Deferred - Approved`, or `Not Applicable - Verified`. Nothing disappears silently.
- Surface unresolved `GAP`, `CONFLICT`, `ASSUMPTION REQUIRED`, `TECHNICAL CONSTRAINT`, and `STANDARD CONFLICT` items. An unresolved material `Conflict` or `Gap` blocks the work it affects until a human resolves it.
- Generate no more than five macro phases, `P01` to `P05`, per the five-phase model in `.claude/rules/solution-development-kit.md`. Detailed work belongs in tasks and subtasks.
- Run the Phase 1 infrastructure and Master DB readiness baseline through `infra-readiness-agent` where live checks are possible, and mark anything it cannot verify as not verified.
- Update the compact `/doc/corerepo/` planning and current-state records through `core-repo-maintainer`.
- End at the human approval gate.

## Human Approval Gate
Implementation remains blocked until a human explicitly approves both:
- `/doc/phasedocument/02_Gap_Conflict_Report.md`
- `/doc/phasedocument/MASTER_PHASE_PLAN.md`

Each approval is recorded, with who approved it and when, in the Solution Planning And Task Tracking section of `.claude/PROJECT-CONTEXT.md`. A status line inside the document itself is not approval.

After generation, summarize:
- active source folders/files reviewed;
- archive items excluded;
- standards/gates loaded;
- requirements captured by category;
- coverage dispositions by count, and any item not yet `Covered`;
- gaps/conflicts/assumptions;
- infrastructure readiness baseline result, with what could not be verified;
- proposed phases, no more than five;
- `/doc/corerepo/` files updated;
- QA result;
- exact files requiring human approval.
