# MCP Token Refresh

Refresh the short-lived credential for the Schema MCP server `schema` (or any HTTP-transport MCP using a short-lived bearer token) without exposing the token. Use it when `schema` returns unauthorized/expired errors because its token TTL lapsed.

The server URL, auth model, token-acquisition command, TTL, and registration scope are confirmed facts in `.claude/PROJECT-CONTEXT.md` (Schema MCP section). Do not invent them; if any is unset, stop and ask.

## Read First

1. Read `.claude/rules/secret-handling.md`, `.claude/rules/schema-mcp.md`, `.claude/docs/MCP-USAGE.md`, and `.claude/PROJECT-CONTEXT.md`.
2. Resolve the confirmed values: MCP alias (`<MCP_ALIAS>`), server URL (`<SCHEMA_MCP_URL>`), auth model, token-acquisition command (`<MCP_TOKEN_COMMAND>`), TTL, and registration scope. If any is unset, stop and ask before running anything.

## Secret Handling (hard)

- Never print, echo, log, or paste the token, the `Authorization` header, or any `claude mcp add` line with the token expanded. Redact as `Bearer <TOKEN>`.
- Never write the raw token into a committed file. A `.mcp.json` carrying a literal token stays gitignored per `.claude/rules/secret-handling.md`.
- Capture the token into a shell variable and reference the variable only.

## Preferred: self-refreshing auth via a headers helper (set up once)

Claude Code re-runs a `headersHelper` command on every connect and reconnect, so a fresh token is fetched automatically and the TTL stops causing manual work. No token is stored in `.mcp.json`. Prefer this over manual re-registration.

1. Create a developer-owned helper that fetches a fresh token and prints only the header JSON to stdout, keeping the raw token out of logs. Fill `<MCP_TOKEN_COMMAND>` with the project's confirmed token-printing command.
   - POSIX (`<HEADERS_HELPER_PATH>`, e.g. `.claude/scripts/mcp-headers-helper.sh`):
     ```sh
     #!/usr/bin/env sh
     set -eu
     TOKEN="$(<MCP_TOKEN_COMMAND>)"
     [ -n "$TOKEN" ] || { echo '{"error":"failed to obtain token"}' >&2; exit 1; }
     printf '{"Authorization":"Bearer %s"}' "$TOKEN"
     ```
   - PowerShell (`<HEADERS_HELPER_PATH>`, e.g. `.claude/scripts/mcp-headers-helper.ps1`):
     ```powershell
     $ErrorActionPreference = 'Stop'
     $token = <MCP_TOKEN_COMMAND>
     if (-not $token) { Write-Output '{"error":"failed to obtain token"}'; exit 1 }
     Write-Output ("{`"Authorization`":`"Bearer $token`"}")
     ```
2. Point the server at the helper in `.mcp.json` (no token stored):
   ```json
   {
     "mcpServers": {
       "<MCP_ALIAS>": {
         "type": "http",
         "url": "<SCHEMA_MCP_URL>",
         "headersHelper": "${CLAUDE_PROJECT_DIR}/<HEADERS_HELPER_PATH>"
       }
     }
   }
   ```
3. With no token stored, this `.mcp.json` is safe to commit if the team chooses (the secret stays in the developer's local auth context). Confirm with the developer before changing the repo's `.gitignore` policy for `.mcp.json`.
4. Restart the session, or let the server auto-reconnect on the next unauthorized response, so the helper takes effect.

## Fallback: manual re-registration (static token)

Use only when no headers helper is in place. Re-registration does not hot-swap into a running session; restart afterward.

1. Acquire a fresh token into a variable via `<MCP_TOKEN_COMMAND>`; never echo it.
2. Remove and re-add the server at the confirmed scope, passing the token through the variable only:
   ```powershell
   $TOKEN = <MCP_TOKEN_COMMAND>
   claude mcp remove <MCP_ALIAS>
   claude mcp add --scope <MCP_REGISTRATION_SCOPE> --transport=http --header="Authorization: Bearer $TOKEN" <MCP_ALIAS> <SCHEMA_MCP_URL>
   ```
3. Do not display the resulting `.mcp.json` or the command with the token expanded.
4. Restart the session so the new token loads (`claude mcp add`/`remove` do not reload a running session).

## Verify

1. After restart or reconnect, run `/mcp-health-check` (or `claude mcp list`) and confirm `<MCP_ALIAS>` is connected. Do not print `.mcp.json`, tokens, or Authorization headers.
2. Confirm the `mcp__schema__*` tools are available before resuming schema work, per `.claude/rules/schema-mcp.md`.

## Return

- Refresh path used (headers helper vs manual re-registration).
- MCP connection state after refresh (connected, pending, unauthorized, or unknown).
- Whether a session restart or reconnect is still required.
- Confirmation no token, Authorization header, or token-bearing command line was printed or committed.
