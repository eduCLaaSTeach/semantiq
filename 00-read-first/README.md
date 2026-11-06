# Developer Handbook

> [!IMPORTANT]
> Must read, all of it, before you do anything. Read every file listed below, in the order given in the [root README](../README.md).

Human-facing guides for developers who use this Claude gateway kit. The gateway itself is the root `CLAUDE.md` plus three folders, `.claude/`, `doc/`, and `scripts/`; these files explain how to work with it. Start with getting-started, run the gateway commands during daily work, check guidelines for behavior, and use reference when you need field-level detail.

## Getting Started

- [01-getting-started/HOW_TO_USE.md](01-getting-started/HOW_TO_USE.md) - how to use the kit: the first command, the start to finish order, gateway commands, rules overview, what to ask, what to store, and checklists.
- [01-getting-started/WORKING_WITH_CLAUDE.md](01-getting-started/WORKING_WITH_CLAUDE.md) - the team mindset for working with Claude.

## Commands

Every workflow that used to be a copy-and-paste prompt is now a gateway command in `.claude/commands/`: `/day-start`, `/session-handoff`, `/account-handover`, `/git-workflow`, and `/orientation-map`. The full list is under Gateway Commands in [01-getting-started/HOW_TO_USE.md](01-getting-started/HOW_TO_USE.md).

## Guidelines

- [02-guidelines/DO_AND_DO_NOT.md](02-guidelines/DO_AND_DO_NOT.md) - the do and do-not behavior mirror of the gateway rules.
- [02-guidelines/APPROVED_PLUGINS.md](02-guidelines/APPROVED_PLUGINS.md) - the approved-plugins list.
- [02-guidelines/MODEL-ROUTING.md](02-guidelines/MODEL-ROUTING.md) - team policy for which Claude model to pick in Claude Code (Sonnet by default, Opus for deep reasoning), the biggest lever on plan usage.

## Reference

- [03-reference/PROJECT-CONTEXT-UNDERSTANDING.md](03-reference/PROJECT-CONTEXT-UNDERSTANDING.md) - every intake field in `.claude/PROJECT-CONTEXT.md`, what it means, who answers it, and what a good answer looks like.
- [03-reference/CONTEXT-COST-FIX-MIGRATION-NOTES.md](03-reference/CONTEXT-COST-FIX-MIGRATION-NOTES.md) - what the context-cost fix changed in `CLAUDE.md`, the `ui-ux-design` skill, and `ui-ux-quality.md`, why, its verified token impact, and how to apply it.
- [03-reference/CREDIT-OPTIMIZATION.md](03-reference/CREDIT-OPTIMIZATION.md) - the canonical reference on how a Claude plan's usage is consumed across a team of developers and the three levers (model routing, lazy context, schema reads on demand) that reduce it, with where each fix lives.

## Solution Development Kit

The Claude Solution Development Kit V1.4.1 documents, adapted to this copy of the gateway. The kit is merged into the gateway, and `.claude/rules/solution-development-kit.md` is the authority for planning, task tracking, and task execution.

- [04-solution-development-kit/README.md](04-solution-development-kit/README.md) - the kit overview: requirement intake, the five macro phases, the task lifecycle, time logging, controlled task execution, and the core repo.
- [04-solution-development-kit/FINAL_RELEASE_MANIFEST.md](04-solution-development-kit/FINAL_RELEASE_MANIFEST.md) - the V1.4.1 release manifest and the controlled execution sequence.
- [04-solution-development-kit/integration/README.md](04-solution-development-kit/integration/README.md) - how the standalone kit merges into an existing standards repository, and how an existing project upgrades safely.
- [04-solution-development-kit/integration/PRE_UPGRADE_CHECKLIST.md](04-solution-development-kit/integration/PRE_UPGRADE_CHECKLIST.md) - the checklist to work through before upgrading the kit in an existing project.
- [04-solution-development-kit/integration/POST_UPGRADE_VALIDATION.md](04-solution-development-kit/integration/POST_UPGRADE_VALIDATION.md) - the validation run after an upgrade and before its branch merges, ending in `PASS - SAFE TO MERGE` or `FAIL - DO NOT MERGE`.
- [04-solution-development-kit/integration/VERSIONING_POLICY.md](04-solution-development-kit/integration/VERSIONING_POLICY.md) - how the kit's own version numbers move, apart from the gateway's CalVer releases.
- [04-solution-development-kit/integration/V1_3_TO_V1_4_1_UPGRADE_PROMPT.md](04-solution-development-kit/integration/V1_3_TO_V1_4_1_UPGRADE_PROMPT.md) - the copy-ready prompt that upgrades an existing project from V1.3 or V1.4 to V1.4.1.
- [04-solution-development-kit/integration/V1_3_TO_V1_4_UPGRADE_PROMPT.md](04-solution-development-kit/integration/V1_3_TO_V1_4_UPGRADE_PROMPT.md) - the earlier upgrade prompt from V1.3 to V1.4, kept for reference.
- [04-solution-development-kit/integration/V1_4_1_MIGRATION.md](04-solution-development-kit/integration/V1_4_1_MIGRATION.md) - what V1.4.1 adds to V1.4: the upgrade safety and history preservation controls.
- [04-solution-development-kit/integration/V1_4_MIGRATION.md](04-solution-development-kit/integration/V1_4_MIGRATION.md) - migrating from V1.3 to V1.4: the five macro phases and the core repo.
- [04-solution-development-kit/integration/OPTIONAL_CLAUDE_MD_NOTE.md](04-solution-development-kit/integration/OPTIONAL_CLAUDE_MD_NOTE.md) - the optional root `CLAUDE.md` note for the standalone kit.
- [04-solution-development-kit/integration/V1_1_MIGRATION.md](04-solution-development-kit/integration/V1_1_MIGRATION.md) - migrating a standalone install from V1.1.
- [04-solution-development-kit/integration/V1_3_MIGRATION.md](04-solution-development-kit/integration/V1_3_MIGRATION.md) - migrating a standalone install from V1.2 to V1.3.

## Layout Template

- [05-layout-template/](05-layout-template/) - a static UI layout template (HTML and brand assets) showing the approved shell and design tokens.
