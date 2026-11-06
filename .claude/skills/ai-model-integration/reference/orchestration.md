# Orchestration

How one step of a feature uses one or more model calls. Everything here is calling code built above the one engine. The engine makes one call and returns one result; picking a second record, judging drafts, repairing output, and deciding acceptance all happen here, in the caller, never inside the engine.

The binding rules are `.claude/rules/ai-agent-governance.md` for routing, multi-call patterns, and safe agent actions, and `.claude/rules/ai-model-catalog.md` for the one engine with no fallback hop. The checks around every call stay in `.claude/rules/ai-guardrails.md`.

Every pattern in this file is optional. The patterns a project allows, the default route for each step, and the catalog records a step may use are confirmed facts recorded in the AI Model Catalog section of `.claude/PROJECT-CONTEXT.md`. The live route is data an administrator changes on the AI Routing screen, never copied back into that file. The field names are concepts, not required identifiers. Map them to the project's confirmed naming convention.

## Where This Sits

```text
step code -> route (data) -> pattern -> engine.run(record, prompt) x N
          -> caller validation -> output check -> accept check
```

- Each `engine.run` is one call through `request-engine.md`, with its own result, tokens, and cost.
- Caller validation is the step's own schema check on the content, per the hand-off in `response-paths.md`.
- The output check is the guardrail layer in `guardrails.md`. The accept check is below.

## Routing Is Data

One route per step, stored as data an administrator changes on a screen:

```text
step               shape        records
outline            single       main:    <RECORD_ID>
classify_request   fast         fast:    <RECORD_ID>
write_section      dual_judge   draft_a: <RECORD_ID>
                                draft_b: <RECORD_ID>
                                judge:   <RECORD_ID>
```

A route row, as concepts:

```text
step               the step code, stable
shape              coded: single, fast, dual_judge
records            a catalog record id per role
max output         per role
temperature, seed  per role, only where the model accepts them
prompt version     per role
critique           on or off, dual_judge only
judge mode         coded: patch, rewrite
max edits          the patch-mode edit cap
pre-draft          on or off
```

- A route names record ids, never a provider name or a free-text model name.
- Resolve the records at call time. A record that is not active, or has not passed its test call, makes the step refuse with a handled outcome. The step never picks another record on its own.
- `shape` and `judge mode` are closed reference sets, per `.claude/rules/semantic-data-model.md`.
- A route change is audited, per Changes Are Audited in `.claude/rules/ai-guardrails.md`. The screen is in `ai-admin-ui.md`.
- A route change is gated on a recorded eval result. Record each result, pass or fail, with its timestamp, per step, catalog record, and prompt version. A route may point a role at a record only where that triple has a passing result, and the server checks it again on save, per Evaluation in `.claude/rules/ai-agent-governance.md`.

## Single Call

The default. One record, one call, then caller validation, the output check, and the accept check. The `fast` shape is the same pattern pointed at a cheaper or quicker record.

## Dual Drafting

```text
1  check the step's worst case against every limit
2  start draft A (primary) and draft B in parallel
3  stream only draft A to the user
4  both succeed   -> critique (when on), then the judge
   one fails      -> return the survivor, mark the result degraded, record why
   both fail      -> handled failure
```

- Only the primary draft streams. The second draft runs silently, so the user never watches two answers race.
- `degraded` is a coded outcome on the result and on the step's timing row, never a silent difference.
- Both drafts count against the budget, a failed one included whenever the provider billed it.

## Cross Critique

Optional, per route. Each draft is reviewed by the other draft's record against the rubric, and returns findings, not a rewrite.

- The findings go to the judge as data, inside labeled boundaries like any other untrusted content.
- A critique never edits a draft and never decides acceptance.
- It adds one or two calls to the step, and each counts.

## The Judge

The judge reads the drafts, and the critiques when they ran, against the rubric and the output schema. Its mode is confirmed per step.

Patch mode:

```text
input    both drafts, the critiques when run, the rubric, the output schema
output   base:  A or B
         edits: at most <MAX_EDITS> of { path, new value, reason }
apply    for each edit, in order:
           path does not exist in the base          -> skip, record the reason
           new value fails the schema at that path  -> skip, record the reason
           otherwise                                -> apply
result   the base draft with the applied edits, plus the skipped edits and their reasons
```

- A path here points into the step's own output structure, not into a provider response.
- The applier is deterministic code. It never asks a model whether an edit is safe, and an invalid edit is skipped safely rather than failing the step.
- More edits than the cap is an invalid judge output, handled by the repair retry below.

Rewrite mode:

- The judge returns a complete new output. It is new model output, so it takes the same caller validation, repair retry, and output check as any other.

Patch mode keeps a base draft a person can compare against the result. Rewrite mode can change structure a patch cannot reach. Either way the judge picks and edits; it never decides acceptance.

## Repair Retry

```text
call -> caller validates the content against the step's schema
  valid    -> continue
  invalid  -> one retry: same record, same prompt version, plus a labeled section
              repair_note: "The previous output was invalid: <WHAT_FAILED_VALIDATION>"
                valid    -> continue, mark the result repaired
                invalid  -> handled failure
```

- One retry by default. The count is a confirmed fact, and zero is a legitimate answer.
- The repair note is a labeled section the versioned prompt defines, with its fixed wording, per Prompt Structure in `.claude/rules/ai-agent-governance.md`. Code fills in only what failed validation. It is never free text appended to the instruction sections.
- The retry lives in the caller. The engine never repairs, reshapes, or re-prompts, per `response-paths.md`.
- The retry is a second call, and it counts.
- The repair retry is for output that arrived and failed validation. A refusal, a content-filter stop, and a length stop are their own coded outcomes, per `response-paths.md`, and are not repaired.

## Streaming And Background Work

```text
request  -> write a job row (state: queued) -> start a background task -> return the job id
task     -> its own database session -> run the pattern -> save the result
         -> state: done or failed
stream   -> forwards the job's events to the browser:
              status    the step or stage changed
              delta     text appended, primary draft only
              final     carries the saved result
              error     a generic message and the correlation id
            and a keep-alive comment on the confirmed interval while nothing else is sent
```

- Long AI work runs in a background task with its own database session, never on the request that started it. A browser that disconnects loses nothing: the task finishes, the result is saved, and a reconnect reads the job.
- The `final` event carries the result as saved, read back after the save, so what the user sees is what was stored.
- No database transaction or row lock stays open across a model call. Read what the call needs, commit, make the call, then save in a new transaction, per `.claude/rules/resilience.md`. A test proves it.
- Job state, the event buffer, and any cache live in a shared store when the deployment runs more than one worker or instance, never in process memory, so any instance can serve the stream and the reconnect.
- The stream follows the confirmed streaming mode, buffered or retractable, per `.claude/rules/ai-guardrails.md`. A `delta` is never sent past a check that runs only on the saved copy.
- The `error` event carries a generic message and the correlation id, never the provider's error, per `.claude/rules/secure-coding.md`.

## Progressive Rendering

Partial structured output may render while it streams, as text, never as markup. The fields that have arrived show as they complete, and the `final` event replaces the partial view with the saved result. The image and link policy in `.claude/rules/ai-guardrails.md` applies to every partial render. The UI side is in `ai-admin-ui.md`.

## Pre-Drafting The Next Step

Optional, off by default, confirmed per step.

```text
when step N's proposal is shown:
  fingerprint = hash of the proposal content exactly as shown
  start a background draft of step N+1, assuming the proposal is accepted as shown
  store { step N+1 draft, fingerprint, created at }

when the user accepts step N:
  accepted content's fingerprint == stored fingerprint  -> use the pre-draft once, then delete it
  otherwise                                             -> discard it, draft step N+1 normally
```

- Use a pre-draft at most once, and only on an exact match. Any edit before acceptance changes the fingerprint and discards it.
- A pre-draft respects every limit and the kill switch, and is checked against the budget before it starts. It never runs past a limit to save time.
- Its calls count, used or discarded. A discarded pre-draft is money spent.
- A used pre-draft passes the output check and the accept check like any other draft, and its timing row is marked pre-drafted.

## Provenance

Every generated item carries its provenance, stored with the item rather than rebuilt from logs:

```text
records          the catalog record ids that produced it, per role
prompt versions  of every prompt used
mode             the route shape, and the judge mode when a judge ran
review flag      whether a person has reviewed it
outcome flags    degraded, repaired, pre-drafted
timing           a reference to the step's timing row
correlation id   shared with the calls and the audit trail
generated at     timezone-aware
```

The UI shows it as a provenance line, per `ai-admin-ui.md`.

## Per-Step Timing

One row per step run:

```text
step, shape, correlation id
started at, ended at    timezone-aware
time to first text      from the start of the step to the first delta
draft duration          the slower of the drafts, or the single call
judge duration          critique and judge together, when they ran
total duration          from the start of the step to the saved result
pre-drafted             whether a pre-draft was used
outcome                 coded: ok, degraded, repaired, failed
```

- Store the step's start and end times beside the durations, so every duration traces to its endpoints, per `.claude/rules/semantic-data-model.md`.
- The speed report built on these rows is in `cost-and-usage.md`.

## The Accept Check And Sign-Off

- One server-side function decides whether a generated item may be accepted or applied. It is deterministic code: the output schema, the sign-off checklist, the user's grant and record scope, and the record's state.
- Every path calls it: a button, a chat command, a voice command, and an API call. No path has its own version.
- No model decides it: not a draft, not a critique, not the judge.
- Each checklist item is at one of two levels. An unresolved `blocking` item refuses the sign-off and names the item. A `warning` item is shown and recorded with the sign-off, and does not prevent it.
- Record who accepted or signed off, when, and the warnings shown at the time.

## Batch Fixes

```text
snapshot the target
for each suggested fix:
  run the same validation a single fix takes
    fails   -> skip, record { fix, reason }
    passes  -> apply
result  the applied count, the skipped fixes with their reasons, an undo reference
undo    restore the snapshot in one step
```

- A fix that fails validation is skipped, never forced. The user sees every skipped fix and why.
- Undo restores the state from before the batch in one step. It refuses when the target changed after the batch, rather than overwriting the newer change.

## Cost

Every call in every pattern counts: failed drafts, critiques, the judge, repair retries, and pre-drafts, used or discarded.

```text
single                           1 call
single with repair retry         up to 2
dual drafting plus a judge       3
with cross critique              4 to 5
each repair retry                +1
pre-draft                        the next step's count again, used or not
```

- Check the step's worst case against every limit before its first call, per `cost-and-usage.md`, so a multi-call step cannot overshoot.
- Show administrators the per-step estimate on the routing screen: the call count, and the worst-case tokens and cost from the records' prices, or unknown where a price is unknown.

## Testing

- [ ] Two records for genuinely different providers run as the two drafts of one step through the same engine.
- [ ] One draft fails: the survivor is returned and the result is marked degraded.
- [ ] A dual step with one draft record inactive or untested refuses to run with a handled outcome, rather than running single.
- [ ] Judge patch with one edit on a missing path and one failing the schema: both are skipped with reasons, the rest apply.
- [ ] The judge's output never accepts anything: the accept check runs after it.
- [ ] Invalid output gets one repair retry, then a handled failure, with both calls counted.
- [ ] A refusal or content-filter stop is not repaired.
- [ ] A browser disconnect mid-stream: the task finishes, the result is saved, and a reconnect reads it.
- [ ] No database transaction is open at the moment of a model call.
- [ ] With two instances, the stream and the reconnect are served from either one.
- [ ] A pre-draft is used on an exact fingerprint match, discarded on any edit, never used twice, and counted either way.
- [ ] Every accept path runs the same check, and a blocking item refuses on each path.
- [ ] A batch with one invalid fix skips it with a reason, applies the rest, and undo restores the snapshot.
- [ ] A step whose worst case exceeds a limit refuses before its first call.

## Do And Do Not

Do:

- Keep routing as data, pointing at catalog record ids.
- Build every pattern in the caller, above the one engine.
- Count every call in every pattern, and check the step's worst case before the first one.
- Run long work in a background task with its own database session.
- Store provenance and timing with every generated item.
- Decide acceptance in one deterministic server-side check that every path runs.

Do not:

- Do not add a fallback hop, a judge, or a repair loop to the engine.
- Do not name a provider or a free-text model in a route.
- Do not let a draft, a critique, or the judge decide acceptance.
- Do not hold a database transaction or row lock open across a model call.
- Do not keep job state or a cache in process memory when more than one instance runs.
- Do not use a pre-draft twice, or on content that changed after it was drafted.
- Do not force a fix that failed validation, or drop it without telling the user.
