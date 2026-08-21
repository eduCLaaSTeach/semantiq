# CLaaS2SaaS SemantIQ — Design Documentation

A front-end control plane for Microsoft Fabric that takes an organisation from raw source
systems to a governed conversational AI application, following the 80-step end-to-end
procedure.

## Documents

| # | Document | What it covers |
|---|---|---|
| 00 | [Solution Architecture](00-Solution-Architecture.md) | Confirmed decisions, the automation-tier model, application architecture, the Blueprint engine, Microsoft integration surface, data model, roles, non-functionals, **deployment feasibility on the confirmed GoDaddy / AlmaLinux target**, delivery phases |
| 01 | [Requirement Scoping](01-Requirement-Scoping.md) | All 80 steps scoped as features — Step, Activities, Who, When, How, Feature, Feature Description, plus automation tier. 14 clusters, cross-cutting concerns, dependencies, and Appendix A listing all 80 in original sequence |
| 01a | [Data and Artifact Request](01a-Artifact-Data-Request.md) | The customer-facing request for the existing artifacts needed before configuration begins |
| 02 | [Functional Specification](02-Functional-Specification.md) | 78 features — Feature Name, Description, Actors, Preconditions, Steps/Workflow, Expected Outcome. Includes Cluster 0 platform foundations and three keystone features in narrative form |
| 03 | [Workflow and Process Specification](03-Workflow-Process-Specification.md) | 25 end-to-end workflows — Cluster, Feature, Workflow, Triggers, Steps, Conditions, Integration, AI & Automation, Expected Output. Includes an explicit map of where AI is and is not used |
| 04 | [UI Specification](04-UI-Specification.md) | The navigation tree, role labels, common standards, and per-screen specifications against the approved CLaaS2SaaS design system. Two archetype conflicts named and resolved |
| 05 | [Mockup Screen Curation](05-Mockup-Screen-Curation.md) | 14 curated screens with archetype rationale, runners-up, and what was deliberately excluded |
| — | [Shell mockup](mockups/semantiq-shell-mockup.html) | The approved shell and four screens, both themes, all UI states — self-contained HTML |
| — | [Sign-in mockup](mockups/semantiq-signin-mockup.html) | The Auth screen with Microsoft SSO, the real approved brand assets, both themes, all message states |
| — | [`word/`](word/) | Word versions of every document, each with its right-justified footnote per the house format |

## Reading order

For a **business or sponsor audience**: 01 → 05 → the interactive mockup.
For a **technical audience**: 00 → 02 → 03 → 04.
For **delivery planning**: 00 section 10, then 01 for the backlog.

## Reference architecture

```
ERP / CRM / LMS / SQL / Excel / APIs / Dataverse / Business Central / Other sources
        |
Fabric Data Factory / Mirroring / Shortcuts / Gateway
        |
OneLake
        |
Lakehouse - Bronze -> Silver -> Gold
        |
Fabric Warehouse / Gold tables
        |
Power BI Semantic Model + DAX + RLS + Prep for AI
        |
Fabric Data Agent
        |
Copilot Studio Agent
        |
Teams / Web / Business application
```

## The central design idea

Not every one of the 80 steps has an API. Each is classified into one of three tiers and the
product is honest about which one the user is standing in:

- **Tier A — Automated.** SemantIQ makes the change through a Microsoft API.
- **Tier B — Guided and verified.** SemantIQ instructs and deep-links, then **reads the state
  back to verify it**. A step is never complete on a user's assertion alone.
- **Tier C — Governed record.** SemantIQ is the system of record and the approval workflow.

Tiers are configuration, not code, and a capability probe re-confirms them per tenant and
per release.

## Confirmed decisions

| Decision | Value |
|---|---|
| Fabric tenancy | Bring-your-own-tenant |
| Provisioning | Both create and attach; attach built first |
| Conversational runtime | Fabric Data Agent, surfaced through Copilot Studio |
| Stack | Laravel 13, PHP 8.3+, React 19 with TypeScript, MySQL 8 |
| Host | GoDaddy server, AlmaLinux 9.8, deployed to cPanel over SSH by GitHub Actions on push to `DEV` (verified) |
| Personas | Data Engineer, Business User, Administrator |

## Outstanding items

1. ~~Hosting profile~~ — **resolved by reading `.github/workflows/deploy.yml`: cPanel over
   SSH, rsync from GitHub Actions, deploying on every push to `DEV`.** Two gaps follow from
   it, both in 00 section 9.2: no queue worker or scheduler cron, and no post-deploy release
   steps.
2. **Outbound egress test** to the Microsoft endpoints listed in 00 section 9.1, **from the
   cPanel host**. Still the highest-risk unknown in the plan.
3. ~~Brand asset pack~~ — **resolved: present on `DEV`** at
   `.claude/skills/ui-ux-design/assets/`. Still to confirm: which copy is authoritative, and
   the `<BRAND_ASSETS_PATH>` install location.
4. **Confirmation** of the navigation tree (04 section 4) and the role labels (04 section 5).
5. **Entra app registration model** — one multi-tenant registration, or one per customer.
6. **Archetype decision** for the conversational surface (04 section 7, conflict 1).
