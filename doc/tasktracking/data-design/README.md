# Task Data Design Evidence

This folder is generated during execution of tasks that require persistence/schema work. Per Task ID, Claude may create:

- `<TASK-ID>_LOGICAL_DATA_PROPOSAL.md`
- `<TASK-ID>_PHYSICAL_TABLE_PROPOSAL.md`
- `<TASK-ID>_SCHEMA_VERIFICATION.md`

Do not treat a proposal as an implemented schema. Application coding may depend on physical schema only after the verification record says `SCHEMA VERIFIED`, per `.claude/rules/database-schema.md`, and the human explicitly authorises coding.
