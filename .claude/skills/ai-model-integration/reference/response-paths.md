# Response Paths And The Hand-Off Boundary

This is the design principle the whole pattern rests on. It defines where the catalog's responsibility stops and the calling code's begins.

## Two Things, Two Owners

A model response contains two different things.

The envelope is the provider's wrapper: `choices.0.message.content`, `content.0.text`, `candidates.0.content.parts.0.text`. It is fixed by the provider, identical for every prompt, and documented by the provider. The catalog owns it, through `content_path`. The catalog's job ends when it hands over the value sitting at that path.

The content is whatever the model generated and put inside that block. Its shape is decided by the prompt, not the provider. Ask for a sentence and you get a sentence. Ask for an object with three fields and you get three fields. Same model, same envelope, different inner shape, because the request was different. The inner shape is a property of the prompt, so the catalog cannot own it and should not try.

The calling code owns the inside, because it wrote the prompt, it chose the fields, and it is the only place that knows what a correct answer looks like. It parses and validates the content where it uses it, and it pins the shape at request time using the provider's structured-output or JSON-mode feature rather than hoping.

Stated plainly: from the outside in to the content path, the catalog controls it. Inside the content block, the caller controls it. `content_path` is the hand-off point.

> [!IMPORTANT]
> Do not add content parsing, schema repair, retry-on-bad-shape, or field extraction to the engine. Each one moves prompt-specific knowledge into shared transport, and every caller then inherits one caller's assumptions.

When the content arrives and fails the caller's validation, the caller may make one repair retry with a note saying what failed. That retry lives in the caller, is counted as its own call, and is described in `orchestration.md`. The engine never repairs.

## Dot-Path Semantics

A path is segments joined by dots, resolved against the decoded response.

- A non-numeric segment reads a named member.
- A numeric segment indexes a list, zero-based.
- Any missing segment ends the walk and the path does not match.
- A path never executes anything: no wildcards, no filters, no expressions, no functions. It is a walk, and that is deliberate.

```text
choices.0.message.content            -> list index, then two named members
usage.prompt_tokens                  -> two named members
candidates.0.content.parts.0.text    -> two list indexes on one path
```

An empty or unset path means "do not look", which is different from a path that looked and missed.

## The Paths

| Path | Read | When it misses |
| --- | --- | --- |
| `content_path` | Always, on a 2xx | Report the miss, return the raw response |
| `usage_input_path` / `usage_output_path` | Always | Usage is unknown, so cost is unknown (see `cost-and-usage.md`) |
| `error_path` | On a non-2xx | Fall back to the transport error, then a generic status message |
| `finish_reason_path` | When set | Finish reason is unknown; do not infer one |
| `answered_model_path` | When set | The answering model is unknown; record the requested one only |

Keep `error_path` per record. Providers put the message in different places, and a hardcoded path means every failure reads as a bare status code while the sentence explaining it sits in the response, unread.

## The Path Miss

A 2xx response whose `content_path` matches nothing is its own outcome. The call succeeded, the money was spent, and the answer is somewhere the record does not point.

- Report it distinctly. `ok` stays true, `content` is empty, and `content_path_hit` is false. Collapsing that into a null answer means "the model said nothing" and "our path is wrong" look identical, and the wrong one gets investigated.
- Always return the untouched response body. That is what lets someone read the real shape and correct the path without a code change or a log dive.
- Surface the raw body in the UI on a miss, so the fix is one edit to the record. See `catalog-ui.md`.
- Never fabricate content. Not from the raw body, not by guessing another path, not by scanning for the longest string.

This is the failure mode that degrades instead of breaking, and it is why the raw response is part of the result shape rather than a debug flag.

## Reasoning And Multi-Block Responses

Some models return more than one content block, and the first block is not always the text. A reasoning model may put a thinking or tool block at index 0, so a path pinned to index 0 misses on that call and hits on the next.

Handle it honestly rather than cleverly:

- Treat it as an intermittent path miss, reported as one, with the raw response shown.
- Prefer a request that pins the response shape, using the provider's structured-output or JSON-mode option set as a body field on the record.
- When a provider genuinely needs block selection, that is a record field the catalog does not have yet. Raise it as a change to the record shape rather than adding a search loop to the engine.

Recording the behavior on the record's notes, so the next person is not surprised, is part of the entry being complete.

## Truncation

A truncated answer usually returns a 2xx and a content path that hits, so nothing looks wrong until the text stops mid-sentence.

Set `finish_reason_path` and check it. When the reason says the output limit stopped generation, treat the result as incomplete and let the caller decide, rather than passing a cut-off answer along as a clean success.

## Stop Outcomes

Three stop reasons are their own coded outcomes, read from `finish_reason_path` or `refusal_path`, never guessed from the text:

| Outcome | Means | The result |
| --- | --- | --- |
| `refusal` | The model declined to answer | Not content. Not repaired and not retried |
| `content_filter` | The provider's filter stopped generation | Not content. Not repaired and not retried |
| `length` | The output limit stopped generation | Incomplete. The caller decides; it is never passed on as complete |

- Providers use different stop values. Which of a record's values mean `complete`, `refusal`, `content_filter`, and `length` is data on the record, per `model-record.md`, so the engine codes them without a provider branch.
- A normal finish is coded `complete`, and only when the record's map lists the value under `complete`. A stop value the record does not map is coded `unknown`, never read as a clean finish.
- A record with no `finish_reason_codes` map, or no `finish_reason_path`, codes every stop `unknown`. The result reports it as `unknown`, and the caller never assumes the answer is complete.
- Where a provider reports a refusal in a field other than the finish reason, the record's `refusal_path` points at it, and a value there codes the stop `refusal`. That is a record field, never a provider branch.
- A refusal or a content-filter stop that arrives mid-stream discards the partial output. It is never saved, logged, or kept in history, and text already shown is retracted, per Streaming Moderation Is A Confirmed Choice in `.claude/rules/ai-guardrails.md`.
- A refusal from a model used as a guardrail check is a block verdict, per `.claude/rules/ai-guardrails.md`.
- The repair retry in `orchestration.md` is for content that arrived and failed validation. It does not apply to these three.

## Do And Do Not

Do:

- Own the envelope, hand over the content.
- Keep every path on the record, including the error path.
- Report a path miss as a path miss, and keep the raw response reachable.
- Pin the content shape at request time when the caller needs structure.
- Code a refusal, a content-filter stop, and a length stop as their own outcomes.

Do not:

- Do not parse, validate, repair, or reshape the generated content in the engine.
- Do not treat a 2xx path miss as a null answer or as a failure.
- Do not hardcode any path, including the error path, in the engine.
- Do not add wildcards, filters, or expressions to the path syntax.
- Do not guess a path, or fall back to searching the response for something that looks like an answer.
- Do not pass a truncated answer on as a complete one.
- Do not keep the partial output of a refusal or a content-filter stop.
- Do not repair the content, or re-prompt for a better answer, inside the engine. The repair retry belongs to the caller.
