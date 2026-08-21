# Resilience Rules

Claude must make integration boundaries resilient whenever a change calls a dependency the service does not control.

Conditional: applies only when code calls a dependency across a process or network boundary (a database, external API, queue, model/LLM, or another service). It does not apply to purely in-process work, and does not override `.claude/rules/secret-handling.md`, `.claude/rules/schema-mcp.md`, `.claude/rules/production-readiness.md`, or the DDL restrictions.

This file is the primary home for idempotency and retry-safety guidance; other rules cross-reference here.

## Assume No Resilience Defaults

- Do not assume any concrete timeout value, retry-attempt cap, backoff schedule, or circuit-breaker trip level. Those are confirmed facts in `.claude/PROJECT-CONTEXT.md`; ask when unknown.
- Reuse the project's confirmed resilience conventions and any existing client/wrapper that enforces them. Do not introduce a new resilience library or competing pattern without approval, per `.claude/rules/enterprise-governance.md`.

## Bounded Calls

- Give every outbound call an explicit, bounded timeout; never issue a call that can wait indefinitely.
- Use the confirmed per-dependency timeout values, and ensure the timeout covers the whole operation including connection establishment.

## Retry Safety

- Retry only operations safe to repeat. Do not retry a non-idempotent operation unless it is paired with a dedupe/idempotency key so a retry never double-acts.
- Cap the number of attempts, and space them with exponential backoff plus jitter so retries do not synchronize into a thundering herd.
- Carry a stable dedupe/idempotency key through retries and across any boundary that may deliver more than once, so the receiver can collapse duplicates. Use the confirmed key strategy; ask when unknown.

Retries are only as safe as the idempotency behind them. Before adding a retry, confirm the target is idempotent or guarded by a dedupe/idempotency key.

## Fail Fast And Isolate

- Wrap flaky dependencies so the service fails fast and recovers rather than piling up calls against a struggling dependency.
- Isolate resource pools (connections, threads, concurrency limits) per dependency so one slow dependency cannot starve the rest.

## Fallback Behavior

- Define explicit behavior for a dependency being down: degrade to a reduced result, queue for later, or return a clear, actionable error.
- Never crash silently and never hang. A failed dependency surfaces as a handled outcome through the project's established error/result pattern.

## Async And Multi-Step Flows

- Route work that keeps failing past its attempt cap to a dead-letter path instead of retrying forever.
- Keep each step idempotent so the flow is safe to replay from a known point without duplicating side effects.
- Provide a replay path so dead-lettered work can be reprocessed once the issue is fixed.

## Cross-References

- Correlation-id propagation lives in `.claude/rules/production-readiness.md` under Observability Authoring.
- Least-privilege access to dependencies and the AI/LLM security baseline live in `.claude/rules/enterprise-governance.md`.

## Final Reporting

For resilience work, report: which boundaries were hardened and the failure modes considered (timeout, dependency down, duplicate delivery); the timeout, retry-attempt cap, backoff, and breaker settings applied and where their values came from; how idempotency/dedupe was guaranteed for any retried or replayable operation; the fallback behavior chosen and any dead-letter/replay path added; risks, assumptions, and manual follow-up.

Final rule: if a dependency's timeout, retry policy, idempotency strategy, breaker thresholds, or fallback expectation is unclear, do not guess. Ask and record the confirmed values in `.claude/PROJECT-CONTEXT.md` first.
