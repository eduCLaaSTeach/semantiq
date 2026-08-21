# Claude Code Credit Optimization

How a Claude subscription plan's usage is consumed when a team of developers builds one application with Claude Code, and how to reduce it. This covers the setup it assumes, where the usage goes, what kind of usage it is, and the reduction plan, then lists where each fix lives.

## The Setup This Assumes

- A team of developers building features for one application, each owning different modules or features.
- An MCP Schema server connected so all database tables come from one shared database, which prevents duplicate or redundant tables and fields.
- Local environments with Git-hosting integration.
- A Claude subscription plan, for example Team. Each developer uses Claude Code with `claude login` (OAuth), and no `ANTHROPIC_API_KEY` is set.
- A top-tier model on a high reasoning setting, for example Opus (High), as the working default. The gateway loads the governance files.

## Billing

Every Claude Code turn is a `/v1/messages` request at the protocol level. Because developers sign in with `claude login` (OAuth) and set no `ANTHROPIC_API_KEY`, there is no separate metered pay-per-token API bill. Every turn draws down the subscription plan's usage allowance instead.

The MCP Schema connector and the Git-hosting connector are not billed separately, but they cause extra model turns: the model calls a tool, gets a result, then reasons again. Each round-trip is a billable request against the allowance. So MCP raises usage indirectly even though the connector itself is free.

## Where The Usage Goes

In order of impact:

1. A top-tier model on everything. It is the most expensive model, and a high reasoning setting adds extended-thinking tokens. Using it for routine CRUD, UI, and tests is the largest cost.
2. Always-on context re-sent every turn. If startup reads most of the rule files plus the full `PROJECT-CONTEXT.md` and README, that content rides along on every turn.
3. The governance files. `CLAUDE.md` loads each session, and if it pulls the other rule files up front, that content rides along every turn, for every developer.
4. MCP Schema tool results. `describe_table` and `list_tables` output is added to context and stays for the session, which inflates later turns.
5. Independent per-developer sessions. No context is shared across developers, so the fixed overhead is paid once per developer, on every turn.

## What Kind Of Usage It Is

Subscription plan usage allowance, measured against the plan's included usage and rate limits over rolling windows, not a per-token API invoice. With no API key in the loop, you are not consuming metered input, output, or cache token credits in the API sense.

Exact allowance mechanics, measurement, and reset windows are set by Anthropic and can change; see https://support.claude.com for current figures. The numbers here are relative estimates (characters divided by 4), not billing figures.

## How To Reduce It

Three levers, in order of impact. All three are implemented in this repo (see Where Each Fix Lives).

1. Model routing. Default to Sonnet for feature, CRUD, UI, tests, and docstrings; keep Opus (High) for architecture, schema design, and hard debugging. This changes the per-token rate, so it compounds across every turn and every developer.
2. Lazy, task-gated context. Load rule files only when the task hits their gate, instead of all upfront. This cuts always-on context sharply (see the table below).
3. MCP on demand and session hygiene. Do not call MCP unless the task touches data. Batch schema lookups, reuse verified schema within a session, `/clear` between unrelated tasks, and batch `PROJECT-CONTEXT.md` edits to closeout so prompt caching holds.

### Schema Is Verified Live, Never Time-Cached

A "cache schema for N hours" rule would undercut the reason the Schema MCP exists: it stops developers from duplicating tables and fields on one shared database. Per `.claude/rules/schema-mcp.md`, prior-session schema is potentially stale, so always re-verify against the live MCP result in the current session. Reusing what you already verified within a session is fine; a stale cross-session cache is not.

## Impact

Rough char/4 token estimates for relative comparison, not figures any specific team will reproduce exactly.

| Scenario | Before | After | Reduction |
| --- | --- | --- | --- |
| Always-on context at session start (every session) | ~38,400 | ~4,000 | ~89% |
| Backend / DB feature turn | ~40,000 | ~12,300 | ~69% |
| UI feature turn (list screen) | ~71,800 | ~36,600 | ~49% |

The Sonnet-default policy also lowers the rate on the bulk of daily work, which is usually the larger saving and does not show up in a token count.

## Where Each Fix Lives

| Fix | File | Path |
| --- | --- | --- |
| Lazy task-gated rule loading; inline safety invariants; concise reports; cost-discipline section | `CLAUDE.md` | repo root |
| UI: token values deferred to design-tokens.md; load only the needed component references | `SKILL.md` | `.claude/skills/ui-ux-design/SKILL.md` |
| Dev-time model policy: Sonnet default, Opus on demand | `MODEL-ROUTING.md` | `developer-handbook/guidelines/MODEL-ROUTING.md` |
| Daily session primer: model, MCP-on-demand, live schema | `DAY_START_PROMPT.md` | `developer-handbook/prompts/DAY_START_PROMPT.md` |

## What Each Developer Does

- Daily: run `developer-handbook/prompts/DAY_START_PROMPT.md` before coding, keep Sonnet as the default and move to Opus (High) only when reasoning is genuinely hard, `/clear` between unrelated tasks, and glance at `/cost` on long sessions.
- One-time per repo: adopt the gateway file changes above through the team's normal review flow, then each developer pulls and starts a fresh session.
- When data work happens: run `mcp__schema__find_existing_tables_for_concept` before proposing new storage, and verify tables live with `mcp__schema__describe_table` in the current session.
- When the allowance runs out and you move to another account: follow `developer-handbook/prompts/ACCOUNT_HANDOVER_PROMPT.md` before switching. Switching accounts ends every open session, so capture each chat you are transferring, tie them together with one index file, and resume from that index rather than reopening the old transcripts - a long transcript re-read on the new account starts the next allowance at a deficit.

## How To Confirm It Worked

Watch `/cost` per developer and overall plan usage over a week. If usage does not fall roughly in line with the table above, the most likely cause is model choice not actually shifting to Sonnet in practice, which is a habit gap, not a kit gap.
