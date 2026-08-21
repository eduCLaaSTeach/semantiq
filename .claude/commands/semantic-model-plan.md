# Semantic Model Plan

Use before proposing storage for a feature, so the shape answers the feature's analytics and lifts into a semantic model. Run it after `schema-reuse-plan` or together with it.

Input: `[FEATURE_OR_DATA_CONCEPT]`

Process:

1. Read `.claude/rules/semantic-data-model.md` and `.claude/skills/semantic-data-model/SKILL.md`, then only the `reference/` file this task needs. Read `.claude/PROJECT-CONTEXT.md` for the Analytics And Semantic Model facts, and ask for any that are unset.
2. Draft the analytics question set by walking the ten families in `reference/question-set.md`, then ask the developer to confirm, cut, or add, and to say which questions are day-one.
3. Verify the Schema MCP server `schema` is reachable, and run `mcp__schema__find_existing_tables_for_concept` for each concept in the set, including the reference sets (source, status, outcome, reason) and any existing history or event table that already fits.
4. Use `mcp__schema__describe_table` on every candidate before recommending reuse, and reuse a conformed platform dimension rather than proposing a private copy.
5. Draft the shape: a grain sentence per table, the measures with units and additivity, the dimension keys with reserved members, the history, outcome, and identity capture the questions need, and the business dates with their roles.
6. Trace every confirmed question to what answers it, and name every question the shape does not answer.
7. Show the developer the question trace and the exact shape together, state what was deliberately not built, and stop for explicit confirmation. Revise and re-show if they ask for changes.
8. Only after explicit confirmation of the shown shape, call `mcp__schema__propose_table_change`.
9. After the change is verified, produce the deliverables in `reference/erd-and-handoff.md` (data structure, entity relationships, ERD, semantic hand-off) through the knowledge-base gate in `.claude/rules/knowledge-base.md`; schema metadata for an actual schema change travels in the same change unit.

Return:

- Confirmed analytics question set, and which questions are day-one
- Grain sentence per table
- Measures with units, default aggregation, and additivity
- Dimensions used, which existing platform set was reused, and reserved members
- History, outcome, and identity capture added, and why each question needs it
- Business dates and their roles, plus the reporting time zone applied
- Questions the shape does not answer, raised as gaps
- What was deliberately not built
- MCP tools used, tables verified, proposal IDs
- Deliverable and knowledge-base plan
