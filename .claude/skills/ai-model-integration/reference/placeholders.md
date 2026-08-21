# Placeholders And Substitution

A record is a template. Placeholders are the only way a call-time value reaches the request, and substitution is where injection bugs live, so the rules here are exact.

Syntax is `{{name}}`. A name is made of letters, digits, underscore, and dot, so `env.OPENAI_API_KEY` is one name and not a path expression.

## The Placeholder Set

| Placeholder | Resolves to | Allowed in |
| --- | --- | --- |
| `{{prompt}}` | The caller's prompt text for this call. | body values |
| `{{api_key}}` | The record's own `api_key` field. | header values, url, body values |
| `{{env.NAME}}` | A named value from the project's confirmed environment or configuration source. | url, header values, body values |

Three names, nothing else. There is no placeholder for the current time, a random value, a database lookup, or arbitrary code. A caller that needs one of those computes it and passes it in the prompt or sets it as an environment value.

`{{api_key}}` is the one that surprises people: the key is not fetched from anywhere, it is the value the admin typed into the record's masked key field. One field, referenced wherever the provider wants it.

> [!IMPORTANT]
> A name outside this set does not resolve. Fail the call and say which name and which field, rather than sending the literal `{{name}}` or a blank.

## The Substitution Rules (ENFORCED)

1. One pass, left to right. Scan the template once. A value that was just substituted is never re-scanned. This is the guard that stops a prompt containing `{{api_key}}` from resolving to the key.
2. An unknown name fails the call. Report which name and which field, before any request leaves the process. Never resolve an unknown name to an empty string, and never leave the literal `{{name}}` in the outgoing request. A silently empty prompt is a call that costs money and returns nonsense.
3. A known name that resolves to nothing is a different case. When an environment value is genuinely absent, that is a configuration failure and follows the boot-validation rule in `.claude/rules/production-readiness.md`, not a blank substitution.
4. Escape for the context you are substituting into. Three contexts, three rules, below.
5. Never log the template after substitution. The resolved request contains the key; only the masked form is safe to show or store. See `security-and-masking.md`.

## The Three Contexts

### JSON Context (A `json` Or Nested Body Value)

The value being built is a JSON document, so the substituted value must be valid JSON at that position.

- A quoted placeholder, `"{{prompt}}"`, is matched including its surrounding quotes and replaced whole by the JSON encoding of the value. The encoder supplies the quotes and all escaping, so a prompt containing a quote, a backslash, a newline, or a brace stays safe and stays intact.
- A bare placeholder, `{{prompt}}`, is replaced by the JSON literal of the value. A string becomes a quoted JSON string; a number stays a number.
- Substitute first, then parse. If the result is not valid JSON, fail the call and name the field. Do not send a half-built document.

Matching the quotes as part of the placeholder is the detail that makes this safe. Replacing only what is inside the quotes forces you to hand-escape, and hand-escaping is where prompt-driven JSON breakage comes from.

### URL Context

- Percent-encode every substituted value. A model id, a region, or a value that reaches the path or query string must not be able to introduce a `/`, a `?`, an `&`, or a `#`.
- Reject a resolved value that would change the host or scheme. A placeholder fills a segment or a parameter, never the origin.

### Header Context

- Insert the value verbatim, with no encoding, because a header value is not a JSON or URL context.
- Reject a resolved value containing a carriage return or a line feed. That is header injection, and it is the one check a header context needs.
- A header row whose key is blank is skipped.

## Worked Examples

A prompt with awkward characters, substituted into a `json` body field:

```text
record value : [{"role":"user","content":"{{prompt}}"}]
prompt       : She said "use {{api_key}}" then hit enter\n
sent         : [{"role":"user","content":"She said \"use {{api_key}}\" then hit enter\\n"}]
```

The quotes are escaped by the encoder, the literal braces from the prompt survive as text, and they are not re-scanned. The prompt reached the model unchanged, the payload stayed valid, and the key did not leak into it.

The key in a header, and the same header in the echoed request:

```text
record value : Bearer {{api_key}}
sent         : Bearer <the key from the record, never written down>
echoed to UI : Bearer ***
```

A provider that wants the key under its own header name, or in the query string:

```text
header  : { key: x-api-key, value: "{{api_key}}" }
url     : <PROVIDER_HOST>/v1/models/<MODEL_ID>:generate?key={{api_key}}
```

Same placeholder, three positions, no engine change.

## Do And Do Not

Do:

- Fail loudly on an unresolved placeholder, naming the field.
- Match the quotes when substituting a string into JSON, and let the encoder do the escaping.
- Percent-encode into a URL and reject line breaks in a header.
- Put the key in the record's key field once and reference it as `{{api_key}}` wherever it is needed.

Do not:

- Do not re-scan a substituted value, and do not loop substitution until nothing changes.
- Do not hand-escape a value for JSON.
- Do not resolve an unknown or missing name to an empty string.
- Do not let a placeholder set the scheme, host, or full URL.
- Do not add a placeholder that reads the filesystem, the database, or the clock directly.
- Do not paste the key into a header row as a literal. That is what `{{api_key}}` exists to prevent, and a literal key in a header row is a key in an export.
- Do not log, echo, or persist the substituted request in unmasked form.
