# Secret Handling Rules

- Treat MCP bearer tokens as secrets.
- Do not commit real `.mcp.json` files.
- Do not read or print `%USERPROFILE%\.claude.json` unless the developer explicitly opens a security troubleshooting task.
- Treat the override key file (`~/.c2s/override.key`) as a secret: read it only to hash it for the authority check, hash it in the same command, and never print, echo, mask, paraphrase, copy, move, or commit it. Never confirm a guess about the override identity. See `.claude/rules/owner-override.md`.
- Do not paste full JWTs, Authorization headers, refresh tokens, private keys, `.env` values, or decoded claims into chat summaries.
- Use placeholders such as `<TOKEN>`, `<TENANT_ID>`, and `<MCP_SERVER_URL>` in docs and examples.
- If a real token is found in a repository, replace it with a placeholder and tell the developer to rotate or refresh it.
