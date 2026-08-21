# AI Agent Governance Rules

Claude must govern the full lifecycle of any runtime LLM or agent behavior the project ships, treating each deployed agent's prompt and model selection as a governed production artifact.

Conditional: applies only when the project ships runtime LLM or agent behavior in production. It does not override `.claude/rules/secret-handling.md`, `.claude/rules/schema-mcp.md`, `.claude/rules/production-readiness.md`, or the DDL restrictions.

This file owns lifecycle, evaluation, routing, and budget governance. The AI/LLM security baseline (prompt-injection boundaries, no raw model-output rendering, model-version pinning, token/cost budgets) lives in `.claude/rules/enterprise-governance.md`; cross-reference it rather than duplicating. The integration shape a runtime model call takes - one configuration-driven catalog record per model, one generic engine, the API key on the record, response-path hand-off, per-call cost - lives in `.claude/rules/ai-model-catalog.md` and the bundled `ai-model-integration` skill; this file governs the lifecycle around it.

## Assume No Agent Stack

- Do not assume the project runs any LLM or agent at runtime. Confirm from `.claude/PROJECT-CONTEXT.md` or ask.
- Do not assume or mandate any model provider, model id, agent framework, orchestration library, prompt store, or evaluation harness. Model choices, token ceilings, cost limits, evaluation score bars, and the prompt-store location are confirmed facts; confirm and record each before relying on it.

## Prompt As A Governed Artifact

- Treat a deployed prompt as a versioned production artifact, not a string literal in code. Externalize prompts to the confirmed versioned location and load them by reference.
- Carry per-prompt metadata: owner, version, target model, last-evaluation date. Confirm the metadata shape and record it.
- A released prompt is immutable. Version-bump instead of editing in place, so rollback is a pointer change.

A prompt change and a model change are production changes. Roll either back by moving a version pointer, not by hand-editing a live artifact.

## Model Routing

- Route all model calls through one thin adapter so provider and model are a configuration change, not a code rewrite.
- Record per-task routing as data, not inline constants: selected model, max-tokens, temperature. Failover between models is a caller decision, not a hop inside the engine.
- Keep provider endpoints and credentials in configuration per `.claude/rules/enterprise-governance.md`, never in source.

The catalog record in `.claude/rules/ai-model-catalog.md` is where that routing data lives: one record per model carrying the endpoint, the typed payload, the response paths, and its own API key, read by one engine that holds no provider knowledge. Follow that rule for the record shape and the engine; keep the per-task selection and the prompt lifecycle here.

## Evaluation

Evaluate non-deterministic output in layers, matched to the output:

- Assert structured output hard: schema valid, required fields present, values in the allowed set. A structured contract that fails assertion is a failure, not a warning.
- Score subjective output against a maintained golden set using a rubric, model-as-judge, or both. The score bar is a confirmed fact.
- Pin temperature and seed where supported so evaluation is reproducible.
- Make the evaluation run a required, merge-blocking check for any prompt or model change, and ship the eval cases in the same change as the prompt or routing diff.

## Safe Agent Actions

- Validate and sanitize agent output before it touches a database, another service, or a user. Untrusted-output handling and no-raw-render follow `.claude/rules/enterprise-governance.md`.
- Make every agent step idempotent so a retry does not double-act; see `.claude/rules/resilience.md`.
- Require explicit human confirmation before any irreversible or high-impact action an agent initiates.
- Scope each agent to least-privilege tools only; never a broad or implicit tool grant.

## Data Minimization

- Minimize PII sent to a model. Prefer redaction or tokenization over raw identifiers, following `.claude/rules/data-governance.md`.
- Never log raw prompts that contain PII. Apply the same redaction before any prompt reaches a log sink.

## Budget And Observability

- Enforce a per-agent token budget and cost ceiling. Cap or escalate a call that would exceed it; never silently overrun. The budget and ceiling are confirmed facts.
- Aggregate per-agent token usage so spend is attributable.
- Log the model id, prompt version, and token usage for every call, joined to a correlation id so any output is reproducible. Propagate that id per Observability Authoring in `.claude/rules/production-readiness.md`.

## Final Reporting

For runtime AI-agent work, report: which prompts or routing entries changed, their new versions, and the metadata recorded; the evaluation layers run (structured assertions and/or golden-set scoring), the bar, and the result; whether the merge-blocking eval check passed and the eval cases shipped with the change; token-budget and cost-ceiling impact and how over-budget calls are capped or escalated; least-privilege tool scope per agent and any human-confirmation gates; data-minimization handling for PII sent to or logged from the model; security impact, confirming no secrets, raw PII prompts, or token claims were printed.

Final rule: if the runtime LLM/agent presence, model choices, prompt-store location, evaluation bar, token budget, or cost ceiling is unclear, do not guess. Ask and record the confirmed values first.
