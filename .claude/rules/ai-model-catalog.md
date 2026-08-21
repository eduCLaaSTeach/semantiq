# AI Model Catalog Rules

Claude must build every runtime AI model integration as a configuration-driven catalog: one self-contained record per model, called through one generic engine, so adding, changing, or removing a model is a configuration change and never a code change.

Conditional: applies whenever the project calls an AI model or LLM at runtime, adds or changes a provider or model, or builds a screen that manages or exercises those calls. It does not override `.claude/rules/secret-handling.md`, `.claude/rules/schema-mcp.md`, `.claude/rules/production-readiness.md`, or the DDL restrictions.

The concrete shape is the bundled `ai-model-integration` skill at `.claude/skills/ai-model-integration/`: the record fields, the placeholder contract, the engine responsibilities, response-path semantics, cost math, credential and masking handling, a worked provider matrix, and the catalog UI. This rule is the authority; that skill is how it is implemented. Load `SKILL.md` first, then only the `reference/` file the task needs.

Lifecycle, evaluation, routing, and budget governance for shipped agent behavior stay in `.claude/rules/ai-agent-governance.md`. This rule owns the integration shape those requirements sit on top of; cross-reference rather than duplicate.

## Assume No Provider

- Do not assume the project calls a model at runtime. Confirm from `.claude/PROJECT-CONTEXT.md` or ask.
- Do not assume or mandate any provider, model id, endpoint, price, token limit, catalog storage location, secret store, or evaluation harness. Each is a confirmed fact; ask when unknown and record the answer.
- Do not carry a model id, an endpoint, or a price forward from memory or from another project. Verify each against the provider's current documentation, then prove it with a real call.

## Definition Is Data, Not Code

- Keep the endpoint, method, headers, request payload, response paths, price, and API key in a catalog record. Never in a provider-specific branch, a per-provider class, or a hardcoded payload.
- Route every model call through one adapter that reads a record and holds no provider knowledge. A second call path, client, or payload builder for a second provider is the defect this rule exists to prevent.
- Never branch on a provider name, a label, or a URL host anywhere in the call path.
- Type each body field so the payload is correct per model: a number stays an unquoted number, a boolean stays a boolean, and a structured field can carry a nested object or array. A string-only payload form breaks real APIs.
- When a provider cannot be expressed by the record shape, name the missing field and propose adding it to the record. Do not add a special case to the engine to get past it.

## Substitution Is Single-Pass And Fails Loudly

- Substitute call-time values through the placeholder set only, which is `{{prompt}}`, `{{api_key}}`, and `{{env.NAME}}`, in one left-to-right pass, and never re-scan a value that was just substituted.
- Fail the call before anything leaves the process when a placeholder cannot be resolved, naming the placeholder and the field. Never resolve an unknown or missing name to an empty string, and never send the literal placeholder.
- Escape for the destination: JSON encoding inside a structured value with the quotes matched as part of the placeholder, percent-encoding into a URL, and rejection of a line break in a header value.
- Fail the call, naming the field, when a structured field does not parse after substitution.

## The Key Lives On The Record

- Give each record one masked `api_key` field, referenced in a template as `{{api_key}}` wherever the provider wants it: an `Authorization` header, a differently named header, or a query parameter. A key and the endpoint it authorizes are one unit, so adding a model stays one form.
- Enter it once. Never re-render a saved key into a form, an export, a fixture, a log line, an error message, or a commit, and never show a partial value or a length hint.
- A blank key field on edit means unchanged, never cleared.
- Mask every occurrence in the echoed request before it leaves the engine, whole rather than partially.
- Treat the store holding these records as sensitive and encrypt it at rest, per `.claude/rules/data-governance.md`. Rotation is a record edit, not a secret-store operation, so record that in the project's rotation procedure.
- An entry with no key is legitimate. Some endpoints are unauthenticated, and a project that runs a secret store can reference it with `{{env.NAME}}` instead.

## The Response Hand-Off

- The catalog owns the provider envelope up to the content path. The calling code owns whatever the model generated inside it, because only the caller knows what it asked for.
- Never parse, validate, repair, reshape, or re-prompt the generated content inside the engine.
- Keep every response path on the record, including the error path. A hardcoded error path reports a bare status code while the message explaining it sits unread in the response.
- Always return the untouched raw response. A path that matches nothing on an otherwise successful call is its own reported outcome, not a failure and not a null answer, so "the model returned nothing" and "our path is wrong" stay distinguishable.
- Set and check the finish-reason path where the provider supplies one, so a truncated answer is not passed on as complete.

## Cost Is Part Of The Result

- Carry the per-million input and output prices and the usage paths on the record, and return tokens and cost with every call result. The currency is one project-wide constant, not a per-record field, rendered as its symbol beside each price.
- Compute cost in exact decimal arithmetic, never floating-point. Round for display only.
- Report unknown when a price or a token count is missing. Never report zero, which claims the call was free.
- Enforce the confirmed per-agent token budget and cost ceiling from `.claude/rules/ai-agent-governance.md`: check before the call, cap or escalate rather than overrun, count actual usage after, and count every retry and every fallback as its own spend.
- Do not estimate tokens when the provider reported them.

## Bounded, Retry-Safe Calls

- Give every call the project's one confirmed timeout, applied by the engine, per `.claude/rules/resilience.md`. The timeout is not a per-record field.
- Retry only under the confirmed policy, with a dedupe or idempotency key where the provider supports one, so a retry after a timeout cannot double-act or double-bill silently.
- Surface an exhausted call as a handled outcome through the project's error or result pattern. There is no fallback hop: a second call to a different model is a second price and a different answer, so choosing that is the caller's decision, not the engine's.

## Test Before A Record Becomes Callable

- Treat a record as connection configuration. It is saved only after a real call on the current values succeeds, per the test-before-save contract in `.claude/rules/ui-ux-quality.md`.
- Record the test outcome and its timestamp on the record, and reset it to untested the moment any tested field changes.
- Offer no save-anyway, skip-test, or force-save path. An entry that has never made a successful call must not be callable.
- Verify every path against the raw response of a real call rather than trusting documented shapes.

## Storage And Governance

- Confirm where the catalog lives before building it: a database table, a versioned configuration file, or the project's settings provider. Ask when unset.
- When the catalog is database-backed, take the table through the Schema MCP workflow in `.claude/rules/schema-mcp.md` like any other table: search for an existing table for the concept, show the developer the exact shape, get explicit confirmation, then propose. Never write DDL and never invent column names.
- Treat a record in use as a governed production artifact: audit every change, and keep historical call records with the cost they were computed with rather than recomputing from the current price.
- Gate catalog management to the system admin tier, at the handler as well as in the navigation, and audit every create, update, delete, and test. It is system configuration holding provider credentials, not application administration.
- Mask the echoed request inside the engine, scoped to the secrets that call actually used, masked whole rather than partially. Masking is a display aid; authorization is the control.
- Render model output as text, never as markup, per the untrusted-output baseline in `.claude/rules/enterprise-governance.md`.
- Log the record id and name, token counts, cost, status, duration, and the correlation id. Never the prompt text, the response content, the resolved headers, or the credential.

## Final Reporting

For AI model integration work, report: which catalog records were added or changed; that the definition stayed data with one engine and no provider branch; the placeholder handling and escaping applied; confirmation that no key value was re-rendered, printed, or logged; the response paths set and how a path miss is reported; the usage paths, prices, and how unknown cost is reported; the budget and ceiling impact including retries and fallbacks; the timeout and retry values applied and where they came from; the test-call result that gated the save; the catalog storage location and, when database-backed, the Schema MCP tools used and proposal IDs; the UI archetypes and states covered when a screen was built; security impact, confirming no credential, prompt text, response content, or resolved header was printed.

Final rule: if the provider, model id, endpoint, price, currency, catalog storage location, timeout, retry policy, token budget, or cost ceiling is unclear, do not guess. Ask and record the confirmed values first.
