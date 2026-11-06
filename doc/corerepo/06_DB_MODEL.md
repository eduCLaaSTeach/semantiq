# Database Model

Record only verified physical structures: a structure after the schema self-check against the project's schema source records `SCHEMA VERIFIED`, per `.claude/rules/database-schema.md`. Never record a structure from memory, chat, a screenshot or an unapproved proposal.

For each structure record the store it lives in, its grain, the table, key relationships, important constraints and indexes, the verification source and the timestamp. Column-level metadata belongs in the table dictionary under `.claude/rules/knowledge-base.md`; link to it rather than copying it here.
