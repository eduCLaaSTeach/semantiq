# Git Workflow

Run a common Git or GitHub workflow the way the branching and release model in `.claude/rules/git-branching-release.md` expects. Every Git and GitHub action is pre-authorized, so Claude states the plan and then acts without waiting. Running a production deploy or a database migration is a separate action and still needs the developer's explicit approval.

Input: `<ACTION> [details]` - one of `sync`, `stash`, `branch`, `commit`, `pr`, `promote`, `release`, `inspect`, `conflicts`, `undo`, `force-push`, `cleanup`, `feature-to-prod`, `hotfix`. Details name the branches, files, or values the action needs, using these placeholders: `<FEATURE_BRANCH>` (a short-lived working branch, `feature/*`, `bugfix/*`, `release/*`, or `hotfix/*`), `<BUGFIX_BRANCH>` and `<HOTFIX_BRANCH>` (working branches named for their purpose), `<INTEGRATION_BRANCH>` (the branch you integrate into and sync from, `DEV` in this kit), `<BASE_BRANCH>` (the branch the working branch was created from), `<TARGET_BRANCH>` (the branch a Pull Request targets), `<RELEASE_BRANCH>` (a release candidate, `release/vYYYY.R.P`), `<TAG>` (a CalVer production tag, `vYYYY.R.P`), `<COMMIT_SHA>` (a commit hash), `<FILE>` (a file path), and `<N>` (a number of commits). Ask for any value the action needs and the details do not give.

The environment branches are fixed and promote in order: `DEV` -> `QA` -> `STAG` -> `PROD`. Use those uppercase names only, never `dev`, `qa`, `stag`, or `prod`.

## Before Any Write

1. Read the Git workflow knobs in `.claude/PROJECT-CONTEXT.md` that the action depends on: commit signing, merge strategy, working-branch deletion, working-branch lifetime, update-from-base cadence, path-based required reviewers, and required-approver count. Ask and record any the action needs that is unset.
2. Check the repository's commit-identity requirements before any commit or push.
3. State the source branch, the target branch, the files or commits involved, the command, and the expected result, so the change stays auditable. Then proceed without waiting for confirmation.
4. Stop and ask when the merge target, the source branch, the release version, the deployment state, or an approval state is unclear. Never guess one.

## sync

Use when a teammate pushed to the integration branch and you need it locally, or when your working branch has fallen behind its base. `sync` pulls the integration branch; `sync base` updates the working branch from its base.

Process for `sync`:

1. Run `git fetch` and show what changed on the remote `<INTEGRATION_BRANCH>` (the incoming commits and files) before any checkout.
2. Check `git status`. Stash the uncommitted work only when switching would conflict with it, with a named entry such as `git stash push -u -m "<FEATURE_BRANCH> sync"`. Otherwise switch directly.
3. Check out `<INTEGRATION_BRANCH>` and pull the latest. Stop and report if the local integration branch has diverged from the remote rather than merging over it.
4. Switch back to `<FEATURE_BRANCH>` and restore the stash, if one was created, by its name. Stop on a restore conflict and walk the developer through it.

Process for `sync base`:

1. Show how far `<FEATURE_BRANCH>` is behind `<BASE_BRANCH>`, as `<N>` commits and their subjects.
2. Say whether you will rebase or merge, and why, following the confirmed merge strategy and the update-from-base cadence. A rebase of a branch that is already pushed needs the `force-push` action afterwards, so say so.
3. Bring the branch up to the latest `<BASE_BRANCH>` commit.
4. Stop on any conflict and resolve it with the developer through the `conflicts` action. Never resolve one by guessing.

Return: the branch you left the developer on, what came in, whether a stash was created and restored, and anything that stopped the sync.

## stash

Use to set work aside and switch away, or to come back and restore it.

Process:

1. To stash and switch: on `<FEATURE_BRANCH>`, stash the current changes with a named entry, then switch to the branch the developer named, usually `<INTEGRATION_BRANCH>`.
2. To restore: check out `<FEATURE_BRANCH>`, then pop the most recent stash.
3. When more than one stash exists, list them first and ask which one to restore. Never pop a stash the developer did not pick.

Return: the current branch, the stash created or restored, and the stashes that remain.

## branch

Use to start a working branch from the right base: `branch feature`, `branch bugfix`, `branch release`, or `branch hotfix`.

Process:

1. Pick the base from the branch type, and correct a request that names the wrong one:
   - `feature/*` starts from `DEV` and merges back into `DEV`.
   - `bugfix/*` starts from the branch where the bug exists (`DEV`, `QA`, `STAG`, or the active `release/*`) and usually merges back into it. A QA issue during a release is fixed on a `bugfix/*` cut from the active `release/*`.
   - `release/*` is cut from `DEV` as `release/vYYYY.R.P` for the next release, then promoted through `QA`, `STAG`, and `PROD`. Only release bug fixes land on it.
   - `hotfix/*` starts from `PROD`, for an urgent production fix only. It merges back to `PROD`, then to `DEV`, and to any active `release/*`.
2. Fetch, then create the branch from the latest remote commit of its base and switch to it.
3. Keep it short-lived and one logical change per branch, within the confirmed working-branch lifetime.

Return: the new branch, its base and the commit it started from, and where it merges back.

## commit

Use to review your changes and commit them.

Process:

1. Show the diff of the changes, staged and unstaged.
2. Flag anything that must not be committed: a secret, a token, a `.env` file, a large binary, or a generated artifact. Leave it out and add it to `.gitignore` instead, per `.claude/rules/secret-handling.md`.
3. Stage deliberately, by file, so the commit holds only the intended changes. Never stage everything blindly.
4. Commit with a clear conventional message, such as `feat: add user registration`, `fix: handle invalid login password`, `chore: update package dependencies`, `refactor: simplify payment calculation`, or `docs: update API usage guide`. Sign it when the commit-signing knob requires it.
5. Keep commits small and meaningful. Split unrelated concerns into separate commits, and separate branches where they are separate work.

Return: the commit created, its message, the files it holds, and anything left out and why.

## pr

Use to commit, push, and hand off a Pull Request, or to open one with a full description: `pr` hands it off, `pr open` creates it, `pr merge` creates and merges it.

Process:

1. Commit any outstanding changes through the `commit` action, then push the branch.
2. Pick the recommended target from the promotion flow: `feature/*` and `bugfix/*` into `DEV` (a release `bugfix/*` back into its `release/*`); `release/*` promoted through `QA`, `STAG`, then `PROD`; `hotfix/*` into `PROD`, then back to `DEV` and any active `release/*`.
3. Write the description: what changed, why, how it was tested, the source and target branch, the ticket or issue link, and whether it affects release, deployment, database, API, configuration, or operations. Flag any impact note that is missing.
4. For `pr` (the default), do not open or merge the Pull Request. Hand it to the developer:
   - Show the PR creation URL, from the `git push` output or the host's compare URL.
   - State the recommended target.
   - Give an ordered, copy-ready list: open the Pull Request against the target; fill in the description above; request the required reviewer approvals, stronger for `STAG` and `PROD`, and the path-based owner for a change touching database or security; merge after the approvals and status checks pass; promote to the next environment, and create the `vYYYY.R.P` tag from `PROD` after a production release.
5. For `pr open`, create the Pull Request from `<FEATURE_BRANCH>` to `<TARGET_BRANCH>` with that description, and show its URL.
6. For `pr merge`, create it and merge it only because the developer asked for that exact action, using the confirmed merge strategy and after the required status checks pass. The author of a database or security change does not self-merge without the owning reviewer. Delete the merged working branch when the deletion knob says so.

Return: the PR URL, the source and target, the description used, and the ordered next steps or the merge result.

## promote

Use to promote a release forward one environment by Pull Request: `release/vYYYY.R.P` -> `QA`, then `QA` -> `STAG`, then `STAG` -> `PROD`.

Process:

1. Confirm the step is the next one in order. Never skip a stage, such as `DEV` -> `PROD`. The release branch is what gets promoted to `QA`, not `DEV` directly, so a request to promote `DEV` to `QA` gets a release branch cut first through `branch release`.
2. Confirm the approval the step needs: QA approval before `QA` -> `STAG`, staging approval before `STAG` -> `PROD`.
3. Prepare the Pull Request with what changed, why, how it was tested, the source and target, and any release, deployment, database, API, configuration, or operations impact.
4. Hand it off as the `pr` action does, noting that `STAG` and `PROD` expect stronger reviewer approval. Create or merge it only when the developer asks.
5. Merging to `PROD` through Git needs no approval. The production deploy that follows is a separate action and needs the developer's explicit approval; do not run it.

Return: the promotion prepared, its PR URL, the approvals still needed, and the next promotion step.

## release

Use after production is deployed, to tag the release from `PROD`.

Process:

1. Confirm the production deploy has happened. Do not tag ahead of it.
2. Work out the version in CalVer `vYYYY.R.P` from the existing tags: a planned release increments `R` and resets `P` to `0`; a hotfix keeps `YYYY.R` and increments only `P`; a new year updates `YYYY` and restarts at `R = 1`. Use `v2026.1.0`, never a text form such as `2026.release1`. Confirm the tag does not already exist.
3. Fetch, then create the annotated tag `<TAG>` from the latest `PROD` commit. Production tags come from `PROD` only, never from `DEV`, `QA`, `STAG`, or `release/*`.
4. Push the tag.
5. Draft the release notes to publish from the changes since the previous tag. Publish them only when the developer asks.
6. Remind the developer that the release fixes merge back into `DEV`, through the `feature-to-prod` final step.

Return: the tag, the commit it points at, the push result, and the draft release notes.

## inspect

Use to check where you are, or to compare a branch with its base.

Process:

1. For the current state: show the current branch, what has changed (staged, unstaged, untracked), and whether it is ahead of or behind `<BASE_BRANCH>`, after a fetch.
2. For a comparison: show how `<FEATURE_BRANCH>` differs from `<BASE_BRANCH>`, both the commits and the files changed.
3. Change nothing.

Return: the branch, the change summary, the ahead and behind counts, and the commit and file comparison asked for.

## conflicts

Use when updating `<FEATURE_BRANCH>` from `<BASE_BRANCH>` has left merge conflicts.

Process:

1. List every conflicted file.
2. Walk through each conflict, show both sides, and propose a resolution for each.
3. Never discard anyone's work. Keep both contributions where both belong, and ask when they cannot both stand.
4. Apply a resolution only once the developer agrees with it, then continue the merge or rebase.
5. Follow the kit's etiquette: the second party to merge resolves the conflict, and re-requests review when the resolution changed logic.

Return: each conflict and how it was resolved, and whether review must be re-requested.

## undo

Use carefully to discard edits to one file, undo the last commit while keeping its changes, or reword the last commit message.

Process:

1. To discard one file's edits: discard the uncommitted changes to `<FILE>` only, and keep everything else as it is. Say first that discarded edits cannot be recovered.
2. To undo the last commit: undo it and keep its changes staged so the developer can recommit.
3. To reword: reword the last commit message.
4. Before undoing or rewording a commit, check whether it has been pushed. Rewrite history only on the developer's own unpushed work. Never rewrite history others have already pulled, and never on a shared or environment branch; for pushed work, offer a new commit or a `git revert` of `<COMMIT_SHA>` instead.

Return: what was undone, the current branch state, and anything declined and why.

## force-push

Use after rebasing your own working branch, to update the remote.

Process:

1. Confirm the branch is the developer's own short-lived working branch. Never force-push `DEV`, `QA`, `STAG`, `PROD`, or any shared branch. Name the conflict, decline, and offer a new commit or a revert instead.
2. Use a lease-protected force push, `git push --force-with-lease`, never `--force`, so a teammate's commits are not overwritten.
3. Stop and report if the lease is rejected, because someone else pushed to the branch.

Return: the branch, the push result, and whether the lease held.

## cleanup

Use when a working branch is merged, to delete it and prune stale remote-tracking branches.

Process:

1. Confirm the branch is a short-lived working branch. Never delete `DEV`, `QA`, `STAG`, or `PROD`.
2. Confirm it is merged into every branch it must reach: a `release/*` back into `DEV`, and a `hotfix/*` into `PROD`, `DEV`, and any active `release/*`.
3. Follow the working-branch deletion knob.
4. Delete it locally and on the remote, then prune stale remote-tracking branches.

Return: the branches deleted, locally and remotely, and what was pruned.

## feature-to-prod

Use to take a feature from development to production end to end, along `feature/*` -> `DEV` -> `release/vYYYY.R.P` -> `QA` -> `STAG` -> `PROD` -> `vYYYY.R.P` tag.

The release branch is cut from `DEV` before `QA`, and it is the release branch that gets promoted, not `DEV`. QA issues are fixed on `bugfix/*` cut from the release branch. The version tag is created from `PROD` at the very end, after the production deploy.

Process:

1. Start the feature: create `<FEATURE_BRANCH>` from `DEV` and switch to it, through `branch feature`.
2. Optionally, while the work goes on, bring it up to the latest `DEV` commit when it falls `<N>` commits behind, through `sync base`, stopping on conflicts.
3. Commit and hand off a Pull Request from `<FEATURE_BRANCH>` to `DEV`, through `pr`, without merging it. Wait while the developer reviews and merges it into `DEV`.
4. When the release is ready, cut `release/<vYYYY.R.P>` from `DEV`, through `branch release`.
5. Prepare the Pull Request to promote `release/<vYYYY.R.P>` to `QA`, with what changed, why, how it was tested, and any deployment, database, API, or configuration impact, through `promote`.
6. For each QA issue, create `<BUGFIX_BRANCH>` from `release/<vYYYY.R.P>` and switch to it, commit the fix, and open a Pull Request from `<BUGFIX_BRANCH>` back to `release/<vYYYY.R.P>`, showing its URL. After the fix merges into the release branch, repeat step 5 to re-promote to `QA`, until QA approves.
7. After QA sign-off, prepare the Pull Request to promote `QA` to `STAG`.
8. After staging sign-off, prepare the Pull Request to promote `STAG` to `PROD`. Merging to `PROD` through Git is allowed; the production deploy is a separate action that needs the developer's explicit approval.
9. After production is deployed, create the annotated CalVer tag `<vYYYY.R.P>` from the latest `PROD` commit, push it, and draft the release notes, through `release`.
10. Merge the release fixes from `release/<vYYYY.R.P>` back into `DEV`, so `DEV` has everything that shipped.

Stop at every step that waits on the developer: a Pull Request to review and merge, a QA or staging approval, or the production deploy. Do not run ahead of an approval.

Return: the step reached, the PR URLs and tag so far, and the next step and who it waits on.

## hotfix

Use for an urgent production issue, along `PROD` -> `hotfix/*` -> `PROD` -> patch tag -> `DEV`, and any active `release/*`.

Process:

1. Cut `<HOTFIX_BRANCH>` from `PROD` and switch to it, through `branch hotfix`. Use this flow only for an urgent production fix.
2. Commit the hotfix and open a Pull Request from `<HOTFIX_BRANCH>` to `PROD`, showing its URL, without merging it. Wait while the developer has it approved and merged.
3. After the hotfix is deployed, create the annotated patch tag `<vYYYY.R.P>` from the latest `PROD` commit and push it, keeping the same `YYYY.R` and incrementing only the patch number, through `release`. The production deploy needs the developer's explicit approval; do not run it.
4. Merge the hotfix into `DEV`, and into any active `release/*` branch, so the fix carries forward and is not lost in the next release.

Return: the step reached, the PR URL, the patch tag, and the branches the fix still has to reach.

## Always

- State the source branch, the target branch, the files or commits, the command, and the expected result before any write action.
- Work Pull-Request-first. After pushing a working branch, hand the Pull Request to the developer, unless they explicitly asked Claude to create or merge it.
- Promote through Git freely, including to `PROD`. Never run a production deploy or a database migration without the developer's explicit approval.
- Change an environment branch through a Pull Request. A direct push to `DEV`, `QA`, `STAG`, or `PROD` is permitted but discouraged outside urgent cases, and is stated as one before it runs.
- Prefer `git push --force-with-lease` on your own working branch, and never force-push a shared or environment branch.
- Never commit a secret, a `.env` file, a large binary, or a generated artifact.
- Resolve every Pull Request review conversation before merge.
- Serialize dependency-bump and lockfile changes, so only one is in flight at a time.
- Treat shared configuration and environment changes as additive in parallel work: add a new variable rather than renaming or removing one, documented in the Pull Request with placeholders only.
- Ask before recommending any non-standard merge path.
- For the full rules behind this command, see `.claude/rules/git-branching-release.md`.
