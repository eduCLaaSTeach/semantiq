# Model Record

One record holds everything needed to make one call to one model. Nothing about the call lives anywhere else, so the record is the unit you add, review, test, and retire.

The field names below are concepts. Map each one to the project's confirmed naming convention (see `.claude/PROJECT-CONTEXT.md`), and when the catalog is database-backed, get the real column names through the schema workflow in `.claude/rules/database-schema.md`.

The record is self-contained. No joins, no separate keys table, no per-provider lookup table. Everything for one call sits in one row, which is what keeps both the mental model and the engine simple.

## The Fields

### Identity

| Field | Type | Required | Holds |
| --- | --- | --- | --- |
| `id` | system-assigned | yes | What calling code passes to select this entry. Assigned by the store, not typed by a user. |
| `name` | string | yes | The human label shown in the list and used to order it. Name it after the provider and model, the way someone picking it from a dropdown would recognise it (`OpenAI GPT-5.6`, `Claude Opus 5`), not after the job it does today. |
| `api_key` | string, masked | no | The provider API key for this entry, referenced in a template as `{{api_key}}`. |
| `status` | enum | yes | `draft`, `active`, `retired`. Only `active` is callable. |

There is no user-entered slug, no version counter, and no owner column. A change to a record is an update to that record; if the project needs version history, that is an audit-table concern, not a field on the form.

> [!IMPORTANT]
> Do not add a provider label, and never read one. The moment code branches on a provider name, a label, or a URL host, the pattern is broken and per-provider branching is back. Every behavior difference belongs in the other fields.

### The API Key

The key lives on the record, in one field, and is referenced as `{{api_key}}` wherever the provider wants it. That is usually an `Authorization` header, sometimes a differently named header, occasionally a query parameter.

Rules, all enforced:

- Entered once, in a masked input. Never re-rendered into the form with a saved value.
- Blank on edit means unchanged, never cleared. Say so in the field's help text.
- Masked to `***` in any echoed request shown back to the user, per `security-and-masking.md`.
- Never written to a log line, an export, a fixture, or a commit.
- Treat the store holding these records as sensitive, and encrypt it at rest per the project's confirmed data-classification handling in `.claude/rules/data-governance.md`.

An entry with no key is legitimate. Some endpoints are unauthenticated, and some take their credential from `{{env.NAME}}` instead.

### Transport

| Field | Type | Required | Holds |
| --- | --- | --- | --- |
| `method` | string | yes | The HTTP method, stored uppercase. Defaults to `POST`. |
| `url` | string template | yes | The full endpoint. May contain placeholders, since some providers put the model id in the path. |
| `headers` | list of `{key, value}` | yes | Header rows. `value` is a template. A row with a blank key is skipped. |

The call timeout is not on the record. It is one project-wide confirmed value applied by the engine to every call, per `.claude/rules/resilience.md`. One model is not meaningfully slower than another in a way a per-entry field would capture, and a per-entry timeout is one more thing to get wrong on every new record.

### Payload

`body` is an ordered list of typed fields. Each field is `{type, key, value}`, where `value` is a template.

| `type` | Sends | Notes |
| --- | --- | --- |
| `text` | a string | The substituted value as-is. |
| `number` | a numeric literal | Whole numbers stay integers, so the payload carries `1024`, not `1024.0`. |
| `boolean` | `true` or `false` | Parsed from one documented truthy list, defined once in the engine. |
| `json` | a nested object or array | The value is itself structured content, parsed after substitution. This is what lets one field carry a message array or a nested config object. |

The type is the whole reason this pattern works on real APIs. A flat key/value form sends every field as a quoted string, and a provider that expects an unquoted number, a real boolean, or a nested array rejects it or silently misreads it.

Rules for the list:

- A field with a blank key or a blank value is skipped before any work.
- A field whose value resolves to nothing is dropped from the payload, not sent as null. Sending an explicit null is a different request from omitting the field, and some providers treat it as an error.
- A `json` field that does not parse after substitution fails the call before the request goes out, naming the field.
- Field order in the record is presentation only. Do not depend on payload key order.

### Response Paths

| Field | Type | Required | Holds |
| --- | --- | --- | --- |
| `content_path` | dot-path | yes | Where the answer text sits in the response. |
| `usage_input_path` | dot-path | no | Where the input-token count sits. |
| `usage_output_path` | dot-path | no | Where the output-token count sits. |
| `error_path` | dot-path | no | Where the provider's error message sits on a failure. |
| `finish_reason_path` | dot-path | no | Where the stop reason sits, so a truncated answer is detectable. |
| `finish_reason_codes` | map | no | Which of the provider's stop values mean `complete`, `length`, `refusal`, and `content_filter`, so the engine codes the stop without a provider branch. A record with no map codes every stop `unknown`. |
| `refusal_path` | dot-path | no | Where the provider reports a refusal in a field other than the finish reason. A value there codes the stop `refusal`. |
| `answered_model_path` | dot-path | no | Where the provider reports which model actually answered. |

Keep `error_path` on the record rather than hardcoding one shape in the engine. Providers disagree about where an error message lives, and a hardcoded path silently reports "HTTP 400" instead of the message that says why. Full path semantics, and the stop outcomes `finish_reason_codes` feeds, in `response-paths.md`.

### Lifecycle

| Field | Type | Required | Holds |
| --- | --- | --- | --- |
| `provider_lifecycle_state` | enum | no | The provider's published state for this model, mapped to one coded set: `current`, `deprecated`, `retired`. |
| `retirement_date` | date | no | The date the provider stops serving this model, once announced. |

These are the provider's facts about the model, kept apart from `status`, which is the record's own state in this catalog. They are not tested fields, so changing them does not reset `last_test_status`. How they are used is in Model Lifecycle below.

### Cost

| Field | Type | Required | Holds |
| --- | --- | --- | --- |
| `cost_input_per_million` | exact decimal | no | Price per one million input tokens. |
| `cost_output_per_million` | exact decimal | no | Price per one million output tokens. |

Store money as an exact decimal or a minor-unit integer, never as a floating-point number, per `.claude/rules/api-design.md`. Cost math is in `cost-and-usage.md`.

There is no currency field. The currency is one project-wide confirmed constant, rendered as its symbol beside each price, so it cannot drift row to row. There is no price-confirmed date either: the record's `updated_at` already says when the price was last touched.

### Test And Audit

| Field | Type | Holds |
| --- | --- | --- |
| `last_tested_at` | timestamp | When a real call last succeeded against these exact values. |
| `last_test_status` | enum | `untested`, `passed`, `failed`. Resets to `untested` when any tested field changes. |
| `created_at` / `updated_at` | timestamp | Creation and last-change audit, in UTC. |

`last_test_status` is what the test-before-save gate reads, and what the list screen shows so a stale entry is visible before someone depends on it. Timestamps are timezone-aware and stored in UTC.

There is no fallback field. A failed call is a handled failure surfaced through the project's error or result pattern, not a silent second call to a different model at a different price. If a project genuinely needs failover, raise it as a routing decision under `.claude/rules/ai-agent-governance.md` rather than adding a hop to the engine.

Never set a provider's own fallback option as a body field either. An option that lets a second model answer inside the same request is the same fallback hop, moved to the provider's side.

## Model Lifecycle

Providers retire models on their own schedule, and a retired model fails every call made to it.

- Put the provider's dated snapshot model id in the record, never a moving alias. An alias can be repointed by the provider to a different model, which changes behavior and price under a record that passed its test on the old one.
- Keep `provider_lifecycle_state` and `retirement_date` current from the provider's announcements.
- Alert administrators the confirmed lead time before a retirement date, alongside the other operational alerts in `.claude/rules/operations-incident.md`.
- Replace a model with a new record, never by editing the model id in place. Test the new record, then run the full eval set against it, per Evaluation in `.claude/rules/ai-agent-governance.md`. A route moves to the new record id only after the eval set passes.
- Retire the old record once no route points at it.
- Whether the provider's terms promise notice of model changes, retirements, and incidents is a project concern for whoever owns the supplier agreement, raised once. It is not a record field.

## Worked Shape

A record for a chat-completions style endpoint, with placeholders unresolved and no real values:

```yaml
id:     <ASSIGNED_BY_THE_STORE>
name:   OpenAI GPT-5.6
status: active
api_key: <ENTERED_ONCE_IN_A_MASKED_FIELD, NEVER_SHOWN_AGAIN>

method: POST
url:    <PROVIDER_CHAT_ENDPOINT>

headers:
  - { key: Authorization, value: "Bearer {{api_key}}" }
  - { key: Content-Type,  value: "application/json" }

body:
  - { type: text,   key: model,      value: "<DATED_SNAPSHOT_MODEL_ID>" }
  - { type: number, key: max_tokens, value: "1024" }
  - { type: json,   key: messages,   value: '[{"role":"user","content":"{{prompt}}"}]' }

content_path:      choices.0.message.content
usage_input_path:  usage.prompt_tokens
usage_output_path: usage.completion_tokens
error_path:        error.message
finish_reason_path: choices.0.finish_reason
finish_reason_codes:
  complete:       [stop]
  length:         [length]
  content_filter: [content_filter]
refusal_path:      choices.0.message.refusal
answered_model_path: model

provider_lifecycle_state: current
retirement_date:          <EMPTY_UNTIL_ANNOUNCED>

cost_input_per_million:  <PRICE_IN>
cost_output_per_million: <PRICE_OUT>
```

Three real providers filled into this shape, and every place they diverge, are in `provider-matrix.md`.

## Do And Do Not

Do:

- Keep one record self-contained. Everything for one call in one place, no joins and no lookup table of per-provider quirks.
- Keep the API key in its one masked field, and reference it with `{{api_key}}` everywhere it is needed.
- Store structured request content in a `json` field rather than flattening it into several string fields.
- Re-confirm a price when the provider changes pricing, and let `updated_at` record when that happened.

Do not:

- Do not read a provider label, or any other label, to decide how to build or parse a call.
- Do not add a field nothing reads. The engine reads the call fields, the test gate reads the test fields, and the retirement alert reads the lifecycle fields. An unused field becomes a lie about how the call works.
- Do not point a record at a moving model alias, or edit a live record's model id in place to switch models.
- Do not re-render a saved API key into the form, put it in an export or a log, or show any part of it, including a length hint.
- Do not put a full connection string or a decoded token claim in any field.
- Do not let a record reference a prompt by pasting its text. A prompt is its own governed artifact under `.claude/rules/ai-agent-governance.md`; reference it by version.
