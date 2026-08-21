# Phased Workflow Rules

Claude must run non-trivial work through an explicit, gated lifecycle instead of jumping to code. The lifecycle is stack-neutral; the concrete contents of each gate (reviewers, migration tool, validation commands, CI checks) are confirmed facts in `.claude/PROJECT-CONTEXT.md`, not in this rule.

## The Five Phases

Work moves through five phases in order. Each ends in a hard-stop gate requiring explicit human approval before the next begins.

| Phase | Goal | Gate (must pass before advancing) |
| --- | --- | --- |
| Plan | Understand and restate the requirement | Human confirms the spec, assumptions, and unknowns |
| Design | Define interfaces, contracts, file plan, rollback | Human (and confirmed reviewers for sensitive areas) approves the design |
| Recommend | Present the option(s), tradeoffs, and a clear recommendation | Human chooses or approves the recommended path |
| Evaluate | Build in small increments and evaluate each against the plan, design, and rules | Self-verify per `.claude/rules/production-readiness.md` before claiming done |
| Validate | Change management, Pull Request, review, validation | Approvals plus the project's confirmed merge rules |

- Plan: restate the requirement in your own words, then list assumptions, open questions, and unknowns. Follow `.claude/rules/project-intake.md` as the entry gate; do not advance while any requirement, stack choice, hosting/deployment detail, database/MCP detail, security boundary, validation path, or success criterion is unclear. Silence is not approval.
- Design: define interfaces, contracts, and file plan before writing code; name a rollback/mitigation strategy. Where the work touches data, run the reuse-check through the Schema MCP per `.claude/rules/schema-mcp.md` before proposing new storage. Sensitive areas (schema, authentication, deployment surface) require the confirmed reviewers to approve at this gate.
- Recommend: when more than one workable approach exists, lay out the options with their tradeoffs (risk, effort, blast radius, reversibility) and state which one you recommend and why. Do not build on a guessed choice; the human picks or approves the path. When only one reasonable approach exists, say so and recommend it plainly rather than manufacturing alternatives. Any schema proposal is surfaced here for approval before the Evaluate phase writes code against it, per `.claude/rules/schema-mcp.md`.
- Evaluate: build in small, reviewable increments tied to the approved recommendation, and evaluate each increment against the plan, the design, and the applicable rules. Do not silently expand scope; if the approach proves wrong, back up to Design or Recommend and re-gate. This is the done gate: self-verify against `.claude/rules/production-readiness.md` and run available validation before claiming complete.
- Validate: promote through the confirmed change-management process (Pull Request, review, validation) per `.claude/rules/git-branching-release.md` and `.claude/rules/deployment.md`. Passes only with the required approvals and merge rules.

## Phase Discipline

- State which phase you are in at the start of each response on a task, and name the gate you are working toward.
- Stop at each gate and wait for explicit human approval; do not treat your own confidence as approval. Do not collapse, skip, or reorder phases.
- If asked to jump straight to building, back up to the correct earlier phase, complete it, and pass its gate first.
- Keep stack-specific gate contents in `.claude/PROJECT-CONTEXT.md` and reference them; do not bake specific products, tools, or counts into this workflow.

## Final Reporting

For phased work, state: the current phase and which gates passed, with who approved each; assumptions and unknowns surfaced during Plan; the options weighed and the recommendation chosen at the Recommend gate; design decisions and the named rollback/mitigation strategy; validation run and results, or the documented reason it could not run; any phase re-entered and why.

Final rule: if the phase, the gate criteria, or whether a gate was actually approved is unclear, do not advance. Ask first.
