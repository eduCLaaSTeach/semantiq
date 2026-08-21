# Cost And Usage

Every call reports what it spent. Cost is not a reporting feature added later; it is part of the result, because a model call is the one dependency in most systems that bills per request.

## The Calculation

```text
input_cost  = input_tokens  / 1_000_000 * cost_input_per_million
output_cost = output_tokens / 1_000_000 * cost_output_per_million
total       = input_cost + output_cost
```

Prices are per one million tokens because that is how providers publish them, so the record stores the published number and no conversion is needed when it changes.

Rules:

- Compute in exact decimal arithmetic, never floating-point, per `.claude/rules/api-design.md`. Rounding a fraction of a cent the wrong way at scale is a real number.
- Round for display only, at the confirmed precision. Keep full precision for aggregation.
- Use the project's one confirmed currency. It is a constant, not a per-record field, so two entries cannot disagree about it. Render it as its symbol beside each price.
- Count input and output separately. Output is usually the more expensive direction, so a single blended number hides where the spend went.

## Unknown Is Not Zero

Cost is unknown when either price is unset or when the usage path did not return a number. Report unknown, and render it as unknown.

Zero is a claim that the call was free. Unknown is a statement that the catalog entry is incomplete. They lead to different actions, and reporting the first when you mean the second turns a spend dashboard into a lie that trends toward zero as more entries go unpriced.

The same applies to usage. A missing usage path means the token count is unknown, not zero. Aggregate unknowns as a separate count so a spend total says how much of the traffic it actually covers.

## Getting The Tokens

Usage comes from the provider's response through `usage_input_path` and `usage_output_path`. Providers disagree on both the names and the nesting, which is exactly why the paths sit on the record. Worked examples in `provider-matrix.md`.

- Do not estimate tokens by counting characters or words when the provider reported them. An estimate that looks like a measurement is worse than a gap.
- Do not derive the input count from the prompt. The provider counts the whole assembled request, including anything the endpoint adds.
- When a provider reports extra directions, such as cached or reasoning tokens, add the paths and prices for them to the record rather than folding them into the two existing fields. Silently mixing a cheaper cached rate into the input number understates the next month.

## Budget And Ceiling

`.claude/rules/ai-agent-governance.md` requires a per-agent token budget and cost ceiling. This is where they are enforced.

- Read the confirmed budget and ceiling from `.claude/PROJECT-CONTEXT.md`. Never invent a number.
- Check before the call, using the record's max-output field as the worst case, and cap or escalate a call that would exceed the ceiling. Never silently overrun.
- Count after the call, on actual usage, and attribute it to the caller so spend is traceable to a feature rather than to "the AI".
- Count every attempt. A retry after a timeout may have been billed by the provider even though the caller saw one result, so a retried call is two counts against the budget, not one.
- Count a retry separately. A retried call spent tokens twice even when the caller saw one result.

A call blocked by the ceiling is a handled outcome through the project's error or result pattern, with a clear message. It is not an exception, and it is not a silent empty answer.

## Aggregation

Per `.claude/rules/production-readiness.md`, emit one structured record per call carrying the correlation id, the model key and version, token counts, cost, currency, duration, retry count, and the calling feature. That is enough to answer the three questions people actually ask: what did this feature cost, what did this model cost, and what changed this week.

Keep aggregation out of the engine. The engine reports one call; the metrics destination sums them.

Alert on cost-budget breach alongside the other operational alerts in `.claude/rules/operations-incident.md`. A cost runaway is an incident with a different signal, not a billing surprise at the end of the month.

## Price Changes

Provider prices change, and a stale price produces confident wrong numbers.

- There is no separate price-confirmed date. Changing a price is a record change, so `updated_at` already says when the price was last touched.
- Surface the price and that timestamp on the catalog list, so a stale entry is visible.
- Do not backfill old call records with a new price. What it cost then is what it cost.

## Do And Do Not

Do:

- Return tokens and cost on every call result.
- Report unknown when the price or the token count is missing.
- Use exact decimal arithmetic, and render the project's one currency symbol.
- Count retries against the budget.
- Log per call with the correlation id and the calling feature.

Do not:

- Do not report zero cost for an unpriced or unmeasured call.
- Do not estimate tokens when the provider reported them.
- Do not blend input and output, or two currencies, into one number.
- Do not invent a budget, a ceiling, or a rounding precision.
- Do not let a call proceed past the confirmed ceiling without capping or escalating.
- Do not aggregate inside the engine.
