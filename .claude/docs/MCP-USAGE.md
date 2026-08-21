# MCP Usage

This repository requires the SQL Server Schema MCP server named `schema` for all database schema and database-backed code work. It is the final database metadata source of truth for this gateway.

Read this file before touching database schema. If the target project has `./docs/MCP-USAGE.md`, read that first; otherwise use this bundled fallback. After reading, confirm to the developer that it was read and summarize these four rules before schema work starts:

1. Do not write DDL.
2. Do not invent table or column names.
3. Always check existing tables before proposing new ones.
4. Stop and tell the developer if the MCP server is unreachable.

## Required Workflow

1. Read `.claude/rules/schema-mcp.md` and this document.
2. Verify the `schema` server is reachable before database-dependent design or code.
3. `mcp__schema__find_existing_tables_for_concept` before proposing any new storage.
4. `mcp__schema__list_tables` when schema/table existence needs confirmation.
5. `mcp__schema__describe_table` before referencing any table or column; use only verified names.
6. `mcp__schema__propose_table_change` only after showing the developer the exact proposed table/column shape and getting explicit confirmation; revise and re-show if they ask for adjustments. Show each table's one-sentence grain and the developer-confirmed analytics question set alongside the shape, per `.claude/rules/semantic-data-model.md`.
7. Stop schema-dependent work when MCP is unreachable, unauthorized, unavailable, or missing required scope.

## Schema Tools

`mcp__schema__list_tables`, `mcp__schema__describe_table`, `mcp__schema__find_existing_tables_for_concept`, `mcp__schema__list_pending_proposals`, `mcp__schema__propose_table_change`, `mcp__schema__get_proposal`.

## Credential And Token Refresh

The `schema` MCP authenticates with a short-lived credential. When the confirmed auth model uses a bearer token with a TTL (the TTL is a confirmed fact in `.claude/PROJECT-CONTEXT.md`), the token expires on a schedule and schema tools begin returning unauthorized/expired errors once it lapses.

- Prefer a self-refreshing setup: configure a `headersHelper` command that fetches a fresh token on every connect and reconnect, so no token is stored in `.mcp.json`.
- Without a headers helper, refresh on demand by re-acquiring the token and re-registering the server, then restart the session so the new token loads.
- Use `.claude/commands/mcp-token-refresh.md` for both paths; it never prints the token, the Authorization header, or a token-bearing command line, per `.claude/rules/secret-handling.md`.
- Editing `.mcp.json` or running `claude mcp add` / `claude mcp remove` does not reload a running session; restart it, or let an HTTP server auto-reconnect, to pick up a new token.

## Forbidden

- Do not request production row data or run arbitrary SQL through MCP.
- Do not write `CREATE TABLE`, `ALTER TABLE`, `DROP TABLE`, or migration DDL as a shortcut.
- Do not invent schemas, tables, columns, indexes, constraints, enum values, or relationships.
- Do not bypass MCP with guesses from old code, naming patterns, memory, or assumptions.
- Do not print bearer tokens, Authorization headers, refresh tokens, private keys, `.env` values, or decoded token claims.

## Final Reporting

For database/schema work, include: MCP tools used; tables described or concepts searched; proposal IDs submitted or checked; whether any schema-dependent work was blocked; database/schema impact; knowledge-base/table-dictionary status; confirmation that no secrets, tokens, headers, row data, or production values were printed.
