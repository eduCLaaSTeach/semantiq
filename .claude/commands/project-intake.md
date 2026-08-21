# Project Intake

Use before starting work when the task, stack, deployment details, database/MCP details, validation path, or success criteria are missing.

Input: `[TASK_OR_PROJECT_NAME]`

Process:

1. Read `.claude/PROJECT-CONTEXT.md`, `.claude/rules/fresh-project-gateway.md`, `.claude/rules/project-intake.md`, `.claude/rules/deployment.md`, and `.claude/rules/secret-handling.md`.
2. Identify unknown, vague, conflicting, stale, or high-risk facts for the task.
3. Ask what to do, why, how the developer wants it done, which stack is approved, what hosting/deployment and database/MCP details apply, and how success must be validated. Use placeholders for sensitive values.
4. Keep asking follow-up questions until the answers are clear enough to proceed without assumptions. If any required answer is still unclear, stop and ask again. Do not implement, edit, run commands, update project context, or proceed with deployment/schema work from guesses.
5. Track open questions in `.claude/PROJECT-CONTEXT.md`; after the developer confirms, update it with non-secret durable facts.
6. If database work is involved, read `.claude/docs/MCP-USAGE.md` and `.claude/rules/schema-mcp.md`, then confirm the four MCP rules before schema work starts.

Return:

- Missing context found
- Questions asked
- Follow-up questions still needed
- Hard-stop blockers from unclear answers
- Project context read/updated
- Context file updates needed
- MCP readiness requirement
- Deployment details still required
