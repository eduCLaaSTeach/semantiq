# Feedback Logging Rules

This gateway gathers its own improvement feedback automatically. When a developer keeps hitting the same wall, Claude records a structured feedback log so the team can later improve the gateway kit. This is automatic feedback gathering, not a chat with the developer. Logs go to `.claude/feedback-logs/` and are pushed to the remote so they are traceable.

## When To Log

Log when, within a session, the developer has pressed the same underlying request or goal about three times without it being satisfied. Count repeated intent, not repeated text: the developer rephrases or re-asks the same need because previous attempts did not land.

Also log when the friction is unmistakable with fewer repetitions: the developer corrects the same misunderstanding two or more times; expresses clear frustration that a need is unmet ("this still is not what I asked", "I already told you", "why does it keep doing X"); or abandons a goal after repeated failed attempts.

Do not log normal, healthy work: legitimate step-by-step refinement where each request is a genuinely new step; the developer exploring options or changing their mind; a single restated request that Claude then satisfies.

Use judgment around the count (roughly two to four); the signal is repeated unmet intent, not an exact number. Log at most once per distinct unmet intent per session; if it recurs, update the existing log rather than duplicating.

## Capture Silently, Never Ask Permission

Do not ask for permission to log and do not announce it as a question. Capture it as a side effect; you may briefly mention that you recorded a feedback note, but logging never waits on approval. When the developer's true goal is unclear, you may ask one short, non-blocking clarifying question to enrich the log ("so I capture this correctly, what outcome were you expecting?"), and write the log regardless of whether they answer.

## Capture The Developer Identity

Record who the developer was, from non-secret sources already available. Use the session's EOD developer first when one has been given, per `.claude/rules/eod-reporting.md`, since they said who they are. Otherwise fall back to `git config user.name` and `git config user.email`, or the OS username when readily available (`$env:USERNAME`, or `whoami`). The developer's own name and email are attribution, not customer PII, and are allowed. Treat the fallbacks as weak: on a shared plan or a shared machine setup they can name the team rather than the person, so record what they resolved to without asserting it is the developer. If no identity is resolvable, record "unknown" rather than guessing.

## What To Capture

Keep enough context that someone reading only the log later understands what happened and how to improve the gateway. Use this template:

```md
# Feedback Log: <short title of the unmet need>

- Log ID: <YYYY-MM-DD-HHMMSS-slug>
- Captured at: <YYYY-MM-DD HH:MM local> (<UTC>)
- Trigger: repeated-intent x<N> | <other friction signal>

## Developer
- Name: <git user.name or unknown>
- Email: <git user.email or unknown>
- OS user: <username or unknown>

## Repository
- Repo / remote: <repo name or remote>
- Branch: <branch>
- Gateway commit: <HEAD short sha>

## What we were doing
<1 to 3 sentences: the task in progress and the state of the session>

## What the developer was trying to achieve
<the underlying goal in the developer's terms, not the literal prompt>

## The repeated requests
1. <paraphrased attempt 1 - secrets and sensitive data redacted>
2. <paraphrased attempt 2 - reworded but the same intent>
3. <...>
(about three, showing how the wording changed while the intent stayed the same)

## Why this was flagged
<why these attempts are the same underlying intent repeated, and the friction observed>

## What the agent did and where it fell short
<what Claude attempted each round and the gap that left the need unmet>

## Suspected root cause
<gateway rule gap | ambiguous instruction | missing capability | agent misunderstanding | tooling limit | unclear docs | other>

## Suggested improvement to the gateway kit
<one or more concrete, actionable suggestions>

## Status
<resolved | unresolved | workaround> - <one line>
```

## Privacy And Secrets

These logs are committed and pushed, so they must be safe to publish to anyone with repository access.

- Never put secrets, tokens, credentials, full connection strings, private keys, `.env` values, decoded claims, customer records, or production row data in a log. This does not override `.claude/rules/secret-handling.md`.
- Paraphrase the developer's prompts; do not paste raw prompt text that may contain sensitive data. Redact anything secret-like with a placeholder such as `<TOKEN>` or `<DATABASE_NAME>`.
- The only personal data permitted is the developer's own name, email, and OS username. Do not capture third-party PII.

## Where And File Naming

- Write each log as its own Markdown file in `.claude/feedback-logs/`, named `YYYY-MM-DD-HHMMSS-<short-slug>.md` so files sort chronologically and stay unique. Get the time from the system clock when an exact timestamp is needed.
- Do not edit or delete prior logs except to update the matching log when the same unmet intent recurs in the same session.

## Persist And Push

After writing the log file:

1. Stage and commit it per `.claude/rules/git-branching-release.md` and the repository's commit-identity requirements, with a clear message such as `chore(feedback-log): <short slug>`.
2. Push it so the feedback is traceable. Prefer the team's designated location (for example a dedicated `feedback-logs` branch); if none is designated, push to the current working branch.
3. If commit-identity verification or the push cannot complete, keep the written log file in place and tell the developer it is pending push, with the exact follow-up command.

Writing to `.claude/feedback-logs/` is pre-approved in `.claude/settings.json`, so capture does not prompt for permission.

## Final Reporting

When a feedback log was written, note it briefly in the final summary: the log file path, the trigger, and whether it was pushed. Do not reproduce sensitive prompt content.
