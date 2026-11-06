# V1.4.1 Migration And Upgrade Safety

V1.4.1 is a non-breaking safety refresh of V1.4. It preserves the five-phase model, the three core commands, the task lifecycle, the schema controls, the core-repo model and the UAT gates.

## Added
- `PRE_UPGRADE_CHECKLIST.md`
- `POST_UPGRADE_VALIDATION.md`
- `VERSIONING_POLICY.md`
- stronger history-preservation and rollback rules in the kit rule and the upgrade prompt

## Preserve
Do not reset or replace:
- root `CLAUDE.md`;
- the live organisation and project `.claude/`;
- active requirements;
- accepted phase and task history;
- published Task IDs;
- task-event and timestamp history;
- UAT and acceptance records;
- approved database proposals and schema verification evidence;
- `/doc/corerepo/` history and current state.

## Upgrade Method
1. Run `PRE_UPGRADE_CHECKLIST.md`.
2. Create the rollback checkpoint and a working upgrade branch off `DEV` (`chore/` or `feature/`, per the gateway's Git branching rules).
3. Inventory the current state.
4. Merge kit governance files only.
5. Do not code, migrate the database or deploy as part of the upgrade.
6. Run `POST_UPGRADE_VALIDATION.md`.
7. Merge only on `PASS - SAFE TO MERGE`.
