# Integration With An Existing Claude Standards Repository

Note: this kit is part of the gateway. Its rule, `.claude/rules/solution-development-kit.md`, is the authority for requirement intake, planning, task tracking, and per-task execution. The overlay-install and stop-on-conflict instructions below describe Salil's standalone V1.4.1 package. In this gateway, the root `CLAUDE.md`, `.claude/`, `doc/`, and `scripts/` ship together, so there is nothing to merge by hand. The upgrade safety files below still apply whenever a project takes a newer copy of the gateway.

This kit is designed to be merged into a repository that may already contain a mature `CLAUDE.md` and `.claude/` standards tree.

## Do Not Overwrite The Organisation Gateway
If the target repository already has:
- `CLAUDE.md`
- `.claude/rules/`
- `.claude/skills/`
- `.claude/agents/`
- `.claude/PROJECT-CONTEXT.md`

keep them. Merge only the new kit files that do not conflict.

The kit's specialist agents and skills must obey the current root `CLAUDE.md` and current `.claude` standards at run time.

## Files The Kit Adds
- `.claude/rules/solution-development-kit.md`
- `.claude/agents/standards-reader.md`
- planning/tracking agents under `.claude/agents/`, including `infra-readiness-agent` and `core-repo-maintainer`
- `/generate-phase-plan`, `/generate-project-tasks`, and `/execute-approved-task` skills
- `doc/solutiondocument/`, `doc/phasedocument/`, `doc/tasktracking/`, `doc/corerepo/`
- `scripts/task-event.sh`

## Future Standards Versions
When the organisation publishes a newer `.claude` standard, update the project standards normally. Do not copy standards into the kit. The next planning or task-generation run must read the current repository standard and refresh `PROJECT_STANDARDS_BASELINE.md`.

## V1.4.1 Safe-Upgrade Files
- `PRE_UPGRADE_CHECKLIST.md` - run before changing kit files in an existing project.
- `V1_4_1_MIGRATION.md` - migration summary for the V1.4.1 patch release.
- `V1_4_MIGRATION.md` - migration summary from V1.3 to V1.4.
- `V1_3_TO_V1_4_1_UPGRADE_PROMPT.md` - copy/paste controlled upgrade prompt to V1.4.1.
- `V1_3_TO_V1_4_UPGRADE_PROMPT.md` - the earlier copy/paste upgrade prompt to V1.4.
- `POST_UPGRADE_VALIDATION.md` - mandatory before merging an upgrade branch.
- `VERSIONING_POLICY.md` - when to use a patch, minor, or major release.

Earlier migration notes stay for projects still on an older version: `V1_3_MIGRATION.md` and `V1_1_MIGRATION.md`. `OPTIONAL_CLAUDE_MD_NOTE.md` holds a discoverability note for a root `CLAUDE.md` that does not load the kit rule itself.
