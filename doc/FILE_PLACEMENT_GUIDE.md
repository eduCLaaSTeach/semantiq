# SemantIQ GitHub And Claude Code Desktop File Placement Guide

## Existing Repository Is The Base

Do not create a second repository and do not replace the existing `doc/`, `.github/workflows/`, `deployment/` or `doc/design-system/` content wholesale.

This v1.3 kit is an overlay. Copy the contents of the package `repository/` folder into the root of the existing `eduCLaaSTeach/semantiq` clone and review conflicts before committing.

## Exact Placement

```text
semantiq/
|-- CLAUDE.md
|-- README.md
|-- IMPLEMENTATION_STATUS.md
|-- doc/
|   |-- README.md                         # existing repository file, preserve
|   |-- README_PHASED_IMPLEMENTATION.md   # new phase-package notes
|   |-- MASTER_IMPLEMENTATION_PLAN.md
|   |-- MERGE_INSTRUCTIONS_v1.3.md
|   |-- CONFLICT_RESOLUTION_v1.3.md
|   |-- design-system/                    # existing repository authority, preserve
|   |-- phases/
|   |-- reference/
|   |   |-- word/
|   |   `-- ...
|   |-- context/
|   |-- execution/
|   `-- templates/
|-- .github/workflows/                    # existing pipeline, preserve
|-- deployment/                           # existing cPanel deployment assets, preserve
`-- application code when Phase 00 scaffolds it
```

## Claude Code Desktop

Clone/open the repository itself, for example `~/Development/semantiq`. Do not open only `doc/` and do not keep a second independent copy of the instructions in Claude Desktop.

## Files That Replace Existing Root Files

The merged v1.3 `CLAUDE.md` and `README.md` intentionally replace the earlier root versions because they preserve the confirmed Laravel/React/MySQL/cPanel facts while adding phase gates, data protection, sovereignty, AI technology governance and context preservation.

`IMPLEMENTATION_STATUS.md` remains at repository root.

## Files This Kit Must Not Overwrite Automatically

- `.github/workflows/deploy.yml`
- `deployment/public_html.htaccess`
- `doc/design-system/**`
- existing mockups/assets
- any application code or migrations already present

Those are inspected in Phase 00 and changed only through an approved plan.
