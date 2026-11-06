# AI Guardrails Rules

Claude must put a confirmed, layered set of checks between every user-influenced input and the model, and between every model output and the place it lands, so a feature that calls a model cannot be turned against its users, its data, or its budget.

Conditional: applies whenever user-influenced input reaches a model at runtime, or model output reaches a user, a tool, or another system. It does not override `.claude/rules/secret-handling.md`, `.claude/rules/database-schema.md`, `.claude/rules/production-readiness.md`, or the DDL restrictions.

The concrete shape is the bundled `ai-model-integration` skill's `.claude/skills/ai-model-integration/reference/guardrails.md`: the ordered pipeline, word normalization, the small-talk shortcut, the relevance classifier contract, strikes, verdict rows, settings, limits, streaming modes, the dry run, the kill switch, annotate mode, and the tests. This rule is the authority; that reference is how it is implemented.

Lifecycle, evaluation, routing, and budget governance stay in `.claude/rules/ai-agent-governance.md`. The integration shape every model call takes, guardrail calls included, stays in `.claude/rules/ai-model-catalog.md`. Output encoding and the audit trail stay in `.claude/rules/secure-coding.md`. This rule owns the checks that sit between them; cross-reference rather than duplicate.

## Assume No Guardrail Stack

- Do not assume the project calls a model at runtime, or that any guardrail layer already exists. Confirm from `.claude/PROJECT-CONTEXT.md` or ask.
- Do not assume or mandate any moderation provider, classifier model, threshold, label set, fail mode, streaming mode, strike setting, limit, or quarantine. Each is a confirmed fact, recorded in the AI Guardrails section of `.claude/PROJECT-CONTEXT.md`; ask when unknown.
- `.claude/PROJECT-CONTEXT.md` records the confirmed defaults, the allowed ranges, and the allowed shapes. The live values an administrator changes on the guardrail screens, the word lists among them, are data in the application, never copied back into that file.
- Every model call a guardrail makes, a moderation check or a relevance classifier, goes through the catalog engine per `.claude/rules/ai-model-catalog.md` and counts against the confirmed budget. A guardrail is never a second call path.

## The Pipeline Runs Cheapest First

Run the checks in this order, and stop at the first one that blocks, so a costly check never runs on a message a cheap one already refused:

1. Hard limits: the kill switch, a paused user, and the limits below.
2. The small-talk shortcut: a message that is only a greeting or a thanks gets a canned reply and no model call.
3. The length cap.
4. The rules layer: spam patterns, ban words, and known injection phrasings.
5. The moderation check.
6. The relevance classifier.
7. The model call itself.
8. The output check, before anything the model produced lands anywhere.

- Every layer returns one of three verdicts: pass, a hit carrying a coded category, or unavailable. Unavailable is never read as pass; it takes the fail mode below.
- Match ban words on normalized text, whole words only, after removing the terms on an allow-list of legitimate topics, so a medical, legal, or security subject is not refused for its vocabulary.
- The word list and the allow-list are project data an administrator manages. The kit ships no word list, and no word list lives in code.
- Configure the checks as data merged over safe defaults held in code. The server validates every setting against its allowed range and caps the size of every list, per `.claude/rules/secure-coding.md`. A value outside its range is refused on save, and a stored value that no longer validates falls back to its default with a warning to administrators, never to off.
- A blocked message tells the user it was not processed and why in general terms. It never names the word or pattern that matched, which would teach the way around it.

## Untrusted Content Is Data

- Uploaded files, text read from images, retrieved chunks, tool results, fetched pages, and stored model memory reach the model as data, never as instructions. Place them in a separate, labeled part of the request, each carrying its source and trust level, and never in the system or instruction part.
- The catalog has one prompt placeholder, `{{prompt}}`, per `.claude/rules/ai-model-catalog.md`. The value it carries is built from labeled sections: first the instruction sections the versioned prompt owns, then the data sections, each wrapped as data. "Never in the instruction part" means never inside an instruction section. A separate instruction placeholder is not part of the contract today. Adding one is a change to the catalog contract, made for every record, never a provider branch.
- Wrap user text in explicit boundaries as well, per the untrusted-content baseline in `.claude/rules/enterprise-governance.md`.
- Screen retrieved and tool content for injection, the same as user input. A hit drops that content from the request, records the verdict, and tells the user that part of the source was left out.
- An instruction found inside data never widens what an agent may do. Tool access stays least privilege and is decided by code, per Safe Agent Actions in `.claude/rules/ai-agent-governance.md`.

## Retrieval Respects Access

- Filter retrieval by the requesting user's own record scope and tenant in the data layer, before any chunk reaches the model, using the same query scope as the records the content came from, per the role and access model in `.claude/rules/ui-ux-quality.md`. A prompt instruction is not a filter, and neither is filtering the answer afterwards.
- Resolve the tenant from the application's own store, keyed on the user's id, per the role and access model in `.claude/rules/ui-ux-quality.md`.
- An embedding, a vector index entry, and stored model memory carry the classification of their source, per `.claude/rules/data-governance.md`. A delete, a retention purge, or a change of who may see the source reaches them too. A stale index entry is a leak with a delay.
- Confirm who may add content to the retrieval store. Record each entry's provenance: its source, who added it, and when. Give administrators a path to remove an entry and re-index. Content from an unvetted source is untrusted content, above.

## Prompts Hold No Secrets

- Assume the system prompt, the tool schemas, and every instruction the application sends will leak to the user. Write them so the leak costs nothing.
- Never put a secret, a credential, an internal hostname, or customer data in a prompt, per `.claude/rules/secret-handling.md`.
- Never put authorization logic in a prompt. "Answer only administrators" is not access control; the check runs in code before the call.
- A canary string, a unique marker whose appearance in output shows the prompt leaked, is an optional project choice, recorded when used. A canary in the output is an output check hit.

## Output Is Checked Before It Lands

- Run the confirmed output check on model output before a user sees it, or a tool or another system receives it, except in the confirmed retractable mode, where the check runs on the stream and shown text can be taken back.
- Render model output as text, per `.claude/rules/secure-coding.md`. That also means:
  - never render an image from model output automatically, a Markdown image included, because the image URL can carry conversation data to any host;
  - render a link only when its destination is on the confirmed allow-list, or traces to a source the application supplied rather than one the model generated;
  - never fetch a URL, load a resource, or call a service because the output named it.
- Validate and encode output for its destination like any other input before it reaches a query, a command, a path, a URL, a header, an email, or a tool argument, per `.claude/rules/secure-coding.md`. Strip control characters. Response content is never logged, per `.claude/rules/ai-model-catalog.md`.
- Never return log probabilities, token-level scores, hidden reasoning, or the raw provider response to a client. The admin-tier test screens `.claude/rules/ai-model-catalog.md` allows are the only exception.

## Streaming Moderation Is A Confirmed Choice

A feature that streams model text to a user runs one of two modes, confirmed per feature:

| Mode | How it works | Cost |
| --- | --- | --- |
| Buffered | Output is held until the output check passes, then shown | Slower to the first word |
| Retractable | Output streams as it arrives while the check runs on the stream; the UI takes back text already shown and replaces it with a notice | Needs a UI that can retract |

- Retracted text is never kept: not saved, not logged, and not in the conversation history.
- A refusal or a content filter stop from the provider mid-stream discards the partial output the same way.
- Never stream text to a user past a check that runs only on the saved copy.

## Strikes And Pauses

- A blocked input counts as a strike against the user who sent it, weighted by category. Abusive content counts more than an off-topic message. The weights and the threshold are confirmed facts.
- Only a content verdict counts: the categories the project codes for what the user wrote, such as abusive, injection, off-topic, and spam. A refusal by the kill switch, a pause, a limit, or the length cap is never a strike. It is still recorded as an outcome, coded apart from the content verdicts, so a user who hits a limit is never paused for it.
- Content dropped from a retrieved document or a tool result is not the user's strike, because the user did not write it.
- Reaching the threshold pauses the user's access to AI features for the confirmed time. The pause ends on its own, or an administrator resumes it early. The rest of the application keeps working.
- A paused user sees a plain message naming the pause and when it ends.
- Each strike and each pause is a row, with its history kept per `.claude/rules/semantic-data-model.md`, never a counter overwritten in place.

## Fail Mode Is Decided, Then Visible

- Each check has a confirmed fail mode for when it is unavailable: fail closed, which blocks, or fail open, which passes with the gap recorded.
- Two cases are fixed and are not a project choice. A refusal from a screening model is a block verdict, never an unavailable one. Injection screening for an agent that holds write tools fails closed.
- A classifier label outside the confirmed set takes the check's fail mode. It is never read as allow.
- A degraded check is never silent. Record an outcome row for every message it passed unchecked, show administrators a warning on the guardrail screens while it lasts, and alert per `.claude/rules/operations-incident.md`.

## Limits And Budgets

- Enforce the confirmed limits: a per-user rate, a per-record cap, and a global daily budget in calls, tokens, and cost, with an alert at the confirmed percent.
- Cap the whole workflow, not only one call: steps, tool calls, output tokens, input size, and concurrent runs. A loop of cheap calls is an expensive workflow.
- Check every limit before any model call, the guardrails' own calls included. A refused call is a handled outcome and is recorded.
- Answer an exceeded limit with a plain message that names the limit and when it resets, with `429` where a rate or a budget is used up, and `423` where the user is paused or the per-record cap is reached. Show the reset time in the confirmed reporting time zone.
- The money math, the ceiling check, and counting retries stay in `.claude/skills/ai-model-integration/reference/cost-and-usage.md`.

## What Is Stored

- A message blocked as abusive keeps verdict metadata only: the category, a keyed hash of the text, and its length. The text itself is not stored, not logged, and not sent anywhere else.
- The hash is keyed, an HMAC whose key lives in the secret store and does this one job, never a plain hash. A plain hash of a short message can be reversed by hashing guesses.
- A message blocked for any other category may keep a short excerpt.
- Keeping blocked abusive text in a quarantine is a confirmed project decision, off unless recorded. A quarantine is restricted to its own grant, enforced at all four layers, carries a short confirmed retention, and every read of it is audited, per `.claude/rules/secure-coding.md`.
- Every verdict from every layer is a row: one row per layer verdict per message attempt.
- Verdict, strike, pause, and quarantine rows carry their own confirmed classification and retention, per `.claude/rules/data-governance.md`, and are persisted data under gates D and SEM.

## Measure The Guardrails

- Track per layer and category: the block rate, false positives from appeals and sampled review, false negatives from red teaming and incidents, latency, and cost. Send them to the confirmed metrics destination, per `.claude/rules/production-readiness.md`.
- Annotate mode records a check's verdicts and blocks nothing. It applies only to a check added to a feature whose pipeline already enforces, or to a changed threshold. That check enforces only after its verdicts were reviewed, and the switch is a settings change like any other. The annotate-mode policy is a confirmed fact.
- Annotate mode never applies to a feature's baseline layers, to injection screening for an agent that holds write tools, or to treating a screening refusal as a block. Those are locked to enforce.

## Kill Switch

- Every AI feature has its own kill switch, and one global switch covers them all. Ship no AI feature without one.
- The switches are data, effective on the next request, with no deployment.
- Each feature has a confirmed non-AI fallback path and a user message shown while it is off.
- Who may flip a switch is a confirmed fact. That owner holds the tier that reaches the screen's cluster, per Who Manages Them below.

## Admin Dry Run

- An administrator may test a sample message against the settings on the screen, saved or not, and see each layer's verdict.
- A dry run records no strike and writes no verdict row, and its sample text is not stored.
- The dry run is itself an admin action: it leaves an audit record without the sample text, and its model calls count against the budget.

## Changes Are Audited

- Every change to guardrail settings, limits, word lists, allow-lists, kill switches, and model routing is an audit record per `.claude/rules/secure-coding.md`, carrying what it was, what it became, and the authority the actor held. A refused change is recorded too.
- The same holds for an administrator resuming a pause, reading a quarantine, and running a dry run.

## Disclosure Is A Confirmed Fact

- Whether users must be told they are dealing with AI, how AI content is labeled, and any regulatory marking obligation are confirmed facts recorded in `.claude/PROJECT-CONTEXT.md`. Never assume none applies, and never assume which regime applies.
- When the requirement is unconfirmed, ask. AI content does not reach users before it is confirmed.

## Who Manages Them

- The AI screens sit in three places unless `.claude/PROJECT-CONTEXT.md` records otherwise. Every AI configuration screen, the guardrails, word lists, limits, routing, kill switches, and the AI providers, sits in the System Administration `AI` group, system admin only. AI usage, guardrail events, and a confirmed quarantine sit in Compliance > AI Activity and are view-only: `View`, and an audited `Export` at most, never an action that changes state. AI Pauses and AI Feedback, the two screens that act on what they show, sit in Application Administration > AI Oversight, admin and above.
- Each screen is its own feature in the registry with its own grant, gated at the handler as well as in the navigation, at all four layers, per `.claude/rules/ui-ux-quality.md`.
- A confirmed owner of a screen, such as the kill switch owner or the feedback queue owner, holds the tier that reaches that screen's cluster. Where the owner does not, the screen moves to a cluster the owner can reach. A screen that acts never moves into Compliance, whose AI screens are view-only, and System Administration is never widened to a lower tier to fit one.
- The screens are specified in `.claude/skills/ai-model-integration/reference/ai-admin-ui.md`.

## References

- OWASP Top 10 for LLM Applications and NIST AI 600-1, the Generative AI Profile, are references for the threats and controls in this rule. They are not frameworks this rule makes an application compliant with.
- Describe what was built as the control it is, per Compliance Claims in `.claude/rules/production-readiness.md`. Never call an AI feature compliant, certified, or aligned because it follows this rule.

## Final Reporting

For guardrail work, report: the layers built or changed and their order; the moderation and relevance checks and the catalog records they call; the fail mode of each check, and confirmation that a screening refusal blocks and that injection screening for an agent with write tools fails closed; how untrusted content is labeled and screened; how retrieval is scoped to the user and tenant, and how a source delete or re-permission reaches its index entries; confirmation that no secret or authorization logic sits in a prompt; the output check, the image and link policy, and destination validation; the streaming mode per feature; the strike weights, threshold, and pause length, and confirmation that only content categories count as strikes; the limits enforced, the status and message for each, and the time zone of the reset time; what is stored for a blocked message, and whether a quarantine exists with its access and retention; the verdict, strike, and pause rows and their classification and retention; the metrics tracked and which checks run in annotate mode, with confirmation that no baseline layer does; the kill switches, their fallbacks, and who may flip them; the dry run; the audited actions added; the disclosure requirement applied; the tests run with synthetic payloads and their results; security impact, confirming no secret, prompt text, or blocked abusive text entered a log, an audit record, or an error.

Final rule: if a fail mode, a threshold, the moderation provider, the streaming mode, the quarantine decision, the kill switch owner, a limit, or a disclosure obligation is unclear, do not guess. Ask and record the confirmed values first.
