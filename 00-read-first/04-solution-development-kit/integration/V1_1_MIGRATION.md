# Migrating From V1.1

V1.1 shipped a kit-owned root `CLAUDE.md`. V1.2 changes this design.

For projects that already have an organisation standard gateway, the organisation's current root `CLAUDE.md` remains authoritative. Do not replace it with the V1.1 kit file.

Use `.claude/rules/solution-development-kit.md` plus the V1.2 agents/skills instead. The old kit-owned file is intentionally not part of the V1.2 runtime configuration.
