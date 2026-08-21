# API And Interface Contract Rules

Claude must design and evolve service interfaces against one project-confirmed set of conventions whenever a change exposes or consumes a service interface.

Conditional: applies only when the project exposes or consumes a service interface, any paradigm (request/response, RPC, query-graph, message-based). It does not override `.claude/rules/secret-handling.md`, `.claude/rules/schema-mcp.md`, `.claude/rules/production-readiness.md`, or the DDL restrictions.

## Assume No Interface Paradigm

- Do not assume the project has a service interface. Confirm from `.claude/PROJECT-CONTEXT.md` or ask.
- Do not assume or mandate any interface paradigm, naming style, error format, version scheme, header names, page-size cap, field-naming style, or contract-spec tool. Those are confirmed facts; ask when unknown.
- Follow ONE confirmed set of conventions consistently. Do not invent a new per-endpoint shape, error format, or naming style for a single operation.

## Consistent Surface

- Use a consistent resource/operation naming and addressing convention across the whole interface, matching the confirmed paradigm.
- Apply bounded, consistent pagination, filtering, and sorting to every collection response, with a maximum page-size cap so a single request cannot be unbounded. Confirm and record the cap; do not invent one.
- Make the transport result code truthfully reflect the outcome. Never return a success code with an error body, or an error code for a successful operation.

## Single Error Contract

- Use a single machine-readable error contract across all endpoints.
- Every error response carries a correlation id; propagate it per the Observability Authoring guidance in `.claude/rules/production-readiness.md`.
- Error responses leak no internals: no stack traces, SQL/query text, secrets, internal hostnames, or PII in any body or detail. An error response is still an external boundary; the same secret-handling and data-governance limits apply.

## Retry-Safe Mutations

- Mutating operations that may be retried must be made safe through a client-supplied idempotency/dedupe key. The key requirements live in `.claude/rules/resilience.md`; follow them.
- Keep naturally idempotent operations idempotent. Do not give a repeatable read or replace operation a side effect that makes repetition unsafe.

## Boundary Authentication

- Validate each inbound request's credential at the boundary (signature, issuer, audience, expiry) using the confirmed credential format. Do not assume the format; confirm or ask.
- Distinguish unauthenticated (no valid credential) from authorized-but-insufficient (valid credential, lacking permission) in the result code.
- Apply least privilege at the interface per `.claude/rules/enterprise-governance.md`. Do not print or persist credentials, tokens, or decoded claims.

## Lossless Serialization

- Serialize losslessly. Never represent money or exact-precision values as floating-point; use the confirmed exact-decimal or minor-unit representation.
- Use timezone-aware timestamps in the confirmed serialized format; do not emit an ambiguous local time.
- Use one consistent field-naming style, mapped from internal naming in the data-access layer rather than leaking internal names. Confirm and record the style.

## Compatible Evolution

- Additive changes are backward-compatible by default; adding an optional field or new operation must not break existing consumers.
- A breaking change requires both a new version under the confirmed version scheme AND a deprecation window with explicit sign-off before the old shape is removed. Confirm and record the scheme and window.
- The party changing a shared or published contract owns notifying its consumers. Do not ship a breaking change to a shared contract silently.

## Single Source Of Truth

- Author the contract in one authoritative machine-readable spec.
- Generate clients and types from that spec rather than hand-maintaining both sides, so they cannot drift. Use the confirmed spec tool; ask when unknown.

## Final Reporting

For interface work, report: the paradigm, naming/addressing convention, and field-naming style used; the error contract and correlation-id propagation; pagination/filtering/sorting bounds and the page-size cap; which mutating operations carry an idempotency/dedupe key; boundary authentication and the unauthenticated-vs-insufficient distinction; whether the change was additive or breaking, and the version/deprecation/sign-off path if breaking; the authoritative spec touched and what was generated; security impact, confirming no internals, secrets, or PII leaked.

Final rule: if the paradigm, naming convention, error format, version scheme, credential format, page-size cap, field-naming style, or spec tool is unclear, do not guess. Ask and record the confirmed values first.
