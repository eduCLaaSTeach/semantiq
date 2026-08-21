# Git Workflow Prompts

A copy-ready prompt cookbook for the development team. Give any of these prompts to Claude to run a common Git or GitHub workflow. They follow the branching and release model in `.claude/rules/git-branching-release.md`.

How to use this file:

- Replace every `<PLACEHOLDER>` with your real value before sending. Nothing here is hardcoded to a project.
- All Git and GitHub actions are pre-authorized in this gateway; Claude states the plan first, then acts. Running a production deploy or database migration is a separate action that still needs your explicit approval.
- After Claude pushes a working branch, it hands you the Pull Request (shows the URL and next steps) rather than merging on its own, unless you ask it to merge.

## Placeholder Legend

- `<FEATURE_BRANCH>` - your short-lived working branch (`feature/*`, `bugfix/*`, `release/*`, or `hotfix/*`).
- `<INTEGRATION_BRANCH>` - the branch you integrate into and sync from (`DEV` in this kit's model).
- `<BASE_BRANCH>` - the branch your working branch was created from.
- `<TARGET_BRANCH>` - the branch a Pull Request targets.
- `<RELEASE_BRANCH>` - a release-candidate branch, `release/vYYYY.R.P`.
- `<TAG>` - a CalVer production tag, `vYYYY.R.P`.
- `<COMMIT_SHA>` - a commit hash.
- `<FILE>` - a file path.
- `<N>` - a number of commits.

Environment branches are fixed in this kit and promote in order: `DEV` -> `QA` -> `STAG` -> `PROD`.

## Sync And Update

Pull latest onto the integration branch safely, then return to your work:

```text
I am working on <FEATURE_BRANCH>, but I need the latest changes on <INTEGRATION_BRANCH>
that a teammate just pushed.
- First fetch and show me what changed on the remote <INTEGRATION_BRANCH> before I switch.
- If switching would conflict with my uncommitted work, stash it first; otherwise switch directly.
- Check out <INTEGRATION_BRANCH> and pull the latest.
- Then switch me back to <FEATURE_BRANCH> and restore my stash if you created one.
```

What it does: verifies the remote before any checkout, stashes only when it is actually needed, and leaves you back on your branch with your work restored.

Bring a stale working branch up to date with its base:

```text
I am on <FEATURE_BRANCH> and it is <N> commits behind <BASE_BRANCH>. Bring it up to the
latest commit. Tell me first whether you will rebase or merge and why, and stop if there
are conflicts so we can resolve them together.
```

What it does: updates a lagging branch per the kit's update-from-base cadence. Whether it rebases or merges follows the project's confirmed merge strategy; it pauses on conflicts instead of guessing.

## Stash

Stash current work and switch away:

```text
I am on <FEATURE_BRANCH>. Stash my current changes and switch me to <INTEGRATION_BRANCH>.
```

Switch back and restore:

```text
Check out <FEATURE_BRANCH>, then restore (pop) my most recent stash.
```

Note: if you have more than one stash, tell Claude which one, or ask it to list your stashes first.

## Start A New Branch

New feature or bugfix branch from the right base:

```text
Create a new <feature|bugfix> branch named <FEATURE_BRANCH> from <BASE_BRANCH> and switch to it.
```

Note: `feature/*` starts from `DEV`; `bugfix/*` starts from the branch where the bug exists.

Cut a release-candidate branch:

```text
Cut a release branch <RELEASE_BRANCH> from DEV for the next release.
```

Start an urgent production hotfix:

```text
Start a hotfix branch <FEATURE_BRANCH> from PROD for an urgent production fix.
```

Note: a hotfix merges back to `PROD`, then to `DEV`, and to any active `release/*` branch.

## Commit And Pull Request

Review before committing:

```text
Show me the diff of my changes, then stage and commit them with a clear conventional message.
```

Commit and hand off a Pull Request:

```text
Commit my changes and prepare a Pull Request to <TARGET_BRANCH>. Show me the PR URL and the
next steps; do not merge it yourself.
```

What it does: commits, pushes the branch, and gives you the PR URL and ordered next steps. Add "create and merge the PR yourself" only if you want Claude to drive the whole flow.

Fill in a complete PR description:

```text
Open the Pull Request from <FEATURE_BRANCH> to <TARGET_BRANCH> with a description covering what
changed, why, how it was tested, source and target branch, and any release, deployment,
database, API, configuration, or operations impact.
```

## Promotion And Release

Promote a release forward one environment:

```text
Prepare the Pull Request to promote <RELEASE_BRANCH> to QA. Include what changed, why, how it
was tested, and any deployment, database, API, or configuration impact.
```

Note: promote `QA` -> `STAG` -> `PROD` each by Pull Request, with stronger approval expected for `STAG` and `PROD`.

Tag a production release after deployment:

```text
Production is deployed. Create the annotated CalVer tag <TAG> from the latest PROD commit and
push it, then give me the release notes to publish.
```

Note: production release tags are created from `PROD` only, never from `DEV`, `QA`, `STAG`, or `release/*`.

## Inspect And Compare

Check your current state:

```text
What branch am I on, what has changed, and am I ahead or behind <BASE_BRANCH>?
```

Compare a branch to its base:

```text
Show me how <FEATURE_BRANCH> differs from <BASE_BRANCH>, both the commits and the files changed.
```

## Resolve Conflicts

```text
I have merge conflicts after updating <FEATURE_BRANCH> from <BASE_BRANCH>. Walk me through each
conflict, propose a resolution for each, and do not discard anyone's work.
```

Note: the kit's etiquette is that the second party to merge resolves the conflict and re-requests review when the resolution changed logic.

## Undo And Recover (careful)

Discard local edits to one file only:

```text
Discard my uncommitted changes to <FILE> only, and keep everything else as-is.
```

Undo the last commit but keep the changes:

```text
Undo my last commit but keep the changes staged so I can recommit.
```

Reword the last, not-yet-pushed commit message:

```text
Reword my last commit message; it has not been pushed yet.
```

Note: rewrite history only on your own unpushed work. Never rewrite history that others already pulled, and never on a shared or environment branch.

## Force-Push Safely

```text
I rebased <FEATURE_BRANCH> and need to update the remote. Use a lease-protected force push so I
do not overwrite a teammate's commits. Never force-push DEV, QA, STAG, or PROD.
```

## Clean Up Merged Branches

```text
<FEATURE_BRANCH> is merged. Delete it locally and on the remote, and prune stale
remote-tracking branches.
```

Note: this applies only to short-lived working branches. Never delete the environment branches (`DEV`, `QA`, `STAG`, `PROD`).

## Safety Notes

- Claude states the source branch, target branch, files or commits involved, the command, and the expected result before any write action, so the change stays auditable.
- The recommended flow is Pull-Request-first: Claude hands you the PR unless you explicitly ask it to create or merge one.
- Promoting code through Git (including to `PROD`) does not require approval here; running a production deploy or a database migration does.
- For the full rules behind these prompts, see `.claude/rules/git-branching-release.md`.

## Scenarios (End To End)

These walkthroughs chain the prompts above into the complete lifecycle from `.claude/rules/git-branching-release.md`. Replace every placeholder. Alongside the legend above, these scenarios use `<BUGFIX_BRANCH>` and `<HOTFIX_BRANCH>` as working-branch names (like `<FEATURE_BRANCH>`).

The canonical release flow is:

```text
feature/* -> DEV -> release/vYYYY.R.P -> QA -> STAG -> PROD -> vYYYY.R.P tag
```

Key points that are easy to misremember: the `release/*` branch is cut from `DEV` before `QA` (the release branch is what gets promoted, not `DEV` directly), QA issues are fixed on `bugfix/*` cut from the release branch, and the version tag is created from `PROD` at the very end, after the production deploy.

### Scenario A: A feature from development to production

1. Start the feature from the integration branch:

   ```text
   Create a new feature branch <FEATURE_BRANCH> from DEV and switch to it.
   ```

2. While you work, keep it current when it falls behind (optional):

   ```text
   I am on <FEATURE_BRANCH> and it is <N> commits behind DEV. Bring it up to the latest
   commit and stop if there are conflicts so we can resolve them together.
   ```

3. Commit and hand off a Pull Request into `DEV`:

   ```text
   Commit my changes and prepare a Pull Request from <FEATURE_BRANCH> to DEV. Show me the
   PR URL and next steps; do not merge it yourself.
   ```

   You review and merge the PR, so the feature integrates into `DEV`.

4. When the release is ready, cut the release branch from `DEV`:

   ```text
   Cut a release branch release/<vYYYY.R.P> from DEV for this release.
   ```

5. Promote the release branch to `QA` by Pull Request:

   ```text
   Prepare the Pull Request to promote release/<vYYYY.R.P> to QA, with what changed, why,
   how it was tested, and any deployment, database, API, or configuration impact.
   ```

6. Fix QA issues on a bugfix branch cut from the release branch, then re-promote:

   ```text
   Create a bugfix branch <BUGFIX_BRANCH> from release/<vYYYY.R.P> for a QA issue and switch to it.
   ```

   ```text
   Commit my fix and open a Pull Request from <BUGFIX_BRANCH> back to release/<vYYYY.R.P>.
   Show me the PR URL.
   ```

   After the fix merges into the release branch, repeat step 5 to re-promote to `QA` until QA approves.

7. Promote `QA` to `STAG` after QA sign-off:

   ```text
   Prepare the Pull Request to promote QA to STAG after QA approval.
   ```

8. Promote `STAG` to `PROD` after staging sign-off:

   ```text
   Prepare the Pull Request to promote STAG to PROD after staging approval.
   ```

   Merging to `PROD` through Git is allowed here; the actual production deploy is a separate, approval-gated action.

9. After production is deployed, tag the release from `PROD`:

   ```text
   Production is deployed. Create the annotated CalVer tag <vYYYY.R.P> from the latest PROD
   commit, push it, and give me the release notes to publish.
   ```

10. Merge the release fixes back into `DEV`:

    ```text
    Merge the release fixes from release/<vYYYY.R.P> back into DEV so DEV has everything that shipped.
    ```

### Scenario B: An urgent production hotfix

The hotfix flow is:

```text
PROD -> hotfix/* -> PROD -> patch tag -> DEV (and any active release/*)
```

1. Cut the hotfix from `PROD`:

   ```text
   Start a hotfix branch <HOTFIX_BRANCH> from PROD for an urgent production issue and switch to it.
   ```

2. Commit and hand off a Pull Request back to `PROD`:

   ```text
   Commit my hotfix and open a Pull Request from <HOTFIX_BRANCH> to PROD. Show me the PR URL;
   do not merge it yourself.
   ```

3. After the hotfix is deployed, tag the patch from `PROD` (keep the same `YYYY.R`, increment only the patch number):

   ```text
   The hotfix is deployed. Create the annotated patch tag <vYYYY.R.P> from the latest PROD
   commit and push it.
   ```

4. Propagate the fix so it is not lost in the next release:

   ```text
   Merge the hotfix into DEV, and into any active release/* branch, so the fix carries forward.
   ```
