# V1.3 Migration

V1.3 adds controlled single-task execution. Merge these paths into an existing V1.2 installation:

- `.claude/rules/solution-development-kit.md` (merge the Approved-task execution gate section)
- `.claude/agents/task-execution-orchestrator.md`
- `.claude/agents/data-design-reviewer.md`
- `.claude/skills/execute-approved-task/SKILL.md`
- `doc/tasktracking/data-design/README.md`

Do not replace the repository's authoritative root `CLAUDE.md`. The kit remains an overlay and follows the current gateway at run time.
