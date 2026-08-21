# Developer Handbook

Human-facing guides for developers who use this Claude gateway kit. The gateway itself lives in `.claude/`; these files explain how to work with it. Start with getting-started, reach for prompts during daily work, check guidelines for behavior, and use reference when you need field-level detail.

## Getting started

- [getting-started/HOW_TO_USE.md](getting-started/HOW_TO_USE.md) - how to use the kit: first prompt, gateway commands, rules overview, what to ask, what to store, and checklists.
- [getting-started/WORKING_WITH_CLAUDE.md](getting-started/WORKING_WITH_CLAUDE.md) - the team mindset for working with Claude.

## Prompts

- [prompts/GIT_PROMPTS.md](prompts/GIT_PROMPTS.md) - copy-ready prompts for Git and GitHub workflows: sync, stash, branch, Pull Request, promotion, and release.
- [prompts/DAY_HANDOFF_PROMPT.md](prompts/DAY_HANDOFF_PROMPT.md) - session handoff prompts (mid-session, end-of-day, and developer-to-developer), each paired with its resume prompt.
- [prompts/ACCOUNT_HANDOVER_PROMPT.md](prompts/ACCOUNT_HANDOVER_PROMPT.md) - account handover prompts for switching to a different Claude account: capture each session, write one batch index, resume from it.
- [prompts/DAY_START_PROMPT.md](prompts/DAY_START_PROMPT.md) - the daily start primer: set the model, confirm the sprint item, and enforce lazy rule loading with MCP-on-demand but always-live schema.

## Guidelines

- [guidelines/DO_AND_DO_NOT.md](guidelines/DO_AND_DO_NOT.md) - the do and do-not behavior mirror of the gateway rules.
- [guidelines/APPROVED_PLUGINS.md](guidelines/APPROVED_PLUGINS.md) - the approved-plugins list.
- [guidelines/MODEL-ROUTING.md](guidelines/MODEL-ROUTING.md) - team policy for which Claude model to pick in Claude Code (Sonnet by default, Opus for deep reasoning), the biggest lever on plan usage.

## Reference

- [reference/PROJECT-CONTEXT-UNDERSTANDING.md](reference/PROJECT-CONTEXT-UNDERSTANDING.md) - every intake field in `.claude/PROJECT-CONTEXT.md`, what it means, who answers it, and what a good answer looks like.
- [reference/CONTEXT-COST-FIX-MIGRATION-NOTES.md](reference/CONTEXT-COST-FIX-MIGRATION-NOTES.md) - what the context-cost fix changed in `CLAUDE.md`, the `ui-ux-design` skill, and `ui-ux-quality.md`, why, its verified token impact, and how to apply it.
- [reference/CREDIT-OPTIMIZATION.md](reference/CREDIT-OPTIMIZATION.md) - the canonical reference on how a Claude plan's usage is consumed across a team of developers and the three levers (model routing, lazy context, MCP-on-demand) that reduce it, with where each fix lives.

## Layout template

- [layout-template/](layout-template/) - a static UI layout template (HTML and brand assets) showing the approved shell and design tokens.
