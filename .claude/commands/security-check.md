# Security Check

Run the security review pass over a change on demand. `.claude/rules/review-gates.md` already requires this pass before any code change is called done; use this command to run it earlier, to re-run it over a fix, or to review something Claude did not write.

Input: `[TARGET]` - the change to review. Defaults to the uncommitted diff plus anything committed on this branch and not yet merged. May instead name a path, a commit range, or a Pull Request.

Process:

1. Establish the target and say what it resolved to: the files and the revision range. Review the actual diff, not a description of the intent.
2. Read the confirmed facts the review depends on from `.claude/PROJECT-CONTEXT.md`: authentication model, data sensitivity and classification, security and identity conventions, configuration contract, and the architecture boundaries. Name any that are unconfirmed rather than assuming a default.
3. Run the `security-reviewer` subagent in `.claude/agents/security-reviewer.md` against the target. Run it as a subagent, not as a self-assessment in this thread.
4. Apply every conditional list in that agent file the change actually triggers: AI/LLM features, cryptography and identity, governed or classified data, and agent lifecycle.
5. Rate each finding `Critical`, `High`, `Medium`, or `Low` per the scale in `.claude/rules/review-gates.md`. Rate what the finding enables, not how it sounds, and take the higher of two arguable severities.
6. For each finding, give the file and line, what an attacker or a failure would actually do with it, and a concrete remediation in the project's existing patterns. No generic advice.
7. Name any list that could not be checked, and why, rather than letting an unchecked area read as clear.
8. Do not edit files. Fixing is separate work in the main thread, and each fix is re-reviewed.

Return:

- The target reviewed, as files and revision range
- Findings, highest severity first, each with severity, location, impact, and remediation
- The `Critical` and `High` count, and that they block the change from being called done until fixed and re-reviewed or explicitly signed off by the developer
- `Medium` and `Low` findings, recorded rather than blocking
- Any list not checked, and the unconfirmed project fact or unreadable code behind it
- An explicit statement when the pass ran clean, rather than silence

Never report a pass that did not run, and never report "no security issues" as a conclusion this pass did not actually reach.
