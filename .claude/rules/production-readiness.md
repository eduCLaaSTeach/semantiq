# Production Readiness Rules

Claude must make changes production ready before delivering them as complete.

## Required Behavior

- Read existing code, docs, and knowledge-base context before editing. Read `.claude/PROJECT-CONTEXT.md` and ask for missing stack, hosting/deployment, database, MCP, or validation details first.
- Never assume unclear requirements or operational details; ask until the developer has confirmed enough to proceed safely.
- Verify applicable rules and source files before changing behavior. Preserve the repository's existing stack, framework, architecture, naming, formatting, and test conventions.
- Keep changes small, targeted, reviewable. Do not introduce new dependencies, services, queues, databases, build tools, deployment requirements, or architecture changes without approval.
- Validate all external input at boundaries. Use safe framework/database APIs instead of ad hoc string concatenation for queries, commands, templates, or paths.
- Keep secrets out of source code, logs, generated docs, and chat summaries.
- Handle expected failures through the established error/result pattern.
- Add or update tests for behavior changes. Run available validation and state exact results. Do not treat work as bug-free without validation evidence; if validation cannot run, state why and provide exact follow-up commands or deployment verification steps.

## Context And Retrieval Discipline

- Pull, do not dump. Search to locate material, then open the specific file/section narrowly. Do not read the whole repository for a scoped task.
- Progressive disclosure: load detail only when the task reaches it. Do not pre-load docs, schemas, or large files "just in case".
- Checkpoint when the conversation grows long. Persist state to the right tier per `.claude/rules/knowledge-base.md` (working-memory log for in-flight state, `.claude/PROJECT-CONTEXT.md` for confirmed intake facts; knowledge-base files only through that rule's approval gate), and summarize long output instead of carrying it verbatim.
- Prefer stable references (file paths, symbol names, ticket/PR IDs) over re-pasting large blobs.

## Formatter And Linter Authority

- The confirmed formatter and linter configuration is the authoritative source of code style. Conform to it instead of hand-styling or re-arguing preferences; if they disagree with your preference, the formatter wins.
- Running the confirmed formatter and linter is part of validation. Apply them and report the result.

Layer-boundary and dependency-direction rules are not style; they live in `.claude/rules/enterprise-governance.md`.

## Integration Safety

- Keep intentionally incomplete functionality out of the active code path, or inert until finished and verified: disable it by default, gate it, or leave it unwired rather than merging a half-built behavior into the shared path.
- Small, reviewable, and self-validated is necessary but not sufficient; a tidy change can still be unsafe if it wires unfinished work into the live path.
- Choose the confirmed gating mechanism. A feature flag is one example; the project decides whether flags, configuration, build exclusion, or non-registration is right.

## Configuration As A Contract

Applies only when the change reads runtime configuration (environment variables, a config file, or a settings provider).

- Enumerate every configuration value a service reads in a central config contract, and ship a checked-in example file listing variable names with placeholders only. No undocumented variable: a value the code reads but the contract and example file omit is a defect. The example-file path and config library are confirmed facts; ask when unknown, and do not invent variable rows.
- Validate required configuration on boot. Refuse to start with a clear message naming the missing/malformed value rather than degrading silently or failing deep in a later request.
- Resolve configuration by one consistent, documented precedence order (for example injected process environment over a local dev file over safe built-in defaults). Record the confirmed precedence and apply it everywhere.
- Any built-in default must be non-secret and safe to ship. Secrets never have a hardcoded default; a missing secret is a boot failure, not a fallback.
- Evolve configuration additively: adding a variable is non-breaking but documented in the same change; renaming or removing one is breaking and must be announced to dependents.
- Secrets are not configuration. The contract documents names and shapes only; values live in the secret manager. Follow `.claude/rules/secret-handling.md`; use placeholders such as `<TOKEN>` and `<DATABASE_NAME>`.

## Testing Contract

- When the project declares a minimum coverage bar, respect it and report against it. Also declare what is exempt (for example generated code or thin pass-through adapters) so the bar is unambiguous.
- Write tests for a behavior change and confirm they pass before the change is claimed done or proposed for merge. Prefer test-first where practical.
- Require tests when present for: core/business logic; public API or contract behavior; authentication and authorization paths including negative cases (missing credential, insufficient scope); and, where applicable, migration apply and rollback paths.
- Treat the confirmed CI gate (tests passing, coverage met, lint and build clean) as the merge contract and report against it.

Test hygiene: each test creates and tears down its own data (no shared mutable fixtures or prior-test residue); use only synthetic data, never production data or real PII; isolate shared test-database state per developer or branch; mock external dependencies at their adapter boundary, and keep a small contract test against a real development instance to catch drift.

## Observability Authoring

When writing or editing code that emits telemetry:

- Emit logs in the confirmed structured logging format. Route metrics and traces to the confirmed destination.
- Follow the confirmed health and readiness endpoint conventions. A readiness check should reflect the health of the dependencies the service needs, not merely that the process is up.
- Never log secrets or PII; follow `.claude/rules/secret-handling.md`.

Correlation-id propagation is owned here:

- Generate a correlation id at the system edge when an incoming request lacks one.
- Thread it through every downstream hop, including into and across any agent or model calls, so the whole path shares one id.
- Emit the id in every structured log line and include it in every error response, so a request can be traced end to end.

## Code Cleanliness

- One responsibility per file or module; split a unit that has grown to serve several unrelated concerns.
- Push side effects to the edges and keep core logic pure where practical, so the deciding parts are testable apart from the parts that touch the outside world.
- Leave no dead code (unreachable branches, unused symbols, abandoned helpers) and no commented-out code blocks; version control preserves history.
- Do not leave a TODO in place of doing a small thing; just do it. Surface genuinely large follow-ups to the developer explicitly.

## Completeness Bars

- For any data-driven path, handle the empty, error, and loading states, not only success.
- A flow or screen that ignores its empty, error, or loading states is unfinished, not done.

## Validation Order And Fallback

Extends "Run available validation commands" above.

- When checks can run, run them in order (build, then type/static checks, then lint and format, then tests) and fix what breaks before moving on. Use the confirmed commands.
- When checks cannot run, self-review your diff: confirm imports/exports resolve and every referenced symbol exists and matches its use.
- For a UI change, walk first-paint, interaction, error, loading, empty, then small-screen, and confirm each is handled. See `.claude/rules/ui-ux-quality.md`.

Do not claim done if a validation step was skipped. State plainly what is done and what is pending.

## Not Complete Until

A task is not complete until either validation ran successfully, or validation could not run and the reason plus exact follow-up command is documented. Never claim tests passed unless they were actually run.
