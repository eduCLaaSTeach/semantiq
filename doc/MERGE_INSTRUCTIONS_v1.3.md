# SemantIQ v1.3 Merge Instructions

## Before Copying

1. Clone/pull the existing `eduCLaaSTeach/semantiq` repository.
2. Ensure the working tree is clean or commit/stash local work.
3. Create a short-lived documentation/phase-00 preparation branch if you want a reviewable pull request.
4. Back up the current root `CLAUDE.md` and `README.md` outside the repository if desired.

## Copy

Copy the contents of this kit's `repository/` folder into the repository root.

The v1.3 root `CLAUDE.md` and `README.md` are merged replacements. The `doc/` content is additive and uses the repository's existing documentation root.

Do not delete or overwrite existing `doc/design-system/`, `.github/workflows/`, `deployment/`, mockups/assets or application code.

## Review Before Commit

Run:

```bash
git status
git diff -- CLAUDE.md README.md IMPLEMENTATION_STATUS.md doc/
```

Verify that no credential or `.env` value appears in the diff.

## First Claude Code Task

After merge, open the repository root in Claude Code Desktop and instruct Claude to perform the Phase 00 repository assessment and create `doc/execution/PHASE-00-PLAN.md`. It must stop for approval before material code changes.
