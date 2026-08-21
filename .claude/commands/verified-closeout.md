# Verified Closeout

Use after implementation and validation are complete.

Input: `[TASK_SUMMARY]`

Process:

1. Confirm what changed and which files were touched.
2. Confirm validation commands and results.
3. Run both review passes over the actual diff per `.claude/rules/review-gates.md`, unless they already ran over this exact change and nothing has been edited since: the `code-reviewer` subagent and the `security-reviewer` subagent, as subagents rather than a self-assessment. A code change reaching closeout without both passes is not closed out.
4. Resolve the findings before continuing. `Critical` and `High` block: fix and re-run the pass over the fix, or get the developer's explicit sign-off on that named finding and record it as a documented exception in `.claude/PROJECT-CONTEXT.md`. `Medium` and `Low` are recorded, not blocking. Do not proceed to the knowledge-base steps with an unresolved `Critical` or `High`.
5. Confirm the developer has verified and validated that the task works.
6. Ask whether the knowledge base should be created/updated now or deferred. Do not write knowledge-base files without explicit approval; if deferred, skip to the return and report the deferral.
7. Ask whether the work is a solution, module, or feature and where it sits in the hierarchy (a solution has multiple modules; each module has multiple features). Do not classify it yourself.
8. Read existing knowledge-base files relevant to the task, including the README index.
9. Write or update the solution/module/feature write-up per `.claude/rules/knowledge-base.md`. A feature write-up covers logical flows and workflows, data models and tables, frontend, backend, API routing, and system architecture.
10. Update the master knowledge base when the task affects shared behavior, multiple solutions, or repo-wide conventions.
11. Update the table dictionary when database/table/schema knowledge changed, including each table's grain sentence and column roles, and place the data-model deliverables (entity relationships, ERD, semantic hand-off) per `.claude/rules/semantic-data-model.md`.
12. Update the README index so every knowledge-base file, including any new one, is linked.
13. Do not record secrets, production row data, tokens, or unverified assumptions.

Return:

- Validation status
- Review pass status: that both the code review and security review ran and over which files, the findings with their severities, and explicitly that a pass ran clean when it did
- `Critical` and `High` findings and how each was resolved: fixed and re-reviewed, or accepted with the developer's explicit sign-off and the recorded exception
- Developer verification and approval status (approved or deferred)
- Classification confirmed (solution, module, or feature) and its place in the hierarchy
- Knowledge-base files updated
- README index status
- Table dictionary updates made
- Data-model deliverables status (grain per table, entity relationships, ERD, semantic hand-off)
- Master knowledge-base updates made
- Remaining risks or follow-up
