# MCP Health Check

Check whether Claude Code is ready to use the SQL Server-backed Schema MCP server `schema`.

Steps:

1. Read `.claude/README.md`, `.claude/PROJECT-CONTEXT.md`, `.claude/docs/MCP-USAGE.md`, `.claude/rules/schema-mcp.md`, and `.claude/rules/secret-handling.md`.
2. Do not print `.mcp.json`, `.claude.json`, Authorization headers, or tokens.
3. Run `claude mcp list` and report whether `schema` is connected, pending approval, missing, unauthorized, or unknown.
4. If unauthorized, tell the developer to refresh credentials through their approved setup path. Do not generate or display tokens yourself unless explicitly asked in a setup session.
5. If `schema` is connected, list these expected tools without calling data-changing tools: `mcp__schema__list_tables`, `mcp__schema__describe_table`, `mcp__schema__find_existing_tables_for_concept`, `mcp__schema__list_pending_proposals`, `mcp__schema__propose_table_change`, and `mcp__schema__get_proposal`.

Return:

- Setup status
- Missing prerequisites
- MCP connection state
- Safe next step
