# Request Engine

The engine turns one record plus one prompt into one call and one result. It is the only place a model call happens, and it holds no knowledge of any provider. If you can tell from the engine's source which providers the project uses, the engine is wrong.

Written in the project's confirmed stack, using its confirmed HTTP client, error pattern, and logging format. Nothing here names a language.

## Responsibilities, In Order

```text
run(record, prompt, inputs) ->
  1. gate          record is active, and its last test passed
  2. resolve       build the resolver for {{prompt}} {{api_key}} {{env.*}}
  3. build url     single-pass substitution, percent-encoded
  4. build headers single-pass substitution, reject line breaks, skip blank-name rows
  5. build body    typed fields, skip blanks, drop nothing-values, parse json fields
  6. send          bounded timeout, no retry unless the confirmed policy allows it
  7. parse         content, usage, error, finish reason, by the record's dot-paths
  8. cost          tokens times per-million price, or unknown
  9. mask          build the echoable request with the call's own secrets replaced
 10. return        one result shape, always the same shape
```

Steps 1 to 5 can fail before anything leaves the process. Fail there rather than sending a request you already know is wrong: an unresolved placeholder, a `json` field that will not parse, a missing credential, a retired record.

## The Split

Keep the engine in small pieces with one responsibility each, so the parts that decide are testable apart from the part that touches the network. This mirrors the code-cleanliness rule in `.claude/rules/production-readiness.md`.

| Piece | Does | Pure |
| --- | --- | --- |
| Substitutor | Fills placeholders in the three contexts | yes |
| Payload builder | Turns typed fields into the request body | yes |
| Sender | Performs the call, returns status, raw body, decoded body, transport error | no |
| Parser | Reads content, usage, error, finish reason by dot-path | yes |
| Cost calculator | Turns tokens plus price into cost, or unknown | yes |
| Masker | Produces the echoable request | yes |
| Engine | Orchestrates the above and returns one shape | no |

Everything except the sender and the orchestrator is a pure function of its inputs, which is what makes the whole path unit-testable without a provider.

## Building The Body

For each typed field, in order:

- Skip the field when its key or its raw value is blank.
- For a `json` field, substitute in JSON context, then parse. A parse failure fails the whole call, naming the field.
- For every other type, substitute in plain context, then convert by type: `text` stays a string, `number` becomes a numeric literal keeping whole numbers integral, `boolean` becomes a real boolean from one truthy list.
- Drop the field when the converted value is nothing. Omitting a field and sending it as null are different requests.

Define the truthy list once, in one place, and use it everywhere the engine converts a boolean. Two truthy lists in two helpers is a bug waiting for the day they disagree.

## Sending

- Apply the record's `timeout_ms` as the whole-operation bound, and its `connect_timeout_ms` when the client separates them. Values come from the confirmed resilience thresholds, per `.claude/rules/resilience.md`.
- Send the body in the encoding the record's headers declare. Do not add a header the record did not ask for.
- Return the transport error separately from the HTTP status. A timeout is not an HTTP 500, and conflating them hides which one happened.
- Do not write the request or the response to a log from inside the sender. Logging happens once, in the engine, on the masked form.

## Retry And Fallback

A model call is not free and usually not idempotent in the sense that matters here: a retry can bill twice for one logical request.

- Retry only what the confirmed policy allows, with the confirmed attempt cap, backoff, and jitter, per `.claude/rules/resilience.md`.
- Carry a dedupe or idempotency key when the provider supports one, so a retry after a timeout cannot double-act.
- Count every attempt against the budget. A retried call spent tokens twice even when the caller saw one result. See `cost-and-usage.md`.
- On exhaustion, surface a handled failure through the project's error or result pattern. Never hang and never crash silently. There is no fallback hop: a second call to a different model is a second price and a different answer, and choosing that is the caller's decision, not the engine's.

## Success And Failure

- Success is a 2xx status with no transport error. Nothing else is success.
- On failure, take the message from `error_path` first, then the transport error, then a generic status message. The record's path is what turns "HTTP 400" into the sentence that says why.
- A 2xx whose `content_path` matches nothing is not a failure and not a success with a null answer. Report it as its own outcome: the call worked, the path did not match. See `response-paths.md`.
- Failures surface through the project's established error or result pattern, never as an exception crossing the boundary uncaught.

## The Result Shape

One shape, every time, success or failure, so callers and the UI never branch on which fields exist.

```text
ok                  boolean   2xx and no transport error
status              integer   the HTTP status, or 0 when nothing was sent
content             any       the value at content_path, or nothing
content_path_hit    boolean   whether content_path matched
finish_reason       string    the value at finish_reason_path, when set
usage               object    { input, output } token counts, each possibly unknown
cost                object    { input, output, total }, or unknown
error               string    a human message when ok is false, else nothing
model_id            string    which record ran
request             object    { method, url, headers, body } MASKED, for display and audit
response_raw        string    the untouched response body
correlation_id      string    the id threaded through this call
```

Two details carry their weight:

- `content_path_hit` separates "the model returned nothing" from "our path is wrong". Without it both look like a null answer, and the wrong one gets debugged.
- `request` is the masked echo. It is what makes a broken record diagnosable without anyone reading a log or a credential. Build it from the values actually sent, not by re-rendering the template.

## Observability

Per `.claude/rules/production-readiness.md` and `.claude/rules/ai-agent-governance.md`, log once per call, structured, carrying: the correlation id, `model_key` and version, the prompt version when one applies, status, token counts, cost, duration, retry count, and whether the content path hit.

Never log the prompt text, the response content, the resolved headers, or the key. When a project needs prompt-level debugging, it stores the prompt reference and version, not the text, and applies the redaction in `.claude/rules/data-governance.md` before anything reaches a log sink.

## Testing The Engine

- Unit-test the substitutor, payload builder, parser, cost calculator, and masker as pure functions. No network.
- Cover the failure paths deliberately: unknown placeholder, unparseable `json` field, blank fields, a nothing-value field, a header value with a line break, a URL-context value with a slash, a 2xx path miss, a non-2xx with and without an error path, a transport timeout.
- Keep one contract test per provider in the catalog against a real development key, so provider drift is caught rather than discovered in production. Mock at the sender boundary everywhere else, per the test hygiene rules in `.claude/rules/production-readiness.md`.
- Prove the no-branching claim with a test that runs two records for genuinely different providers through the same engine.

## Do And Do Not

Do:

- Fail before sending when the record or the substitution is already wrong.
- Return one shape always, with the raw response reachable.
- Keep the deciding parts pure and the network part thin.
- Log once, masked, with a correlation id.

Do not:

- Do not branch on a provider name, a URL host, or a record label anywhere in the path.
- Do not repair, reshape, validate, or re-prompt the generated content inside the engine.
- Do not retry without the confirmed policy and a dedupe key.
- Do not swallow a transport error into a fake HTTP status.
- Do not log or persist the unmasked request.
- Do not let a second call path grow for "the one provider that is different". That provider's difference is a record field you have not added yet.
