# Git Branching And Release Rules

Claude must follow the team's Git branching and release guideline whenever discussing, reviewing, or preparing branch, Pull Request, merge, release, hotfix, or version-tag work.

## Scope

This is the recommended branching and release workflow, not a permission gate. All Git and GitHub commands and tasks (commits, pushes, branches, Pull Requests, merges, tags, releases, branch-protection changes, force pushes, history rewrites) are always allowed and need no per-action confirmation. Running an actual production deploy or database migration is a separate action that still requires approval per `.claude/rules/deployment.md` and `.claude/rules/production-readiness.md`; promoting code through Git, including to `PROD`, does not.

The model below is environment-branch promotion with permitted direct pushes to environment branches. The collaboration and safe-practice sections complement it; none introduces a trunk-based, "main always deployable", "no direct push", or image-tag-promotion topology. Keep the `DEV -> QA -> STAG -> PROD` model fully intact.

## Environment Branches

Use these official uppercase environment branches only. Do not create or recommend lowercase variants (`dev`, `qa`, `stag`, `prod`).

| Branch | Meaning | Stability |
| --- | --- | --- |
| `DEV` | Development integration branch | Active development |
| `QA` | Testing branch for QA verification | Test-ready |
| `STAG` | Staging / pre-production branch | Release candidate |
| `PROD` | Production branch | Production-ready |

## Supporting Branches

Use short-lived working branches for all work:

| Branch Pattern | Purpose | Created From | Merged Into |
| --- | --- | --- | --- |
| `feature/*` | New feature development | `DEV` | `DEV` |
| `bugfix/*` | Normal bug fixes before production | The branch where the bug exists (`DEV`, `QA`, `STAG`, or `release/*`) | Usually back to the source branch |
| `release/*` | Release candidate preparation | `DEV` | Promoted through `QA`, `STAG`, then `PROD` |
| `hotfix/*` | Urgent production fix | `PROD` | `PROD`, then back to `DEV`, and any active `release/*` |

## Normal Release Flow

```text
feature/* -> DEV -> release/vYYYY.R.P -> QA -> STAG -> PROD -> vYYYY.R.P tag
```

Code should not skip stages; do not recommend direct promotion such as `DEV -> PROD`. When a release is ready:

1. Create `release/vYYYY.R.P` from `DEV`.
2. Allow only release bug fixes on the release branch.
3. Promote the release branch to `QA` by Pull Request.
4. Fix QA issues through `bugfix/*` branches created from the active `release/*`.
5. Promote `QA` to `STAG` by Pull Request after QA approval.
6. Promote `STAG` to `PROD` by Pull Request after staging approval.
7. After production deployment, create the final annotated version tag from the latest `PROD` commit.
8. Merge release fixes back into `DEV`.

## Hotfix Flow

Use hotfixes only for urgent production issues.

```text
PROD -> hotfix/* -> PROD -> patch version tag -> DEV
```

If an active release branch exists, also merge the hotfix into it (`hotfix/* -> release/vYYYY.R.P`). Patch tags for hotfixes are created from `PROD` after the hotfix is approved, merged, and deployed.

## Version Tags

Use Calendar Versioning with an annual release sequence for production release tags:

```text
vYYYY.R.P
```

- `YYYY`: release year (calendar year of the production release).
- `R`: production release number in that year (increment per planned release).
- `P`: patch/hotfix number for that release (reset to `0` for planned releases; increment for hotfixes).

Examples: `v2026.1.0`, `v2026.1.1`, `v2026.1.2`, `v2026.2.0`, `v2027.1.0`.

For planned releases, increment `R` and reset `P` to `0`. For hotfixes, keep `YYYY.R` and increment only `P`. When the year changes, update `YYYY` and restart the sequence at `R = 1`. Prefer clear tags (`v2026.1.0`) over text formats (`2026.release1`). Final production release tags must be created from `PROD`, not `DEV`/`QA`/`STAG`/`release/*`; treat `PROD` as the source of truth.

## Pull Request Rules

As the recommended workflow, change environment branches through Pull Requests; direct pushes to `DEV`, `QA`, `STAG`, `PROD` are permitted without confirmation but discouraged outside urgent cases. After pushing a working branch, the default is to hand the Pull Request to the developer (see "After Pushing A Branch"). Claude may still create, update, or merge Pull Requests itself, including merges to any environment branch, when the developer explicitly asks for that exact action.

Every Pull Request should state: what changed; why; how it was tested; source and target branch; whether it affects release, deployment, database, API, configuration, or operations. Flag missing impact notes during review.

### Parallel-Work Collaboration

These practices coordinate several people or agents working at once; they complement the promotion model and direct-push allowance. Concrete timing values (review-response windows, etc.) are confirmed facts in `.claude/PROJECT-CONTEXT.md`.

- Keep working branches short-lived and update them from the integration branch on the confirmed cadence (see the Update-from-base knob), so parallel branches do not drift into stale-branch conflicts.
- Distinguish merge-ready from release-ready: merge-ready when required gates are green and incomplete work is gated off; release-ready only when feature-complete and the gate is flipped on. Merging incomplete-but-gated work into the integration branch is allowed; promoting it as a finished feature is not.
- Set a review first-response expectation using the confirmed review-response window; if unset, ask and record it rather than assuming.
- The author of a sensitive change (touching database or security concerns) must not self-merge without the owning reviewer; route it to the path-based required reviewer (see that knob and `.claude/rules/schema-mcp.md`).
- Serialize dependency-bump and lockfile changes so only one is in flight; others wait and rebase onto the merged result.
- Treat shared configuration/environment changes as additive-only in parallel work: add new variables rather than renaming or removing existing ones, documenting each in the Pull Request with placeholders only, per `.claude/rules/secret-handling.md`.
- Conflict etiquette: the second party to merge resolves the conflict, never discards another contributor's code, and re-requests review when the resolution changed logic.
- Require a ticket or issue link in every Pull Request.

## After Pushing A Branch (PR Hand-Off)

After committing and pushing a working branch, the default is to hand the Pull Request to the developer instead of opening or merging it automatically. Claude must:

1. Show the PR creation URL (from the `git push` output, or the equivalent compare/PR URL for the host).
2. State the recommended target from the promotion flow: `feature/*` and `bugfix/*` into `DEV`; `release/*` promoted through `QA`, `STAG`, then `PROD`; `hotfix/*` into `PROD` then back to `DEV` and any active `release/*`.
3. Give an ordered, copy-ready list: open the Pull Request against the correct target; fill in what changed, why, how tested, source/target, and release/deployment/database/API/configuration/operations impact; request the required reviewer approvals (stronger for `STAG` and `PROD`); merge after approvals and status checks pass; promote to the next environment, and create the `vYYYY.R.P` tag from `PROD` after a production release.
4. Let the developer perform the PR creation, merge, and promotion steps.

Claude may still create, update, or merge the Pull Request when the developer explicitly asks. Include the PR URL and next steps in the final summary whenever a branch was pushed.

## Branch Protection Expectations

Protect `DEV`, `QA`, `STAG`, `PROD`. Recommended controls: require Pull Requests before merging; require reviewer approval (stronger for `STAG` and `PROD`); require status checks where available; require branches up to date before merge where available; dismiss stale approvals on new commits; restrict who can push; prevent branch deletion; disable force push. If protection is not yet enforced during a transition period, still recommend following the process manually.

## Configurable Workflow Knobs

These knobs tune how working branches and merges behave without changing the promotion flow or direct-push allowance. Each value is a confirmed fact in `.claude/PROJECT-CONTEXT.md`. Read the confirmed values before acting; if a knob the task depends on is unset, ask and record the answer rather than guessing.

| Knob | Neutral principle |
| --- | --- |
| Commit signing | Sign commits when the project requires it; otherwise follow the project's signing setting. |
| Merge strategy | Use the confirmed merge strategy for integrating working branches and promotions (for example a squash-merge policy). |
| Working-branch deletion | Delete merged short-lived working branches (`feature/*`, `bugfix/*`) after merge, per the project's setting. |
| Working-branch lifetime | Keep working branches short-lived within the confirmed lifetime cap. |
| Update-from-base cadence | Rebase or update a working branch from its base on the confirmed cadence to limit drift. |
| Path-based required reviewers | Honor the code-owners / path-based reviewer mapping (for example a required owner on schema or migration paths). |
| Required-approver count | Require the confirmed number of approvals before merge, with the stronger `STAG`/`PROD` expectation still applying. |

The working-branch deletion knob is distinct from Branch Protection's "prevent branch deletion": that control protects the environment branches (which must never be deleted); the knob applies only to short-lived working branches after they merge.

## Commit And PR Guidance

Prefer small, meaningful commits with conventional-style messages: `feat: add user registration`, `fix: handle invalid login password`, `chore: update package dependencies`, `refactor: simplify payment calculation`, `docs: update API usage guide`. Pull Request titles should be clear and action-oriented (`feat: add user login API`).

## Safe Working Practices

- Review the diff before committing and stage deliberately (by file or hunk, not blindly), so each commit holds only intended changes.
- Keep one logical change per branch and Pull Request; split separate concerns into separate branches.
- Resolve all Pull Request review conversations before merge.
- When a force push is genuinely needed on your own short-lived working branch, prefer `git push --force-with-lease` over `--force`. Never force-push a shared or environment branch.
- Keep `.gitignore` honest: do not commit secrets, `.env` files, large binaries, or generated artifacts; add them to `.gitignore` instead, per `.claude/rules/secret-handling.md`.

## Claude Behavior

When asked about Git workflow, branching, releases, tags, PR targets, hotfixes, or production promotion, Claude must: use the branch names and promotion paths above; preserve the PR-first workflow; use the `vYYYY.R.P` tag format; keep production tags tied to `PROD`; treat release and hotfix actions as production-impacting unless the developer confirms otherwise; perform Git/GitHub tasks freely with no per-action confirmation; after pushing a working branch, hand the Pull Request to the developer (PR URL plus ordered next steps) unless explicitly asked to drive it; ask before recommending any non-standard merge path; stop and ask if the correct merge target is unclear; apply the confirmed workflow knobs and ask the developer to set any the task depends on when missing.

Final rule: if the merge target, source branch, release version, deployment state, or approval state is unclear, do not guess. Ask first.
