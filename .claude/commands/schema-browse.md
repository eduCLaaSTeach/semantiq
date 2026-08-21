# Schema Browse

Use to inspect existing SQL Server database metadata through the Schema MCP server `schema`.

Input: `[SCHEMA_OR_TABLE_OR_CONCEPT]`

Process:

1. Read `.claude/docs/MCP-USAGE.md`, `.claude/rules/schema-mcp.md`, and `.claude/PROJECT-CONTEXT.md`.
2. Confirm to the developer that Schema MCP is the source of truth and summarize the four rules: do not write DDL, do not invent table or column names, check existing tables before proposing new ones, and stop if MCP is unreachable.
3. Run `claude mcp list` only when MCP connection status is unknown. Do not print `.mcp.json`, tokens, Authorization headers, or secret values.
4. General browse: use `mcp__schema__list_tables`.
5. Specific table: use `mcp__schema__describe_table` before discussing columns, keys, indexes, constraints, or relationships.
6. Business concept: use `mcp__schema__find_existing_tables_for_concept` before proposing new storage.
7. Proposal review: use `mcp__schema__list_pending_proposals` or `mcp__schema__get_proposal` as needed.
8. Do not use `mcp__schema__propose_table_change` unless you have shown the developer the exact proposed table or column shape and they have explicitly confirmed it; revise and re-show if they ask for adjustments.

Return:

- MCP connection status
- Schema/table/concept searched
- Tables listed or described
- Columns and relationships verified
- Existing reuse candidates
- Proposal IDs reviewed, if any
- Blockers or missing developer decisions
