# Fresh Project Gateway Rules

This `.claude/` folder is a gateway for every project. A developer may copy it into a blank repository and start from zero.

## Mandatory Start From Blank

On every new project or unclear task, treat the repository as unknown until the developer confirms otherwise. Do not infer project facts from folder names, old templates, package files, default framework behavior, or previous projects without asking.

## Required Startup Flow

1. Read `.claude/README.md`.
2. Read `.claude/PROJECT-CONTEXT.md`.
3. Read `.claude/rules/project-intake.md`, `deployment.md`, `enterprise-governance.md`, `production-readiness.md`, `secret-handling.md`, and task-specific rules.
4. Acknowledge the bootstrap before proceeding (see below): name which bootstrap files were loaded and flag any that are missing, empty, or stale.
5. Inspect the repository structure only to identify what to ask, not to make assumptions.
6. Ask what to build/change, how, which stack is approved, what hosting/deployment applies, what database/MCP details apply, and how success is validated. Keep asking until enough context is confirmed.
7. Update `.claude/PROJECT-CONTEXT.md` with confirmed non-secret facts.
8. Proceed only after the requested work, constraints, validation path, and risk boundaries are clear.

## Bootstrap Acknowledgment Gate

Loading the startup context is not enough. After loading the ordered context above and before inspecting the repo or asking intake questions, confirm to the developer that the bootstrap actually happened; do not proceed on a guess about project state.

- Name which bootstrap files were actually loaded (for example `.claude/README.md`, `.claude/PROJECT-CONTEXT.md`, and the applicable `.claude/rules/` files) rather than implying it.
- Flag any required bootstrap file that is missing, empty, or stale (for example `PROJECT-CONTEXT.md` with no confirmed facts, or content that contradicts the current repository) before doing anything else.
- Stop and ask if a required bootstrap file cannot be loaded or its state is unclear, treating that uncertainty like any other unclear context under "Hard Stop When Unclear".

This is the same "confirm you actually read it" discipline schema work requires for the MCP usage document, generalized to the whole session bootstrap. Derive the acknowledged set from the files this rule lists plus any task-specific rules in play; do not hard-code a separate companion-file checklist.

## Ask Until Certain

If not satisfied that you understand exactly what to do and how to do it safely, ask again. Many rounds of questions are fine. Do not prioritize speed over certainty.

## Hard Stop When Unclear

There is no exception to clarification. If any requirement, context, stack choice, hosting/deployment detail, database/MCP detail, security boundary, validation path, file ownership, or success criterion is unclear, stop and ask before doing anything else.

Do not implement, edit files, run commands, update project context, write deployment steps, propose schema changes, or mark work complete while required context is uncertain. Frustration, urgency, or convenience is not a valid reason to guess.

## Do Not Store Secrets

Never store hosting passwords, deployment credentials, database passwords, tokens, private keys, `.env` values, full connection strings, production row data, or customer records in `.claude/PROJECT-CONTEXT.md`, docs, source code, or chat summaries.
