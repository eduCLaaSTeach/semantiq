# Pre-Upgrade Checklist

Use before upgrading the Claude Solution Development Kit in an existing project. In a gateway project, that is whenever the project takes a newer copy of the gateway's `.claude/`, `doc/`, and `scripts/` folders.

## 1. Safe Checkpoint
- [ ] No active uncommitted application change that would be confused with the kit upgrade.
- [ ] No database migration or schema creation currently in flight.
- [ ] No production deployment or hotfix currently in flight.
- [ ] No UAT defect fix currently mid-change, unless the project owner explicitly accepts the risk.
- [ ] Upgrade immediately after an accepted Task ID or another controlled checkpoint where possible.

## 2. Git Rollback Point
- [ ] `git status` reviewed.
- [ ] Current work committed, staged by path after reviewing `git status`.
- [ ] Pre-upgrade tag or commit recorded.
- [ ] A working upgrade branch created off `DEV` (a `chore/` or `feature/` branch, per the gateway's Git branching rules).

Recommended commands:
```bash
git status
git add <paths>
git commit -m "chore: checkpoint before kit upgrade"
git tag kit-pre-upgrade DEV
git checkout -b chore/sdk-kit-upgrade DEV
```

Stage each path deliberately rather than with `git add .`, so the checkpoint holds only the work it is meant to hold.

## 3. Inventory To Preserve
Record or verify:
- [ ] root `CLAUDE.md`;
- [ ] existing `.claude/` rules, skills, agents and project context;
- [ ] `/doc/solutiondocument/`;
- [ ] accepted phase outputs and standards baseline;
- [ ] `/doc/tasktracking/TASK_REGISTER.md`;
- [ ] published Task IDs;
- [ ] `task-events.jsonl` and completion timestamps;
- [ ] UAT and acceptance evidence;
- [ ] approved logical and physical database proposals, and schema verification records;
- [ ] `/doc/corerepo/` state;
- [ ] current active Task ID and current gate/status.

## 4. Upgrade Constraints
- [ ] The upgrade is governance and tooling only.
- [ ] No application feature coding.
- [ ] No DDL or migration execution.
- [ ] No deployment.
- [ ] No renumbering of historical Task IDs.
- [ ] No deletion of requirements, features or history.
- [ ] No replacement or weakening of current project standards.

Do not begin the upgrade until the checklist above is satisfied or the project owner's explicit exception is recorded.
