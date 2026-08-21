# Model Routing For Claude Code

This is a developer-tooling policy: which Claude model each developer selects in Claude Code. It is the largest single lever on a Claude subscription plan's usage, because it changes the rate per token, not just the amount of context. It is separate from the application's runtime model routing, which lives in `.claude/PROJECT-CONTEXT.md` under AI/LLM feature conventions and governs the deployed product.

## Default To Sonnet

Use the current Sonnet model as the everyday default. It handles most gateway work well:

- Feature and CRUD implementation against a known stack
- UI screens from the approved design system
- Tests, fixtures, and test-data wiring
- Docstrings and code documentation
- Refactors within a known module
- Git, PR, and branch work, and release mechanics
- Reading and summarizing code, docs, and schema
- Routine bug fixes with a clear repro

## Escalate To Opus (High)

Move to Opus (High) only for work where deeper reasoning changes the outcome, then switch back to Sonnet once that step is done:

- Architecture and design decisions: layer boundaries, integration seams, trade-offs
- Schema design and non-trivial data modeling before a `propose_table_change`
- Hard debugging where the cause is unclear after a first honest attempt
- Ambiguous, cross-cutting changes that touch several modules at once
- Reviewing a high-risk change (security, auth, migrations) before merge

A simple test: if you can describe the change in one clear sentence and the path is obvious, use Sonnet. If you are still working out how to approach it, use Opus (High) for that stretch, then go back to Sonnet to build it.

## Why This Matters

When a team runs independent Claude Code sessions with no shared context, all on a top-tier model, every turn re-sends the session's context at the most expensive rate. Defaulting to Sonnet for the bulk of work, and keeping Opus (High) for the small share that needs it, saves more than any context trim, because it compounds across turns and across developers.

## Habits That Pair With This

- `/clear` between unrelated tasks so stale reads do not ride forward.
- Batch `PROJECT-CONTEXT.md` edits to session closeout so prompt caching is not broken mid-session.
- Glance at `/cost` during long sessions to see what is using the allowance.

## Note On Exact Usage Figures

Model tiers, usage measurement, and reset windows are set by Anthropic and can change. This policy is about relative cost discipline. For exact plan mechanics see https://support.claude.com.
