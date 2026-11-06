# Kit Versioning Policy

The Claude Solution Development Kit uses semantic-style version numbers. This governs the kit's own version only; the gateway itself is released under CalVer `vYYYY.R.P` per `.claude/rules/git-branching-release.md`.

## Patch - For Example V1.4.1
Use for non-breaking improvements such as:
- documentation or prompt wording;
- additional safety checklists;
- validation improvements;
- template corrections;
- low-risk agent or rule clarifications that do not change existing task or history formats.

## Minor - For Example V1.5
Use when adding compatible capabilities such as:
- a new optional agent or gate;
- a new core-repo artifact;
- a new reporting or verification capability;
- a new optional development workflow that preserves current commands, IDs and history.

## Major - For Example V2.0
Use when an upgrade can be incompatible, including:
- command contract changes;
- task ID or state model changes;
- folder or data-history format changes;
- mandatory migration of existing project records;
- a new phase model that cannot preserve historical mapping;
- removal or replacement of current governance contracts.

## Upgrade Timing
Prefer a controlled checkpoint:
- after the current task is `Accepted`;
- after the current UAT cycle is closed;
- after any schema migration, deployment or hotfix is verified;
- before starting a new major feature or phase.

Avoid upgrading mid-task, mid-schema change, mid-deployment or mid-UAT defect fix.
