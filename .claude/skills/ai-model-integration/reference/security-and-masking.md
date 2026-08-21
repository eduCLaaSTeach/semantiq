# Security And Masking

A model catalog concentrates two sensitive things in one place: the API keys that authorize paid calls, and a screen that echoes a request back so a human can debug it. Both need handling, and one is not a substitute for the other.

Nothing here overrides `.claude/rules/secret-handling.md`, `.claude/rules/enterprise-governance.md`, or `.claude/rules/data-governance.md`.

## The API Key Lives On The Record

The record carries its own `api_key`, entered once into a masked field and referenced in a template as `{{api_key}}`. There is no separate credential section, no reference field, and no second place a key can live.

The reason is that a key and the endpoint it authorizes are one unit. Splitting them into a record plus a pointer into a secret store adds a second thing to keep in step, a second thing to get wrong when adding a model, and a second screen to look at when a call fails with 401. Adding a model stays one form.

That decision only holds because these controls come with it, all enforced:

- **Never re-rendered.** The stored key is never returned to the browser, in any screen, in any state. The field is a password input and it renders blank on edit.
- **Blank means unchanged.** A blank key field on edit restores the previously saved key rather than clearing it. Say so in the field's help text.
- **Masked in the echo.** Every occurrence is replaced with a fixed marker before the request object leaves the engine.
- **Never logged.** Not in a log line, an export, a fixture, a seed, a commit, or an error message.
- **Encrypted at rest.** The store holding these records is sensitive by classification, per `.claude/rules/data-governance.md`. Treat a catalog backup like a credential backup.
- **System-admin only.** Managing the catalog is a privileged action, gated at the handler, not just in the navigation.

An entry with no key is legitimate: some endpoints are unauthenticated, and a project that already runs a secret store can point at it with `{{env.NAME}}` instead of typing a key, without changing the record shape.

> [!WARNING]
> Rotation is a record edit under this design, not a secret-store operation. When a key is rotated at the provider, every record using it has to be updated and re-tested. Record that in the project's rotation procedure, because nothing in the catalog will remind anyone.

Exposure is handled the same way it is anywhere else in this gateway.

> [!CAUTION]
> If a key was ever printed, committed, exported, or written to a log, treat it as exposed. Tell the developer to rotate it at the provider, and do not print the value while saying so.

## The Echoed Request

The result shape returns `request`, the method, url, headers, and body actually sent, for display and audit. It is the single most useful debugging aid in the pattern and the single easiest place to leak a credential.

Rules:

- Mask before the request leaves the engine. Never build an unmasked echo and mask it later at the view layer.
- Mask the values this call actually used: the record's key and any resolved environment secret. Replace each occurrence with a fixed marker.
- Do not mask by scanning for every configuration value the process knows. A broad mask list scrubs harmless strings that happen to match a config value, so a body field containing an ordinary word comes back as a marker and the person debugging chases a ghost. Scope the list to this call's secrets.
- Mask whole, never partially. No first four characters, no last four, no length hint. A partial mask narrows a brute force and tells a reader how long the secret is.
- Recurse into nested body structures and mask string leaves only.
- The raw response is returned as received. It came from the provider and it is what the user needs to see; it does not contain the project's secrets. If a provider echoes a credential back, that is a provider incident, and the record's own logging must not persist it.

## Access To The Catalog

The catalog is system configuration that authorizes spending. Gate it accordingly, per the role model in `.claude/rules/ui-ux-quality.md` and least privilege in `.claude/rules/enterprise-governance.md`.

- Catalog management (create, edit, delete, test) sits in the System Administration cluster, restricted to the system admin tier, gated at the handler as well as in the navigation.
- Viewing the echoed request is part of that admin surface. Masking is a display aid; authorization is the control.
- Calling a model at runtime is a different permission from managing the catalog. A feature that calls a model never reads the record's key; the engine resolves it internally and nothing hands it outward.
- Audit every create, update, delete, and test, with who and when, into the confirmed audit trail. A price change or an endpoint change is a change to what the system spends money on.
- Retire rather than delete where the project keeps history, so past call records still resolve to a record.

## Untrusted Input And Untrusted Output

Both directions are untrusted, per the AI security baseline in `.claude/rules/enterprise-governance.md`.

Inbound, when user-influenced content reaches a prompt: sanitize it and wrap it in explicit instruction boundaries. A record's body field is a template, and the single-pass substitution rule in `placeholders.md` is what stops user text from resolving further placeholders.

Outbound, when model output reaches a user: it is text, not markup. Render it as text. A result panel that shows the answer, the request, or the raw response through raw HTML injection is an XSS hole fed by a third party. Set text content, escape server-side output, and never build the result panel by string-concatenating provider data into markup.

## Logging And PII

- Log the record id and name, token counts, cost, status, duration, and the correlation id. Never the prompt text, the response content, the resolved headers, or the key.
- When personal data can reach a prompt, minimize it first: redact or tokenize identifiers rather than sending raw ones, per `.claude/rules/data-governance.md`.
- Never place real production data or PII in a test record, a fixture, or a saved example prompt.

## Do And Do Not

Do:

- Keep the key in its one masked field, reference it as `{{api_key}}`, and never render it back.
- Mask the echoed request inside the engine, scoped to this call's secrets, whole rather than partial.
- Gate the catalog to the system admin tier at the handler, and audit every change and test.
- Render provider output as text.
- Tell the developer to rotate any key that was ever printed, committed, exported, or logged.

Do not:

- Do not export, seed, or commit a key value, and do not re-render a saved one into a form.
- Do not mask against every known configuration value.
- Do not partially mask, or hint at a secret's length.
- Do not rely on masking as an access control.
- Do not log a prompt, a response, a resolved header, or a key.
- Do not render any provider text as markup.
