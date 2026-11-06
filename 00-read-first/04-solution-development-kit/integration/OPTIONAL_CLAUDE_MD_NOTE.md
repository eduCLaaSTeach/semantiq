# Optional Note For The Root CLAUDE.md

The kit works without replacing the repository's root `CLAUDE.md` because its skills explicitly load the kit rule and then follow the repository gateway.

If desired, add a short discoverability note to the organisation's current root `CLAUDE.md`:

> **Solution planning / tracking:** when `/generate-phase-plan` or `/generate-project-tasks` is invoked, read `.claude/rules/solution-development-kit.md` and continue to obey all normal task-gated loading rules. The active requirements are under `/doc/solutiondocument/`; never treat `/doc/solutiondocument/Archive/` as authoritative by default.

Do not replace the organisation's root `CLAUDE.md` with a kit copy.
