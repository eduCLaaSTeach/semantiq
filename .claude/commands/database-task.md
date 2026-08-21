# Database Task

Use for database-backed features, schema questions, reports, imports, exports, mappings, or query changes.

Input: `[DATABASE_TASK_OR_DATA_CONCEPT]`

Process:

1. Read `.claude/docs/MCP-USAGE.md`, `.claude/rules/schema-mcp.md`, `.claude/rules/knowledge-base.md`, and `.claude/PROJECT-CONTEXT.md`.
2. Confirm to the developer that the MCP usage document was read and summarize the four key rules.
3. Verify the SQL Server-backed Schema MCP server `schema` is reachable before schema-dependent work.
4. Use `mcp__schema__find_existing_tables_for_concept` before proposing new storage, including for the feature's shared reference sets (source, status, outcome, reason).
5. Use `mcp__schema__list_tables` and `mcp__schema__describe_table` before referencing any table or column.
6. When the task designs or alters a structure, run `.claude/commands/semantic-model-plan.md` first: confirm the analytics question set, declare each table's grain, and show both with the shape before proposing.
7. Stop if MCP is unreachable, unauthorized, or missing required scope.
8. After validation, follow the knowledge-base gate in `.claude/rules/knowledge-base.md`: prompt the developer to create/update the knowledge base now or defer, and write files only on explicit approval. A table-dictionary update documenting an actual schema change travels in the same change unit as the schema change.

Return:

- MCP file read confirmation
- Four-rule summary
- MCP tools used
- Tables/concepts verified
- Analytics question set, grain per table, and any unanswered question
- Proposed path or blocker
- Validation plan
- Knowledge-base/table-dictionary follow-up
