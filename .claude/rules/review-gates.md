# Review Gate Rules

Claude must run a code review pass and a security review pass over every code change it delivers, before that change is called done. Neither pass is optional, and neither is satisfied by having followed the other rules while writing the code.

Applies to every task that writes or edits code that will be delivered, so it fires with gate CODE. It does not override `.claude/rules/secret-handling.md`, `.claude/rules/schema-mcp.md`, `.claude/rules/production-readiness.md`, or the DDL restrictions.

Writing carefully is not reviewing. The author of a change is the worst reader of it, because the same assumption that produced the defect hides it on the next read. These passes exist to be a second look with a different question, not a second opinion from the same one.

## The Two Passes

| Pass | Runs | Asks |
| --- | --- | --- |
| Code review | The `code-reviewer` subagent in `.claude/agents/code-reviewer.md` | Is it correct, complete, in the project's patterns, and no larger than the request |
| Security review | The `security-reviewer` subagent in `.claude/agents/security-reviewer.md` | Can it be abused, does it leak, does it trust something it should not |

- Run them as subagents against the change, not as a self-assessment in the main thread. A reviewer that shares the author's context inherits the author's blind spot.
- Run both. A clean code review does not stand in for a security review, and neither one covers the other's list.
- Run them on the actual diff, after the code is written and validation has run, not on a description of what was intended.
- The subagents review and report. They never edit. Fixing what they find is the author's work, in the main thread, and each fix is reviewed like any other change.

## Every Change, Proportionate Depth

The pass always runs and its result is always reported. What varies is how much there is to look at, never whether the look happened.

- A change that touches authentication, authorization, data access, an external boundary, file handling, configuration, or dependencies gets the full pass on every applicable list in the agent files.
- A small, contained change gets a real but short pass over the paths it actually touches.
- A change with no code paths at all, such as documentation or a comment fix, reports plainly that there is nothing to review and why. That sentence is the pass.

Never skip the pass to save a turn, and never report a pass that did not run. An unrun review reported as clean is worse than no review, because it converts an unknown into a false assurance that somebody will act on.

## Severity

Every finding from either pass carries exactly one severity. The scale is fixed so the blocking rule below has something to bind to and so findings can be compared across changes.

| Severity | Means | Effect |
| --- | --- | --- |
| `Critical` | Exploitable now, or data loss, or a secret exposed. No preconditions the attacker does not already control. | Blocks |
| `High` | A real defect in a security or correctness control, exploitable given a condition an attacker can plausibly reach. | Blocks |
| `Medium` | A weakness that needs an unlikely precondition, or a correctness defect with a bounded blast radius. | Ships recorded |
| `Low` | Hardening, defense in depth, or a maintainability defect with no current failure path. | Ships recorded |

- Rate what the finding actually enables, not how alarming it sounds. A missing header on an endpoint that serves no credentials is not the same as one that does.
- When two severities are arguable, take the higher one and say why it was close. Do not average.
- A finding whose severity depends on an unconfirmed project fact is raised as a question at the higher severity, not quietly rated at the lower one.

## Blocking And Sign-Off

- A `Critical` or `High` finding blocks. The change is not done, is not claimed done, and is not proposed for merge until it is fixed and the pass re-run over the fix, or the developer explicitly signs off on accepting it.
- Sign-off is the developer's, stated explicitly for that named finding. Silence is not sign-off, the passage of time is not sign-off, and Claude's own judgment that the finding is acceptable is never sign-off.
- Record every accepted `Critical` or `High` in the final report and in `.claude/PROJECT-CONTEXT.md` as a documented exception: the finding, its severity, who accepted it, and the reason.
- `Medium` and `Low` findings do not block. Report them with the change so the decision to carry them is visible and deliberate, rather than losing them.
- Re-run the pass over the fix. A fix is a code change, and the most common way a Critical becomes a different Critical is the patch for it.

Blocking is about the claim, not about the developer. Claude does not refuse to keep working on a change with an open `High`; it refuses to call that change done.

## Reporting The Result

State the outcome of both passes in the final summary, always, in the same place whether or not anything was found:

- which passes ran, and over what;
- the findings, each with its severity and the file and line it sits on;
- for a `Critical` or `High`: fixed and re-reviewed, or accepted with the developer's explicit sign-off recorded;
- for a clean pass: that it ran and found nothing, which is a different statement from saying nothing about it.

Never report "no security issues" as a conclusion the pass did not actually reach. If a list in the agent file could not be checked, because a project fact is unconfirmed or the relevant code was not readable, say which list and why rather than letting it read as clear.

## What A Review Is Not

- Not a rubber stamp. A pass that finds nothing on a change touching authentication has either verified that specifically or has not run properly, and those two must not look the same in the report.
- Not a substitute for validation. Tests, build, lint, and type checks still run per `.claude/rules/production-readiness.md`; the review reads what those cannot.
- Not a gate on the developer's own commits. Every Git action stays allowed without per-action confirmation per `.claude/rules/git-branching-release.md`. This gate governs when Claude may call work done, and what it must tell the developer before they merge.
- Not the place to relitigate approved design. A finding is about this change; an objection to a decision already made and recorded goes to the developer as an open question, per `.claude/rules/knowledge-base.md`.

## Final Reporting

For any delivered code change, report: which of the two passes ran and over which files; each finding with its severity, location, and remediation; every `Critical` and `High` and whether it was fixed and re-reviewed or accepted with explicit developer sign-off; the recorded exception for anything accepted; any list in an agent file that could not be checked and why; and explicit confirmation when a pass ran clean.

Final rule: if it is unclear whether a change needs the full pass, whether a finding is `High` or `Medium`, or whether the developer actually signed off on carrying one, do not resolve it in the permissive direction. Run the fuller pass, take the higher severity, and ask for the sign-off in words.
