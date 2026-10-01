# Database Task

Use for database-backed features, schema questions, reports, imports, exports, mappings, or query changes.

Input: `[DATABASE_TASK_OR_DATA_CONCEPT]`

Process:

1. Read `.claude/rules/database-schema.md`, `.claude/rules/knowledge-base.md`, and `.claude/PROJECT-CONTEXT.md`.
2. Confirm the project's schema source from `.claude/PROJECT-CONTEXT.md`, or ask, before schema-dependent work.
3. Search the existing schema and the table dictionary before proposing new storage, including for the feature's shared reference sets (source, status, outcome, reason).
4. Verify every table and column against the schema source before referencing it.
5. When the task designs or alters a structure, run `.claude/commands/semantic-model-plan.md` first: confirm the analytics question set, declare each table's grain, and show both with the shape before authoring any migration.
6. Stop if the schema source cannot be read, or if no migration tool is confirmed for a schema change.
7. After validation, follow the knowledge-base gate in `.claude/rules/knowledge-base.md`: prompt the developer to create/update the knowledge base now or defer, and write files only on explicit approval. A table-dictionary update documenting an actual schema change travels in the same change unit as the schema change.

Return:

- Schema source used
- Tables/concepts verified
- Analytics question set, grain per table, and any unanswered question
- Proposed path or blocker
- Validation plan
- Knowledge-base/table-dictionary follow-up
