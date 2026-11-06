# AI Agent Governance Rules

Claude must govern the full lifecycle of any runtime LLM or agent behavior the project ships, treating each deployed agent's prompt and model selection as a governed production artifact.

Conditional: applies only when the project ships runtime LLM or agent behavior in production. It does not override `.claude/rules/secret-handling.md`, `.claude/rules/database-schema.md`, `.claude/rules/production-readiness.md`, or the DDL restrictions.

This file owns lifecycle, evaluation, routing, and budget governance. The AI/LLM security baseline (prompt-injection boundaries, no raw model-output rendering, model-version pinning, token/cost budgets) lives in `.claude/rules/enterprise-governance.md`; cross-reference it rather than duplicating. The integration shape a runtime model call takes - one configuration-driven catalog record per model, one generic engine, the API key on the record, response-path hand-off, per-call cost - lives in `.claude/rules/ai-model-catalog.md` and the bundled `ai-model-integration` skill; this file governs the lifecycle around it.

The checks that sit between a user's input and the model, and between the model's output and the place it lands, live in `.claude/rules/ai-guardrails.md`: the guardrail pipeline, untrusted content, retrieval scope, the output check, limits, and the kill switch. This file assumes them and does not restate them.

## Assume No Agent Stack

- Do not assume the project runs any LLM or agent at runtime. Confirm from `.claude/PROJECT-CONTEXT.md` or ask.
- Do not assume or mandate any model provider, model id, agent framework, orchestration library, prompt store, or evaluation harness. Model choices, token ceilings, cost limits, evaluation score bars, and the prompt-store location are confirmed facts; confirm and record each before relying on it.

## Prompt As A Governed Artifact

- Treat a deployed prompt as a versioned production artifact, not a string literal in code. Externalize prompts to the confirmed versioned location and load them by reference.
- Carry per-prompt metadata: owner, version, target model, last-evaluation date. Confirm the metadata shape and record it.
- A released prompt is immutable. Version-bump instead of editing in place, so rollback is a pointer change.

A prompt change and a model change are production changes. Roll either back by moving a version pointer, not by hand-editing a live artifact.

## Prompt Structure

- Build every prompt from the same parts, in the same order: a fixed persona or role, then the per-step task, the output schema, and the quality rubric the output is judged by.
- Put each input in its own labeled section, named for what it holds, so the model and a reviewer can tell the task from the material it works on.
- User text and all other untrusted content, such as uploads, retrieved chunks, tool results, and stored memory, go in as data inside explicit boundaries, never in the persona or the task, per Untrusted Content Is Data in `.claude/rules/ai-guardrails.md`.
- The persona, the task, the schema, and the rubric are all part of the versioned prompt. Code fills the labeled input sections and nothing else.
- The catalog has one prompt placeholder, `{{prompt}}`, per `.claude/rules/ai-model-catalog.md`. The value it carries is built from these labeled sections in order: the instruction sections the versioned prompt owns, then the data sections, each wrapped as data. "Never in the persona or the task" means never inside an instruction section. A separate instruction placeholder is not part of the catalog contract today. Adding one is a change to that contract for every record, never a provider branch.

## Model Routing

- Route all model calls through one thin adapter so provider and model are a configuration change, not a code rewrite.
- Record routing per step or task as data an administrator can change, not as inline constants. Each route points at catalog record ids, never at a provider name or a free-text model name, and carries its max-tokens and, where the model accepts them, its temperature and seed.
- A route takes one of three shapes: the main model alone, a fast model for a cheap or latency-sensitive step, or dual drafting plus a judge. Which shapes the project allows, the default route for each step, and the catalog records a step may use are confirmed facts recorded in `.claude/PROJECT-CONTEXT.md`. The live route is data an administrator changes on screen, never copied back into that file.
- A route whose record is not active, or has not passed its test call, refuses to run. It never picks another record on its own, and a dual step with one unusable draft record refuses rather than running single.
- A route change moves production traffic, so it is gated on a recorded eval result, per Evaluation below.
- Failover between models is a caller decision, not a hop inside the engine.
- A routing change is audited like any other guardrail settings change, per Changes Are Audited in `.claude/rules/ai-guardrails.md`.
- Keep provider endpoints and credentials in configuration per `.claude/rules/enterprise-governance.md`, never in source.

The catalog record in `.claude/rules/ai-model-catalog.md` is where the call itself is defined: one record per model carrying the endpoint, the typed payload, the response paths, and its own API key, read by one engine that holds no provider knowledge. Follow that rule for the record shape and the engine; keep the per-step selection and the prompt lifecycle here.

## Multi-Call Orchestration

- One step may make more than one model call: dual drafting with a judge, a critique pass, a repair retry, or a pre-draft of the next step. Each is a caller-level pattern built above the one engine, never inside it. The concrete shapes are in `.claude/skills/ai-model-integration/reference/orchestration.md`.
- Every pattern is optional, and the patterns a project allows are confirmed and recorded in `.claude/PROJECT-CONTEXT.md`. None is on by default.
- Every call in every pattern is a separate counted spend against the confirmed budget and ceiling, and the whole step is checked against its worst case before its first call.
- The engine never gains a fallback hop. A second model is the caller's choice, made in a route, never a retry the engine decides on its own.
- A judge or a critique compares and edits drafts. It never decides whether the result is accepted; see Safe Agent Actions.

## Evaluation

Evaluate non-deterministic output in layers, matched to the output:

- Assert structured output hard: schema valid, required fields present, values in the allowed set. A structured contract that fails assertion is a failure, not a warning.
- Score subjective output against a maintained golden set using a rubric, model-as-judge, or both. The score bar is a confirmed fact.
- Pin temperature and seed only where the model accepts them, so evaluation is reproducible. Some current models reject one or both; for those, the parameter is not sent, and reproducibility rests on repeated evaluation runs instead.
- Make the evaluation run a required, merge-blocking check for any prompt or model change that ships in code, and ship the eval cases in the same change as the prompt or routing diff.
- A route changed at runtime on a screen has no merge to block, so it is gated on a recorded eval result instead. Record each result, pass or fail, with its timestamp, per step, catalog record, and prompt version. The routing screen offers only the records whose triple passed, and the server checks again on save and refuses the rest.
- Keep an attack corpus inside the eval set: direct jailbreaks; injection through uploads, emails, and retrieved content; data exfiltration attempts; prompt leakage; tool misuse; and encoded and multilingual variants of each. Every case uses synthetic payloads only, per `.claude/rules/data-governance.md`.
- Refresh the attack corpus from incidents, red-team findings, and user feedback, so an attack that worked once becomes a regression case.
- Run red teaming on the confirmed cadence, by people who did not build the feature. Every finding that holds becomes an eval case.

## Safe Agent Actions

- Validate and sanitize agent output before it touches a database, another service, or a user. Untrusted-output handling and no-raw-render follow `.claude/rules/enterprise-governance.md` and `.claude/rules/ai-guardrails.md`.
- Make every agent step idempotent so a retry does not double-act; see `.claude/rules/resilience.md`.
- Require explicit human confirmation before any irreversible or high-impact action an agent initiates.
- Scope each agent to least-privilege tools only; never a broad or implicit tool grant.
- Authorize every tool call in code against the requesting user's own feature grants and record scope, per the role and access model in `.claude/rules/ui-ux-quality.md`, and run it as that user. Never run a tool call as a broad service identity, and never let the model's choice of tool stand in for the grant check.
- The decision to accept, apply, or sign off on generated content never comes from a model that drafted it, a judge or critique model included. Deterministic code or a person makes it.
- One server-side accept check decides whether content may be accepted or applied, and every path runs it: a button, a chat command, a voice command, and an API call. A path that skips it is a second accept path, and a defect.
- A manual sign-off is gated by a checklist the server evaluates, each item at one of two levels: blocking, which prevents the sign-off until it is resolved, and warning, which is shown and recorded but does not prevent it. The items and their levels are confirmed per feature.
- Apply a batch of suggested fixes one fix at a time, each through the same validation a single fix takes. A fix that fails validation is skipped, never forced, and the result lists every skipped fix with its reason. Take a snapshot before the batch, so one step undoes the whole batch.
- The confirmation for an irreversible or high-impact action shows the concrete action in deterministic terms, read from the call the action will make: the recipient, the amount, the records affected. Never the model's summary of what it is about to do.
- Tell the user when a guardrail acted on their request, in general terms, per `.claude/rules/ai-guardrails.md`.

## Grounding

- An answer drawn from company data cites the retrieved source it came from, by a reference the application can resolve and show.
- Give the model an explicit "I do not know" path, in the prompt and in the output schema. That answer is a valid outcome, not a failure.
- Check each cited quote against its source in code. A claim whose quote cannot be found in the source is withdrawn before the answer is shown.
- Scale verification to the impact of the decision the answer supports. An answer that feeds a high-impact decision gets stronger checks, up to human review. Which features need which level is a confirmed fact.

## Data Minimization

- Minimize PII sent to a model. Prefer redaction or tokenization over raw identifiers, following `.claude/rules/data-governance.md`.
- Never log raw prompts that contain PII. Apply the same redaction before any prompt reaches a log sink.

## Budget And Observability

- Enforce a per-agent token budget and cost ceiling. Cap or escalate a call that would exceed it; never silently overrun. The budget and ceiling are confirmed facts.
- Aggregate per-agent token usage so spend is attributable.
- Log the model id, prompt version, and token usage for every call, joined to a correlation id so any output is reproducible. Propagate that id per Observability Authoring in `.claude/rules/production-readiness.md`.

## User Feedback

- Every AI output offers a rating with a coded reason, and a report-a-problem action tied to the call's correlation id.
- The reasons are a codified reference set, per `.claude/rules/semantic-data-model.md`, never free text alone.
- A report goes to a human review queue with a confirmed owner. It never lands only in a log.
- Feedback rows are persisted data under gates D and SEM, with their classification and retention confirmed per `.claude/rules/data-governance.md`.
- Reviewed feedback feeds the eval set: a confirmed bad output becomes an eval case.

## AI Inventory

- Keep an inventory of every AI feature: its models as catalog record ids, its prompts and their versions, its data sources, its tools, its owner, and its risk level.
- In a project that uses the solution development kit, the inventory is `doc/corerepo/09_AI_COMPONENT_REGISTER.md`, kept current per `.claude/rules/solution-development-kit.md`. Otherwise it lives in the confirmed location recorded in `.claude/PROJECT-CONTEXT.md`.
- Update it when an AI feature is added or retired, or when its models, prompts, data sources, tools, or owner change.
- The risk-level scale is a confirmed fact. Do not invent one.

## Final Reporting

For runtime AI-agent work, report: which prompts or routing entries changed, their new versions, the catalog record ids each route points at, and the metadata recorded; that each prompt follows the persona, task, schema, and rubric structure with labeled input sections; the multi-call patterns used per step and the worst-case call count checked against the budget; the evaluation layers run (structured assertions and/or golden-set scoring), the bar, and the result; whether temperature and seed were pinned or the model rejects them; the attack corpus cases added or run and the red-team cadence; whether the merge-blocking eval check passed and the eval cases shipped with the change; the eval result recorded per step, catalog record, and prompt version for any runtime route change, and that the routing screen and the server both check it; token-budget and cost-ceiling impact and how over-budget calls are capped or escalated; least-privilege tool scope per agent, that every tool call is authorized in code and runs as the requesting user, and any human-confirmation gates and what they show; the accept check, every path that runs it, and confirmation that no drafting model decides acceptance; the sign-off checklist and its levels; batch-fix skip reporting and undo; the grounding citation and quote check, and the "I do not know" path; the feedback rating, reasons, and review queue owner; the AI inventory entry added or updated; data-minimization handling for PII sent to or logged from the model; security impact, confirming no secrets, raw PII prompts, or token claims were printed.

Final rule: if the runtime LLM/agent presence, model choices, the route per step, the multi-call patterns allowed, prompt-store location, evaluation bar, red-team cadence, token budget, cost ceiling, feedback queue owner, or inventory location is unclear, do not guess. Ask and record the confirmed values first.
