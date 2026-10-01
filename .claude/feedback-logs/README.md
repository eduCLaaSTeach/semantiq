# Feedback Logs

Automatic feedback logs the gateway records to improve itself. Behavior is defined in `.claude/rules/feedback-logging.md`.

## What lands here

When a developer keeps pressing the same underlying request about three times in a session without it being satisfied (repeated intent, not the same literal prompt), Claude writes a structured log here describing what happened and why it was flagged, then commits and pushes it so the team can trace and review it.

Each log is a Markdown file named `YYYY-MM-DD-HHMMSS-<short-slug>.md`. See the template and field list in `.claude/rules/feedback-logging.md`.

## How to use these logs

Review periodically to find recurring friction:

- group logs by "Suspected root cause" to spot systemic gaps
- turn frequent "Suggested improvement" entries into changes to the gateway rules, docs, or commands
- watch which goals repeatedly go unmet to prioritize work

## What these logs contain

- The developer's own identity (name, email, OS username) for attribution.
- Paraphrased descriptions of the repeated requests, session context, suspected root cause, and improvement suggestions.

## What they must never contain

Secrets, tokens, credentials, full connection strings, private keys, `.env` values, customer records, production data, or third-party PII. Prompts are paraphrased and sensitive content is redacted with placeholders. This folder is committed and pushed, so treat its contents as publishable to anyone with repository access, and keep repository access appropriate to that.
