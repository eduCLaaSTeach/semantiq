# Provider Matrix

Three real providers filled into the same record shape. This file exists to show how much diverges, so nobody reaches for a shared payload builder or a provider branch again.

These are worked examples, not defaults. The project's providers, endpoints, model ids, and prices are confirmed facts in `.claude/PROJECT-CONTEXT.md`. Model ids and prices change often, so they are placeholders here; the header names, payload structure, and response paths are the durable part and are written out.

> [!NOTE]
> Verify every value against the provider's current documentation before entering a record, then prove it with a real test call. A matrix in a document is a starting point, never authority.

## The Differences At A Glance

| Aspect | Provider A (chat completions style) | Provider B (messages style) | Provider C (generate-content style) |
| --- | --- | --- | --- |
| Auth header | `Authorization: Bearer {{api_key}}` | `x-api-key: {{api_key}}` | `x-goog-api-key: {{api_key}}` |
| Extra required header | none | an API version header | none |
| Where the model id goes | body field `model` | body field `model` | in the URL path |
| Output-token field | `max_completion_tokens` on newer models, `max_tokens` on older, `max_output_tokens` on the responses-style endpoint | `max_tokens`, top level | `maxOutputTokens`, nested in a config object |
| Prompt container | `messages` array of role/content objects | `messages` array of role/content objects | `contents` array of role/parts objects |
| Content path | `choices.0.message.content` | `content.0.text` | `candidates.0.content.parts.0.text` |
| Input tokens path | `usage.prompt_tokens` | `usage.input_tokens` | `usageMetadata.promptTokenCount` |
| Output tokens path | `usage.completion_tokens` | `usage.output_tokens` | `usageMetadata.candidatesTokenCount` |
| Error path | `error.message` | `error.message` | `error.message` |

Count the rows that differ. Nine of eleven, and four of them differ inside the request payload. That is why the payload is data.

The output-token field alone moves three times inside one provider's own product line. A hardcoded payload does not survive that provider's next model, let alone a second provider.

## Provider A - Chat Completions Style

```yaml
method: POST
url:    <PROVIDER_A_CHAT_ENDPOINT>

headers:
  - { key: Authorization, value: "Bearer {{api_key}}" }
  - { key: Content-Type,  value: "application/json" }

body:
  - { type: text,   key: model,                 value: "<MODEL_ID>" }
  - { type: number, key: max_completion_tokens, value: "1024" }
  - { type: json,   key: messages,              value: '[{"role":"user","content":"{{prompt}}"}]' }

content_path:      choices.0.message.content
usage_input_path:  usage.prompt_tokens
usage_output_path: usage.completion_tokens
error_path:        error.message
```

The output-token field name depends on the model generation. Older models take `max_tokens`, reasoning models take `max_completion_tokens`, and the responses-style endpoint takes `max_output_tokens`. It is a record field, so all three are one edit apart.

## Provider B - Messages Style

```yaml
method: POST
url:    <PROVIDER_B_MESSAGES_ENDPOINT>

headers:
  - { key: x-api-key,         value: "{{api_key}}" }
  - { key: <VERSION_HEADER>,  value: "<API_VERSION>" }
  - { key: Content-Type,      value: "application/json" }

body:
  - { type: text,   key: model,      value: "<MODEL_ID>" }
  - { type: number, key: max_tokens, value: "1024" }
  - { type: json,   key: messages,   value: '[{"role":"user","content":"{{prompt}}"}]' }

content_path:      content.0.text
usage_input_path:  usage.input_tokens
usage_output_path: usage.output_tokens
error_path:        error.message
finish_reason_path: stop_reason
```

Two things to note. The key is the whole header value, with no `Bearer` prefix, which is why the header value is a template rather than a fixed scheme plus a key field. And the required API version header is a plain row, not a special case in the engine.

Content arrives as a list of blocks, and a reasoning model can put a non-text block first, so `content.0.text` can miss on one call and hit on the next. Report it as a path miss and show the raw response, per `response-paths.md`. Do not add block-searching to the engine.

## Provider C - Generate-Content Style

```yaml
method: POST
url:    <PROVIDER_C_HOST>/v1beta/models/<MODEL_ID>:generateContent

headers:
  - { key: x-goog-api-key, value: "{{api_key}}" }
  - { key: Content-Type,   value: "application/json" }

body:
  - { type: json, key: contents,         value: '[{"role":"user","parts":[{"text":"{{prompt}}"}]}]' }
  - { type: json, key: generationConfig, value: '{"maxOutputTokens":1024}' }

content_path:      candidates.0.content.parts.0.text
usage_input_path:  usageMetadata.promptTokenCount
usage_output_path: usageMetadata.candidatesTokenCount
error_path:        error.message
finish_reason_path: candidates.0.finishReason
```

Three structural differences in one entry. The model id is in the URL path, not the body, so it arrives as a config placeholder and is percent-encoded on substitution. There is no top-level token limit; it is nested inside a config object, which is only expressible because the field is typed `json`. And the prompt sits two levels down, in `parts`, inside `contents`.

An engine that assumed a `messages` array and a top-level token limit cannot express this entry at all. That is the concrete failure the typed-field record avoids.

## What This Proves

- Auth is a header row, so a scheme prefix, a bare key, and a vendor-specific header name are all the same feature.
- Model selection is sometimes a body field and sometimes a URL segment, so both must be templatable.
- The token limit's name and nesting vary, so the payload must carry nesting.
- Response location varies in depth and in list indexing, so paths must be per record.
- Usage field names vary in both naming style and nesting, so usage paths must be per record too.

Every one of those is a record field. None of them is a code branch.

## Adding A Fourth Provider

1. Read the provider's current request and response documentation.
2. Fill the record shape from `model-record.md`. Nothing new should be needed; if something is, stop and raise a record-shape change rather than adding a special case to the engine.
3. Enter the API key once in the record's masked key field and reference it as `{{api_key}}` wherever the provider wants it.
4. Run a real test call and read the raw response to confirm every path, rather than trusting the documented shape.
5. Confirm the prices and record the date they were confirmed.
6. Save only after the test passes, per the test-before-save gate in `catalog-ui.md`.
7. Add a contract test against a development key so provider drift is caught, per `request-engine.md`.

If a new provider truly cannot be expressed by the record shape, that is a finding worth surfacing: name the exact field that is missing, propose adding it to the record, and get it approved. Adding a provider branch to the engine to get past it undoes the pattern for every entry.
