# Verified Closeout

Use after implementation and validation are complete.

Input: `[TASK_SUMMARY]`

Process:

1. Confirm what changed and which files were touched.
2. Confirm validation commands and results.
3. Run both review passes over the actual diff per `.claude/rules/review-gates.md`, unless they already ran over this exact change and nothing has been edited since: the `code-reviewer` subagent and the `security-reviewer` subagent, as subagents rather than a self-assessment. A code change reaching closeout without both passes is not closed out.
4. Resolve the findings before continuing. `Critical` and `High` block: fix and re-run the pass over the fix, or get the developer's explicit sign-off on that named finding and record it as a documented exception in `.claude/PROJECT-CONTEXT.md`. `Medium` and `Low` are recorded, not blocking, except a dependency version carrying a critical, high, or unrated advisory, which stays blocked under `.claude/rules/enterprise-governance.md` whatever the finding is rated. Do not proceed to the knowledge-base steps with an unresolved `Critical` or `High`.
5. Describe the security impact as the controls that were built and the requirements they support. Never call the application, a feature, or the change SOC 2, ISO 27001, or otherwise compliant, certified, audit-ready, or aligned, per `.claude/rules/production-readiness.md`.
6. Confirm the developer has verified and validated that the task works.
7. Ask whether the knowledge base should be created/updated now or deferred. Do not write knowledge-base files without explicit approval; if deferred, skip to the return and report the deferral.
8. Ask whether the work is a solution, module, or feature and where it sits in the hierarchy (a solution has multiple modules; each module has multiple features). Do not classify it yourself.
9. Read existing knowledge-base files relevant to the task, including the README index.
10. Write or update the solution/module/feature write-up per `.claude/rules/knowledge-base.md`. A feature write-up covers logical flows and workflows, data models and tables, frontend, backend, API routing, and system architecture.
11. Update the master knowledge base when the task affects shared behavior, multiple solutions, or repo-wide conventions.
12. Update the table dictionary when database/table/schema knowledge changed, including each table's grain sentence and column roles, and place the data-model deliverables (entity relationships, ERD, semantic hand-off) per `.claude/rules/semantic-data-model.md`.
13. Update the README index so every knowledge-base file, including any new one, is linked.
14. Regenerate the orientation map when this closeout accepts a milestone or a sprint item, per `.claude/rules/orientation-map.md`. Rebuild the page from the repository rather than adjusting the numbers already on it, and say what changed and whether anything previously listed as open is now resolved.
15. Do not record secrets, production row data, tokens, or unverified assumptions.

Return:

- Validation status
- Security impact, described as the controls built and the requirements they support
- Review pass status: that both the code review and security review ran and over which files, the findings with their severities, and explicitly that a pass ran clean when it did
- `Critical` and `High` findings and how each was resolved: fixed and re-reviewed, or accepted with the developer's explicit sign-off and the recorded exception
- Developer verification and approval status (approved or deferred)
- Classification confirmed (solution, module, or feature) and its place in the hierarchy
- Knowledge-base files updated
- README index status
- Table dictionary updates made
- Data-model deliverables status (grain per table, entity relationships, ERD, semantic hand-off)
- Master knowledge-base updates made
- Orientation map status: whether it was regenerated, which trigger fired, and what changed on the page
- Remaining risks or follow-up
