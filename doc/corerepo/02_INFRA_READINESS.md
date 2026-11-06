# Infrastructure Readiness

Maintained by `infra-readiness-agent` in Phase 1 and whenever infrastructure materially changes. Check only what the active solution requires.

Overall status: NOT VERIFIED

Each check carries one status: `PASS`, `FAIL`, `NOT APPLICABLE`, `BLOCKED` or `NOT VERIFIED`. The overall status is READY only when every required check is `PASS` with evidence recorded. When the schema source recorded in `.claude/PROJECT-CONTEXT.md` cannot be read, mark database-dependent work `BLOCKED`. Evidence names what was checked and how; never a secret, a token or a full connection string.

| Check | Status | Evidence / Note | Last Verified |
|---|---|---|---|
| Repository | NOT VERIFIED | | |
| Frontend runtime/build | NOT VERIFIED | | |
| Backend runtime/health | NOT VERIFIED | | |
| Backend readiness endpoint | NOT VERIFIED | | |
| Configuration mechanism | NOT VERIFIED | | |
| Database connectivity | NOT VERIFIED | | |
| Database schema source and migration tool recorded | NOT VERIFIED | | |
| Schema source readable | NOT VERIFIED | | |
| Hosting/deployment target | NOT VERIFIED | | |
| Sign-in provider reachability, where an external one is used | NOT VERIFIED | | |
| Required integrations | NOT VERIFIED | | |
| Required AI connectivity | NOT VERIFIED | | |
| Logging/observability | NOT VERIFIED | | |

Blockers: None recorded
Next action: Run the Phase 1 readiness checks.
