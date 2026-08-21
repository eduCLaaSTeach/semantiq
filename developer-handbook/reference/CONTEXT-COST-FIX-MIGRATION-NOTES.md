# Context-Cost Fix Migration Notes

These changes cut how much of the kit loads into every Claude Code session, which is the main driver of plan usage across a team of developers on a top-tier model. The rules themselves do not change. Only when each file is read changes. Every safety invariant stays in force: hard-stops, secret handling, mandatory Schema MCP, no direct DDL, ask-don't-assume, the approved design system, phase gates, and the knowledge-base approval gate.

## What Changed

1. `CLAUDE.md` (repo root). Startup used to read `.claude/README.md`, the full `PROJECT-CONTEXT.md`, and all applicable files in `.claude/rules/`, which in practice meant most of the rules on every turn. Startup now reads only `hard-stops.md` and `secret-handling.md`, plus the relevant slice of `PROJECT-CONTEXT.md`. Every other rule loads from a task-gate table, only when its trigger fires. The critical invariants are summarized inline in `CLAUDE.md`, so the agent stays safe without reading the full rulebook. Final reporting is concise by default. A model-routing and cost-discipline section is added.

2. `.claude/skills/ui-ux-design/SKILL.md`. Stops duplicating the surface and spacing token tables that already live in `design-tokens.md`. Keeps the enforced constants: palette, brand, color rules, and theme switcher. Hardens the instruction to open one or two component files per screen rather than the whole `reference/` folder.

3. `.claude/rules/ui-ux-quality.md`, kept inline. A dedup of this rule was considered: removing the brand-asset and token-value blocks that also live in `design-tokens.md` and the skill, and compressing the shell collapse and expand mechanics that also live in `reference/topbar-sidenav.md`. The team chose to keep them inline. The brand palette, surface and text values, icon-style spec, and the full collapse and expand mechanics stay written in this rule for direct reference. `ui-ux-quality.md` is unchanged by this fix.

## Impact

Rough char/4 token estimates for relative comparison, not billing figures.

| Scenario | Before | After | Reduction |
| --- | --- | --- | --- |
| Always-on context at session start (every session) | ~38,400 | ~4,000 | ~89% |
| Backend / DB feature turn (schema and code rules) | ~40,000 | ~12,300 | ~69% |
| UI feature turn (list screen: shell rule, tables, search-filter, tokens) | ~71,800 | ~36,600 | ~49% |

The always-on cut applies to every session regardless of task, so it matters most. It compounds across turns, across developers, and against the top-tier model rate.

UI turns still look large because the bulk is the component reference files themselves, such as `tables.md` and `search-filter.md`, which are needed to build the screen and must load. The fix stops the other reference files and the full rulebook from loading alongside them.

## Notes On The Change

- The gating matches each rule's own trigger. Every conditional rule already states that it applies only when its condition holds, and the task-gate table follows those triggers.
- The eager startup read came from `fresh-project-gateway.md`. Its startup flow only needs to run on a fresh or unknown project, so it is now gated there rather than on every feature turn.
- The change follows the kit's own principle. `production-readiness.md` already says to pull rather than dump and to load detail only when the task reaches it. The old startup contradicted that; the new loader applies it to the bootstrap.
- Concise reporting is still faithful. The full report is the union of each rule's own reporting section, which now fire only for the gates that apply.
- `production-readiness.md` and `enterprise-governance.md` load on the CODE gate, since they apply to any delivered code change.
- Feedback logging is preserved. The three-times-repeat trigger is in the inline summary, so Claude still notices it, and the detailed template loads only when logging.

## Status In This Repo

These changes are already in this repo: the task-gated `CLAUDE.md` loader and the `ui-ux-design` skill dedup, with `ui-ux-quality.md` kept inline per item 3. They shipped across the v2026.8.x releases and travel inside the `.claude/` folder, so a consumer picks them up on the next pull. There is nothing to apply by hand here.

## Recommended Follow-Ups

- Fill the model-routing field in `PROJECT-CONTEXT.md` and make it policy: Opus (High) for architecture, schema, and hard debugging; Sonnet for feature, CRUD, UI, tests, and docstrings. This likely saves more than any file trim, because it changes the rate, not just the token count.
- Trim the empty `<ASK_DEVELOPER>` scaffolding in `PROJECT-CONTEXT.md` once the fields are answered.
- Team habits: `/clear` between unrelated tasks, batch `PROJECT-CONTEXT.md` edits to closeout to protect prompt caching, and watch `/cost` per session.

## On The Numbers

The figures are estimates, characters divided by 4, for relative comparison, not billing figures. For exact plan usage mechanics and reset windows, see https://support.claude.com.
