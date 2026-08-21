---
name: ai-model-integration
description: Builds AI model integration as a configuration-driven catalog instead of per-provider code - one self-contained record per model holding endpoint, headers, typed body fields, response paths, cost, and its own API key, called through one generic engine. Use when a project calls an AI model or LLM at runtime, adds or changes a provider or model, builds an AI provider catalog screen, or when the user mentions model routing, prompt payloads, response paths, token usage, or per-call cost.
---

# AI Model Integration Skill

Adds a model to a project as data, not as code. One record holds everything needed to make one call. One generic engine makes every call. Adding, changing, or removing a model is a configuration change with no code change and no deployment of new provider logic.

These are the approved rules for AI model integration. Use them by default in any project that calls a model at runtime. Deviate only when the developer explicitly asks, recorded as a documented exception in `.claude/PROJECT-CONTEXT.md`.

The binding rule is `.claude/rules/ai-model-catalog.md`. Lifecycle, evaluation, and budget governance for shipped agent behavior stay in `.claude/rules/ai-agent-governance.md`. This skill is the concrete shape those rules expect.

## How To Use This Skill (Load Only What You Need)

1. Read this file for the enforced rules and the shape of the pattern. It is deliberately small.
2. Open only the reference file the current task needs. Each one is detailed; most turns need one or two. Never read the whole `reference/` folder.
3. Before finalizing, run the Delivery Checklist at the foot of this file.

Every rule is tagged ENFORCED (follow exactly) or PRINCIPLED (a sensible default, deviation allowed with written justification).

## Reference Index - Open Only The File You Need

| Reference file | Use when |
| --- | --- |
| `reference/model-record.md` | Defining or changing what a catalog entry holds, field by field |
| `reference/placeholders.md` | Substituting the prompt, the key, or an environment value into a request |
| `reference/request-engine.md` | Building the engine that turns a record plus a prompt into one call |
| `reference/response-paths.md` | Locating the answer, usage, or error in a provider response |
| `reference/cost-and-usage.md` | Capturing tokens, computing per-call cost, enforcing a budget |
| `reference/security-and-masking.md` | Credential handling, echoed-request masking, access to the catalog |
| `reference/provider-matrix.md` | Worked entries showing how three real providers differ |
| `reference/catalog-ui.md` | Building the provider list or the add/edit form |

## Why The Pattern Exists

No single interface gives you, per model: the endpoint, the exact request payload, the response shape, and the price. Those live in different places, and the payload differs per model, not just per provider. The output-token parameter alone moves around: one provider takes `max_tokens` at the top level, another takes `max_completion_tokens`, another nests `maxOutputTokens` inside a config object. A hardcoded payload breaks on the next model.

So the variation becomes data. A record carries the endpoint, the header rows, the typed body rows, the response paths, and the price. The engine carries only generic transport. A new model is a new record.

## The Enforced Rules

- Model definition is data (ENFORCED). Endpoint, method, headers, request payload, response paths, and price live in a catalog record, never in a provider-specific code branch. No `if provider == "x"` in the call path.
- One engine (ENFORCED). Every model call goes through one adapter that reads a record and knows nothing about any provider. Two call paths for two providers is the defect this pattern exists to prevent.
- Typed body fields (ENFORCED). Each body field declares its type, so a number stays an unquoted number, a boolean stays a boolean, and a structured field can hold a nested object or array. A flat string-only form sends everything quoted and breaks real APIs. See `reference/model-record.md`.
- The key is on the record (ENFORCED). One masked `api_key` field per entry, referenced in a template as `{{api_key}}`. Entered once, never re-rendered, blank on edit means unchanged, masked in the echoed request, never logged. See `reference/security-and-masking.md`.
- One-pass substitution (ENFORCED). A resolved value is never re-scanned for further placeholders, and an unresolved placeholder fails the call before anything leaves the process. See `reference/placeholders.md`.
- The response-path hand-off (ENFORCED). The catalog owns the provider envelope up to the content path. Whatever the model generated inside that block belongs to the calling code, because only the caller knows what it asked for. See `reference/response-paths.md`.
- Raw response always available (ENFORCED). A path that matches nothing degrades to the untouched response with the miss reported, never to a crash and never to a silent null. See `reference/response-paths.md`.
- Test before save (ENFORCED). A record is connection configuration. It is saved only after a real call against the current values succeeds, per the test-before-save contract in `.claude/rules/ui-ux-quality.md`. See `reference/catalog-ui.md`.
- Cost per call (ENFORCED). Every record carries its per-million input and output price and its usage paths, so each call reports tokens and cost. Unknown cost reports as unknown, never as zero. See `reference/cost-and-usage.md`.
- Bounded calls (ENFORCED). Every call has an explicit timeout inside the project's confirmed cap, per `.claude/rules/resilience.md`. A model call is slow by nature; unbounded is not an option.

## The Responsibility Boundary

Two different things arrive in a response, with two different owners.

The envelope is the provider's wrapper around the answer. It is fixed by the provider, identical for every prompt, and the catalog owns it through the content path. The catalog's job ends when it hands over the value sitting at that path.

The content is whatever the model generated inside that block. Its shape is decided by the prompt, not the provider. Ask for a sentence and you get a sentence; ask for an object with three fields and you get three fields, from the same model through the same envelope. The calling code owns the inside, because it wrote the prompt, chose the fields, and is the only place that can validate them.

The content path is the hand-off point. Full detail in `reference/response-paths.md`.

## Storage Is Confirmed, Never Assumed

The catalog is a set of records. Where those records live is a project decision recorded in `.claude/PROJECT-CONTEXT.md`: a database table, a versioned configuration file, or the project's settings provider. Ask when it is unset.

When the catalog is database-backed, the table goes through the Schema MCP workflow in `.claude/rules/schema-mcp.md` like any other table: search for an existing table for the concept first, show the developer the exact shape, get explicit confirmation, then propose. Never write DDL and never invent column names.

The field names in `reference/model-record.md` are concepts, not required identifiers. Map them to the project's confirmed naming convention.

## Global Anti-Patterns (ENFORCED - Never)

- Branch on a provider name anywhere in the call path, or keep a second engine, client, or payload builder for a second provider.
- Hardcode an endpoint, a request payload, a response shape, or a price in source when it belongs in a record.
- Re-render a saved key into a form, or put one in an export, a fixture, a log line, or a commit.
- Re-scan a substituted value for placeholders, or resolve an unknown placeholder to an empty string.
- Send a body field as a quoted string when the API expects a number, a boolean, or a nested object.
- Return a parsed answer without keeping the raw response reachable.
- Report a cost of zero when the price or the token count is unknown.
- Save a record that has not passed a real call on its current values.
- Render provider output as markup. It is untrusted text, per `.claude/rules/enterprise-governance.md`.
- Reshape, repair, or validate the generated content inside the engine. That is the caller's job.

## Delivery Checklist

- [ ] The catalog's storage location is confirmed, and a database-backed catalog went through the Schema MCP proposal workflow.
- [ ] Every field the engine reads exists on the record, and the record shape is documented where the project keeps durable knowledge.
- [ ] One engine, no provider branches. Verified by grep for provider names in the call path.
- [ ] Placeholder resolution is single-pass, fails on an unknown name, percent-encodes into a URL, and rejects a header value containing a line break.
- [ ] Body fields are typed, blank rows are skipped, and a field resolving to nothing is dropped rather than sent as null.
- [ ] Content, usage, and error paths are per record; a path miss is reported and the raw response is returned.
- [ ] Tokens and cost are returned per call, logged with the model key and a correlation id, and counted against the confirmed budget and ceiling.
- [ ] The key is read from the record at call time, masked in any echoed request, and never logged or re-rendered.
- [ ] The project-wide timeout and the confirmed retry policy are applied, and a retry cannot double-bill silently.
- [ ] The two catalog screens follow the approved archetypes and the test-before-save gate, sit inside the Integrations group, keep each section on one row, and cover empty, loading, and error states.
- [ ] At least two records for genuinely different providers were exercised, proving the engine holds no provider knowledge.

## Deviating From A Principled Default

State the standard pattern, the proposed deviation, the rationale in two or three sentences, the domain context, and the trade-offs acknowledged. Get sign-off before building. Anything ENFORCED is not deviable, and an authorized deviation is recorded as a documented exception in `.claude/PROJECT-CONTEXT.md`, never applied silently.
