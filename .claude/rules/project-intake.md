# Project Intake Rules

Claude must verify required project context before making code or documentation changes. This `.claude/` folder is a gateway that may be copied into a blank repository, so start from zero unless confirmed project context already exists.

## Ask First When Missing

If the task lacks context, ask concise questions for the missing facts before editing. Required facts:

- what the developer wants to build, change, fix, review, deploy, or investigate, why, and what success means
- how the developer wants it done, including constraints and preferred approach
- technology stack, versions, framework, package manager, runtime; application type and architecture style
- database engine/version if applicable, SQL Server schema/source of truth, and Schema MCP `schema` readiness
- hosting provider/target, domain/base URL, build system, deployment method, and rollback path
- required environment variables (placeholders only)
- validation commands and where they can run
- security/data sensitivity and authentication model

## Clarification Loop

- Never assume an answer for missing, vague, conflicting, or high-risk context. Ask whenever certainty is below what is needed to act safely, and keep asking until the answer is specific enough to implement, validate, and explain.
- Many rounds of questions are acceptable; accuracy and explicit confirmation beat speed. On a blank repository, expect many questions; do not stop because the list is long.
- Do not convert a guess into `.claude/PROJECT-CONTEXT.md`, source code, deployment instructions, MCP usage, or knowledge-base entries.
- Hard stop: do not implement, edit, run commands, update project context, update docs, write deployment steps, or proceed with MCP/schema work until the unclear point is clarified. There is no shortcut and no excuse for guessing.

## Where To Store Answers

- Durable non-secret answers go in `.claude/PROJECT-CONTEXT.md` after the developer confirms them.
- Reusable project knowledge goes in the repository knowledge base only after implementation is complete, validation is documented, and the developer has verified, validated, and explicitly approved the update, per `.claude/rules/knowledge-base.md`.
- Do not store secrets in project context, documentation, source files, command output, or chat summaries.

## Deployment Neutrality

There is no default deployment model. Do not assume any hosting provider, source control provider, CI/CD system, deployment target, local workflow, database access, build tooling, background processing, cache, search, storage, infrastructure model, or server access unless the developer explicitly confirms it.

## Before Editing

Verify: `.claude/PROJECT-CONTEXT.md` was read; applicable `.claude/rules/` files were read; project context is sufficient or missing facts were requested; relevant existing files and knowledge-base context were checked; database-dependent work has followed `.claude/docs/MCP-USAGE.md` and `.claude/rules/schema-mcp.md`.
